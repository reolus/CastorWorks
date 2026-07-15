(()=>{const forms=document.querySelectorAll('.mobile-location-form');forms.forEach(form=>form.addEventListener('submit',()=>{if(!navigator.geolocation)return;const lat=form.querySelector('[name=latitude]'),lng=form.querySelector('[name=longitude]'),acc=form.querySelector('[name=accuracy]');navigator.geolocation.getCurrentPosition(p=>{lat.value=p.coords.latitude;lng.value=p.coords.longitude;acc.value=p.coords.accuracy},()=>{}, {enableHighAccuracy:true,timeout:5000})}));document.querySelectorAll('[data-draft-key]').forEach(form=>{const key=form.dataset.draftKey,field=form.querySelector('textarea'),status=form.querySelector('.draft-status');if(!field)return;field.value=localStorage.getItem(key)||field.value;let timer;field.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{localStorage.setItem(key,field.value);if(status){status.textContent='Draft saved on this device.';status.classList.add('saved')}},250)});form.addEventListener('submit',()=>localStorage.removeItem(key))});document.querySelectorAll('[data-offline-form]').forEach(form=>{const key='serviceos-'+form.dataset.offlineForm;const saved=JSON.parse(localStorage.getItem(key)||'[]');form.querySelectorAll('input[type=checkbox]').forEach(box=>{if(saved.includes(box.value))box.checked=true;box.addEventListener('change',()=>{const values=[...form.querySelectorAll('input[type=checkbox]:checked')].map(x=>x.value);localStorage.setItem(key,JSON.stringify(values))})});form.addEventListener('submit',()=>localStorage.removeItem(key))});document.querySelectorAll('.mobile-signature-form').forEach(form=>{const canvas=form.querySelector('canvas'),hidden=form.querySelector('[name=customer_signature_data]'),clear=form.querySelector('.signature-clear'),ctx=canvas.getContext('2d');let drawing=false;const resize=()=>{const ratio=window.devicePixelRatio||1;const width=canvas.clientWidth,height=180;canvas.width=width*ratio;canvas.height=height*ratio;ctx.setTransform(ratio,0,0,ratio,0,0);ctx.lineWidth=2.2;ctx.lineCap='round';ctx.strokeStyle='#102a43'};resize();const point=e=>{const r=canvas.getBoundingClientRect();return[e.clientX-r.left,e.clientY-r.top]};canvas.addEventListener('pointerdown',e=>{drawing=true;canvas.setPointerCapture(e.pointerId);ctx.beginPath();ctx.moveTo(...point(e))});canvas.addEventListener('pointermove',e=>{if(!drawing)return;ctx.lineTo(...point(e));ctx.stroke()});canvas.addEventListener('pointerup',()=>drawing=false);clear?.addEventListener('click',()=>ctx.clearRect(0,0,canvas.clientWidth,180));form.addEventListener('submit',()=>hidden.value=canvas.toDataURL('image/png'))});if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{});})();

(()=>{
 const context=document.getElementById('mobileGpsContext');
 const status=document.getElementById('mobileGpsStatus');
 if(!context||context.dataset.clockedIn!=='1'||context.dataset.gpsEnabled!=='1'||!navigator.geolocation)return;
 const interval=Math.max(15,Number(context.dataset.gpsInterval||60))*1000; let lastSent=0;
 const setStatus=(text,kind='secondary')=>{if(!status)return;status.className=`alert alert-${kind} py-2 small`;status.innerHTML=`<i class="fa-solid fa-location-crosshairs"></i> ${text}`};
 const send=async position=>{
  const now=Date.now(); if(now-lastSent<interval)return; lastSent=now;
  const payload={job_id:Number(context.dataset.jobId||0),latitude:position.coords.latitude,longitude:position.coords.longitude,accuracy:position.coords.accuracy,heading:position.coords.heading,speed:position.coords.speed};
  try{
   const res=await fetch('/portal/mobile/location',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':context.dataset.csrf},body:JSON.stringify(payload)});
   const data=await res.json();
   if(!res.ok||!data.ok)throw new Error(data.error||'Location update failed');
   const state=data.proximity?.state?` ${data.proximity.state.replace('_',' ')}.`:'';
   setStatus(`Location updated ${new Date().toLocaleTimeString()}.${state}`,'success');
  }catch(error){setStatus(error.message||'Location update failed','warning')}
 };
 setStatus('GPS tracking active while clocked in.','info');
 navigator.geolocation.watchPosition(send,err=>setStatus(`GPS unavailable: ${err.message}`,'warning'),{enableHighAccuracy:true,maximumAge:15000,timeout:20000});
})();
