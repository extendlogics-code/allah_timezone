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

function ClockCanvas({schedule, onHover}){
  const canvasRef = React.useRef(null);
  const coordsRef = React.useRef([]);
  React.useEffect(()=>{
    const c=canvasRef.current; if(!c) return; const ctx=c.getContext('2d');
    const W=c.width,H=c.height; let R=Math.min(W,H)/2-24; const ANG_OFF=-Math.PI/2;
    const THEME = { ring:'rgba(56,189,248,.35)', hHand:'#e5e7eb', mHand:'#cbd5e1', sHand:'#38bdf8', text:'rgba(203,213,225,.9)'};
    let secTrail=[];
    const allahImg=new Image(); allahImg.src='/assets/allah.png';
    const bgImg=new Image(); bgImg.src='/Allah_4K_Green.jpg';
    const arcPoint=(r,a)=>[r*Math.cos(a), r*Math.sin(a)];
    const drawHand=(a,l,w,col)=>{ ctx.save(); ctx.rotate(a); ctx.beginPath(); ctx.moveTo(-10,0); ctx.lineTo(l,0); ctx.strokeStyle=col; ctx.lineWidth=w; ctx.lineCap='round'; ctx.stroke(); ctx.restore(); };
    const pnoise=(a,t,s)=> Math.sin(a*3+t*0.0007+s)*0.5 + Math.sin(a*5-t*0.0003+s*2)*0.3 + Math.sin(a*11+t*0.00013+s*3)*0.2;
    const drawAurora=(R,t)=>{ ctx.save(); ctx.globalCompositeOperation='lighter'; for(let k=0;k<2;k++){ const base=R+6+k*6; ctx.beginPath(); for(let i=0;i<=360;i+=2){ const a=i*Math.PI/180; const off=pnoise(a,t+k*10000,0.7+k)*8; const rr=base+off; const x=rr*Math.cos(a), y=rr*Math.sin(a); if(i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);} const g=ctx.createLinearGradient(-R,0,R,0); g.addColorStop(0,'rgba(56,189,248,0.12)'); g.addColorStop(0.5,'rgba(34,197,94,0.08)'); g.addColorStop(1,'rgba(99,102,241,0.12)'); ctx.strokeStyle=g; ctx.lineWidth=3; ctx.stroke(); } ctx.restore(); };
    const drawRosette=(R)=>{ ctx.save(); for(let i=0;i<5;i++){ const r=R*(0.1+i*0.06); ctx.rotate(Math.PI/12); ctx.beginPath(); for(let j=0;j<8;j++){ const a=j*(Math.PI*2/8); const x=r*Math.cos(a), y=r*Math.sin(a); if(j===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);} ctx.closePath(); ctx.strokeStyle=`rgba(203,213,225,${0.18 - i*0.02})`; ctx.lineWidth=1.2; ctx.stroke(); } ctx.restore(); };
    const drawBeads=(R)=>{ ctx.save(); for(let i=0;i<60;i++){ const a=i*(Math.PI*2/60)-Math.PI/2; const rr=R-(i%5===0?14:8); const [ix,iy]=arcPoint(rr,a); const g=ctx.createRadialGradient(ix,iy,0, ix,iy, i%5===0?5:3); g.addColorStop(0, i%5===0?'rgba(99,102,241,0.95)':'rgba(148,163,184,0.85)'); g.addColorStop(1,'rgba(2,6,23,0)'); ctx.fillStyle=g; ctx.beginPath(); ctx.arc(ix,iy, i%5===0?4.5:3, 0, Math.PI*2); ctx.fill(); } ctx.restore(); };
    const todayAt=(hhmm)=>{ const [hh,mm]=hhmm.split(':').map(Number); const d=new Date(); d.setHours(hh,mm,0,0); return d; };
    const nextFrom=(times)=>{ const now=new Date(); const list=times.map(t=>todayAt(t)); for(let i=0;i<list.length;i++){ if(list[i]>now) return {date:list[i], idx:i}; } const t=todayAt(times[0]); t.setDate(t.getDate()+1); return {date:t, idx:0}; };
    const drawNextProgress=(R, prevDate, nextDate)=>{ if(!prevDate||!nextDate) return; const now=Date.now(); const a=Math.max(0,Math.min(1,(now-prevDate.getTime())/(nextDate.getTime()-prevDate.getTime()))); const base=R-26; ctx.beginPath(); ctx.arc(0,0,base,0,Math.PI*2); ctx.strokeStyle='rgba(148,163,184,0.18)'; ctx.lineWidth=12; ctx.stroke(); const grad=ctx.createConicGradient(-Math.PI/2,0,0); grad.addColorStop(0,'rgba(56,189,248,0.85)'); grad.addColorStop(0.5,'rgba(34,197,94,0.85)'); grad.addColorStop(1,'rgba(99,102,241,0.85)'); ctx.beginPath(); ctx.arc(0,0,base,-Math.PI/2,-Math.PI/2 + a*Math.PI*2); ctx.strokeStyle=grad; ctx.lineWidth=12; ctx.lineCap='round'; ctx.stroke(); };

    function draw(){ const now=new Date(), s=now.getSeconds(), m=now.getMinutes(), h=now.getHours()%12; coordsRef.current=[]; const t=performance.now(); ctx.clearRect(0,0,W,H); ctx.save(); ctx.translate(W/2,H/2); R=Math.min(W,H)/2-24;
      // face
      ctx.save(); ctx.beginPath(); ctx.arc(0,0,R,0,Math.PI*2); ctx.clip(); if (bgImg.complete && bgImg.naturalWidth){ const scale=Math.max((R*2)/bgImg.naturalWidth,(R*2)/bgImg.naturalHeight); const w=bgImg.naturalWidth*scale,h=bgImg.naturalHeight*scale; ctx.drawImage(bgImg,-w/2,-h/2,w,h); const g=ctx.createRadialGradient(0,0,R*0.2,0,0,R); g.addColorStop(0,'rgba(0,0,0,0)'); g.addColorStop(1,'rgba(0,0,0,.25)'); ctx.fillStyle=g; ctx.fillRect(-R,-R,2*R,2*R);} else { const bg=ctx.createRadialGradient(-R*.2,-R*.2,R*.2,0,0,R); bg.addColorStop(0,'#0b132a'); bg.addColorStop(1,'#070d1c'); ctx.fillStyle=bg; ctx.fillRect(-R,-R,2*R,2*R);} ctx.restore();
      drawAurora(R,t); ctx.beginPath(); ctx.arc(0,0,R+6,0,Math.PI*2); ctx.strokeStyle=THEME.ring; ctx.lineWidth=10; ctx.stroke(); drawRosette(R); drawBeads(R);
      // numerals
      ctx.fillStyle=THEME.text; ctx.font='600 20px system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif'; ctx.textAlign='center'; ctx.textBaseline='middle'; [12,3,6,9].forEach(n=>{ const a=(Math.PI/6)*n - Math.PI/2, r=R-40; ctx.fillText(String(n), r*Math.cos(a), r*Math.sin(a)); });
      // pearls + coords
      if (schedule?.times?.length){ for(let i=0;i<schedule.times.length;i++){ const hhmm=schedule.times[i]; const [hh,mm]=hhmm.split(':').map(Number); const a=(Math.PI*2)*((hh%12 + mm/60)/12)-Math.PI/2 + Math.sin(t*0.0002 + i)*0.03; const rr=R + 10*Math.sin(t*0.001 + i) + 6; const [x,y]=arcPoint(rr,a); coordsRef.current[i]={x:W/2+x,y:H/2+y,r:10}; const g=ctx.createRadialGradient(x,y,0,x,y,10); g.addColorStop(0,'rgba(56,189,248,0.95)'); g.addColorStop(1,'rgba(56,189,248,0)'); ctx.fillStyle=g; ctx.beginPath(); ctx.arc(x,y,6.5,0,Math.PI*2); ctx.fill(); } const nx=nextFrom(schedule.times); const idx=nx.idx; const hhmm=schedule.times[idx]; const [hh2,mm2]=hhmm.split(':').map(Number); const a2=(Math.PI*2)*((hh2%12 + mm2/60)/12)-Math.PI/2 + Math.sin(t*0.0002 + idx)*0.03; const rr2=R + 10*Math.sin(t*0.001 + idx) + 9; const [x2,y2]=arcPoint(rr2,a2); ctx.beginPath(); const g2=ctx.createRadialGradient(x2,y2,0,x2,y2,12); g2.addColorStop(0,'rgba(250,204,21,0.95)'); g2.addColorStop(1,'rgba(250,204,21,0)'); ctx.fillStyle=g2; ctx.arc(x2,y2,8.5,0,Math.PI*2); ctx.fill(); ctx.beginPath(); ctx.arc(x2,y2,11,0,Math.PI*2); ctx.strokeStyle='rgba(250,204,21,0.85)'; ctx.lineWidth=2; ctx.stroke(); const prevIdx=(idx-1+schedule.times.length)%schedule.times.length; const prev=todayAt(schedule.times[prevIdx]); const next=nx.date; if (prev>next){ prev.setDate(prev.getDate()-1); } drawNextProgress(R, prev, next); }
      // hands + comet
      const sa=(Math.PI*2)*(s/60)-Math.PI/2; const ma=(Math.PI*2)*((m+s/60)/60)-Math.PI/2; const ha=(Math.PI*2)*((h+m/60)/12)-Math.PI/2; drawHand(ha,R*.52,8,THEME.hHand); drawHand(ma,R*.72,6,THEME.mHand); ctx.save(); ctx.rotate(sa); ctx.beginPath(); ctx.moveTo(-14,0); ctx.lineTo(R*.80,0); ctx.strokeStyle='rgba(56,189,248,1)'; ctx.lineWidth=2.5; ctx.lineCap='round'; ctx.stroke(); ctx.beginPath(); ctx.fillStyle='rgba(56,189,248,1)'; ctx.arc(R*.80,0,3.5,0,Math.PI*2); ctx.fill(); ctx.restore(); const gx=(R*.80)*Math.cos(sa), gy=(R*.80)*Math.sin(sa); secTrail.push([gx,gy,Date.now()]); if (secTrail.length>12) secTrail.shift(); for (let i=0;i<secTrail.length;i++){ const [x,y,t0]=secTrail[i]; const age=(Date.now()-t0)/1000; const alpha=Math.max(0, .45 - age*.08); const rad=Math.max(1, 4 - i*.2); if (alpha<=0) continue; ctx.beginPath(); ctx.fillStyle=`rgba(56,189,248,${alpha.toFixed(3)})`; ctx.arc(x,y,rad,0,Math.PI*2); ctx.fill(); }
      ctx.beginPath(); ctx.arc(0,0,6,0,Math.PI*2); ctx.fillStyle='rgba(56,189,248,1)'; ctx.fill(); if (allahImg.complete && allahImg.naturalWidth){ const SZ=R*.58, ar=allahImg.naturalWidth/allahImg.naturalHeight; const w=ar>=1?SZ:SZ*ar, h=ar>=1?SZ/ar:SZ; ctx.globalAlpha=.12; ctx.drawImage(allahImg,-w/2,-h/2,w,h); }
      ctx.restore();
    }
    const id=setInterval(draw,500); draw();
    return ()=>clearInterval(id);
  },[schedule?.times?.join(','), schedule?.names?.join(',')]);

  React.useEffect(()=>{
    const c=canvasRef.current; if(!c) return; const onMove=(e)=>{ const rect=c.getBoundingClientRect(); const x=e.clientX-rect.left, y=e.clientY-rect.top; const pts=coordsRef.current||[]; let hit=-1; for(let i=0;i<pts.length;i++){ const d=Math.hypot(x-pts[i].x+rect.left, y-pts[i].y+rect.top); if (d < (pts[i].r||10)) { hit=i; break; } } if (hit>=0 && schedule?.times?.[hit]){ onHover && onHover({ idx:hit, name:(schedule.names?.[hit]||'Prayer'), time:schedule.times[hit], x:e.clientX, y:e.clientY }); } else { onHover && onHover(null); } }; const onLeave=()=> onHover && onHover(null); c.addEventListener('mousemove', onMove); c.addEventListener('mouseleave', onLeave); return ()=>{ c.removeEventListener('mousemove', onMove); c.removeEventListener('mouseleave', onLeave); };
  },[onHover, schedule?.times?.join(',')]);

  return <canvas id="clockCanvas" width={610} height={610} ref={canvasRef} aria-label="Analog Clock" />
}

function App(){
  const api = useApi({});
  const sel = api.selection || {};
  const [playing, setPlaying] = React.useState(false);
  const [hover, setHover] = React.useState(null);
  const [blocked, setBlocked] = React.useState(false);
  const [showFS, setShowFS] = React.useState(false);
  const [showUnmute, setShowUnmute] = React.useState(false);
  const localVidRef = React.useRef(null);
  const [ytReady, setYtReady] = React.useState(false);
  const ytContainerRef = React.useRef(null);
  const playerRef = React.useRef(null);

  React.useEffect(()=>{ window.onYouTubeIframeAPIReady = ()=>setYtReady(true); if (window.YT?.Player) setYtReady(true); },[]);

  const schedule = React.useMemo(()=>({ times: api.data?.scheduleTimes||[], names: api.data?.scheduleNames||[] }), [api.data]);
  const nextInfo = React.useMemo(()=>{
    const times = schedule.times||[]; if (!times.length) return null;
    const todayAt=(hhmm)=>{ const [hh,mm]=hhmm.split(':').map(Number); const d=new Date(); d.setHours(hh,mm,0,0); return d; };
    const nextFrom=(times)=>{ const now=new Date(); const list=times.map(t=>todayAt(t)); for(let i=0;i<list.length;i++){ if(list[i]>now) return {date:list[i], idx:i}; } const t=todayAt(times[0]); t.setDate(t.getDate()+1); return {date:t, idx:0}; };
    const nx=nextFrom(times); const name=schedule.names?.[nx.idx]||'Next'; const time=times[nx.idx];
    const rem = (()=>{ const ms=nx.date-Date.now(); const s=Math.max(0, Math.floor(ms/1000)); const h=Math.floor(s/3600); const m=Math.floor((s%3600)/60); const ss=s%60; const pad=n=>String(n).padStart(2,'0'); return `${pad(h)}:${pad(m)}:${pad(ss)}`; })();
    return { name, time, remain: rem };
  }, [schedule.times.join(','), schedule.names?.join(',')]);

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
    // Best-effort: also try to enter browser fullscreen; ignore errors if blocked
    try{ const el=document.getElementById('videoWrap'); if (el.requestFullscreen) await el.requestFullscreen(); }catch(e){}
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
          <div className="next-info" aria-live="polite">
            <span>Next:</span>
            <span className="next-name">{nextInfo?.name || '—'}</span>
            <span className="next-time">{nextInfo?.time || '--:--'}</span>
            <span className="next-rem">in {nextInfo?.remain || '--:--:--'}</span>
          </div>
          <div className="date-line">
            <span>{dates.greg}</span><br/>
            <span>{dates.hijri}</span>
          </div>
          <ClockCanvas schedule={{times:schedule.times, names:schedule.names}} onHover={setHover} />
          {hover && (
            <div style={{position:'fixed', left:hover.x+12, top:hover.y+12, background:'rgba(2,6,23,0.9)', color:'#e5e7eb', border:'1px solid rgba(148,163,184,.35)', borderRadius:8, padding:'6px 8px', pointerEvents:'none', fontSize:12, zIndex:10}}>
              <div style={{fontWeight:600}}>{hover.name}</div>
              <div>{hover.time}</div>
            </div>
          )}
          <div id="nowText" className="time-readout">{nowText}</div>
          <div className="status">{status || (schedule.times.length? 'Waiting for next prayer time to autoplay…':'Couldn’t find times for your selection in the CSVs.')}</div>
          <div id="videoWrap" style={{display:playing?'block':'none', position:'relative'}}>
            <div id="ytContainer" ref={ytContainerRef}></div>
            <video id="localVid" ref={localVidRef} preload="auto" playsInline webkit-playsinline="true"></video>
            <button id="unmuteBtn" className="chip" style={{display:showUnmute?'inline-block':'none', position:'absolute', top:12, right:12}} onClick={()=>{ try{ const v=localVidRef.current; if (!v.paused){ v.muted=false; setShowUnmute(false); return; } }catch(e){} try{ playerRef.current && playerRef.current.unMute && playerRef.current.unMute(); setShowUnmute(false); }catch(e){} }}>🔊 Unmute</button>
           
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
