/* Private recording; nothing is sent until the correction form is submitted. */
document.querySelectorAll('.wb-recorder').forEach(box => {
  const start = box.querySelector('.wb-record'), stop = box.querySelector('.wb-stop');
  const input = box.querySelector('input[name=audio]'), preview = box.querySelector('.wb-preview');
  const message = box.querySelector('.wb-message'), file = box.querySelector('.wb-file');
  const submit = box.closest('form').querySelector('button[type=submit],button.button-primary');
  let recorder, stream, timer, url, processing = false;
  const release = () => { clearTimeout(timer); if (stream) stream.getTracks().forEach(t => t.stop()); stream = null; };
  const load = async blob => {
    if (!blob || blob.size > 1048576) throw Error('Audio trop volumineux (1 Mo maximum).');
    const data = await new Promise((resolve, reject) => { const r = new FileReader(); r.onload = () => resolve(r.result); r.onerror = reject; r.readAsDataURL(blob); });
    input.value = String(data).split(',')[1];
    if (url) URL.revokeObjectURL(url); url = URL.createObjectURL(blob); preview.src = url; preview.hidden = false;
    message.textContent = 'Audio prêt. Écoutez-le puis enregistrez la correction.';
  };
  start.onclick = async () => {
    try {
      if (!navigator.mediaDevices || !window.MediaRecorder) throw Error('Enregistrement indisponible : utilisez un fichier audio.');
      processing = true; start.disabled = true; submit.disabled = true; file.disabled = true;
      stream = await navigator.mediaDevices.getUserMedia({audio:true});
      // WAV below avoids WebM files without duration metadata produced by some browsers.
      const preferred = ['audio/mp4', 'audio/webm;codecs=opus', 'audio/ogg;codecs=opus'].find(t => MediaRecorder.isTypeSupported(t));
      recorder = new MediaRecorder(stream, preferred ? {mimeType:preferred} : {});
      const chunks = [];
      recorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
      recorder.onstop = async () => {
        release();
        try {
          const raw = new Blob(chunks, {type:recorder.mimeType});
          const ctx = new (window.AudioContext || window.webkitAudioContext)({sampleRate:8000});
          try {
            const audio = await ctx.decodeAudioData(await raw.arrayBuffer());
            const n = Math.min(audio.length, audio.sampleRate * 60), channels = audio.numberOfChannels;
            // Resample to mono 8 kHz PCM: 60 s stays under 1 MiB and has a verifiable duration.
            const count = Math.floor(n * 8000 / audio.sampleRate), buffer = new ArrayBuffer(44 + count * 2), v = new DataView(buffer);
            const str = (at,s) => [...s].forEach((c,i) => v.setUint8(at+i,c.charCodeAt(0)));
            str(0,'RIFF'); v.setUint32(4,36+count*2,true); str(8,'WAVE'); str(12,'fmt '); v.setUint32(16,16,true); v.setUint16(20,1,true); v.setUint16(22,1,true); v.setUint32(24,8000,true); v.setUint32(28,16000,true); v.setUint16(32,2,true); v.setUint16(34,16,true); str(36,'data'); v.setUint32(40,count*2,true);
            const source = Array.from({length:channels}, (_,i) => audio.getChannelData(i));
            for (let i=0; i<count; i++) { const at=Math.min(n-1,Math.floor(i*audio.sampleRate/8000)); let x=0; source.forEach(c=>x+=c[at]/channels); x=Math.max(-1,Math.min(1,x)); v.setInt16(44+i*2,x<0?x*32768:x*32767,true); }
            await load(new Blob([buffer],{type:'audio/wav'}));
          } finally { await ctx.close(); }
        } catch(e) { message.textContent = 'Audio non préparé : '+e.message+'. Vous pouvez choisir un fichier.'; }
        processing=false; start.disabled=false; stop.disabled=true; submit.disabled=false; file.disabled=false;
      };
      recorder.start(); stop.disabled=false; message.textContent='Enregistrement… arrêt automatique après 60 secondes.';
      timer=setTimeout(()=>{ if(recorder.state==='recording') recorder.stop(); },60000);
    } catch(e) { release(); processing=false; start.disabled=false; submit.disabled=false; file.disabled=false; message.textContent=e.message; }
  };
  stop.onclick=()=>{ if(recorder && recorder.state==='recording') { stop.disabled=true; recorder.stop(); } };
  file.onchange=async()=>{ submit.disabled=true; processing=true; try { await load(file.files[0]); } catch(e) { input.value=''; message.textContent=e.message; } finally { submit.disabled=false; processing=false; } };
  box.closest('form').addEventListener('submit',e=>{ if(processing){ e.preventDefault(); message.textContent='Arrêtez l’enregistrement et attendez sa préparation.'; } });
  window.addEventListener('pagehide',()=>{ release(); if(url)URL.revokeObjectURL(url); });
});
