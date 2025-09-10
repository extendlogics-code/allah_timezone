<?php
declare(strict_types=1);

namespace App;

// Helper functions in this namespace for template usage
function yt_extract_id(string $url): ?string {
    $parts = parse_url($url);
    if (!$parts) return null;
    $host = strtolower($parts['host'] ?? '');
    $path = trim($parts['path'] ?? '', '/');
    parse_str($parts['query'] ?? '', $q);
    if (preg_match('~(^|\.) (youtu\.be)$~x', $host)) {
        $id = explode('/', $path)[0] ?? '';
    } elseif (preg_match('~(^|\.) (youtube\.com|youtube-nocookie\.com)$~x', $host)) {
        if (!empty($q['v'])) $id = $q['v'];
        elseif (preg_match('~^(embed|shorts|live)/([^/?#]+)~i', $path, $m)) $id = $m[2];
        else $id = '';
    } else {
        $id = '';
    }
    return preg_match('~^[A-Za-z0-9_-]{6,}$~', $id) ? $id : null;
}

function norm_header(string $s): string { return strtolower(trim(preg_replace('/\xEF\xBB\xBF/', '', $s))); }
function hhmm_ok(string $t): bool { return (bool)preg_match('~^(?:[01]\d|2[0-3]):[0-5]\d$~', trim($t)); }
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

final class App
{
    public function handle(array $server): array
    {
        // Config and paths
        $root = dirname(__DIR__);
        $publicDir = $root . '/public';
        $dataDir = $root . '/data';

        $CONFIG = [
            'timezone' => 'Asia/Kolkata',
            'title'    => 'Allah Timezone',
            'yt_url'   => 'https://youtu.be/vS0zBleiJuk?si=7fFhCt8chOMUuI5C',
            'yt_host'  => 'https://www.youtube-nocookie.com',
            'fallback_minutes' => 3,
            'images' => [
                'bg'    => 'allah.png',
                'clock' => 'Allah_4K_Green.jpg',
                'allah' => 'assets/allah.png'
            ],
            // CSVs to merge (data/ first, then repo root fallbacks)
            'csv_files' => [
                $dataDir . '/india_prayer_times_2025.csv',
                $dataDir . '/uae_prayer_times_2025.csv',
                $root    . '/india_prayer_times_2025.csv',
                $root    . '/uae_prayer_times_2025.csv',
            ],
            // Local MP4 Azan path (served from public root)
            'local_video' => $publicDir . '/AzanSong.mp4',
        ];

        // Optional single-file legacy key support
        if (!empty($CONFIG['csv_file'] ?? '')) {
            array_unshift($CONFIG['csv_files'], $CONFIG['csv_file']);
            $CONFIG['csv_files'] = array_values(array_unique($CONFIG['csv_files']));
        }

        // Ensure PHP uses configured timezone for server-side dates
        date_default_timezone_set($CONFIG['timezone'] ?? (string)date_default_timezone_get());

        // Inputs
        $YT_ID = yt_extract_id($CONFIG['yt_url']) ?: 'vS0zBleiJuk';
        $today = new \DateTimeImmutable('today', new \DateTimeZone($CONFIG['timezone']));
        $todayStr = $today->format('Y-m-d');

        $qCountry = trim($_GET['country'] ?? '');
        $qState   = trim($_GET['state']   ?? '');
        $qCity    = trim($_GET['city']    ?? '');

        // Data structures
        $hasAnyCity = false;
        $countryList = [];
        $stateListByCountry = [];
        $cityListByCountryState = [];
        $rowsByCountryState = [];
        $rowsByCountryStateCity = [];

        // Read all CSVs and merge
        $required = ['country','state','fajr','dhuhr','asr','maghrib','isha','date'];
        foreach ($CONFIG['csv_files'] as $csvPath) {
            if (!is_readable($csvPath)) continue;
            if (($fh=fopen($csvPath,'r'))===false) continue;

            $header = fgetcsv($fh, 0, ',', '"', '\\');
            if ($header === false) { fclose($fh); continue; }
            $header = array_map(__NAMESPACE__ . '\\norm_header', $header);

            $ok = true; foreach ($required as $col) { if (!in_array($col, $header, true)) { $ok=false; break; } }
            if (!$ok){ fclose($fh); continue; }

            $idx = array_flip($header);
            $fileHasCity = in_array('city', $header, true);
            if ($fileHasCity) $hasAnyCity = true;

            while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false){
                if (count($row) < count($header)) continue;
                $country = trim($row[$idx['country']] ?? '');
                $state   = trim($row[$idx['state']]   ?? '');
                $date    = trim($row[$idx['date']]    ?? '');
                if ($country==='' || $state==='' || $date==='') continue;

                $rec = [
                    'country' => $country,
                    'state'   => $state,
                    'city'    => $fileHasCity ? trim($row[$idx['city']] ?? '') : '',
                    'fajr'    => trim($row[$idx['fajr']]    ?? ''),
                    'dhuhr'   => trim($row[$idx['dhuhr']]   ?? ''),
                    'asr'     => trim($row[$idx['asr']]     ?? ''),
                    'maghrib' => trim($row[$idx['maghrib']] ?? ''),
                    'isha'    => trim($row[$idx['isha']]    ?? ''),
                    'date'    => $date,
                ];

                $countryList[$country] = true;
                $stateListByCountry[$country][$state] = true;
                $rowsByCountryState[$country][$state][] = $rec;

                if ($fileHasCity && $rec['city']!==''){
                    $city = $rec['city'];
                    $cityListByCountryState[$country][$state][$city] = true;
                    $rowsByCountryStateCity[$country][$state][$city][] = $rec;
                }
            }
            fclose($fh);
        }

        // Sort lists and defaults
        $countryList = array_keys($countryList);
        sort($countryList, SORT_NATURAL|SORT_FLAG_CASE);
        if ($qCountry==='' && !empty($countryList)) $qCountry = $countryList[0];

        $stateList = [];
        if ($qCountry!=='' && !empty($stateListByCountry[$qCountry])) {
            $stateList = array_keys($stateListByCountry[$qCountry]);
            sort($stateList, SORT_NATURAL|SORT_FLAG_CASE);
            if ($qState==='' && !empty($stateList)) $qState = $stateList[0];
        }

        $cityList = [];
        if ($hasAnyCity && $qCountry!=='' && $qState!=='' && !empty($cityListByCountryState[$qCountry][$qState])) {
            $cityList = array_keys($cityListByCountryState[$qCountry][$qState]);
            sort($cityList, SORT_NATURAL|SORT_FLAG_CASE);
            if ($qCity==='' && !empty($cityList)) $qCity = $cityList[0];
        }

        // Choose best row
        $pick_row = function(array $rows, string $targetDate) : ?array {
            if (empty($rows)) return null;
            usort($rows, fn($a,$b)=>strcmp($a['date'],$b['date']));
            foreach ($rows as $r){ if (($r['date']??'') === $targetDate) return $r; }
            foreach ($rows as $r){ if (($r['date']??'') > $targetDate)  return $r; }
            return end($rows);
        };

        $chosen = null;
        if ($qCountry!=='' && $qState!=='') {
            if ($hasAnyCity && $qCity!=='' && !empty($rowsByCountryStateCity[$qCountry][$qState][$qCity])) {
                $chosen = $pick_row($rowsByCountryStateCity[$qCountry][$qState][$qCity], $todayStr);
            } elseif (!empty($rowsByCountryState[$qCountry][$qState])) {
                $chosen = $pick_row($rowsByCountryState[$qCountry][$qState], $todayStr);
            }
        }

        // Build schedule arrays
        $SCHEDULE_TIMES = [];
        $SCHEDULE_NAMES = [];
        if ($chosen){
            $pairs = [
                ['Fajr',    $chosen['fajr'] ?? ''],
                ['Dhuhr',   $chosen['dhuhr'] ?? ''],
                ['Asr',     $chosen['asr'] ?? ''],
                ['Maghrib', $chosen['maghrib'] ?? ''],
                ['Isha',    $chosen['isha'] ?? ''],
            ];
            foreach($pairs as [$name,$t]){
                if (hhmm_ok($t)){ $SCHEDULE_NAMES[]=$name; $SCHEDULE_TIMES[]=$t; }
            }
            array_multisort($SCHEDULE_TIMES, SORT_ASC, $SCHEDULE_NAMES);
        }

        $YT_ID_SAFE = h($YT_ID);

        // Local video: detect in several locations and expose via /media/azan.mp4
        $candidates = [
            $CONFIG['local_video'],
            $publicDir . '/Azan 7 times.mp4',
            dirname($root) . '/Azan 7 times.mp4', // unlikely, but keep
            $root . '/Azan 7 times.mp4',
            $root . '/AzanSong.mp4',
        ];
        $HAS_LOCAL = false;
        foreach ($candidates as $c) { if ($c && is_readable($c)) { $HAS_LOCAL = true; break; } }
        $LOCAL_URL = $HAS_LOCAL ? '/media/azan.mp4' : '';

        // Defaults for date-line text, filled by JS later
        $gregServer = '';
        $hijriServer = '';

        // Render HTML
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($CONFIG['title']) ?></title>
<style>
  :root{--fg:#e5e7eb;--muted:#94a3b8;}
  *{box-sizing:border-box}
  html,body{height:100%}
  body{
    margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;color:var(--fg);
    background:#0b1223; overflow:hidden;
  }
  body::before{content:""; position:fixed; inset:0; z-index:-2; background:url('<?= h($CONFIG['images']['bg']) ?>') center/cover no-repeat fixed; filter:saturate(.9) brightness(.55); opacity:.9;}
  body::after{content:""; position:fixed; inset:0; z-index:-1; background:radial-gradient(1200px 600px at 20% 20%, rgba(2,6,23,.4), rgba(2,6,23,.85) 60%, rgba(2,6,23,1) 100%);} 

  .topbar{
    position:fixed; top:0; left:0; right:0; z-index:5;
    display:flex; gap:10px; align-items:center; justify-content:center;
    flex-wrap:wrap;
    padding:10px;
  }
  .chip{display:inline-flex; align-items:center; gap:6px; font-size:13px; padding:6px 10px; border-radius:10px; border:1px solid rgba(148,163,184,.25); background:#0a1222; color:#cbd5e1}
  .sel{appearance:none; background:#0b1324; color:#e5e7eb; border:1px solid rgba(148,163,184,.35); padding:8px 10px; border-radius:10px; min-width:180px}
  .label{font-size:12px; color:#a5b4fc; margin-right:6px}

  .stage{ position:absolute; inset:0; display:flex; align-items:center; justify-content:center; padding-top:80px; }
  
  
/* Tablet (portrait + small landscape) */
@media (min-width: 601px) and (max-width: 1024px){
  .stage{
    padding-top:0px;              /* a bit more room for the top bar */
  }
}

/* Mobile */
@media (max-width: 600px){
  .stage{
    padding-top:145px;             /* more space since the top bar wraps */
    align-items:flex-start;        /* start from top so content isn't squished */
  }
}
  .card{ padding:20px 16px 22px; display:flex; flex-direction:column; align-items:center; gap:10px; }
  .title{margin:0 0 4px 0; font-size:16px; color:#dbeafe; text-align:center}
  .subtitle{display:block; font-weight:400; font-size:12px; color:#9ca3af; margin-top:2px}

  /* Canvas gets set by JS for responsive size */
  #clockCanvas{ display:block; width:min(70vmin, 92vw); aspect-ratio:1/1; }

  .time-readout{font-variant-numeric:tabular-nums;font-size:14px;color:var(--muted); text-align:center}
  .status{font-size:13px; color:#c7d2fe; text-align:center}
  .next-info{ display:flex; align-items:center; justify-content:center; gap:10px; margin:8px 0 12px; }
  .next-name{ color:#dbeafe; font-weight:600 }
  .next-time{ color:#e5e7eb; font-variant-numeric:tabular-nums }
  .next-rem{ color:#94a3b8; font-variant-numeric:tabular-nums }

  #videoWrap{ display:none; position:relative; width:min(88vmin, 92vw); aspect-ratio:16/9; background:#000; border-radius:16px; border:1px solid rgba(56,189,248,.25); overflow:hidden; }
  #ytBox{position:relative; width:100%; height:100%}
  #ytContainer, #ytBox iframe{position:absolute; inset:0; width:100%; height:100%; display:block}
  /* Local video should behave like iframe */
  #localVid{position:absolute; inset:0; width:100%; height:100%; display:none; background:#000; object-fit:cover}

  .overlay{position:absolute; z-index:3; display:none; align-items:center; justify-content:center; text-align:center; padding:18px}
  #overlayBlocked{inset:0; background:rgba(0,0,0,.55); backdrop-filter:blur(2px)}
  #overlayFS{right:12px; bottom:12px}
  #unmuteBtn{position:absolute; z-index:3; display:none; top:12px; right:12px}
  .date-line{
  margin: 4px 0 8px 0;
  font-size: 14px;
  color: #cbd5e1;
  text-align: center;
}
.date-line .dot{ margin: 0 8px; opacity:.6 }
@media (max-width: 600px){
  .date-line{ font-size: 12px }
}

  /* Mobile tweaks (keep desktop same) */
  @media (max-width: 600px){
    .sel{min-width:140px}
    .title{font-size:14px}
    .subtitle{font-size:11px}
  }

  /* Full-window autoplay mode (no browser fullscreen API) */
  body.fullwindow .topbar{display:none}
  body.fullwindow .stage{padding-top:0; align-items:stretch}
  body.fullwindow #videoWrap{display:block; position:fixed; inset:0; width:100vw; height:100vh; border:none; border-radius:0}
  body.fullwindow #clockCanvas{display:none}
</style>
</head>
<body>

<!-- Top selection bar -->
<form class="topbar" method="get">
  <span class="chip">
    <span class="label">Country</span>
    <select name="country" class="sel" onchange="this.form.submit()">
      <?php foreach ($countryList as $ctry): ?>
        <option value="<?= h($ctry) ?>" <?= strcasecmp($qCountry,$ctry)===0?'selected':'' ?>><?= h($ctry) ?></option>
      <?php endforeach; ?>
    </select>
  </span>

  <span class="chip">
    <span class="label">State</span>
    <select name="state" class="sel" onchange="this.form.submit()">
      <?php foreach ($stateList as $st): ?>
        <option value="<?= h($st) ?>" <?= strcasecmp($qState,$st)===0?'selected':'' ?>><?= h($st) ?></option>
      <?php endforeach; ?>
    </select>
  </span>

  <?php if (!empty($cityList)): ?>
    <span class="chip">
      <span class="label">City</span>
      <select name="city" class="sel" onchange="this.form.submit()">
        <?php foreach ($cityList as $ct): ?>
          <option value="<?= h($ct) ?>" <?= strcasecmp($qCity,$ct)===0?'selected':'' ?>><?= h($ct) ?></option>
        <?php endforeach; ?>
      </select>
    </span>
  <?php endif; ?>
</form>

<div class="stage">
  <div class="card">
    <h1 class="title">
      <?= h($qCountry ?: 'Select Country') ?> — <?= h($qState ?: 'Select State') ?>
      <?php if (!empty($qCity)): ?>
        <span class="subtitle"><?= h($qCity) ?></span>
      <?php endif; ?>
    </h1>
    
    <div class="date-line" id="dateLine">
  <span id="gregText"><?= h($gregServer) ?></span><br>
  <span id="hijriText"><?= h($hijriServer) ?></span>
</div>

    <div class="next-info" id="nextInfo" aria-live="polite">
      <span>Next:</span>
      <span class="next-name" id="nxName">—</span>
      <span class="next-time" id="nxTime">--:--</span>
      <span class="next-rem" id="nxRem">in --:--:--</span>
    </div>
    <!-- Analog Clock -->
    <canvas id="clockCanvas" width="610" height="610" aria-label="Analog Clock"></canvas>

    <div class="time-readout" id="nowText">--:--:--</div>
    <div class="status" id="statusText">
      <?php if (!empty($SCHEDULE_TIMES)): ?>
        Waiting for next prayer time to autoplay (fullscreen)…
      <?php else: ?>
        Couldn’t find times for your selection in the CSVs.
      <?php endif; ?>
    </div>

    <div id="videoWrap">
      <div id="ytBox">
        <div id="ytContainer"></div>
        <!-- Local MP4 Azan (preferred) -->
        <video id="localVid" preload="auto" playsinline webkit-playsinline></video>
      </div>
      <button id="unmuteBtn" class="chip">🔊 Unmute</button>
      <div class="overlay" id="overlayBlocked">
        <div>
          <p>Autoplay was blocked. Tap to start with sound.</p>
          <button class="chip" id="blockedPlay">Play Video</button>
        </div>
      </div>
      <div class="overlay" id="overlayFS">
        <button class="chip" id="goFSBtn" title="Enter fullscreen">⛶ Go Fullscreen</button>
      </div>
    </div>
  </div>
</div>

<!-- YouTube IFrame API (kept for fallback) -->
<script src="https://www.youtube.com/iframe_api"></script>
<script>
(function(){
  const FALLBACK_MIN_MS = <?= (int)$CONFIG['fallback_minutes'] ?> * 60 * 1000;
  const YT_ID   = "<?= $YT_ID_SAFE ?>";
  const YT_HOST = "<?= h($CONFIG['yt_host']) ?>";
  const START_WITH_SOUND = true;
  const STRICT_AUTOPLAY = true; // Fill window and attempt playback without prompts

  // From PHP
  const SCHEDULE_TIMES = <?= json_encode($SCHEDULE_TIMES) ?>;
  const SCHEDULE_NAMES = <?= json_encode($SCHEDULE_NAMES) ?>;
  const HAS_LOCAL = <?= $HAS_LOCAL ? 'true' : 'false' ?>;
  const LOCAL_URL = "<?= h($LOCAL_URL) ?>";

  const statusText      = document.getElementById('statusText');
  const nowText         = document.getElementById('nowText');
  const clockCanvas     = document.getElementById('clockCanvas');
  const videoWrap       = document.getElementById('videoWrap');
  const overlayBlocked  = document.getElementById('overlayBlocked');
  const blockedPlay     = document.getElementById('blockedPlay');
  const unmuteBtn       = document.getElementById('unmuteBtn');
  const overlayFS       = document.getElementById('overlayFS');
  const goFSBtn         = document.getElementById('goFSBtn');
  const localVid        = document.getElementById('localVid');

  let player=null, ytState=-1, restoreTimeout=null;

  let lastUserGesture = 0;
  ['click','touchstart','keydown'].forEach(evt=>{
    window.addEventListener(evt, ()=>{ lastUserGesture = Date.now(); }, {capture:true, passive:true});
  });

  // Fullscreen helper (Chrome, Firefox, Safari, Edge)
  async function forceFullscreen(el){
    if (document.fullscreenElement) return true;
    const fn = el.requestFullscreen || el.webkitRequestFullscreen || el.msRequestFullscreen || el.mozRequestFullScreen;
    if (!fn) return false;
    try { await fn.call(el); return true; } catch(e){ return false; }
  }

  // Canvas setup
  const ctx=clockCanvas.getContext('2d');
  const W=clockCanvas.width,H=clockCanvas.height,R=Math.min(W,H)/2-24;
  const IMG = { clock: "<?= h($CONFIG['images']['clock']) ?>", allah: "<?= h($CONFIG['images']['allah']) ?>" };
  const clockPattern = new Image(); clockPattern.src = IMG.clock;
  const allahImg     = new Image(); allahImg.src     = IMG.allah;

  // ===== Clock theme + helpers (enhanced) =====
  const THEME = {
    ring:   'rgba(56,189,248,.35)',
    tick5:  'rgba(229,231,235,.95)',
    tick1:  'rgba(148,163,184,.65)',
    hHand:  '#e5e7eb',
    mHand:  '#cbd5e1',
    sHand:  '#38bdf8',
    halo1:  'rgba(56,189,248,.30)',
    halo2:  'rgba(56,189,248,.08)',
    cres:   'rgba(56,189,248,.55)',
    text:   'rgba(203,213,225,.9)',
    label:  'rgba(165,180,252,.9)',
    labelBg:'rgba(2,6,23,.65)'
  };
  let __secTrail = [];
  function arcPoint(r, a){ return [r*Math.cos(a), r*Math.sin(a)]; }
  // Pseudo-noise via multi-sine blend for organic motion
  function pnoise(a, t, seed){
    return Math.sin(a*3 + t*0.0007 + seed)*0.5 +
           Math.sin(a*5 - t*0.0003 + seed*2)*0.3 +
           Math.sin(a*11 + t*0.00013 + seed*3)*0.2;
  }

  // Aurora ring: animated organic glow ribbon around the dial
  function drawAurora(ctx, R, t){
    ctx.save();
    ctx.globalCompositeOperation = 'lighter';
    for (let k=0;k<2;k++){
      const base = R+6 + k*6;
      ctx.beginPath();
      for (let i=0;i<=360;i+=2){
        const a = i*Math.PI/180;
        const off = pnoise(a, t + k*10000, 0.7+k)*8;
        const rr = base + off;
        const x = rr*Math.cos(a), y=rr*Math.sin(a);
        if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
      }
      const g = ctx.createLinearGradient(-R,0,R,0);
      g.addColorStop(0,'rgba(56,189,248,0.12)');
      g.addColorStop(0.5,'rgba(34,197,94,0.08)');
      g.addColorStop(1,'rgba(99,102,241,0.12)');
      ctx.strokeStyle = g;
      ctx.lineWidth = 3;
      ctx.stroke();
    }
    ctx.restore();
  }

  // Rosette in center using rotated polygons
  function drawRosette(ctx, R){
    ctx.save();
    const layers = 5;
    for (let i=0;i<layers;i++){
      const r = R*(0.1 + i*0.06);
      ctx.rotate(Math.PI/12);
      ctx.beginPath();
      for (let j=0;j<8;j++){
        const a = j*(Math.PI*2/8);
        const x = r*Math.cos(a), y=r*Math.sin(a);
        if (j===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
      }
      ctx.closePath();
      ctx.strokeStyle = 'rgba(203,213,225,'+(0.18 - i*0.02)+')';
      ctx.lineWidth = 1.2;
      ctx.stroke();
    }
    ctx.restore();
  }

  // Minute beads around rim
  function drawBeads(ctx, R){
    ctx.save();
    for (let i=0;i<60;i++){
      const a = i*(Math.PI*2/60) - Math.PI/2;
      const rr = R - (i%5===0? 14:8);
      const [x,y] = arcPoint(R, a);
      const [ix,iy] = arcPoint(rr, a);
      const g = ctx.createRadialGradient(ix,iy,0, ix,iy, i%5===0? 5:3);
      g.addColorStop(0, i%5===0? 'rgba(99,102,241,0.95)':'rgba(148,163,184,0.85)');
      g.addColorStop(1, 'rgba(2,6,23,0)');
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(ix,iy, i%5===0? 4.5:3, 0, Math.PI*2);
      ctx.fill();
    }
    ctx.restore();
  }

  // Orbiting prayer markers (pearls that gently breathe)
  function drawOrbitingPrayerMarkers(ctx, R, ANG_OFF, t){
    if (!Array.isArray(SCHEDULE_TIMES) || SCHEDULE_TIMES.length===0) return;
    ctx.save();
    for (let i=0;i<SCHEDULE_TIMES.length;i++){
      const hhmm = SCHEDULE_TIMES[i];
      const [hh,mm] = hhmm.split(':').map(Number);
      const a = (Math.PI*2)*((hh%12 + mm/60)/12) + ANG_OFF + Math.sin(t*0.0002 + i)*0.03;
      // highlight next marker later by over-drawing
      const breathe = 1 + 0.06*Math.sin(t*0.003 + i*1.7);
      const rr = R + 10*Math.sin(t*0.001 + i) + 6;
      const [x,y] = arcPoint(rr, a);
      const pearl = ctx.createRadialGradient(x,y,0, x,y, 10*breathe);
      pearl.addColorStop(0,'rgba(56,189,248,0.95)');
      pearl.addColorStop(1,'rgba(56,189,248,0)');
      ctx.fillStyle = pearl;
      ctx.beginPath(); ctx.arc(x,y, 6.5*breathe, 0, Math.PI*2); ctx.fill();
    }
    ctx.restore();
  }
  // Daylight arc between Fajr and Maghrib (approximate daylight band)
  function drawDayArc(ctx, R, ANG_OFF){
    if (!SCHEDULE_TIMES || !SCHEDULE_NAMES) return;
    const fi = SCHEDULE_NAMES.indexOf('Fajr');
    const mi = SCHEDULE_NAMES.indexOf('Maghrib');
    if (fi===-1 || mi===-1) return;
    const [fh,fm] = SCHEDULE_TIMES[fi].split(':').map(Number);
    const [mh,mm] = SCHEDULE_TIMES[mi].split(':').map(Number);
    const fa = (Math.PI*2)*((fh%12 + fm/60)/12) + ANG_OFF;
    const ma = (Math.PI*2)*((mh%12 + mm/60)/12) + ANG_OFF;
    ctx.save();
    ctx.beginPath();
    const g = ctx.createRadialGradient(0,0,R*0.4, 0,0,R+12);
    g.addColorStop(0,'rgba(250,204,21,0)');
    g.addColorStop(1,'rgba(250,204,21,0.18)');
    ctx.strokeStyle = g;
    ctx.lineWidth = 18;
    // handle wrapping across midnight
    let start = fa, end = ma;
    if (ma < fa) end += Math.PI*2;
    ctx.arc(0,0,R-6, start, end, false);
    ctx.stroke();
    ctx.restore();
  }
  function drawSecTail(ctx, tipX, tipY){
    __secTrail.push([tipX, tipY, Date.now()]);
    if (__secTrail.length > 12) __secTrail.shift();
    for (let i=0;i<__secTrail.length;i++){
      const [x,y,t] = __secTrail[i];
      const age = (Date.now() - t)/1000;
      const alpha = Math.max(0, 0.45 - age*0.08);
      const radius = Math.max(1, 4 - i*0.2);
      if (alpha <= 0) continue;
      ctx.beginPath();
      ctx.fillStyle = `rgba(56,189,248,${alpha.toFixed(3)})`;
      ctx.arc(x,y,radius,0,Math.PI*2); ctx.fill();
    }
  }
  function drawWatermark(ctx, R, img){
    if (!(img && img.complete && img.naturalWidth)) return;
    ctx.save();
    const SZ = R*0.58, ar = img.naturalWidth / img.naturalHeight;
    const w = ar>=1 ? SZ : SZ*ar;
    const h = ar>=1 ? SZ/ar : SZ;
    ctx.globalAlpha = 0.12;
    ctx.drawImage(img, -w/2, -h/2, w, h);
    ctx.restore();
  }

  // Timezone helpers (show and schedule by selected country)
  const ACTIVE_TZ = tzForCountry(CURRENT_COUNTRY || '');
  function tzParts(tz){
    const parts = new Intl.DateTimeFormat('en', {
      timeZone: tz,
      hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false,
      year:'numeric', month:'2-digit', day:'2-digit'
    }).formatToParts(new Date());
    const map = Object.fromEntries(parts.map(p=>[p.type,p.value]));
    return { y:+map.year, M:+map.month, d:+map.day, h:+map.hour, m:+map.minute, s:+map.second };
  }
  // Compute offset so that a local Date with hh:mm represents the same wall time in ACTIVE_TZ
  const TZ_OFFSET_MS = (()=>{
    const now = new Date();
    const p = tzParts(ACTIVE_TZ);
    const localLikeTz = new Date(`${p.y}-${p.M.toString().padStart(2,'0')}-${p.d.toString().padStart(2,'0')}T${p.h.toString().padStart(2,'0')}:${p.m.toString().padStart(2,'0')}:${p.s.toString().padStart(2,'0')}`);
    return localLikeTz.getTime() - now.getTime();
  })();

  // Radial countdown ring for next prayer (progress from previous prayer)
  function drawNextProgress(ctx, R, prevDate, nextDate){
    if (!prevDate || !nextDate) return;
    const now = Date.now();
    const a = Math.max(0, Math.min(1, (now - prevDate.getTime()) / (nextDate.getTime() - prevDate.getTime())));
    const base = R - 26;
    // track
    ctx.beginPath(); ctx.arc(0,0,base,0,Math.PI*2);
    ctx.strokeStyle = 'rgba(148,163,184,0.18)'; ctx.lineWidth = 12; ctx.stroke();
    // progress
    const grad = ctx.createConicGradient(-Math.PI/2, 0, 0);
    grad.addColorStop(0, 'rgba(56,189,248,0.85)');
    grad.addColorStop(0.5, 'rgba(34,197,94,0.85)');
    grad.addColorStop(1, 'rgba(99,102,241,0.85)');
    ctx.beginPath();
    ctx.arc(0,0,base, -Math.PI/2, -Math.PI/2 + a*Math.PI*2);
    ctx.strokeStyle = grad; ctx.lineWidth = 12; ctx.lineCap='round'; ctx.stroke();
  }

  function drawClock(){
    const p = tzParts(ACTIVE_TZ); const s=+p.s, m=+p.m, h=(+p.h)%12;
    const ctx=clockCanvas.getContext('2d');
    const W=clockCanvas.width,H=clockCanvas.height,R=Math.min(W,H)/2-24;
    ctx.clearRect(0,0,W,H); ctx.save(); ctx.translate(W/2,H/2);
    const ANG_OFF = -Math.PI / 2;

    // background face
    ctx.save(); ctx.beginPath(); ctx.arc(0,0,R,0,Math.PI*2); ctx.clip();
    if (clockPattern.complete && clockPattern.naturalWidth){
      const scale = Math.max((R*2)/clockPattern.naturalWidth, (R*2)/clockPattern.naturalHeight);
      const w = clockPattern.naturalWidth*scale, h = clockPattern.naturalHeight*scale;
      ctx.drawImage(clockPattern, -w/2, -h/2, w, h);
      const g = ctx.createRadialGradient(0,0,R*0.2, 0,0,R);
      g.addColorStop(0,'rgba(0,0,0,0)'); g.addColorStop(1,'rgba(0,0,0,.25)');
      ctx.fillStyle=g; ctx.fillRect(-R,-R,2*R,2*R);
    } else {
      const bg = ctx.createRadialGradient(-R*0.2,-R*0.2,R*0.2, 0,0,R);
      bg.addColorStop(0,'#0b132a'); bg.addColorStop(1,'#070d1c');
      ctx.fillStyle = bg; ctx.fillRect(-R,-R,2*R,2*R);
    }
    ctx.restore();

    // Aurora halo + daylight arc + outer ring + rosette
    const t = performance.now();
    drawAurora(ctx, R, t);
    drawDayArc(ctx, R, ANG_OFF);
    ctx.beginPath(); ctx.arc(0,0,R+6,0,Math.PI*2);
    ctx.strokeStyle=THEME.ring; ctx.lineWidth=10; ctx.stroke();
    drawRosette(ctx, R);

    // ticks
    drawBeads(ctx, R);

    // numerals
    ctx.fillStyle = THEME.text;
    ctx.font = '600 20px system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif';
    ctx.textAlign='center'; ctx.textBaseline='middle';
    [12,3,6,9].forEach(n=>{
      const a=(Math.PI/6)*n + ANG_OFF, r=R-40;
      ctx.fillText(String(n), r*Math.cos(a), r*Math.sin(a));
    });

    // orbiting prayer markers
    drawOrbitingPrayerMarkers(ctx, R, ANG_OFF, t);
    // highlight the next prayer marker and draw radial progress
    if (Array.isArray(SCHEDULE_TIMES) && SCHEDULE_TIMES.length){
      const nextObj = nextFrom(SCHEDULE_TIMES);
      const idx = nextObj.idx;
      const hhmm = SCHEDULE_TIMES[idx];
      const [hh,mm] = hhmm.split(':').map(Number);
      const a = (Math.PI*2)*((hh%12 + mm/60)/12) + ANG_OFF + Math.sin(t*0.0002 + idx)*0.03;
      const rr = R + 10*Math.sin(t*0.001 + idx) + 9;
      const [x,y] = [rr*Math.cos(a), rr*Math.sin(a)];
      const g = ctx.createRadialGradient(x,y,0,x,y,12);
      g.addColorStop(0,'rgba(250,204,21,0.95)');
      g.addColorStop(1,'rgba(250,204,21,0)');
      ctx.fillStyle = g;
      ctx.beginPath(); ctx.arc(x,y,8.5,0,Math.PI*2); ctx.fill();
      ctx.beginPath(); ctx.arc(x,y,11,0,Math.PI*2); ctx.strokeStyle='rgba(250,204,21,0.85)'; ctx.lineWidth=2; ctx.stroke();
      // radial progress ring from previous to next
      // compute prev date as the previous schedule time (yesterday if wraps)
      const prevIdx = (idx - 1 + SCHEDULE_TIMES.length) % SCHEDULE_TIMES.length;
      const prev = todayAt(SCHEDULE_TIMES[prevIdx]);
      const nextD = nextObj.date;
      if (prev > nextD){ prev.setDate(prev.getDate()-1); }
      drawNextProgress(ctx, R, prev, nextD);
    }

    // hand angles
    const sa=(Math.PI*2)*(s/60)+ANG_OFF;
    const ma=(Math.PI*2)*((m+s/60)/60)+ANG_OFF;
    const ha=(Math.PI*2)*((h+m/60)/12)+ANG_OFF;

    // hour & minute hands
    drawHand(ha, R*0.52, 8, THEME.hHand);
    drawHand(ma, R*0.72, 6, THEME.mHand);

    // second hand + comet tip
    ctx.save();
    ctx.rotate(sa);
    ctx.beginPath(); ctx.moveTo(-14,0); ctx.lineTo(R*0.80,0);
    ctx.strokeStyle = THEME.sHand; ctx.lineWidth = 2.5; ctx.lineCap='round'; ctx.stroke();
    const tipX = R*0.80, tipY = 0;
    ctx.beginPath(); ctx.fillStyle=THEME.sHand; ctx.arc(tipX, tipY, 3.5, 0, Math.PI*2); ctx.fill();
    ctx.restore();

    // comet tail (global coords)
    const gx = (R*0.80)*Math.cos(sa);
    const gy = (R*0.80)*Math.sin(sa);
    drawSecTail(ctx, gx, gy);

    // center + watermark
    ctx.beginPath(); ctx.arc(0,0,6,0,Math.PI*2); ctx.fillStyle=THEME.sHand; ctx.fill();
    drawWatermark(ctx, R, allahImg);

    ctx.restore();

    // digital readout pinned to selected country timezone
    nowText.textContent = new Intl.DateTimeFormat('en', {
      hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone: ACTIVE_TZ
    }).format(new Date());
  }

  function drawHand(a, len, w, col){
    const ctx=clockCanvas.getContext('2d');
    ctx.save();
    ctx.rotate(a);
    ctx.beginPath();
    ctx.moveTo(-10, 0);
    ctx.lineTo(len, 0);
    ctx.strokeStyle = col;
    ctx.lineWidth = w;
    ctx.lineCap = 'round';
    ctx.stroke();
    ctx.restore();
  }

  // YouTube player (fallback)
  window.onYouTubeIframeAPIReady = function(){};
  function createPlayer(){
    const mount = document.getElementById('ytContainer'); mount.innerHTML = '';
    player = new YT.Player('ytContainer', {
      videoId: YT_ID, host: YT_HOST,
      playerVars: { autoplay: 1, mute: START_WITH_SOUND ? 0 : 1, controls: 0, rel: 0, modestbranding: 1, playsinline: 1, enablejsapi: 1, origin: window.location.origin, loop: 0 },
      events: {
        onReady: function(){
          const iframe = player.getIframe();
          iframe.setAttribute('allow','autoplay; encrypted-media; picture-in-picture; fullscreen');
          iframe.setAttribute('allowfullscreen','allowfullscreen');
        },
        onStateChange: function(e){
          ytState = e.data;
          if (e.data === YT.PlayerState.PLAYING){
            overlayBlocked.style.display='none';
            const recent = (Date.now() - lastUserGesture) < 5000;
            try {
              if (START_WITH_SOUND || recent) {
                player.unMute();
                setTimeout(()=>{ try{ if (player.isMuted && player.isMuted()) unmuteBtn.style.display='inline-block'; else unmuteBtn.style.display='none'; }catch(_){} }, 120);
              } else {
                unmuteBtn.style.display = 'inline-block';
              }
            } catch(_) { unmuteBtn.style.display='inline-block'; }
          }
          if (e.data === YT.PlayerState.ENDED){ cleanUpAndBack(); scheduleNextFromNow(); }
        }
      }
    });
  }

  // Local MP4 azan
  function setupLocalVideo(){
    if (!LOCAL_URL) return;
    if (!localVid.getAttribute('data-bound')){
      localVid.src = LOCAL_URL;
      localVid.setAttribute('data-bound','1');
      localVid.loop = false;
      localVid.controls = false;
      localVid.playsInline = true;
      localVid.addEventListener('ended', ()=>{
        cleanUpAndBack();
        scheduleNextFromNow();
      });
    }
  }

  async function playLocal(label){
    setupLocalVideo();
    // Show local, hide YT container
    document.getElementById('ytContainer').style.display='none';
    localVid.style.display='block';

    try{
      localVid.muted = false;
      await localVid.play();
    }catch(e){
      try{
        localVid.muted = true;
        await localVid.play();
        overlayBlocked.style.display='flex';
      }catch(e2){
        overlayBlocked.style.display='flex';
      }
    }
  }

  function cleanUpAndBack(){
    // Stop YouTube
    try{ player && player.mute && player.mute(); player && player.stopVideo && player.stopVideo(); }catch(e){}
    // Stop local video
    try{
      localVid.pause();
      localVid.currentTime = 0;
      localVid.style.display = 'none';
    }catch(e){}
    unmuteBtn.style.display='none';
    overlayBlocked.style.display='none';
    overlayFS.style.display='none';
    if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(()=>{});
    document.body.classList.remove('fullwindow');
    videoWrap.style.display='none'; clockCanvas.style.display='block'; statusText.textContent='Clock is visible.';
    // restore YouTube container visibility (for a future fallback)
    document.getElementById('ytContainer').style.display='block';
  }

  async function playNow(label){
    // Fill entire window without using Fullscreen API (avoids gesture requirement)
    document.body.classList.add('fullwindow');
    clockCanvas.style.display='none';
    videoWrap.style.display='block';
    statusText.textContent = (label?label+' · ':'') + 'Playing video (full window). Will return when it ends (or after <?= (int)$CONFIG['fallback_minutes'] ?> minutes).';

    if (HAS_LOCAL && LOCAL_URL){
      await playLocal(label);
    } else {
      // Fallback to YouTube
      localVid.style.display='none';
      document.getElementById('ytContainer').style.display='block';
      if (!player) createPlayer();
      if (!STRICT_AUTOPLAY) setTimeout(()=>{ if (ytState !== YT.PlayerState.PLAYING) overlayBlocked.style.display='flex'; }, 1000);
    }

    if (restoreTimeout) clearTimeout(restoreTimeout);
    restoreTimeout = setTimeout(()=>{ cleanUpAndBack(); scheduleNextFromNow(); }, <?= (int)$CONFIG['fallback_minutes'] ?> * 60 * 1000);
  }

  blockedPlay.addEventListener('click', async ()=>{
    overlayBlocked.style.display='none';
    if (localVid.style.display === 'block'){
      try{ localVid.muted = false; await localVid.play(); }catch(e){}
      return;
    }
    try{ player.playVideo(); player.unMute(); unmuteBtn.style.display='none'; }catch(e){}
  });

  unmuteBtn.addEventListener('click', async ()=>{
    if (localVid.style.display === 'block'){
      try{ localVid.muted = false; await localVid.play(); }catch(e){}
      unmuteBtn.style.display='none';
      return;
    }
    try{ player.unMute(); }catch(e){}
    unmuteBtn.style.display='none';
  });

  goFSBtn.addEventListener('click', async ()=>{
    if (!STRICT_AUTOPLAY){
      await forceFullscreen(videoWrap);
    }
    overlayFS.style.display = STRICT_AUTOPLAY ? 'none' : 'none';
  });

  // Scheduler
  function todayAt(hhmm){ const [hh,mm]=hhmm.split(':').map(Number); const d=new Date(); d.setHours(hh,mm,0,0); return d; }
  function nextFrom(times){
    const now=new Date();
    const list=times.map(t=> new Date(todayAt(t).getTime() + TZ_OFFSET_MS));
    for (let i=0;i<list.length;i++){ if (list[i]>now) return {date:list[i], idx:i}; }
    const t=new Date(todayAt(times[0]).getTime() + TZ_OFFSET_MS); t.setDate(t.getDate()+1); return {date:t, idx:0};
  }
  function scheduleAt(ts,label){
    if (window.__nextTO) clearTimeout(window.__nextTO);
    const ms = ts - Date.now();
    window.__nextTO = setTimeout(()=>{ playNow(label); }, ms);
  }
  function fmtRemain(ms){ ms=Math.max(0,ms|0); const s=Math.floor(ms/1000); const h=Math.floor(s/3600); const m=Math.floor((s%3600)/60); const ss=s%60; const pad=n=>String(n).padStart(2,'0'); return `${pad(h)}:${pad(m)}:${pad(ss)}`; }
  function updateNextInfo(){
    const box = document.getElementById('nextInfo'); if (!box) return;
    if (!SCHEDULE_TIMES || SCHEDULE_TIMES.length===0){ box.style.display='none'; return; }
    const next = nextFrom(SCHEDULE_TIMES);
    const name = (SCHEDULE_NAMES && SCHEDULE_NAMES[next.idx]) ? SCHEDULE_NAMES[next.idx] : 'Next';
    const time = SCHEDULE_TIMES[next.idx];
    const rem = fmtRemain(next.date - Date.now());
    const nxName = document.getElementById('nxName'); if (nxName) nxName.textContent = name;
    const nxTime = document.getElementById('nxTime'); if (nxTime) nxTime.textContent = time;
    const nxRem  = document.getElementById('nxRem');  if (nxRem)  nxRem.textContent  = 'in ' + rem;
    box.style.display='flex';
  }
  function scheduleNextFromNow(){
    if (!SCHEDULE_TIMES || SCHEDULE_TIMES.length===0){ statusText.textContent='No schedule for this selection.'; return; }
    const next = nextFrom(SCHEDULE_TIMES);
    const label = SCHEDULE_NAMES[next.idx] ? `(${SCHEDULE_NAMES[next.idx]} • ${SCHEDULE_TIMES[next.idx]})` : SCHEDULE_TIMES[next.idx];
    scheduleAt(next.date, label);
    statusText.textContent='Timer ready. Will autoplay at the next scheduled time.';
    if (window.__nxInt) clearInterval(window.__nxInt);
    updateNextInfo();
    window.__nxInt = setInterval(updateNextInfo, 1000);
  }
  
  // --- Country → Timezone mapping for date lines (extend as needed)
const CURRENT_COUNTRY = "<?= h($qCountry) ?>";
function tzForCountry(country){
  if (/uae|united arab emirates/i.test(country)) return 'Asia/Dubai';
  // default: India
  return 'Asia/Kolkata';
}

// Render both Gregorian and Hijri (Umm al-Qura) dates for the selected country
function renderDates(){
  const tz = tzForCountry(CURRENT_COUNTRY || '');
  const now = new Date();

  // Gregorian
  const gFmt = new Intl.DateTimeFormat('en', {
    weekday:'long', day:'2-digit', month:'long', year:'numeric', timeZone: tz
  });
  const greg = gFmt.format(now);

  // Hijri via Intl (Umm al-Qura). Works on current Chrome/Firefox/Edge.
  let hijri = '';
  try{
    const hFmt = new Intl.DateTimeFormat('en-u-ca-islamic-umalqura', {
      day:'2-digit', month:'long', year:'numeric', timeZone: tz
    });
    hijri = hFmt.format(now);
  }catch(e){
    // Very old runtimes fallback: simple month/day (approx). You can keep or remove.
    hijri = 'Hijri date (update your browser)';
  }

  const gEl = document.getElementById('gregText');
  const hEl = document.getElementById('hijriText');
  if (gEl) gEl.textContent = greg;
  if (hEl) hEl.textContent = hijri;
}

// call once and refresh daily (also fine to refresh each minute; cheap)
renderDates();
// refresh at :00 every hour so a midnight rollover is captured in all timezones
setInterval(renderDates, 60 * 1000);


  // init
  drawClock();
  setInterval(drawClock,500);
  scheduleNextFromNow();

  // Tooltip on hover over prayer markers (show name + time)
  (function enableMarkerTooltip(){
    const tip = document.createElement('div');
    tip.style.position='fixed'; tip.style.display='none'; tip.style.pointerEvents='none';
    tip.style.background='rgba(2,6,23,0.9)'; tip.style.color='#e5e7eb'; tip.style.border='1px solid rgba(148,163,184,.35)'; tip.style.borderRadius='8px'; tip.style.padding='6px 8px'; tip.style.fontSize='12px'; tip.style.zIndex='20';
    document.body.appendChild(tip);
    let lastMove=0;
    clockCanvas.addEventListener('mousemove', (e)=>{
      // Recompute current pearl positions similar to drawOrbitingPrayerMarkers
      if (!Array.isArray(SCHEDULE_TIMES) || SCHEDULE_TIMES.length===0){ tip.style.display='none'; return; }
      const rect = clockCanvas.getBoundingClientRect();
      const cx = rect.left + rect.width/2, cy = rect.top + rect.height/2;
      const W = clockCanvas.width, H = clockCanvas.height; const R = Math.min(W,H)/2-24;
      const t = performance.now();
      const xs=[];
      for (let i=0;i<SCHEDULE_TIMES.length;i++){
        const [hh,mm] = SCHEDULE_TIMES[i].split(':').map(Number);
        const a = (Math.PI*2)*((hh%12 + mm/60)/12) - Math.PI/2 + Math.sin(t*0.0002 + i)*0.03;
        const rr = R + 10*Math.sin(t*0.001 + i) + 6;
        const x = cx + rr*Math.cos(a);
        const y = cy + rr*Math.sin(a);
        xs.push({x,y});
      }
      const mx = e.clientX, my = e.clientY;
      let hit = -1;
      for (let i=0;i<xs.length;i++){
        if (Math.hypot(mx - xs[i].x, my - xs[i].y) < 12){ hit = i; break; }
      }
      if (hit>=0){
        const name = (SCHEDULE_NAMES && SCHEDULE_NAMES[hit]) ? SCHEDULE_NAMES[hit] : 'Prayer';
        const time = SCHEDULE_TIMES[hit];
        tip.innerHTML = `<div style="font-weight:600">${name}</div><div>${time}</div>`;
        tip.style.left = (mx + 12) + 'px';
        tip.style.top  = (my + 12) + 'px';
        tip.style.display = 'block';
      } else {
        tip.style.display = 'none';
      }
    });
    clockCanvas.addEventListener('mouseleave', ()=>{ tip.style.display='none'; });
  })();
})();
</script>
</body>
</html>
<?php
        $body = ob_get_clean();

        return [
            'status' => 200,
            'headers' => [
                'Content-Type' => 'text/html; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy' => 'no-referrer',
                'X-Frame-Options' => 'SAMEORIGIN',
            ],
            'body' => $body,
        ];
    }
}
