(function(){'use strict';
const u='lk-admin/api/site_analytics.php';
const d=/Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)?'mobile':'desktop';
let m=25,tick=false;
function post(b){
  const p=JSON.stringify(Object.assign({device_type:d},b));
  if(navigator.sendBeacon){
    navigator.sendBeacon(u,new Blob([p],{type:'application/json'}));
  } else {
    fetch(u,{method:'POST',headers:{'Content-Type':'application/json'},body:p,keepalive:true}).catch(function(){});
  }
}
function chk(){
  const h=document.documentElement,b=document.body,st='scrollTop',sh='scrollHeight';
  const pct=Math.round(((h[st]||b[st])/((h[sh]||b[sh])-h.clientHeight))*100);
  let t=25;
  if(pct>=90) t=100;
  else if(pct>=70) t=75;
  else if(pct>=45) t=50;
  else if(pct>=20) t=25;
  if(t>m) m=t;
}
window.addEventListener('scroll',function(){
  if(!tick){
    window.requestAnimationFrame(function(){chk();tick=false;});
    tick=true;
  }
},{passive:true});
if(document.readyState==='complete') chk(); else window.addEventListener('load',chk);
document.addEventListener('click',function(e){
  const el=e.target&&e.target.closest?e.target.closest('[data-track]'):null;
  if(el){
    const n=el.getAttribute('data-track');
    if(n) post({action:'click',button_name:n});
  }
},{passive:true});
function sync(){post({action:'visit',max_scroll_depth:m,referrer:document.referrer||''});}
setInterval(sync,15000);
window.addEventListener('visibilitychange',function(){if(document.visibilityState==='hidden')sync();});
window.addEventListener('pagehide',sync);
window.addEventListener('beforeunload',sync);
setTimeout(sync,1000);
})();
