/* Inti aplikasi satu halaman: helper, API, notifikasi, modal, dan router antar layar. */
(function(){
const Sys=window.Sys={boot:window.BOOT||{csrf:"",user:null,meta:{}},appMounted:false};
const $=Sys.$=id=>document.getElementById(id);

/* ---- API: semua data lewat api.php (JSON) ---- */
Sys.api=async function(action,data,opts){
  const get=opts&&opts.method==="GET";
  const r=await fetch(get?"api.php?action="+encodeURIComponent(action):"api.php",{
    method:get?"GET":"POST",
    headers:get?{}:{"Content-Type":"application/json","X-CSRF-Token":Sys.boot.csrf},
    body:get?undefined:JSON.stringify(Object.assign({action},data||{})),
    credentials:"same-origin"
  });
  const j=await r.json().catch(()=>({error:"Server tidak menjawab. Pastikan Apache & MySQL menyala."}));
  if(j.csrf)Sys.boot.csrf=j.csrf;
  if(r.status===401&&action!=="login"){Sys.sessionExpired();}
  if(!r.ok){const e=new Error(j.error||"Terjadi kesalahan.");e.status=r.status;e.errors=j.errors||null;throw e}
  return j;
};

/* ---- suara ---- */
let ac;
Sys.chime=function(up){
  try{
    ac=ac||new (window.AudioContext||window.webkitAudioContext)();
    (up?[523,659,784,1047]:[880,1320]).forEach((f,i)=>{
      const o=ac.createOscillator(),g=ac.createGain(),t=ac.currentTime+i*.09;
      o.type="sine";o.frequency.value=f;g.gain.setValueAtTime(0,t);
      g.gain.linearRampToValueAtTime(.12,t+.02);g.gain.exponentialRampToValueAtTime(.001,t+.5);
      o.connect(g).connect(ac.destination);o.start(t);o.stop(t+.55);
    });
  }catch(e){}
};

/* ---- toast & notifikasi browser ---- */
Sys.toast=function(title,body,kind,ms){
  const el=document.createElement("div");el.className="toast"+(kind?" "+kind:"");
  const a=document.createElement("div");a.className="tt";a.textContent=title;
  const b=document.createElement("div");b.className="tb";b.textContent=body;
  el.append(a,b);$("toasts").prepend(el);
  el.onclick=()=>el.remove();
  setTimeout(()=>el.remove(),ms||5000);
};
Sys.canNotif=()=>{try{return "Notification" in window}catch(e){return false}};
let flashT=null;const baseTitle=document.title;
function flashTitle(msg){
  clearInterval(flashT);let on=false;
  flashT=setInterval(()=>{on=!on;document.title=on?"[!] "+msg:baseTitle},1000);
}
document.addEventListener("visibilitychange",()=>{if(!document.hidden){clearInterval(flashT);document.title=baseTitle}});
Sys.sysNotify=function(title,body){
  try{if(Sys.canNotif()&&Notification.permission==="granted")new Notification("[Sistem] "+title,{body,tag:"system-"+title})}catch(e){}
  if(document.hidden)flashTitle(title);
};

/* ---- modal "Notifikasi Sistem" (antrean) ---- */
const queue=[];let showing=false;
Sys.notify=function(n){
  queue.push(n);
  if(n.tag!=="Identitas")Sys.sysNotify(n.title,n.body||(n.rewards||[]).join(" · "));
  if(!showing)nextModal();
};
function nextModal(){
  const n=queue.shift();
  if(!n){$("modal").hidden=true;showing=false;return}
  showing=true;
  $("mTag").textContent=n.tag||"Notifikasi";
  $("mTitle").textContent=n.title;
  $("mBody").textContent=n.body||"";
  $("mRew").innerHTML="";
  (n.rewards||[]).forEach(r=>{const d=document.createElement("div");d.textContent=r;$("mRew").appendChild(d)});
  $("mExtra").innerHTML="";
  if(n.extra)$("mExtra").appendChild(n.extra);
  $("mOk").textContent=n.ok||"Terima";
  $("mOk").onclick=()=>{if(n.onOk&&n.onOk()===false)return;nextModal()};
  $("modal").hidden=false;$("mOk").focus();
  if(n.sound)Sys.chime(n.sound==="up");
}
Sys.closeModal=nextModal;
document.addEventListener("keydown",e=>{if(e.key==="Escape"&&!$("modal").hidden)nextModal()});

/* ---- router: #masuk, #daftar, #profil, atau halaman utama ---- */
const VIEWS=["auth","profile","app"];
function show(name){
  VIEWS.forEach(v=>{const el=$("view-"+v);el.hidden=v!==name;if(v===name){el.classList.remove("enter");void el.offsetWidth;el.classList.add("enter")}});
  scrollTo(0,0);
}
Sys.go=function(hash){
  if((location.hash||"#")===(hash||"#"))route();
  else location.hash=hash;
};
function route(){
  const b=Sys.boot,h=location.hash;
  if(!b.user){show("auth");Views.auth(h==="#daftar"?"register":"login");return}
  if(!b.profile){show("profile");Views.profile(true);return}
  if(h==="#profil"){show("profile");Views.profile(false);return}
  show("app");
  if(!Sys.appMounted){Sys.appMounted=true;SystemApp.mount(b)}
  else SystemApp.refresh();
}
Sys.route=route;
addEventListener("hashchange",route);

/* ---- sesi ---- */
Sys.setBoot=function(boot){
  // Sudah ada Player lain yang dimuat di halaman ini: muat ulang supaya datanya bersih.
  if(Sys.appMounted&&boot.user){location.replace(location.pathname);return}
  Sys.boot=boot;Sys.go("");
};
Sys.logout=async function(){
  try{await Sys.api("logout")}catch(e){}
  Sys.boot={csrf:Sys.boot.csrf,user:null,meta:Sys.boot.meta};
  $("view-profile").innerHTML=""; // jangan tinggalkan data Player di halaman
  Sys.go("#masuk");
};
Sys.sessionExpired=function(){
  if(!Sys.boot.user)return;
  Sys.boot={csrf:Sys.boot.csrf,user:null,meta:Sys.boot.meta};
  $("view-profile").innerHTML=""; // jangan tinggalkan data Player di halaman
  Sys.toast("Sesi berakhir","Silakan masuk lagi untuk melanjutkan.","alert",5000);
  Sys.go("#masuk");
};

document.addEventListener("DOMContentLoaded",route);
})();
