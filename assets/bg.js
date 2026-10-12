(function(){
const $=id=>document.getElementById(id);
  const c=$("bg"),x=c.getContext("2d");let w,h,p=[];
  const reduce=matchMedia("(prefers-reduced-motion: reduce)").matches;
  function size(){w=c.width=innerWidth;h=c.height=innerHeight;p=Array.from({length:Math.min(70,Math.floor(w/18))},()=>({x:Math.random()*w,y:Math.random()*h,r:Math.random()*1.6+.3,v:Math.random()*.3+.08,a:Math.random()*.5+.15}))}
  function draw(){x.clearRect(0,0,w,h);p.forEach(d=>{d.y-=d.v;if(d.y<-4){d.y=h+4;d.x=Math.random()*w}x.beginPath();x.arc(d.x,d.y,d.r,0,7);x.fillStyle="rgba(111,211,255,"+d.a+")";x.fill()});if(!reduce)requestAnimationFrame(draw)}
  addEventListener("resize",size);size();draw();
})();
