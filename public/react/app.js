import React from 'https://esm.sh/react@18.3.1'
import { createRoot } from 'https://esm.sh/react-dom@18.3.1/client'

function hmsPad(n){ return String(n).padStart(2,'0') }
function todayStr(){ const d=new Date(); return `${d.getFullYear()}-${hmsPad(d.getMonth()+1)}-${hmsPad(d.getDate())}` }

function tzForCountry(country){
  if (/uae|united arab emirates/i.test(country||'')) return 'Asia/Dubai';
  return 'Asia/Kolkata';
}

function useApi(initial){
  const [state, setState] = React.useState({ loading:true, error:null, data:null, selection: initial });
  const fetchData = React.useCallback(async (sel)=>{
    setState(s=>({...s, loading:true, error:null}));
    const params = new URLSearchParams({ date: todayStr(), ...sel });
    const res = await fetch(`/api/times?${params.toString()}`);
    if (!res.ok) { setState(s=>({...s, loading:false, error:`HTTP ${res.status}`})); return; }
    const data = await res.json();
    setState({ loading:false, error:null, data, selection: data.selection });
  },[]);
  React.useEffect(()=>{ fetchData(initial); },[]);
  return { ...state, refetch: fetchData };
}

function ClockCanvas({schedule}){
  const canvasRef = React.useRef(null);
  React.useEffect(()=>{
    const c=canvasRef.current; if(!c) return; const ctx=c.getContext('2d');
    const W=c.width,H=c.height; const R=Math.min(W,H)/2-24; const ANG_OFF=-Math.PI/2;
    const THEME = { ring:'rgba(56,189,248,.35)', tick5:'rgba(229,231,235,.95)', tick1:'rgba(148,163,184,.65)', hHand:'#e5e7eb', mHand:'#cbd5e1', sHand:'#38bdf8', halo1:'rgba(56,189,248,.30)', halo2:'rgba(56,189,248,.08)', text:'rgba(203,213,225,.9)', label:'rgba(165,180,252,.9)', labelBg:'rgba(2,6,23,.65)'};
    let secTrail=[];
    const allahImg=new Image(); allahImg.src='/assets/allah.png';
    const bgImg=new Image(); bgImg.src='/Allah_4K_Green.jpg';
    function arcPoint(r,a){ return [r*Math.cos(a), r*Math.sin(a)] }
    function drawHand(a,l,w,col){ ctx.save(); ctx.rotate(a); ctx.beginPath(); ctx.moveTo(-10,0); ctx.lineTo(l,0); ctx.strokeStyle=col; ctx.lineWidth=w; ctx.lineCap='round'; ctx.stroke(); ctx.restore(); }
    function drawMarkers(){ if(!schedule?.times?.length) return; const rim=R+2, textR=R-16; ctx.save(); ctx.font='600 12px system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle';
      schedule.times.forEach((hhmm,i)=>{ const [hh,mm]=hhmm.split(':').map(Number); const a=(Math.PI*2)*((hh%12 + mm/60)/12)+ANG_OFF; const [x,y]=arcPoint(rim,a); ctx.save(); ctx.translate(x,y); ctx.rotate(a); ctx.fillStyle=THEME.tick5; ctx.globalAlpha=.95; ctx.beginPath(); ctx.moveTo(0,-4); ctx.lineTo(4,0); ctx.lineTo(0,4); ctx.lineTo(-4,0); ctx.closePath(); ctx.fill(); ctx.restore(); const label=(schedule.names?.[i]||'')+` ${hhmm}`.trim(); const tw=ctx.measureText(label).width; const [tx,ty]=arcPoint(textR,a); ctx.save(); ctx.translate(tx,ty); ctx.rotate(a); ctx.fillStyle=THEME.labelBg; ctx.globalAlpha=.9; const padX=8,h=18,w=tw+padX*2,r=9; ctx.beginPath(); ctx.moveTo(-w/2+r,-h/2); ctx.lineTo(w/2-r,-h/2); ctx.quadraticCurveTo(w/2,-h/2,w/2,-h/2+r); ctx.lineTo(w/2,h/2-r); ctx.quadraticCurveTo(w/2,h/2,w/2-r,h/2); ctx.lineTo(-w/2+r,h/2); ctx.quadraticCurveTo(-w/2,h/2,-w/2,h/2-r); ctx.lineTo(-w/2,-h/2+r); ctx.quadraticCurveTo(-w/2,-h/2,-w/2+r,-h/2); ctx.closePath(); ctx.fill(); ctx.fillStyle=THEME.label; ctx.globalAlpha=1; ctx.fillText(label,0,1); ctx.restore(); }); ctx.restore(); }
    function draw(){ const now=new Date(), s=now.getSeconds(), m=now.getMinutes(), h=now.getHours()%12; ctx.clearRect(0,0,W,H); ctx.save(); ctx.translate(W/2,H/2);
      // face
      ctx.save(); ctx.beginPath(); ctx.arc(0,0,R,0,Math.PI*2); ctx.clip(); if (bgImg.complete && bgImg.naturalWidth){ const scale=Math.max((R*2)/bgImg.naturalWidth,(R*2)/bgImg.naturalHeight); const w=bgImg.naturalWidth*scale,h=bgImg.naturalHeight*scale; ctx.drawImage(bgImg,-w/2,-h/2,w,h); const g=ctx.createRadialGradient(0,0,R*0.2,0,0,R); g.addColorStop(0,'rgba(0,0,0,0)'); g.addColorStop(1,'rgba(0,0,0,.25)'); ctx.fillStyle=g; ctx.fillRect(-R,-R,2*R,2*R);} else { const bg=ctx.createRadialGradient(-R*.2,-R*.2,R*.2,0,0,R); bg.addColorStop(0,'#0b132a'); bg.addColorStop(1,'#070d1c'); ctx.fillStyle=bg; ctx.fillRect(-R,-R,2*R,2*R);} ctx.restore();
      // ring
      ctx.beginPath(); ctx.arc(0,0,R+6,0,Math.PI*2); ctx.strokeStyle=THEME.ring; ctx.lineWidth=10; ctx.stroke();
      // ticks
      for(let i=0;i<60;i++){ const a=(Math.PI*2)*(i/60)+ANG_OFF; const inner=R-(i%5===0?22:12); ctx.beginPath(); ctx.moveTo(R*Math.cos(a),R*Math.sin(a)); ctx.lineTo(inner*Math.cos(a),inner*Math.sin(a)); ctx.strokeStyle=i%5===0?THEME.tick5:THEME.tick1; ctx.lineWidth=i%5===0?3:1.6; ctx.stroke(); }
      // numerals
      ctx.fillStyle=THEME.text; ctx.font='600 20px system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; [12,3,6,9].forEach(n=>{ const a=(Math.PI/6)*n+ANG_OFF, r=R-40; ctx.fillText(String(n), r*Math.cos(a), r*Math.sin(a)); });
      drawMarkers();
      // hands
      const sa=(Math.PI*2)*(s/60)+ANG_OFF; const ma=(Math.PI*2)*((m+s/60)/60)+ANG_OFF; const ha=(Math.PI*2)*((h+m/60)/12)+ANG_OFF;
      drawHand(ha,R*.52,8,THEME.hHand); drawHand(ma,R*.72,6,THEME.mHand);
      ctx.save(); ctx.rotate(sa); ctx.beginPath(); ctx.moveTo(-14,0); ctx.lineTo(R*.80,0); ctx.strokeStyle=THEME.sHand; ctx.lineWidth=2.5; ctx.lineCap='round'; ctx.stroke(); const tipX=R*.80, tipY=0; ctx.beginPath(); ctx.fillStyle=THEME.sHand; ctx.arc(tipX,tipY,3.5,0,Math.PI*2); ctx.fill(); ctx.restore();
      const gx=(R*.80)*Math.cos(sa), gy=(R*.80)*Math.sin(sa); secTrail.push([gx,gy,Date.now()]); if (secTrail.length>12) secTrail.shift(); for (let i=0;i<secTrail.length;i++){ const [x,y,t]=secTrail[i]; const age=(Date.now()-t)/1000; const alpha=Math.max(0, .45 - age*.08); const rad=Math.max(1, 4 - i*.2); if (alpha<=0) continue; ctx.beginPath(); ctx.fillStyle=`rgba(56,189,248,${alpha.toFixed(3)})`; ctx.arc(x,y,rad,0,Math.PI*2); ctx.fill(); }
      ctx.beginPath(); ctx.arc(0,0,6,0,Math.PI*2); ctx.fillStyle=THEME.sHand; ctx.fill(); if (allahImg.complete && allahImg.naturalWidth){ const SZ=R*.58, ar=allahImg.naturalWidth/allahImg.naturalHeight; const w=ar>=1?SZ:SZ*ar, h=ar>=1?SZ/ar:SZ; ctx.globalAlpha=.12; ctx.drawImage(allahImg,-w/2,-h/2,w,h); }
      ctx.restore();
    }
    const id=setInterval(draw,500); draw();
    return ()=>clearInterval(id);
  },[schedule?.times?.join(','), schedule?.names?.join(',')]);
  return <canvas id="clockCanvas" width={610} height={610} ref={canvasRef} aria-label="Analog Clock" />
}

function App(){
  const api = useApi({});
  const sel = api.selection || {};
  const [playing, setPlaying] = React.useState(false);
  const [blocked, setBlocked] = React.useState(false);
  const [showFS, setShowFS] = React.useState(false);
  const [showUnmute, setShowUnmute] = React.useState(false);
  const localVidRef = React.useRef(null);
  const [ytReady, setYtReady] = React.useState(false);
  const ytContainerRef = React.useRef(null);
  const playerRef = React.useRef(null);

  React.useEffect(()=>{ window.onYouTubeIframeAPIReady = ()=>setYtReady(true); if (window.YT?.Player) setYtReady(true); },[]);

  const schedule = React.useMemo(()=>({ times: api.data?.scheduleTimes||[], names: api.data?.scheduleNames||[] }), [api.data]);

  function todayAt(hhmm){ const [hh,mm]=hhmm.split(':').map(Number); const d=new Date(); d.setHours(hh,mm,0,0); return d; }
  function nextFrom(times){ const now=new Date(); const list=times.map(t=>todayAt(t)); for (let i=0;i<list.length;i++){ if (list[i]>now) return {date:list[i], idx:i}; } const t=todayAt(times[0]); t.setDate(t.getDate()+1); return {date:t, idx:0}; }
  const [status, setStatus] = React.useState('');
  const nextTimer = React.useRef(null);

  const scheduleNext = React.useCallback(()=>{
    if (!schedule.times.length) { setStatus('No schedule for this selection.'); return; }
    const n=nextFrom(schedule.times); const label = schedule.names[n.idx] ? `(${schedule.names[n.idx]} • ${schedule.times[n.idx]})` : schedule.times[n.idx];
    if (nextTimer.current) clearTimeout(nextTimer.current);
    nextTimer.current = setTimeout(()=> playNow(label), n.date - Date.now());
    setStatus('Timer ready. Will autoplay at the next scheduled time.');
  }, [schedule.times.join(','), schedule.names.join(',')]);

  React.useEffect(()=>{ scheduleNext(); return ()=> nextTimer.current && clearTimeout(nextTimer.current); }, [scheduleNext]);

  function ensureYT(){
    if (playerRef.current || !ytReady) return;
    const mount=ytContainerRef.current; mount.innerHTML='';
    playerRef.current = new YT.Player(mount, {
      videoId:'vS0zBleiJuk', host:'https://www.youtube-nocookie.com',
      playerVars:{autoplay:1, mute:0, controls:0, rel:0, modestbranding:1, playsinline:1, enablejsapi:1, origin:location.origin, loop:0},
      events:{ onStateChange:(e)=>{
        if (e.data===YT.PlayerState.PLAYING){ setBlocked(false); setShowUnmute(false); try{ playerRef.current.unMute(); }catch(_){} }
        if (e.data===YT.PlayerState.ENDED){ cleanup(); scheduleNext(); }
      }}
    });
  }
  async function playLocal(){
    const v=localVidRef.current; v.src='/media/azan.mp4'; v.style.display='block';
    try{ v.muted=false; await v.play(); setBlocked(false); }
    catch(e){ try{ v.muted=true; await v.play(); setBlocked(true); }catch(e2){ setBlocked(true); throw e2; } }
  }
  function cleanup(){
    try{ const v=localVidRef.current; v.pause(); v.currentTime=0; v.style.display='none'; }catch(e){}
    try{ playerRef.current && playerRef.current.stopVideo && playerRef.current.stopVideo(); }catch(e){}
    setPlaying(false); setBlocked(false); setShowUnmute(false); setShowFS(false);
    if (document.fullscreenElement && document.exitFullscreen){ try{ document.exitFullscreen(); }catch(_){} }
    document.body.classList.remove('fullwindow');
  }
  async function playNow(label){
    setPlaying(true);
    document.body.classList.add('fullwindow');
    setStatus(`${label?label+' · ':''}Playing video (full window).`);
    try{ await playLocal(); }
    catch(e){ ensureYT(); }
    setTimeout(()=>{ cleanup(); scheduleNext(); }, 3*60*1000); // fallback
  }

  const nowText = React.useMemo(()=> new Intl.DateTimeFormat('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:'Asia/Kolkata'}).format(new Date()), [api.data]);
  React.useEffect(()=>{ const id=setInterval(()=>{ const el=document.getElementById('nowText'); if (el) el.textContent=new Intl.DateTimeFormat('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false,timeZone:'Asia/Kolkata'}).format(new Date()); },1000); return ()=>clearInterval(id); },[]);

  const tz = tzForCountry(sel.country);
  function renderDates(){ const now=new Date(); const gFmt=new Intl.DateTimeFormat('en',{weekday:'long',day:'2-digit',month:'long',year:'numeric',timeZone:tz}); let hijri=''; try{ const hFmt=new Intl.DateTimeFormat('en-u-ca-islamic-umalqura',{day:'2-digit',month:'long',year:'numeric',timeZone:tz}); hijri=hFmt.format(now);}catch(e){ hijri='Hijri date (update your browser)'; } return {greg:gFmt.format(now), hijri}; }
  const dates=renderDates();

  if (api.loading && !api.data) return <div style={{padding:20}}>Loading…</div>;
  if (api.error) return <div style={{padding:20}}>Error: {String(api.error)}</div>;

  const countries = api.data?.countryList || [];
  const states    = api.data?.stateList || [];
  const cities    = api.data?.cityList || [];

  function updateSel(next){ const selection={...sel, ...next}; api.refetch(selection); }

  return (
    <div>
      <form className="topbar" onSubmit={(e)=>e.preventDefault()}>
        <span className="chip">
          <span className="label">Country</span>
          <select className="sel" value={sel.country||''} onChange={(e)=>updateSel({country:e.target.value, state:'', city:''})}>
            {countries.map(c=> <option key={c} value={c}>{c}</option>)}
          </select>
        </span>
        <span className="chip">
          <span className="label">State</span>
          <select className="sel" value={sel.state||''} onChange={(e)=>updateSel({state:e.target.value, city:''})}>
            {states.map(s=> <option key={s} value={s}>{s}</option>)}
          </select>
        </span>
        {cities.length>0 && (
          <span className="chip">
            <span className="label">City</span>
            <select className="sel" value={sel.city||''} onChange={(e)=>updateSel({city:e.target.value})}>
              {cities.map(ct=> <option key={ct} value={ct}>{ct}</option>)}
            </select>
          </span>
        )}
      </form>

      <div className="stage">
        <div className="card">
          <h1 className="title">{sel.country || 'Select Country'} — {sel.state || 'Select State'} {sel.city? <span className="subtitle">{sel.city}</span>: null}</h1>
          <div className="date-line">
            <span>{dates.greg}</span><br/>
            <span>{dates.hijri}</span>
          </div>
          <ClockCanvas schedule={{times:schedule.times, names:schedule.names}} />
          <div id="nowText" className="time-readout">{nowText}</div>
          <div className="status">{status || (schedule.times.length? 'Waiting for next prayer time to autoplay…':'Couldn’t find times for your selection in the CSVs.')}</div>
          <div id="videoWrap" style={{display:playing?'block':'none', position:'relative'}}>
            <div id="ytContainer" ref={ytContainerRef}></div>
            <video id="localVid" ref={localVidRef} preload="auto" playsInline webkit-playsinline="true"></video>
            <button id="unmuteBtn" className="chip" style={{display:showUnmute?'inline-block':'none', position:'absolute', top:12, right:12}} onClick={()=>{ try{ const v=localVidRef.current; if (!v.paused){ v.muted=false; setShowUnmute(false); return; } }catch(e){} try{ playerRef.current && playerRef.current.unMute && playerRef.current.unMute(); setShowUnmute(false); }catch(e){} }}>🔊 Unmute</button>
            <div className="overlay" id="overlayBlocked" style={{display:blocked?'flex':'none'}}>
              <div>
                <p>Autoplay was blocked. Tap to start with sound.</p>
                <button className="chip" onClick={async ()=>{ setBlocked(false); try{ const v=localVidRef.current; v.muted=false; await v.play(); }catch(e){ try{ playerRef.current && playerRef.current.playVideo && playerRef.current.playVideo(); playerRef.current && playerRef.current.unMute && playerRef.current.unMute(); }catch(_){/* ignore */} } }}>Play Video</button>
              </div>
            </div>
            <div className="overlay" id="overlayFS" style={{display:showFS?'flex':'none', right:12, bottom:12, position:'absolute'}}>
              <button className="chip" onClick={async ()=>{ try{ const el=document.getElementById('videoWrap'); if (el.requestFullscreen){ await el.requestFullscreen(); setShowFS(false);} }catch(_){/* ignore */} }}>⛶ Go Fullscreen</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

createRoot(document.getElementById('root')).render(<App />)
