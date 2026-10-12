/*
 * Mode tanpa database: pengganti api.php yang berjalan di browser.
 * Akun, profil, dan progres disimpan di localStorage, jadi hanya ada di browser & perangkat ini.
 * Aksinya sama dengan api.php (boot, login, register, logout, profile, save, weight, reset),
 * sehingga core.js, views.js, dan app.js tidak perlu tahu datanya disimpan di mana.
 */
(function(){
const KEY_USERS="ts_users",KEY_SESSION="ts_session",KEY_DATA="ts_data_";

// Sama dengan includes/profile.php
const PRESETS={
  pemula:  {label:"Pemula",  desc:"Jarang olahraga",        push:20, sit:20, squat:20, run:2},
  menengah:{label:"Menengah",desc:"Olahraga 1–3x seminggu", push:50, sit:50, squat:50, run:5},
  lanjut:  {label:"Lanjut",  desc:"Menu asli Sung Jin-Woo", push:100,sit:100,squat:100,run:10}
};
const GOALS={turun_berat:"Menurunkan berat badan",bangun_otot:"Membangun otot",stamina:"Meningkatkan stamina",disiplin:"Membangun disiplin"};
const RANK_LEVELS={E:1,D:10,C:20,B:35,A:50,S:70};
const DEFAULT_REMINDERS=["07:00","12:00","19:00","22:00"];

/* ---- penyimpanan ---- */
let memory={}; // cadangan kalau localStorage diblokir (mode privat)
function load(k,def){
  try{const v=localStorage.getItem(k);return v===null?def:JSON.parse(v)}
  catch(e){return k in memory?memory[k]:def}
}
function store(k,v){
  try{localStorage.setItem(k,JSON.stringify(v))}catch(e){memory[k]=v}
}
function drop(k){try{localStorage.removeItem(k)}catch(e){delete memory[k]}}
const dataOf=u=>load(KEY_DATA+u.toLowerCase(),null);
const saveData=(u,d)=>store(KEY_DATA+u.toLowerCase(),d);

/* ---- helper ---- */
const today=()=>{const d=new Date();return d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0")};
function fail(status,errors){const e=new Error(Object.values(errors)[0]);e.status=status;e.errors=errors;throw e}
async function hashPassword(password,salt){
  const text=salt+":"+password;
  try{
    const buf=await crypto.subtle.digest("SHA-256",new TextEncoder().encode(text));
    return Array.from(new Uint8Array(buf)).map(b=>b.toString(16).padStart(2,"0")).join("");
  }catch(e){
    // crypto.subtle hanya ada di HTTPS/localhost; cadangan sederhana
    let h=0;for(let i=0;i<text.length;i++){h=(h*31+text.charCodeAt(i))|0}return "x"+h;
  }
}
const randomSalt=()=>Array.from(crypto.getRandomValues(new Uint8Array(16))).map(b=>b.toString(16).padStart(2,"0")).join("");
const validWeight=v=>{const n=Math.round(parseFloat(v)*10)/10;return n>=25&&n<=300?n:null};

function newState(username){
  return {
    name:username,level:1,xp:0,points:0,streak:0,hp:100,mp:10,
    stats:{str:10,agi:10,vit:10,int:10,per:10},
    daily:null,quests:[],
    log:[{t:Date.now(),m:"Kamu telah memperoleh syarat untuk menjadi Player."}],
    rem:{on:true,times:DEFAULT_REMINDERS.slice(),fired:{date:"",list:[]}}
  };
}
// Riwayat daily quest: 14 hari terakhir, terbaru dulu (format sama dengan api.php)
const historyOf=d=>d.history.slice().sort((a,b)=>a.date<b.date?1:-1).slice(0,14);
const weightsOf=d=>d.weights.slice().sort((a,b)=>a.date<b.date?-1:1).slice(-30);

function currentUser(){
  const u=load(KEY_SESSION,null);
  return u&&dataOf(u)?u:null;
}

function boot(){
  const b={csrf:"local",user:null,local:true,meta:{presets:PRESETS,goals:GOALS,rankLevels:RANK_LEVELS}};
  const u=currentUser();
  if(!u)return b;
  const d=dataOf(u);
  b.user={username:d.username};
  b.profile=d.profile;
  b.state=d.state;
  b.history=historyOf(d);
  b.weights=weightsOf(d);
  b.newPlayer=!!(d.profile&&d.newPlayer);
  if(b.newPlayer){d.newPlayer=false;saveData(u,d)}
  return b;
}

/* ---- aksi ---- */
const actions={
  boot:()=>({boot:boot()}),

  async register({username="",password="",confirm=""}){
    username=username.trim();
    if(!/^[A-Za-z0-9_]{3,30}$/.test(username))fail(422,{username:"Username 3–30 karakter: huruf, angka, atau garis bawah."});
    if(password.length<6)fail(422,{password:"Password minimal 6 karakter."});
    if(password!==confirm)fail(422,{confirm:"Konfirmasi password tidak sama."});
    const users=load(KEY_USERS,{});
    if(users[username.toLowerCase()])fail(422,{username:"Username sudah dipakai di browser ini."});
    const salt=randomSalt();
    users[username.toLowerCase()]={username,salt,hash:await hashPassword(password,salt),createdAt:Date.now()};
    store(KEY_USERS,users);
    saveData(username,{username,profile:null,state:newState(username),history:[],weights:[],newPlayer:true});
    store(KEY_SESSION,username.toLowerCase());
    return {boot:boot()};
  },

  async login({username="",password=""}){
    const u=load(KEY_USERS,{})[username.trim().toLowerCase()];
    if(!u||u.hash!==await hashPassword(password,u.salt))fail(422,{form:"Username atau password salah."});
    store(KEY_SESSION,u.username.toLowerCase());
    return {boot:boot()};
  },

  logout(){drop(KEY_SESSION);return {csrf:"local"}},

  profile({profile:f={}}){
    const u=needUser(),d=dataOf(u),errors={};
    const s=k=>String(f[k]==null?"":f[k]).trim();
    const name=s("name");
    if(!name||name.length>30)errors.name="Isi nama Player, maksimal 30 karakter.";
    const birth=s("birth_date");
    if(birth){
      const bd=new Date(birth+"T00:00:00"),age=(Date.now()-bd)/31557600000;
      if(isNaN(bd)||bd>new Date()||age<7||age>100)errors.birth_date="Tanggal lahir tidak valid (umur 7–100 tahun).";
    }
    const height=parseInt(s("height"),10);
    if(!(height>=100&&height<=250))errors.height="Tinggi badan 100–250 cm.";
    const weight=validWeight(s("weight"));
    if(weight===null)errors.weight="Berat badan 25–300 kg.";
    let targetWeight=null;
    if(s("target_weight")&&(targetWeight=validWeight(s("target_weight")))===null)errors.target_weight="Target berat 25–300 kg, atau kosongkan.";
    if(!RANK_LEVELS[s("target_rank")])errors.target_rank="Pilih target rank.";
    if(!PRESETS[s("fitness")])errors.fitness="Pilih tingkat kebugaran.";
    if(!GOALS[s("goal")])errors.goal="Pilih tujuan utama.";
    const t={push:+s("push"),sit:+s("sit"),squat:+s("squat"),run:Math.round(parseFloat(s("run"))*10)/10};
    if(![t.push,t.sit,t.squat].every(n=>Number.isInteger(n)&&n>=1&&n<=1000)||!(t.run>=0.5&&t.run<=50))
      errors.targets="Target repetisi 1–1000, lari 0,5–50 km.";
    if(Object.keys(errors).length)fail(422,errors);

    const prevWeight=d.profile&&d.profile.weight;
    const gender=["L","P"].includes(s("gender"))?s("gender"):null;
    d.profile={gender,birthDate:birth||null,height,weight,targetWeight,targetRank:s("target_rank"),
      fitness:s("fitness"),goal:s("goal"),goalLabel:GOALS[s("goal")],targets:t};
    if(prevWeight==null||Math.abs(prevWeight-weight)>0.01)logWeight(d,weight);
    d.state.name=name;
    const newPlayer=!!d.newPlayer;d.newPlayer=false;
    saveData(u,d);
    return {profile:d.profile,weights:weightsOf(d),name,newPlayer};
  },

  save({state}){
    const u=needUser(),d=dataOf(u);
    if(!state||typeof state!=="object")fail(400,{form:"State kosong."});
    d.state=state;
    const day=state.daily;
    if(day&&day.date){
      const row={date:day.date,done:!!day.done,push:day.counts.push,sit:day.counts.sit,squat:day.counts.squat,run:day.counts.run};
      const i=d.history.findIndex(h=>h.date===day.date);
      if(i>=0)d.history[i]=row;else d.history.push(row);
      d.history=d.history.sort((a,b)=>a.date<b.date?-1:1).slice(-90);
    }
    saveData(u,d);
    return {ok:true,history:historyOf(d)};
  },

  weight({kg}){
    const u=needUser(),d=dataOf(u),w=validWeight(kg);
    if(w===null)fail(422,{form:"Berat badan harus 25–300 kg."});
    logWeight(d,w);saveData(u,d);
    return {ok:true,profile:d.profile,weights:weightsOf(d)};
  },

  reset(){
    const u=needUser(),d=dataOf(u);
    // Profil & riwayat berat tetap; progres game diulang.
    const name=d.state&&d.state.name;
    d.state=newState(d.username);
    d.state.name=name||d.username;
    d.history=[];
    saveData(u,d);
    return {ok:true};
  }
};
function needUser(){const u=currentUser();if(!u)fail(401,{form:"Sesi berakhir. Silakan masuk lagi."});return u}
function logWeight(d,kg){
  const t=today(),i=d.weights.findIndex(w=>w.date===t);
  if(i>=0)d.weights[i].kg=kg;else d.weights.push({date:t,kg});
  d.weights=d.weights.slice(-365);
  if(d.profile)d.profile.weight=kg;
}

/* ---- pasang ke Sys ---- */
Sys.api=async function(action,data){
  const fn=actions[action];
  if(!fn)fail(400,{form:"Aksi tidak dikenal."});
  try{return await fn(data||{})}
  catch(e){
    if(e.status===401)Sys.sessionExpired();
    throw e;
  }
};
Sys.boot=boot();
})();
