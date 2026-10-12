/* Layar login/daftar dan profil, dirender di browser tanpa pindah halaman. */
(function(){
const {$}=Sys;
const esc=s=>String(s==null?"":s).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const errBox=(form,errors)=>{
  form.querySelectorAll(".field-err").forEach(e=>e.remove());
  const top=form.querySelector(".form-err");if(top){top.hidden=true;top.textContent=""}
  Object.entries(errors||{}).forEach(([k,msg])=>{
    const slot=form.querySelector('[data-err="'+k+'"]');
    if(slot){const s=document.createElement("span");s.className="field-err";s.textContent=msg;slot.appendChild(s)}
    else if(top){top.hidden=false;top.textContent=msg}
  });
  const first=form.querySelector(".field-err, .form-err:not([hidden])");
  if(first)first.scrollIntoView({block:"center",behavior:"smooth"});
};
function busy(btn,on,label){btn.disabled=on;if(on){btn.dataset.label=btn.textContent;btn.textContent=label}else if(btn.dataset.label)btn.textContent=btn.dataset.label}

/* ---- login & daftar ---- */
function auth(mode){
  const reg=mode==="register",root=$("view-auth");
  root.innerHTML=
  '<section class="win">'+
    '<div class="win-title"><span>'+(reg?"Registrasi Player":"Autentikasi Player")+'</span></div>'+
    (reg?'<h1>Kamu telah memperoleh syarat untuk menjadi Player.</h1><p class="sub">Daftarkan dirimu untuk menerimanya.</p>'
        :'<h1>Selamat datang kembali, Player.</h1><p class="sub">Masuk untuk melanjutkan daily quest-mu.</p>')+
    '<form id="authForm" autocomplete="on" novalidate>'+
      '<div class="err form-err" role="alert" hidden></div>'+
      '<label for="username" data-err="username">Username<input id="username" name="username" required maxlength="30" autocomplete="username"></label>'+
      '<label for="password" data-err="password">Password<input id="password" name="password" type="password" required autocomplete="'+(reg?"new-password":"current-password")+'"></label>'+
      (reg?'<label for="confirm" data-err="confirm">Ulangi password<input id="confirm" name="confirm" type="password" required autocomplete="new-password"></label>':'')+
      '<button class="btn" type="submit">'+(reg?"Terima &amp; Daftar":"Masuk")+'</button>'+
    '</form>'+
    '<p class="switch">'+(reg?'Sudah punya akun? <a href="#masuk">Masuk</a>':'Belum jadi Player? <a href="#daftar">Daftar sekarang</a>')+'</p>'+
    (Sys.boot.local?'<p class="local-note">Mode tanpa database: akun hanya tersimpan di browser ini. Daftar dulu kalau baru pertama membuka aplikasi di perangkat ini.</p>':'')+
  '</section>';
  const form=$("authForm");
  $("username").focus();
  form.addEventListener("submit",async e=>{
    e.preventDefault();
    const d=Object.fromEntries(new FormData(form));
    const local={};
    if(!d.username.trim())local.username="Isi username.";
    if(!d.password)local.password="Isi password.";
    if(reg&&d.password&&d.password.length<6)local.password="Password minimal 6 karakter.";
    if(reg&&d.password!==d.confirm)local.confirm="Konfirmasi password tidak sama.";
    if(Object.keys(local).length){errBox(form,local);return}
    const btn=form.querySelector("button[type=submit]");
    busy(btn,true,reg?"Mendaftarkan…":"Memeriksa…");
    try{
      const j=await Sys.api(reg?"register":"login",d);
      Sys.setBoot(j.boot);
    }catch(err){
      errBox(form,err.errors||{form:err.message});
      busy(btn,false);
    }
  });
}

/* ---- profil (pertama kali & edit) ---- */
function profile(setup){
  const b=Sys.boot,m=b.meta,p=b.profile||{},t=p.targets||m.presets.pemula;
  const f={
    name:(b.state&&b.state.name)||b.user.username,gender:p.gender||"",birth_date:p.birthDate||"",
    height:p.height||"",weight:p.weight||"",target_weight:p.targetWeight||"",
    target_rank:p.targetRank||"C",fitness:p.fitness||"pemula",goal:p.goal||"disiplin",
    push:t.push,sit:t.sit,squat:t.squat,run:t.run
  };
  const today=new Date().toISOString().slice(0,10);
  const radio=(name,val,cur)=>'name="'+name+'" value="'+esc(val)+'"'+(String(cur)===String(val)?" checked":"");
  const root=$("view-profile");
  root.innerHTML=
  '<header class="top"><div class="brand">[ The System ]</div>'+(setup?'':'<a class="btn" href="#">Kembali</a>')+'</header>'+
  '<form id="pfForm" class="win pf" novalidate>'+
    '<div class="win-title"><span>'+(setup?"Registrasi Profil":"Edit Profil")+'</span></div>'+
    (setup?'<p class="q-intro">Sistem membutuhkan data tubuhmu.</p><p class="q-sub">Data ini dipakai untuk menyesuaikan daily quest dan melacak perkembanganmu. Bisa diubah kapan saja.</p>':'')+
    '<div class="warn form-err" role="alert" hidden></div>'+
    '<fieldset><legend>Identitas</legend><div class="pf-grid">'+
      '<label class="pf-wide" for="name" data-err="name">Nama Player<input id="name" name="name" value="'+esc(f.name)+'" maxlength="30" required></label>'+
      '<div class="pf-field"><span class="pf-label">Jenis kelamin</span><div class="seg">'+
        [["L","Laki-laki"],["P","Perempuan"],["","Tidak disebut"]].map(([v,l])=>'<label><input type="radio" '+radio("gender",v,f.gender)+'><span>'+l+'</span></label>').join("")+
      '</div></div>'+
      '<label for="birth_date" data-err="birth_date"><span>Tanggal lahir <small>(opsional)</small></span><input id="birth_date" name="birth_date" type="date" value="'+esc(f.birth_date)+'" max="'+today+'"></label>'+
    '</div></fieldset>'+
    '<fieldset><legend>Tubuh</legend><div class="pf-grid three">'+
      '<label for="height" data-err="height">Tinggi badan<span class="unit"><input id="height" name="height" type="number" inputmode="numeric" min="100" max="250" step="1" value="'+esc(f.height)+'" placeholder="170" required><i>cm</i></span></label>'+
      '<label for="weight" data-err="weight">Berat badan<span class="unit"><input id="weight" name="weight" type="number" inputmode="decimal" min="25" max="300" step="0.1" value="'+esc(f.weight)+'" placeholder="65" required><i>kg</i></span></label>'+
      '<label for="target_weight" data-err="target_weight"><span>Target berat <small>(opsional)</small></span><span class="unit"><input id="target_weight" name="target_weight" type="number" inputmode="decimal" min="25" max="300" step="0.1" value="'+esc(f.target_weight)+'" placeholder="60"><i>kg</i></span></label>'+
    '</div><div class="bmi" id="bmi" aria-live="polite"></div></fieldset>'+
    '<fieldset><legend>Target</legend>'+
      '<div class="pf-field" data-err="target_rank"><span class="pf-label">Target rank</span><div class="ranks">'+
        Object.entries(m.rankLevels).map(([r,lv])=>'<label class="rank-opt"><input type="radio" '+radio("target_rank",r,f.target_rank)+'><span><b>'+r+'</b><small>Lv '+lv+'</small></span></label>').join("")+
      '</div></div>'+
      '<label for="goal" data-err="goal">Tujuan utama<select id="goal" name="goal">'+
        Object.entries(m.goals).map(([v,l])=>'<option value="'+v+'"'+(f.goal===v?" selected":"")+'>'+esc(l)+'</option>').join("")+
      '</select></label>'+
    '</fieldset>'+
    '<fieldset><legend>Daily Quest</legend>'+
      '<div class="pf-field" data-err="fitness"><span class="pf-label">Tingkat kebugaran</span><div class="levels">'+
        Object.entries(m.presets).map(([v,ps])=>'<label class="lvl-opt"><input type="radio" '+radio("fitness",v,f.fitness)+'><span><b>'+esc(ps.label)+'</b><small>'+esc(ps.desc)+'</small><em>'+ps.push+' rep · '+ps.run+' km</em></span></label>').join("")+
      '</div></div>'+
      '<div class="pf-grid four" data-err="targets">'+
        [["push","Push-up","×",1,1000,1],["sit","Sit-up","×",1,1000,1],["squat","Squat","×",1,1000,1],["run","Lari","km",.5,50,.5]].map(([k,l,u,mn,mx,st])=>
          '<label for="'+k+'">'+l+'<span class="unit"><input id="'+k+'" name="'+k+'" type="number" min="'+mn+'" max="'+mx+'" step="'+st+'" value="'+esc(f[k])+'"><i>'+u+'</i></span></label>').join("")+
      '</div>'+
      '<p class="q-sub pf-note">Target terisi otomatis dari tingkat kebugaran. Kamu boleh mengubah angkanya sendiri.</p>'+
    '</fieldset>'+
    '<button class="btn pf-submit" type="submit">'+(setup?"Simpan &amp; Mulai":"Simpan Profil")+'</button>'+
  '</form>';

  const form=$("pfForm");
  function bmi(){
    const h=parseFloat($("height").value)/100,w=parseFloat($("weight").value),tw=parseFloat($("target_weight").value);
    if(!(h>=1&&h<=2.5&&w>=25&&w<=300)){$("bmi").textContent="Isi tinggi dan berat untuk melihat BMI.";$("bmi").dataset.cat="";return}
    const v=w/(h*h);
    // Klasifikasi IMT Kemenkes RI
    const c=v<18.5?["kurus","Kurus"]:v<=25?["normal","Normal"]:v<=27?["gemuk","Gemuk"]:["obesitas","Obesitas"];
    let txt="BMI "+v.toFixed(1)+" · "+c[1]+". Berat ideal untuk tinggimu: "+(18.5*h*h).toFixed(1)+"–"+(25*h*h).toFixed(1)+" kg.";
    if(tw>=25){const d=tw-w;txt+=" Target: "+(d<0?"turun "+(-d).toFixed(1)+" kg.":d>0?"naik "+d.toFixed(1)+" kg.":"pertahankan.")}
    $("bmi").textContent=txt;$("bmi").dataset.cat=c[0];
  }
  ["height","weight","target_weight"].forEach(id=>$(id).addEventListener("input",bmi));
  form.querySelectorAll('input[name="fitness"]').forEach(r=>r.addEventListener("change",()=>{
    const ps=m.presets[r.value];["push","sit","squat","run"].forEach(k=>$(k).value=ps[k]);
  }));
  bmi();

  form.addEventListener("submit",async e=>{
    e.preventDefault();
    const d=Object.fromEntries(new FormData(form));
    const local={};
    if(!d.name.trim())local.name="Isi nama Player.";
    if(!(+d.height>=100&&+d.height<=250))local.height="Tinggi badan 100–250 cm.";
    if(!(+d.weight>=25&&+d.weight<=300))local.weight="Berat badan 25–300 kg.";
    if(Object.keys(local).length){errBox(form,local);return}
    const btn=form.querySelector("button[type=submit]");
    busy(btn,true,"Menyimpan…");
    try{
      const j=await Sys.api("profile",{profile:d});
      b.profile=j.profile;b.weights=j.weights;
      if(b.state)b.state.name=j.name;
      if(j.newPlayer)b.newPlayer=true;
      if(Sys.appMounted)SystemApp.setProfile(j.profile,j.weights,j.name);
      if(!setup)Sys.toast("Profil disimpan","Data profil dan target harian diperbarui.","gold",3000);
      Sys.go("");
    }catch(err){
      errBox(form,err.errors||{form:err.message});
      busy(btn,false);
    }
  });
}

window.Views={auth,profile};
})();
