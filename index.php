<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/admin/auth_check.php';

$pdo = getDB();
$isAdmin = isAdminLoggedIn();

// Fetch settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
foreach ($settingsStmt->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$eyebrow = $settings['eyebrow'] ?? 'Summer term · Aug – Sep 2026';
$heroTitle = $settings['hero_title'] ?? 'Everything due,<br>in <em>one line</em>.';
$heroSubtitle = $settings['hero_subtitle'] ?? '';
$allowPublicToggle = ($settings['allow_public_toggle'] ?? '1') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semester Ledger — Deadlines</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,500;9..144,600&family=Space+Grotesk:wght@400;500;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
  :root {
    --ink: #161d18;
    --paper: #0f1512;
    --card: #1a231d;
    --line: rgba(232,228,216,0.14);
    --line-strong: rgba(232,228,216,0.28);
    --text: #e8e4d8;
    --text-dim2: #9b9788;
    --amber: #e3a13d;
    --done: #6f9e78;
    --done-dim: #1c2820;
  }

  * { box-sizing:border-box; margin:0; padding:0; }
  html { scroll-behavior:smooth; }
  body {
    background: var(--paper);
    color: var(--text);
    font-family: 'Space Grotesk', sans-serif;
    overflow-x: hidden;
  }
  ::selection { background: var(--amber); color: #161d18; }

  body::before {
    content: "";
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at 50% -10%, rgba(227,161,61,0.08), transparent 55%);
    pointer-events: none;
    z-index: 0;
  }

  /* Top Control Bar */
  .top-bar {
    position: absolute;
    top: 2rem;
    left: 8vw;
    right: 8vw;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 10;
  }
  .portal-link-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.75rem;
    letter-spacing: 0.06em;
    color: var(--amber);
    background: rgba(227, 161, 61, 0.1);
    border: 1px solid rgba(227, 161, 61, 0.3);
    padding: 0.45rem 0.95rem;
    border-radius: 100px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.25s ease;
  }
  .portal-link-btn:hover {
    background: var(--amber);
    color: #121814;
    box-shadow: 0 0 15px rgba(227, 161, 61, 0.4);
    transform: translateY(-1px);
  }

  header.hero {
    position: relative;
    min-height: 92vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: 8vh 8vw 6vh;
    border-bottom: 1px solid var(--line);
  }

  .eyebrow {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.78rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: var(--amber);
    opacity: 0;
    animation: rise 0.8s ease forwards;
    animation-delay: 0.1s;
  }

  h1.title {
    font-family: 'Fraunces', serif;
    font-weight: 500;
    font-size: clamp(2.6rem, 8vw, 6.2rem);
    line-height: 0.98;
    letter-spacing: -0.01em;
    margin-top: 1.2rem;
    color: var(--text);
    opacity: 0;
    animation: rise 0.9s ease forwards;
    animation-delay: 0.25s;
  }
  h1.title em { font-style: italic; color: var(--amber); }

  .hero-sub {
    max-width: 520px;
    margin-top: 1.6rem;
    color: var(--text-dim2);
    font-size: 1.02rem;
    line-height: 1.6;
    opacity: 0;
    animation: rise 0.9s ease forwards;
    animation-delay: 0.4s;
  }

  .hero-stats {
    display: flex;
    gap: clamp(1.5rem, 4vw, 3.5rem);
    margin-top: 3.2rem;
    flex-wrap: wrap;
    opacity: 0;
    animation: rise 0.9s ease forwards;
    animation-delay: 0.55s;
  }
  .stat .num {
    font-family: 'Fraunces', serif;
    font-size: 2.4rem;
    font-weight: 500;
    color: var(--text);
    font-variant-numeric: tabular-nums;
  }
  .stat .sub {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.66rem;
    letter-spacing: 0.03em;
    color: var(--amber);
    margin-top: 0.55rem;
    min-height: 1.6em;
    display: inline-flex;
    align-items: center;
    padding: 0.28rem 0.65rem;
    border-radius: 100px;
    background: rgba(227,161,61,0.12);
    border: 1px solid rgba(227,161,61,0.3);
  }
  .stat .sub:empty {
    padding: 0; border: none; background: none; min-height: 0;
  }
  .stat .lbl {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-dim2);
    margin-top: 0.3rem;
  }

  .scroll-cue {
    position: absolute;
    bottom: 5vh; left: 8vw;
    display: flex; align-items: center; gap: 0.6rem;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    color: var(--text-dim2);
    letter-spacing: 0.08em;
    opacity: 0;
    animation: rise 0.9s ease forwards, bob 2.4s ease-in-out infinite;
    animation-delay: 0.8s, 1.6s;
  }
  .scroll-cue .bar { width: 1px; height: 28px; background: linear-gradient(var(--amber), transparent); }

  @keyframes rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
  @keyframes bob { 0%,100% { transform: translateY(0); } 50% { transform: translateY(6px); } }

  .legend {
    position: relative;
    display: flex; flex-wrap: wrap; gap: 0.5rem 1.4rem;
    padding: 2.2rem 8vw 0.6rem;
    z-index: 1;
  }
  .legend-item {
    display: flex; align-items: center; gap: 0.5rem;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    color: var(--text-dim2);
    letter-spacing: 0.02em;
  }
  .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

  .sync-row {
    padding: 0 8vw 2rem;
    border-bottom: 1px solid var(--line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
  }
  .sync-note {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.66rem;
    color: var(--text-dim2);
    display: flex; align-items: center; gap: 0.4rem;
  }
  .sync-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--done); }
  .sync-dot.off { background: #c96a5a; }

  .timeline { position: relative; padding: 6vh 8vw 14vh; z-index: 1; }

  .spine {
    position: absolute;
    left: calc(8vw + 78px);
    top: 0; bottom: 0;
    width: 1px;
    background: var(--line);
  }

  .row {
    position: relative;
    display: grid;
    grid-template-columns: 78px 1fr;
    gap: 1.6rem;
    padding: 1.6rem 0;
    border-bottom: 1px solid var(--line);
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.7s ease, transform 0.7s ease;
  }
  .row.visible { opacity: 1; transform: translateY(0); }
  .row:last-child { border-bottom: none; }

  .date-col {
    display: flex; flex-direction: column; align-items: flex-end;
    padding-top: 0.3rem;
    padding-right: 0.9rem;
  }
  .date-col .day {
    font-family: 'Fraunces', serif;
    font-size: 1.9rem;
    font-weight: 500;
    line-height: 1;
    color: var(--text);
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .date-col .mon {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.68rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--text-dim2);
    margin-top: 0.3rem;
    white-space: nowrap;
  }

  .node {
    position: absolute;
    left: calc(78px - 4px);
    top: 2.4rem;
    width: 9px; height: 9px;
    border-radius: 50%;
    background: var(--amber);
    box-shadow: 0 0 0 4px var(--paper);
  }

  .cards { display: flex; flex-direction: column; gap: 0.6rem; min-width: 0; }

  .card {
    background: var(--card);
    border: 1px solid var(--line);
    border-left: 3px solid var(--course-color);
    border-radius: 8px;
    padding: 0.9rem 1.1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    cursor: pointer;
    transition: border-color 0.25s ease, transform 0.25s ease, background 0.25s ease;
  }
  .card:hover { border-color: var(--course-color); transform: translateX(4px); background: #1f2a22; }
  .card.locked { cursor: default; }
  .card.locked:hover { transform: none; }

  .card-left { display: flex; align-items: center; gap: 0.8rem; min-width: 0; }

  .check-toggle {
    width: 20px; height: 20px;
    border-radius: 6px;
    border: 1.5px solid var(--line-strong);
    background: transparent;
    flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.2s ease;
    color: transparent;
    font-size: 0.72rem;
    pointer-events: none;
  }
  .card:hover .check-toggle { border-color: var(--course-color); }
  .card.is-done .check-toggle { background: var(--done); border-color: var(--done); color: #0f1512; }

  .meta { min-width: 0; }
  .course-name {
    font-size: 0.68rem;
    font-family: 'JetBrains Mono', monospace;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--course-color);
    margin-bottom: 0.25rem;
    transition: color 0.3s ease;
  }
  .task-name {
    font-size: 1rem;
    color: var(--text);
    font-weight: 500;
    transition: color 0.3s ease, text-decoration-color 0.3s ease;
  }

  .card.is-done { border-left-color: var(--done); background: #161e18; }
  .card.is-done:hover { border-color: var(--done); }
  .card.is-done .course-name { color: var(--text-dim2); }
  .card.is-done .task-name {
    color: var(--text-dim2);
    text-decoration: line-through;
    text-decoration-color: var(--text-dim2);
    text-decoration-thickness: 1.5px;
  }
  .row.all-done .day, .row.all-done .mon { opacity: 0.4; }
  .row.all-done .node { background: var(--done); }

  .countdown {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    color: var(--text-dim2);
    background: var(--course-dim);
    padding: 0.32rem 0.7rem;
    border-radius: 100px;
    white-space: nowrap;
    flex-shrink: 0;
  }
  .countdown.urgent { color: #ff9c8f; }
  .countdown.today { color: var(--amber); }
  .countdown.past { color: var(--done); background: var(--done-dim) !important; }

  .empty-state {
    padding: 4rem 1rem;
    text-align: center;
    color: var(--text-dim2);
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.9rem;
  }

  footer {
    padding: 4vh 8vw 6vh;
    border-top: 1px solid var(--line);
    display: flex; justify-content: space-between; align-items: center;
    flex-wrap: wrap; gap: 1rem;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.7rem;
    color: var(--text-dim2);
    letter-spacing: 0.05em;
    position: relative; z-index: 1;
  }
  footer a {
    color: var(--amber); text-decoration: none;
  }
  footer a:hover { text-decoration: underline; }

  @media (max-width: 640px) {
    .top-bar { top: 1.2rem; }
    .row { grid-template-columns: 66px 1fr; gap: 0.9rem; padding: 1.3rem 0; }
    .spine { left: calc(8vw + 66px); }
    .node { left: calc(66px - 4px); top: 2.1rem; }
    .date-col { padding-right: 0.6rem; }
    .date-col .day { font-size: 1.5rem; }
    .date-col .mon { font-size: 0.62rem; }
    .hero-stats { gap: 1.6rem; }
    .card { padding: 0.8rem 0.9rem; }
    .task-name { font-size: 0.92rem; }
  }

  @media (prefers-reduced-motion: reduce) {
    * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; }
  }
</style>
</head>
<body>

<div class="top-bar">
  <div></div>
  <a href="admin/<?= $isAdmin ? 'index.php' : 'login.php' ?>" class="portal-link-btn">
    <span><?= $isAdmin ? '⚙️' : '🔒' ?></span>
    <span><?= $isAdmin ? 'Admin Portal' : 'Portal Login' ?></span>
  </a>
</div>

<header class="hero">
  <div class="eyebrow" id="heroEyebrow"><?= htmlspecialchars($eyebrow) ?></div>
  <h1 class="title" id="heroTitle"><?= $heroTitle ?></h1>
  <?php if (!empty($heroSubtitle)): ?>
    <div class="hero-sub" id="heroSubtitle"><?= htmlspecialchars($heroSubtitle) ?></div>
  <?php endif; ?>
  <div class="hero-stats">
    <div class="stat"><div class="num" id="statTotal">—</div><div class="lbl">Remaining</div></div>
    <div class="stat"><div class="num" id="statCourses">—</div><div class="lbl">Courses</div></div>
    <div class="stat"><div class="num" id="statNext">—</div><div class="lbl">Days to next</div><div class="sub" id="statNextSub"></div></div>
  </div>
  <div class="scroll-cue"><div class="bar"></div>Scroll</div>
</header>

<div class="legend" id="legend"></div>

<div class="sync-row">
  <div class="sync-note" id="syncNote">
    <span class="sync-dot" id="syncDot"></span>
    <span id="syncText">Connected to live portal database</span>
  </div>
  <div>
    <a href="admin/<?= $isAdmin ? 'index.php' : 'login.php' ?>" style="font-family:'JetBrains Mono',monospace; font-size:0.7rem; color:var(--text-dim2); text-decoration:none;">
      Manage in Portal →
    </a>
  </div>
</div>

<div class="timeline">
  <div class="spine"></div>
  <div id="rows"></div>
</div>

<footer>
  <span>Updated live against today's date</span>
  <span id="todayLabel"></span>
  <span><a href="admin/<?= $isAdmin ? 'index.php' : 'login.php' ?>">Control Portal Login</a></span>
</footer>

<script>
const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

let COURSES = {};
let DATA = [];
let ALLOW_PUBLIC_TOGGLE = <?= $allowPublicToggle ? 'true' : 'false' ?>;

function daysBetween(a, b){
  const ms = new Date(b.getFullYear(), b.getMonth(), b.getDate()) - new Date(a.getFullYear(), a.getMonth(), a.getDate());
  return Math.round(ms / 86400000);
}

function countdownLabel(d){
  if (d < 0) return { text: '✓ Done', cls: 'past' };
  if (d === 0) return { text: 'Today', cls: 'today' };
  if (d === 1) return { text: '1 day left', cls: 'urgent' };
  if (d <= 3) return { text: d + ' days left', cls: 'urgent' };
  return { text: d + ' days left', cls: '' };
}

const legendEl = document.getElementById('legend');
const rowsEl = document.getElementById('rows');
const syncDot = document.getElementById('syncDot');
const syncText = document.getElementById('syncText');

function renderLegend() {
  legendEl.innerHTML = '';
  Object.values(COURSES).forEach(c => {
    const el = document.createElement('div');
    el.className = 'legend-item';
    el.innerHTML = `<span class="dot" style="background:${c.color}"></span>${escapeHtml(c.label || c.name)}`;
    legendEl.appendChild(el);
  });
}

function escapeHtml(str) {
  if (!str) return '';
  return str.replace(/[&<>'"]/g, 
    tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
  );
}

const io = new IntersectionObserver((entries)=>{
  entries.forEach(e=>{
    if (e.isIntersecting){
      e.target.classList.add('visible');
      io.unobserve(e.target);
    }
  });
}, { threshold: 0.15 });

function render(){
  const TODAY = new Date();
  rowsEl.innerHTML = '';

  let totalItems = 0;
  const remainingDays = [];

  if (DATA.length === 0) {
    rowsEl.innerHTML = `<div class="empty-state">No deadlines currently scheduled. Add deadlines from the Control Portal.</div>`;
    document.getElementById('statTotal').textContent = '0';
    document.getElementById('statCourses').textContent = Object.keys(COURSES).length;
    document.getElementById('statNext').textContent = '—';
    document.getElementById('statNextSub').textContent = '';
    return;
  }

  DATA.forEach(group=>{
    const [y, m, d] = group.date.split('-').map(Number);
    const groupDate = new Date(y, m - 1, d);

    const days = daysBetween(TODAY, groupDate);
    const pastDeadline = days < 0;

    const statuses = group.items.map(it => {
      const done = pastDeadline || !!it.is_done;
      const cd = done ? { text: '✓ Done', cls: 'past' } : countdownLabel(days);
      if (!done) remainingDays.push(days);
      return { done, cd, locked: pastDeadline };
    });

    const groupAllDone = statuses.every(s => s.done);
    totalItems += statuses.filter(s => !s.done).length;

    const row = document.createElement('div');
    row.className = 'row' + (groupAllDone ? ' all-done' : '');
    row.innerHTML = `
      <div class="node"></div>
      <div class="date-col">
        <div class="day">${String(groupDate.getDate()).padStart(2,'0')}</div>
        <div class="mon">${MONTHS[groupDate.getMonth()]}</div>
      </div>
      <div class="cards">
        ${group.items.map((it, ii)=>{
          const course = COURSES[it.c] || { label: it.c, color: '#e3a13d', dim: 'rgba(227,161,61,0.15)' };
          const st = statuses[ii];
          return `<div class="card ${st.done ? 'is-done' : ''} ${st.locked ? 'locked' : ''}" data-id="${it.id}" style="--course-color:${course.color}; --course-dim:${course.dim};">
            <div class="card-left">
              <span class="check-toggle">✓</span>
              <div class="meta">
                <div class="course-name">${escapeHtml(course.label || course.name)}</div>
                <div class="task-name">${escapeHtml(it.t)}</div>
              </div>
            </div>
            <div class="countdown ${st.cd.cls}">${st.cd.text}</div>
          </div>`;
        }).join('')}
      </div>
    `;
    rowsEl.appendChild(row);
    io.observe(row);
  });

  const distinctDays = [...new Set(remainingDays)].sort((a,b)=>a-b);

  const statNextEl = document.getElementById('statNext');
  const statNextSubEl = document.getElementById('statNextSub');

  if (distinctDays.length === 0){
    statNextEl.textContent = '—';
    statNextSubEl.textContent = '';
  } else if (distinctDays[0] === 0){
    statNextEl.textContent = 'Today';
    const following = distinctDays[1];
    statNextSubEl.textContent = following === undefined
      ? ''
      : `Next in ${following} day${following === 1 ? '' : 's'}`;
  } else {
    statNextEl.textContent = distinctDays[0];
    statNextSubEl.textContent = '';
  }

  document.getElementById('statTotal').textContent = totalItems;
  document.getElementById('statCourses').textContent = Object.keys(COURSES).length;
  document.getElementById('todayLabel').textContent = 'Today — ' + TODAY.getDate() + ' ' + MONTHS[TODAY.getMonth()] + ' ' + TODAY.getFullYear();
}

// Toggle done on card click (if allowed)
rowsEl.addEventListener('click', async (e)=>{
  const card = e.target.closest('.card');
  if (!card || card.classList.contains('locked')) return;

  const id = parseInt(card.dataset.id);
  if (!id) return;

  // Find task in local state
  let task = null;
  for (const group of DATA) {
    task = group.items.find(i => i.id === id);
    if (task) break;
  }
  if (!task) return;

  const nextDone = !task.is_done;
  task.is_done = nextDone;
  render();

  try {
    const res = await fetch('api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'toggle_done', id, is_done: nextDone ? 1 : 0 })
    });
    const result = await res.json();
    if (!result.success) {
      // Revert if rejected
      task.is_done = !nextDone;
      render();
      alert(result.message || 'Status change not allowed');
    }
  } catch (err) {
    console.error('Failed to sync toggle:', err);
    task.is_done = !nextDone;
    render();
  }
});

// Fetch all live data from Database
async function fetchLiveData() {
  try {
    const [tasksRes, coursesRes, settingsRes] = await Promise.all([
      fetch('api/tasks.php?action=list'),
      fetch('api/courses.php?action=list'),
      fetch('api/settings.php?action=get')
    ]);

    const tasksData = await tasksRes.json();
    const coursesData = await coursesRes.json();
    const settingsData = await settingsRes.json();

    if (coursesData.success) {
      COURSES = coursesData.map || {};
      renderLegend();
    }

    if (tasksData.success) {
      DATA = tasksData.grouped || [];
      render();
    }

    if (settingsData.success && settingsData.settings) {
      const s = settingsData.settings;
      if (s.eyebrow) document.getElementById('heroEyebrow').textContent = s.eyebrow;
      if (s.hero_title) document.getElementById('heroTitle').innerHTML = s.hero_title;
      ALLOW_PUBLIC_TOGGLE = (s.allow_public_toggle === '1');
    }

    syncDot.classList.remove('off');
    syncText.textContent = 'Synced with Database — Changes in Portal reflect live';
  } catch (err) {
    console.error('Sync failed:', err);
    syncDot.classList.add('off');
    syncText.textContent = 'Offline / Could not connect to database';
  }
}

// Initial fetch
fetchLiveData();

// Poll periodically (every 10 seconds) so changes made from Portal show live
setInterval(fetchLiveData, 10000);

// Also refresh dates every minute
setInterval(render, 60000);
</script>
</body>
</html>
