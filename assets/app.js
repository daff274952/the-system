/* Halaman utama: status, daily quest, quest tambahan, pengingat, profil, riwayat. Dipasang oleh router di core.js. */
(function(){
const {$,toast,notify,chime,sysNotify,canNotif}=Sys;
const GOALS=[
  {id:"push",name:"Push-up",target:100,unit:"",steps:[1,10,25]},
  {id:"sit",name:"Sit-up",target:100,unit:"",steps:[1,10,25]},
  {id:"squat",name:"Squat",target:100,unit:"",steps:[1,10,25]},
  {id:"run",name:"Lari",target:10,unit:" km",steps:[0.5,1,2]}
];
// Target harian mengikuti profil Player (tingkat kebugaran).
function applyTargets(){
  const PT=(profile&&profile.targets)||{};
  GOALS.forEach(g=>{if(PT[g.id]>0)g.target=PT[g.id]});
}
const STATS=[
  ["str","STR","Kekuatan"],["agi","AGI","Kelincahan"],["vit","VIT","Daya tahan"],
  ["int","INT","Kecerdasan"],["per","PER","Persepsi"]
];
const TIER_XP={E:20,D:40,C:70,B:110,A:160,S:250};
const TIER_COLOR={E:"#9aa7bd",D:"#46e6a5",C:"#6fd3ff",B:"#a78bfa",A:"#ff9f43",S:"#ffd166"};

const today=()=>{const d=new Date();return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0")};
const fresh=()=>({
  name:"Pemain",level:1,xp:0,points:0,streak:0,hp:100,mp:10,
  stats:{str:10,agi:10,vit:10,int:10,per:10},
  daily:{date:today(),counts:{push:0,sit:0,squat:0,run:0},done:false},
  quests:[
    {id:1,title:"Baca buku 20 halaman",tier:"D",stat:"int",done:false},
    {id:2,title:"Tidur sebelum jam 23.00",tier:"E",stat:"vit",done:false},
    {id:3,title:"Selesaikan tugas paling berat hari ini",tier:"B",stat:"per",done:false}
  ],
  log:[{t:Date.now(),m:"Kamu telah memperoleh syarat untuk menjadi Player."}],
  rem:{on:true,times:["07:00","12:00","19:00","22:00"],fired:{date:"",list:[]}}
});

/* ---- state dari server (MySQL), diisi saat mount() ---- */
let BOOT=null,S=null,history=[],profile=null,weights=[];

// Perubahan langsung tampil; penyimpanan ke server berjalan di belakang (debounce).
let saveTimer=null,saving=false,dirty=false;
function setSync(text,kind){const el=$("sync");if(el){el.textContent=text;el.dataset.kind=kind||""}}
function save(){dirty=true;setSync("Menyimpan…","busy");clearTimeout(saveTimer);saveTimer=setTimeout(flush,600)}
async function flush(){
  if(saving){saveTimer=setTimeout(flush,300);return}
  if(!dirty||!Sys.boot.user)return;
  saving=true;dirty=false;
  try{
    const j=await Sys.api("save",{state:S});
    if(j.history){history=j.history;renderHistory()}
    setSync(dirty?"Menyimpan…":"Tersimpan","ok");
  }catch(e){
    dirty=true;
    if(e.status===401){setSync("Belum tersimpan","err");return}
    setSync("Gagal menyimpan, mencoba lagi…","err");
    clearTimeout(saveTimer);saveTimer=setTimeout(flush,5000);
  }finally{saving=false}
}
addEventListener("beforeunload",e=>{if(dirty||saving){e.preventDefault();e.returnValue=""}});

const xpNeed=l=>Math.round(100+l*60+l*l*4);
const maxHp=()=>100+S.stats.vit*10+S.level*5;
const maxMp=()=>10+S.stats.int*5;
function rankOf(l){
  const r=[[70,"S"],[50,"A"],[35,"B"],[20,"C"],[10,"D"],[0,"E"]];
  for(let i=0;i<r.length;i++)if(l>=r[i][0])return {rank:r[i][1],next:i?r[i-1]:null};
}
function jobOf(){
  if(S.level>=40)return "Shadow Monarch";
  if(S.level>=20)return "Necromancer";
  return "Tidak ada";
}
function titleOf(){
  if(S.streak>=30)return "Yang Tak Kenal Lelah";
  if(S.streak>=7)return "Pejuang Harian";
  if(S.level>=10)return "Pemburu Serigala";
  return "Tidak ada";
}
function addLog(m){S.log.unshift({t:Date.now(),m});S.log=S.log.slice(0,60)}

/* ---- pengingat daily quest ---- */
function remaining(){
  const n=new Date(),m=new Date(n);m.setHours(24,0,0,0);
  const mins=Math.floor((m-n)/60000);
  return mins>=60?Math.floor(mins/60)+" jam "+(mins%60)+" menit":mins+" menit";
}
function leftList(){
  return GOALS.filter(g=>S.daily.counts[g.id]<g.target).map(g=>g.name+" "+(g.target-S.daily.counts[g.id])+g.unit).join(", ");
}
function checkReminders(){
  const t=today(),R=S.rem;
  if(R.fired.date!==t)R.fired={date:t,list:[]};
  if(!R.on||S.daily.done)return;
  const n=new Date(),now=String(n.getHours()).padStart(2,"0")+":"+String(n.getMinutes()).padStart(2,"0");
  const due=R.times.filter(x=>x<=now&&!R.fired.list.includes(x));
  if(!due.length)return;
  R.fired.list.push(...due);save();renderRem();
  const late=n.getHours()>=22;
  const title=late?"Peringatan: waktu hampir habis":"Daily Quest belum selesai";
  const body="Sisa waktu "+remaining()+". Belum selesai: "+leftList()+".";
  toast(title,body,"alert",late?12000:8000);
  sysNotify(title,body);chime(false);
  addLog("Pengingat "+due[due.length-1]+": daily quest belum selesai.");
}

function permText(){
  if(!canNotif())return "Browser ini tidak mendukung notifikasi. Pengingat tetap muncul di halaman.";
  const p=Notification.permission;
  if(p==="granted")return "Status: <b>aktif</b>. Notifikasi muncul walau tab ini tidak sedang dibuka.";
  if(p==="denied")return "Status: <b>diblokir</b>. Pengingat tetap muncul di halaman ini.";
  return "Status: <b>belum diizinkan</b>.";
}
function renderRem(){
  $("remOn").checked=S.rem.on;
  const fired=S.rem.fired.date===today()?S.rem.fired.list:[];
  $("times").innerHTML=S.rem.times.length?S.rem.times.map(t=>
    '<span class="time'+(fired.includes(t)?' fired':'')+'">'+t+'<button class="del" data-time="'+t+'" aria-label="Hapus jam '+t+'">×</button></span>').join("")
    :'<span class="perm">Belum ada jam pengingat.</span>';
  $("permState").innerHTML=permText();
  $("permBtn").hidden=!canNotif()||Notification.permission!=="default";
}

function gainXp(x){
  S.xp+=x;
  while(S.xp>=xpNeed(S.level)){
    S.xp-=xpNeed(S.level);S.level++;S.points+=3;
    S.hp=maxHp();S.mp=maxMp();
    addLog("Level naik! Sekarang Level "+S.level+".");
    const before=rankOf(S.level-1).rank,after=rankOf(S.level).rank;
    notify({tag:"Level Up",title:"Level kamu naik!",body:"Level "+(S.level-1)+" → "+S.level,rewards:["+3 poin stat","HP & MP pulih penuh"],sound:"up"});
    if(before!==after){addLog("Rank naik ke "+after+".");notify({tag:"Peringatan",title:"Rank "+after+" tercapai",body:"Kekuatanmu telah diakui oleh Sistem.",sound:"up"})}
    if(profile&&before!==after&&after===profile.targetRank){
      addLog("Target rank "+after+" tercapai.");
      notify({tag:"Target Tercapai",title:"Kamu mencapai target rank "+after+"!",body:"Pasang target baru di halaman profil.",sound:"up"});
    }
  }
}

/* ---- daily reset & penalty ---- */
function checkDay(){
  const t=today();
  if(S.daily.date!==t){
    const missed=!S.daily.done;
    // also detect skipped full days
    const gap=Math.round((new Date(t)-new Date(S.daily.date))/864e5);
    if(missed||gap>1){
      S.streak=0;S.hp=Math.max(1,Math.round(maxHp()*.5));
      addLog("Daily quest "+S.daily.date+" gagal. Penalti diterapkan.");
      notify({tag:"Penalti",title:"Daily Quest gagal diselesaikan",body:"Kamu dipindahkan ke Zona Penalti. HP berkurang 50% dan streak kembali ke 0. Jangan ulangi lagi.",ok:"Mengerti"});
    }
    S.daily={date:t,counts:{push:0,sit:0,squat:0,run:0},done:false};
    S.quests.forEach(q=>q.done=false);
    addLog("Daily quest baru telah tiba.");
    save();
  }
}

function completeDaily(){
  S.daily.done=true;S.streak++;S.points+=3;
  S.hp=maxHp();S.mp=maxMp();
  addLog("Daily quest selesai. Streak "+S.streak+" hari.");
  notify({tag:"Quest Selesai",title:"Daily Quest: Persiapan Menjadi Kuat",body:"Kamu telah menyelesaikan quest harian.",rewards:["+250 EXP","+3 poin stat","Full Recovery"],sound:"ok"});
  gainXp(250);
}

/* ---- render ---- */
function render(){
  $("lvl").textContent=S.level;
  $("nameBtn").textContent=S.name;
  const r=rankOf(S.level);
  $("rank").textContent=r.rank+"-Rank";
  $("rankHint").textContent=r.next?"(Rank "+r.next[1]+" di Lv "+r.next[0]+")":"(puncak)";
  $("job").textContent=jobOf();
  $("title").textContent=titleOf();
  if(typeof renderProfile==="function")renderProfile();
  const mh=maxHp(),mm=maxMp();S.hp=Math.min(S.hp,mh);S.mp=Math.min(S.mp,mm);
  $("hpF").style.width=(S.hp/mh*100)+"%";$("hpN").textContent=S.hp+" / "+mh;
  $("mpF").style.width=(S.mp/mm*100)+"%";$("mpN").textContent=S.mp+" / "+mm;
  const need=xpNeed(S.level);
  $("xpF").style.width=(S.xp/need*100)+"%";$("xpN").textContent=S.xp+" / "+need;
  $("pts").textContent=S.points;$("streak").textContent=S.streak;

  $("stats").innerHTML=STATS.map(([k,a,n])=>
    '<div class="stat"><div class="s-name">'+a+'<em>'+n+'</em></div><div style="display:flex;gap:10px;align-items:center"><span class="s-val">'+S.stats[k]+'</span>'+
    (S.points>0?'<button class="plus" data-stat="'+k+'" aria-label="Tambah '+a+'">+</button>':'')+'</div></div>').join("");

  $("goals").innerHTML=GOALS.map(g=>{
    const c=S.daily.counts[g.id],done=c>=g.target;
    const fmt=v=>(g.id==="run"?(+v).toFixed(1):v);
    return '<div class="goal'+(done?' done':'')+'"><span class="g-name">'+g.name+'</span><span class="g-count">['+fmt(c)+' / '+g.target+g.unit+']'+(done?' ✓':'')+'</span>'+
      '<div class="track"><div class="fill" style="width:'+Math.min(100,c/g.target*100)+'%"></div></div>'+
      (S.daily.done?'':'<div class="g-btns">'+g.steps.map(s=>'<button class="chip" data-g="'+g.id+'" data-v="'+s+'">+'+s+g.unit+'</button>').join("")+
      '<button class="chip neg" data-g="'+g.id+'" data-v="-'+g.steps[0]+'">−'+g.steps[0]+'</button></div>')+'</div>';
  }).join("");
  $("dqState").innerHTML=S.daily.done
    ?'<div class="cleared">[ QUEST SELESAI ]</div>'
    :'<div class="warn"><b>PERINGATAN:</b> Gagal menyelesaikan daily quest akan dikenai penalti yang sesuai.</div>';

  const ql=$("quests");
  ql.innerHTML=S.quests.length?S.quests.map(q=>
    '<li class="item'+(q.done?' done':'')+'"><button class="check" data-q="'+q.id+'" aria-label="Tandai selesai">'+(q.done?'✓':'')+'</button>'+
    '<span class="i-title"></span><span class="i-meta"><span class="tier" style="color:'+TIER_COLOR[q.tier]+'">'+q.tier+'</span> +'+TIER_XP[q.tier]+' EXP · '+q.stat.toUpperCase()+'</span>'+
    '<button class="del" data-del="'+q.id+'" aria-label="Hapus quest">×</button></li>').join("")
    :'<li class="empty">Belum ada quest tambahan. Tulis kebiasaan yang mau kamu latih di atas.</li>';
  ql.querySelectorAll(".i-title").forEach((el,i)=>el.textContent=S.quests[i].title);

  $("log").innerHTML=S.log.map(l=>{const d=new Date(l.t);return '<li><span>['+String(d.getHours()).padStart(2,"0")+":"+String(d.getMinutes()).padStart(2,"0")+']</span> </li>'}).join("");
  $("log").querySelectorAll("li").forEach((el,i)=>el.appendChild(document.createTextNode(S.log[i].m)));
}

/* ---- events ---- */
document.addEventListener("click",e=>{
  const t=e.target.closest("#view-app button");if(!t||!S)return;
  if(t.dataset.stat&&S.points>0){
    S.points--;S.stats[t.dataset.stat]++;
    if(t.dataset.stat==="vit")S.hp+=10;if(t.dataset.stat==="int")S.mp+=5;
    toast("Stat naik",t.dataset.stat.toUpperCase()+" sekarang "+S.stats[t.dataset.stat]+".","gold",2500);
    save();render();return;
  }
  if(t.dataset.time){S.rem.times=S.rem.times.filter(x=>x!==t.dataset.time);save();renderRem();return}
  if(t.dataset.g&&!S.daily.done){
    const g=GOALS.find(x=>x.id===t.dataset.g);
    const before=S.daily.counts[g.id];
    const v=Math.round((before+parseFloat(t.dataset.v))*10)/10;
    S.daily.counts[g.id]=Math.max(0,Math.min(g.target,v));
    if(before<g.target&&S.daily.counts[g.id]>=g.target&&!GOALS.every(x=>S.daily.counts[x.id]>=x.target))
      toast("Target tercapai",g.name+" "+g.target+g.unit+" selesai. Sisa: "+leftList()+".","",4000);
    if(GOALS.every(x=>S.daily.counts[x.id]>=x.target))completeDaily();
    save();render();return;
  }
  if(t.dataset.q){
    const q=S.quests.find(x=>x.id==t.dataset.q);
    if(!q.done){
      q.done=true;S.stats[q.stat]+= (q.tier==="A"||q.tier==="S")?1:0;
      addLog("Quest selesai: "+q.title+" (+"+TIER_XP[q.tier]+" EXP)");
      notify({tag:"Quest Selesai",title:q.title,rewards:["+"+TIER_XP[q.tier]+" EXP"].concat((q.tier==="A"||q.tier==="S")?["+1 "+q.stat.toUpperCase()]:[]),sound:"ok"});
      gainXp(TIER_XP[q.tier]);
      save();render();
    }
    return;
  }
  if(t.dataset.del){S.quests=S.quests.filter(x=>x.id!=t.dataset.del);save();render();return}
});

$("addForm").addEventListener("submit",e=>{
  e.preventDefault();
  const title=$("qTitle").value.trim();if(!title)return;
  S.quests.push({id:Date.now(),title,tier:$("qTier").value,stat:$("qStat").value,done:false});
  addLog("Quest baru diterima: "+title);
  toast("Quest baru diterima",title,"",3000);
  $("qTitle").value="";save();render();
});

$("remOn").addEventListener("change",e=>{
  S.rem.on=e.target.checked;save();
  toast("Notifikasi",S.rem.on?"Pengingat daily quest aktif.":"Pengingat daily quest dimatikan.","",2500);
});
$("timeForm").addEventListener("submit",e=>{
  e.preventDefault();const v=$("newTime").value;if(!v)return;
  if(!S.rem.times.includes(v)){S.rem.times.push(v);S.rem.times.sort()}
  // a time already past today shouldn't fire immediately
  const n=new Date(),now=String(n.getHours()).padStart(2,"0")+":"+String(n.getMinutes()).padStart(2,"0");
  if(v<=now&&!S.rem.fired.list.includes(v))S.rem.fired.list.push(v);
  save();renderRem();toast("Notifikasi","Pengingat jam "+v+" ditambahkan.","",2500);
});
$("testNotif").addEventListener("click",()=>{
  const body=S.daily.done?"Daily quest hari ini sudah selesai. Kerja bagus.":"Sisa waktu "+remaining()+". Belum selesai: "+leftList()+".";
  toast("Tes notifikasi",body,"alert",6000);chime(false);
  try{if(canNotif()&&Notification.permission==="granted")new Notification("[Sistem] Tes notifikasi",{body})}catch(e){}
});
$("permBtn").addEventListener("click",async()=>{
  try{
    const r=await Promise.race([Notification.requestPermission(),new Promise(res=>setTimeout(()=>res(Notification.permission),8000))]);
    toast("Notifikasi",r==="granted"?"Notifikasi browser aktif.":"Notifikasi browser tidak diizinkan. Pengingat tetap muncul di halaman.",r==="granted"?"gold":"",4000);
  }catch(e){toast("Notifikasi","Browser menolak permintaan izin. Pengingat tetap muncul di halaman.","",4000)}
  renderRem();
});

$("nameBtn").addEventListener("click",()=>{
  const inp=document.createElement("input");inp.id="nameInput";inp.value=S.name;inp.maxLength=30;inp.style.width="100%";
  notify({tag:"Identitas",title:"Siapa namamu, Player?",extra:inp,ok:"Simpan",onOk:()=>{
    const v=inp.value.trim();if(!v){inp.focus();return false}
    S.name=v;addLog("Nama Player diubah menjadi "+v+".");save();render();
  }});
  setTimeout(()=>inp.select(),30);
});

function renderFoot(confirming){
  const f=$("foot");
  if(!confirming){
    f.innerHTML='<button class="btn danger" id="resetBtn">Reset Player</button>';
    $("resetBtn").onclick=()=>renderFoot(true);
    return;
  }
  f.innerHTML='<span>Level, stat, quest, dan riwayat akan hilang. Profil tetap tersimpan. Yakin?</span><button class="btn" id="noR">Batal</button><button class="btn danger" id="yesR">Ya, reset</button>';
  $("noR").onclick=()=>renderFoot(false);
  $("yesR").onclick=async()=>{
    clearTimeout(saveTimer);dirty=false;
    try{await Sys.api("reset");location.reload()}
    catch(e){toast("Reset gagal",e.message,"alert",5000)}
  };
}

$("logoutBtn").addEventListener("click",async()=>{
  // Simpan perubahan yang belum terkirim dulu.
  if(dirty){clearTimeout(saveTimer);await flush()}
  Sys.logout();
});

/* ---- clock & countdown ---- */
function tick(){
  const n=new Date();
  $("clock").textContent=n.toLocaleDateString("id-ID",{weekday:"long",day:"numeric",month:"long"})+" · "+n.toLocaleTimeString("id-ID",{hour12:false});
  const m=new Date(n);m.setHours(24,0,0,0);const s=Math.floor((m-n)/1000);
  $("countdown").textContent=[s/3600,s%3600/60,s%60].map(x=>String(Math.floor(x)).padStart(2,"0")).join(":");
  if(S.daily.date!==today()){checkDay();render();renderRem()}
  checkReminders();
}


/* ---- profil: data tubuh, target rank, berat badan ---- */
function bmiInfo(kg,cm){
  const h=cm/100,b=kg/(h*h);
  // Klasifikasi IMT Kemenkes RI
  const c=b<18.5?["kurus","Kurus"]:b<=25?["normal","Normal"]:b<=27?["gemuk","Gemuk"]:["obesitas","Obesitas"];
  return {v:b.toFixed(1),cls:c[0],label:c[1]};
}
function ageOf(d){
  if(!d)return null;const b=new Date(d+"T00:00:00"),n=new Date();
  let a=n.getFullYear()-b.getFullYear();if(n<new Date(n.getFullYear(),b.getMonth(),b.getDate()))a--;return a;
}
function renderProfile(){
  if(!profile||!$("prof"))return;
  const p=profile,bmi=bmiInfo(p.weight,p.height),age=ageOf(p.birthDate);
  const items=[
    ["Umur",age!==null?age+' <small>tahun</small>':'<small>—</small>'],
    ["Jenis kelamin",p.gender==="L"?"<small>Laki-laki</small>":p.gender==="P"?"<small>Perempuan</small>":"<small>—</small>"],
    ["Tinggi",p.height+' <small>cm</small>'],
    ["Berat",p.weight.toFixed(1)+' <small>kg</small>'],
    ["BMI",bmi.v+' <small>'+bmi.label+'</small>',"cat-"+bmi.cls],
    ["Target berat",p.targetWeight?p.targetWeight.toFixed(1)+' <small>'+(Math.abs(p.targetWeight-p.weight)<0.05?"tercapai":(p.targetWeight<p.weight?"−":"+")+Math.abs(p.targetWeight-p.weight).toFixed(1)+" kg lagi")+'</small>':'<small>—</small>']
  ];
  $("prof").innerHTML=items.map(i=>'<div><dt>'+i[0]+'</dt><dd class="'+(i[2]||"")+'">'+i[1]+'</dd></div>').join("");

  const RL=(BOOT.meta&&BOOT.meta.rankLevels)||{E:1,D:10,C:20,B:35,A:50,S:70},need=RL[p.targetRank]||1;
  const reached=S.level>=need;
  $("tRank").textContent="Rank "+p.targetRank;
  $("tRankN").textContent=reached?"Tercapai di Level "+S.level:"Level "+S.level+" / "+need+" · "+(need-S.level)+" level lagi";
  $("tRankF").style.width=Math.min(100,S.level/need*100)+"%";
  $("goalTxt").textContent="Tujuan: "+p.goalLabel;
  renderWeights();
}
function renderWeights(){
  const svg=$("wtChart");if(!svg)return;
  const w=weights.slice(-14);
  if(w.length<2){
    svg.innerHTML="";
    $("wtCap").textContent=w.length?"Catat berat lagi besok untuk melihat grafiknya.":"Belum ada catatan berat.";
    return;
  }
  const vals=w.map(x=>x.kg);if(profile.targetWeight)vals.push(profile.targetWeight);
  let lo=Math.min(...vals),hi=Math.max(...vals);if(hi-lo<1){lo-=.5;hi+=.5}
  const X=i=>6+i*(288/(w.length-1)),Y=v=>8+(hi-v)/(hi-lo)*54;
  const pts=w.map((x,i)=>X(i).toFixed(1)+","+Y(x.kg).toFixed(1)).join(" ");
  let s='<polygon points="'+X(0)+',70 '+pts+' '+X(w.length-1)+',70" fill="rgba(111,211,255,.12)"/>';
  if(profile.targetWeight)s+='<line x1="0" x2="300" y1="'+Y(profile.targetWeight)+'" y2="'+Y(profile.targetWeight)+'" stroke="#ffd166" stroke-dasharray="4 4" stroke-width="1" vector-effect="non-scaling-stroke"/>';
  s+='<polyline points="'+pts+'" fill="none" stroke="#6fd3ff" stroke-width="2" vector-effect="non-scaling-stroke"/>';
  const l=w[w.length-1];
  s+='<circle cx="'+X(w.length-1)+'" cy="'+Y(l.kg)+'" r="3.5" fill="#6fd3ff"/>';
  svg.innerHTML=s;
  const diff=l.kg-w[0].kg;
  $("wtCap").textContent=w.length+" catatan terakhir · "+(Math.abs(diff)<0.05?"stabil":(diff>0?"naik ":"turun ")+Math.abs(diff).toFixed(1)+" kg")+(profile.targetWeight?" · garis kuning = target":"");
}
$("wtForm").addEventListener("submit",async e=>{
  e.preventDefault();
  const kg=parseFloat($("wtInput").value);
  if(!(kg>=25&&kg<=300)){toast("Berat tidak valid","Isi berat antara 25 dan 300 kg.","alert",4000);return}
  try{
    const j=await Sys.api("weight",{kg});
    const prev=profile.weight;profile=BOOT.profile=j.profile;weights=BOOT.weights=j.weights;renderProfile();
    const d=profile.weight-prev;
    addLog("Berat badan dicatat: "+profile.weight.toFixed(1)+" kg.");save();
    toast("Berat tercatat",profile.weight.toFixed(1)+" kg"+(Math.abs(d)>=0.05?" ("+(d>0?"+":"−")+Math.abs(d).toFixed(1)+" kg)":"")+".","gold",3500);
    if(profile.targetWeight&&Math.abs(profile.weight-profile.targetWeight)<0.05)
      notify({tag:"Target Tercapai",title:"Target berat badan tercapai!",body:"Berat kamu sekarang "+profile.weight.toFixed(1)+" kg.",sound:"up"});
  }catch(err){toast("Gagal menyimpan berat",err.message||"Database tidak bisa dihubungi.","alert",5000)}
});

/* ---- riwayat 14 hari dari tabel daily_quests ---- */
function renderHistory(){
  const el=$("history");if(!el)return;
  const byDate={};history.forEach(h=>byDate[h.date]=h);
  const days=[];
  for(let i=13;i>=0;i--){
    const d=new Date();d.setDate(d.getDate()-i);
    const k=d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0");
    days.push({k,d,h:byDate[k]});
  }
  el.innerHTML=days.map(({k,d,h})=>{
    const isToday=k===today();
    const cls=h&&h.done?"ok":isToday?"now":h?"fail":"none";
    const tip=k+(h?(h.done?" · selesai":" · "+h.push+" push-up, "+h.sit+" sit-up, "+h.squat+" squat, "+h.run+" km"):" · tidak ada data");
    return '<div class="day '+cls+'" title="'+tip+'"><span>'+d.toLocaleDateString("id-ID",{weekday:"short"}).slice(0,2)+'</span><b>'+d.getDate()+'</b></div>';
  }).join("");
  const done=history.filter(h=>h.done).length;
  $("histSum").textContent=done+" dari "+history.length+" hari tercatat selesai";
}

/* ---- dipanggil router ---- */
function mount(boot){
  BOOT=boot;
  S=boot.state=Object.assign(fresh(),boot.state||{}); // satu objek: layar profil membaca nama terbaru dari sini
  if(!S.daily)S.daily=fresh().daily;
  if(!S.rem)S.rem=fresh().rem;
  if(boot.newPlayer&&!S.quests.length)S.quests=fresh().quests; // contoh quest untuk Player baru
  history=boot.history||[];
  profile=boot.profile||null;
  weights=boot.weights||[];
  applyTargets();
  $("whoName").textContent=boot.user.username;
  if(profile)$("wtInput").value=profile.weight;
  renderFoot(false);
  checkDay();render();renderRem();renderHistory();tick();setInterval(tick,1000);
  setSync("Tersimpan","ok");
  if(boot.newPlayer){
    boot.newPlayer=false;
    S.hp=maxHp();S.mp=maxMp();render();save();
    notify({tag:"Notifikasi",title:"Kamu telah memperoleh syarat untuk menjadi Player.",body:"Apakah kamu menerimanya?",ok:"Terima"});
  }
}
function refresh(){render();renderRem();renderHistory()}
function setProfile(p,w,name){
  profile=p;weights=w||weights;
  if(name){S.name=name}
  applyTargets();
  $("wtInput").value=p.weight;
  render();
}
window.SystemApp={mount,refresh,setProfile};
})();
