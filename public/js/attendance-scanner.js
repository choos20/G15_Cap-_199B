(() => {
const resolveUrl=document.currentScript.dataset.resolve, el=id=>document.getElementById('qs-'+id);
const start=el('start'),stop=el('stop'),swap=el('switch'),video=el('video'),canvas=el('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true});
let stream,frame,generation=0,cameras=[],activeDevice,busy=false,lastFrame=0;
const status=(text,kind='')=>{el('status').textContent=text;el('status').className='qs-status '+kind;};
const shutdown=()=>{generation++;cancelAnimationFrame(frame);stream?.getTracks().forEach(t=>t.stop());stream=null;video.srcObject=null;video.style.display=el('reticle').style.display=stop.style.display=swap.style.display='none';el('idle').style.display='flex';start.disabled=false;start.textContent='Start Scanning';busy=false;};
const tick=time=>{
 if(!stream)return;
 if(video.readyState>=2 && time-lastFrame>130){
 lastFrame=time;const scale=Math.min(1,800/video.videoWidth);canvas.width=Math.round(video.videoWidth*scale);canvas.height=Math.round(video.videoHeight*scale);ctx.drawImage(video,0,0,canvas.width,canvas.height);const pixels=ctx.getImageData(0,0,canvas.width,canvas.height);const code=window.jsQR(pixels.data,pixels.width,pixels.height);
 if(code?.data){shutdown();status('QR code detected — validating attendance code…','busy');start.disabled=true;const target=new URL(resolveUrl,location.origin);target.searchParams.set('code',code.data);location.assign(target.href);return;}
 }frame=requestAnimationFrame(tick);
};
const open=async deviceId=>{
 if(busy)return;shutdown();busy=true;const request=generation;start.disabled=true;stop.style.display='inline-flex';status('Allow camera access in your browser to start scanning.','busy');
 try{
 if(!window.isSecureContext || !navigator.mediaDevices?.getUserMedia)throw new Error('Camera access requires HTTPS and a supported browser. You can paste the code below.');
 if(!window.jsQR)throw new Error('The QR reader could not load. Refresh this page or use the paste option below.');
 const next=await navigator.mediaDevices.getUserMedia({video:deviceId?{deviceId:{exact:deviceId}}:{facingMode:{ideal:'environment'}},audio:false});
 if(request!==generation){next.getTracks().forEach(t=>t.stop());return;}
 stream=next;video.srcObject=stream;video.style.display='block';el('idle').style.display='none';await video.play();if(request!==generation)return;
 activeDevice=stream.getVideoTracks()[0]?.getSettings().deviceId;
 try{cameras=(await navigator.mediaDevices.enumerateDevices()).filter(d=>d.kind==='videoinput');}catch{cameras=[];}
 if(request!==generation)return;el('reticle').style.display='block';swap.style.display=cameras.length>1?'inline-flex':'none';start.textContent='Scanning…';busy=false;status('Position the QR code inside the green frame.');frame=requestAnimationFrame(tick);
 }catch(error){if(request!==generation)return;shutdown();const messages={NotAllowedError:'Camera access was denied. Allow it in browser settings, then try again.',NotFoundError:'No camera found. Connect a camera or paste the code.',NotReadableError:'The camera may be in use. Close other camera apps and try again.'};status(messages[error.name]||error.message||'Could not start the camera.','error');}
};
start.addEventListener('click',()=>open());stop.addEventListener('click',()=>{shutdown();status('Camera stopped. Start scanning whenever you are ready.');});swap.addEventListener('click',()=>{const index=cameras.findIndex(c=>c.deviceId===activeDevice);open(cameras[(index+1)%cameras.length]?.deviceId);});document.addEventListener('visibilitychange',()=>{if(document.hidden){shutdown();status('Camera paused. Start scanning to continue.');}});window.addEventListener('pagehide',shutdown);
})();