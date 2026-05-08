<?php
require "config.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
$isAdmin  = !empty($_SESSION['is_admin']);

// Load last 20 conversation turns for display
$historyRows = [];
$histStmt = $conn->prepare(
    "SELECT user_message, assistant_reply FROM conversation_memory
     WHERE user_id = ?
     ORDER BY id DESC LIMIT 20"
);
$histStmt->bind_param("i", $_SESSION['user_id']);
$histStmt->execute();
$histResult = $histStmt->get_result();
while ($row = $histResult->fetch_assoc()) {
    $historyRows[] = $row;
}
$histStmt->close();
$historyRows = array_reverse($historyRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TARS — Tactical AI Assistant</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
/* ── Reset ── */
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}

/* ── Design tokens ── */
:root{
  --bg:       #050811;
  --surface:  rgba(255,255,255,.04);
  --surface2: rgba(255,255,255,.07);
  --border:   rgba(255,255,255,.08);
  --accent:   #f59e0b;
  --accent2:  #d97706;
  --glow:     #fbbf24;
  --text:     #e2e8f0;
  --muted:    #64748b;
  --user-bg:  linear-gradient(135deg,#92400e,#b45309);
  --bot-bg:   rgba(15,23,42,.9);
  --danger:   #ef4444;
  --font:     'Outfit', sans-serif;
  --mono:     'Space Mono', monospace;
  --radius:   14px;
  --sidebar-w:260px;
}

html,body{height:100%;background:var(--bg);font-family:var(--font);color:var(--text);overflow:hidden;}

/* ── Backgrounds ── */
.bg-canvas{position:fixed;inset:0;z-index:0;
  background:
    radial-gradient(ellipse 70% 50% at 10% 5%,  rgba(245,158,11,.12) 0%,transparent 60%),
    radial-gradient(ellipse 50% 70% at 90% 95%,  rgba(217,119,6,.12) 0%,transparent 60%),
    radial-gradient(ellipse 40% 40% at 50% 50%,  rgba(251,191,36,.06) 0%,transparent 70%);
}
.grid{position:fixed;inset:0;z-index:0;
  background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),
                   linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);
  background-size:48px 48px;
  mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black 20%,transparent 100%);
}

/* ── App Shell ── */
.app{position:relative;z-index:1;display:flex;height:100vh;}

/* ── Sidebar ── */
.sidebar{
  width:var(--sidebar-w);
  flex-shrink:0;
  display:flex;
  flex-direction:column;
  border-right:1px solid var(--border);
  background:rgba(5,8,17,.85);
  backdrop-filter:blur(20px);
  padding:20px 16px;
  gap:16px;
  overflow:hidden;
}

.sidebar-logo{
  display:flex;align-items:center;gap:10px;
  padding:6px 8px;
}
.sidebar-logo .ring{
  width:36px;height:36px;border-radius:50%;
  background:linear-gradient(135deg,rgba(245,158,11,.35),rgba(217,119,6,.35));
  border:1px solid rgba(252,211,77,.3);
  display:flex;align-items:center;justify-content:center;
  font-size:16px;
  box-shadow:0 0 18px rgba(245,158,11,.2);
  animation:pulseRing 3s ease-in-out infinite;
}
@keyframes pulseRing{0%,100%{box-shadow:0 0 14px rgba(245,158,11,.2);}50%{box-shadow:0 0 28px rgba(245,158,11,.4);}}
.sidebar-logo .name{
  font-family:var(--mono);font-size:18px;font-weight:700;letter-spacing:3px;
  background:linear-gradient(90deg,#fbbf24,#f59e0b);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;
}

.sidebar-user{
  background:var(--surface);border:1px solid var(--border);border-radius:10px;
  padding:10px 12px;
  display:flex;align-items:center;gap:10px;
}
.user-avatar{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  display:flex;align-items:center;justify-content:center;
  font-weight:700;font-size:13px;color:#fff;flex-shrink:0;
}
.user-meta{overflow:hidden;}
.user-name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.user-role{font-size:11px;color:var(--muted);}

.sidebar-label{font-size:10px;color:var(--muted);letter-spacing:1.5px;text-transform:uppercase;padding:0 4px;}

.sidebar-nav{display:flex;flex-direction:column;gap:4px;}
.nav-btn{
  display:flex;align-items:center;gap:10px;
  padding:9px 12px;border-radius:9px;
  background:transparent;border:none;color:var(--muted);
  font-family:var(--font);font-size:13px;cursor:pointer;
  transition:background .2s,color .2s;text-align:left;width:100%;
}
.nav-btn:hover{background:var(--surface2);color:var(--text);}
.nav-btn.active{background:var(--surface2);color:var(--glow);}
.nav-btn .icon{font-size:15px;width:20px;text-align:center;flex-shrink:0;}

/* history list */
.history-list{
  flex:1;overflow-y:auto;display:flex;flex-direction:column;gap:4px;
  padding-right:2px;
}
.history-list::-webkit-scrollbar{width:3px;}
.history-list::-webkit-scrollbar-thumb{background:rgba(255,255,255,.08);border-radius:4px;}
.hist-item{
  padding:8px 10px;border-radius:8px;cursor:pointer;
  font-size:12px;color:var(--muted);
  overflow:hidden;white-space:nowrap;text-overflow:ellipsis;
  transition:background .15s,color .15s;
  border:none;background:transparent;text-align:left;width:100%;
}
.hist-item:hover{background:var(--surface2);color:var(--text);}

.sidebar-footer{margin-top:auto;}
.logout-btn{
  width:100%;padding:10px;border-radius:9px;
  background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);
  color:#f87171;font-family:var(--font);font-size:13px;font-weight:500;
  cursor:pointer;transition:background .2s,border-color .2s;
  display:flex;align-items:center;justify-content:center;gap:8px;
}
.logout-btn:hover{background:rgba(239,68,68,.18);border-color:rgba(239,68,68,.35);}

/* ── Main chat area ── */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;padding:20px 24px;}

/* Status bar */
.status-bar{
  display:flex;align-items:center;justify-content:space-between;
  margin-bottom:16px;
}
.status-left{display:flex;align-items:center;gap:10px;}
.status-dot{width:8px;height:8px;border-radius:50%;background:#34d399;animation:blink 2s ease-in-out infinite;}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:.4;}}
.status-text{font-size:12px;color:var(--muted);}

.header-actions{display:flex;gap:8px;}
.icon-btn{
  width:36px;height:36px;border-radius:9px;
  background:var(--surface);border:1px solid var(--border);
  color:var(--muted);font-size:16px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s,color .2s,border-color .2s;
}
.icon-btn:hover{background:var(--surface2);color:var(--text);}

/* Chat box */
.chat-box{
  flex:1;overflow-y:auto;
  display:flex;flex-direction:column;gap:14px;
  padding:4px 2px 12px;
  scroll-behavior:smooth;
}
.chat-box::-webkit-scrollbar{width:4px;}
.chat-box::-webkit-scrollbar-thumb{background:rgba(255,255,255,.07);border-radius:4px;}

/* Messages */
.msg{
  max-width:74%;padding:14px 18px;border-radius:16px;
  font-size:14px;line-height:1.65;
  animation:msgIn .3s cubic-bezier(.16,1,.3,1) both;
}
@keyframes msgIn{from{opacity:0;transform:translateY(8px) scale(.98);}to{opacity:1;transform:none;}}

.msg.user{
  align-self:flex-end;
  background:var(--user-bg);
  color:#dbeafe;
  border-bottom-right-radius:4px;
}
.msg.assistant{
  align-self:flex-start;
  background:var(--bot-bg);
  border:1px solid var(--border);
  color:var(--text);
  border-bottom-left-radius:4px;
}

/* Markdown inside assistant */
.msg.assistant strong{color:#fff;}
.msg.assistant em{color:#94a3b8;}
.msg.assistant h1,.msg.assistant h2,.msg.assistant h3{color:var(--glow);margin:10px 0 6px;}
.msg.assistant ul,.msg.assistant ol{padding-left:20px;margin:8px 0;}
.msg.assistant li{margin:4px 0;}
.msg.assistant pre{
  background:rgba(0,0,0,.5);border:1px solid var(--border);
  padding:12px;border-radius:8px;overflow-x:auto;margin:10px 0;
}
.msg.assistant code{font-family:var(--mono);color:var(--glow);font-size:13px;}
.msg.assistant a{color:var(--glow);}
.msg.assistant blockquote{border-left:3px solid var(--accent);padding-left:12px;color:var(--muted);margin:8px 0;}

/* msg meta */
.msg-wrap{display:flex;flex-direction:column;gap:4px;}
.msg-wrap.user{align-items:flex-end;}
.msg-meta{font-size:10px;color:var(--muted);padding:0 4px;}

/* Thinking bubble */
.thinking-wrap{align-self:flex-start;}
#thinking{background:var(--bot-bg);border:1px solid var(--border);animation:msgIn .3s ease both;}
.thinking-inner{display:flex;align-items:center;gap:12px;font-size:13px;color:var(--muted);}
.dots{display:flex;gap:5px;}
.dots span{
  width:7px;height:7px;border-radius:50%;background:var(--glow);
  animation:dotPop 1.2s ease-in-out infinite;
}
.dots span:nth-child(2){animation-delay:.2s;}
.dots span:nth-child(3){animation-delay:.4s;}
@keyframes dotPop{0%,100%{opacity:.25;transform:scale(.7);}50%{opacity:1;transform:scale(1.2);}}

/* Input row */
.input-row{
  display:flex;align-items:flex-end;gap:8px;
  margin-top:8px;
  background:var(--surface);
  border:1px solid var(--border);
  border-radius:14px;
  padding:10px 12px;
  transition:border-color .2s,box-shadow .2s;
}
.input-row:focus-within{border-color:rgba(252,211,77,.35);box-shadow:0 0 0 3px rgba(245,158,11,.1);}

#textInput{
  flex:1;background:transparent;border:none;outline:none;
  color:var(--text);font-family:var(--font);font-size:14px;
  resize:none;max-height:120px;min-height:22px;line-height:1.5;
  padding:2px 0;
}
#textInput::placeholder{color:var(--muted);}

.send-btn{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  background:linear-gradient(135deg,var(--accent),var(--accent2));
  border:none;color:#fff;font-size:16px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:opacity .2s,transform .15s,box-shadow .2s;
  box-shadow:0 2px 12px rgba(245,158,11,.35);
}
.send-btn:hover{opacity:.9;transform:translateY(-1px);box-shadow:0 6px 20px rgba(245,158,11,.45);}

.mic-btn{
  width:38px;height:38px;border-radius:10px;flex-shrink:0;
  background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);
  color:#f87171;font-size:16px;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:background .2s,border-color .2s,transform .15s;
}
.mic-btn:hover{background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.4);}
.mic-btn.active{background:rgba(239,68,68,.35);border-color:rgba(239,68,68,.6);animation:micPulse 1s ease-in-out infinite;}
@keyframes micPulse{0%,100%{transform:scale(1);}50%{transform:scale(1.12);}}

/* Research toggle */
.toolbar{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.toggle-chip{
  display:flex;align-items:center;gap:6px;
  padding:5px 10px;border-radius:20px;
  background:var(--surface);border:1px solid var(--border);
  font-size:12px;color:var(--muted);cursor:pointer;
  transition:background .2s,border-color .2s,color .2s;
  user-select:none;
}
.toggle-chip.active{background:rgba(245,158,11,.15);border-color:rgba(252,211,77,.4);color:var(--glow);}
.toggle-chip input{display:none;}

/* Toast */
.toast{
  position:fixed;bottom:28px;right:28px;z-index:999;
  background:rgba(15,23,42,.95);border:1px solid var(--border);
  border-radius:10px;padding:12px 18px;
  font-size:13px;color:var(--text);
  box-shadow:0 8px 32px rgba(0,0,0,.5);
  animation:toastIn .3s ease both;
  pointer-events:none;
}
.toast.hidden{display:none;}
@keyframes toastIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:none;}}

/* Source badge */
.source-badge{
  display:inline-block;font-size:10px;padding:2px 7px;border-radius:20px;
  background:rgba(245,158,11,.12);border:1px solid rgba(252,211,77,.2);
  color:var(--glow);margin-top:8px;font-family:var(--mono);
}

/* Responsive: collapse sidebar on small screens */
@media(max-width:700px){
  .sidebar{display:none;}
  .main{padding:14px;}
}

/* Clear chat confirm */
.confirm-overlay{
  position:fixed;inset:0;z-index:100;background:rgba(0,0,0,.6);backdrop-filter:blur(6px);
  display:flex;align-items:center;justify-content:center;
}
.confirm-overlay.hidden{display:none;}
.confirm-box{
  background:#0d1526;border:1px solid var(--border);border-radius:16px;
  padding:32px;max-width:360px;width:90%;text-align:center;
}
.confirm-box h3{margin-bottom:10px;}
.confirm-box p{font-size:13px;color:var(--muted);margin-bottom:24px;}
.confirm-btns{display:flex;gap:10px;justify-content:center;}
.btn-cancel{padding:10px 20px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text);cursor:pointer;font-family:var(--font);}
.btn-danger{padding:10px 20px;border-radius:8px;border:none;background:var(--danger);color:#fff;cursor:pointer;font-family:var(--font);font-weight:600;}

/* UPDATED — TARS MODE: Delete history button */
#deleteHistBtn{color:#f87171 !important;}
#deleteHistBtn:hover{background:rgba(239,68,68,.15) !important;border-color:rgba(239,68,68,.35) !important;color:#fca5a5 !important;}

/* UPDATED — TARS MODE: Cue Light active state (amber glow) */
#cueLightChip.active{
  background:rgba(245,158,11,.18);
  border-color:rgba(251,191,36,.5);
  color:#fbbf24;
  box-shadow:0 0 12px rgba(245,158,11,.2);
}
/* Research tactical mode active */
#researchChip.active{
  background:rgba(239,68,68,.12);
  border-color:rgba(239,68,68,.35);
  color:#f87171;
}
/* TARS source badge styling */
.source-badge[data-src="TARS"]{
  background:rgba(245,158,11,.12);
  border-color:rgba(251,191,36,.3);
  color:#fbbf24;
}
</style>
</head>
<body>

<div class="bg-canvas"></div>
<div class="grid"></div>

<div class="app">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">

    <div class="sidebar-logo">
      <div class="ring">▣</div>
      <div class="name">TARS</div>
    </div>

    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($username,0,1)) ?></div>
      <div class="user-meta">
        <div class="user-name"><?= $username ?></div>
        <div class="user-role"><?= $isAdmin ? '⭐ Admin' : 'Member' ?></div>
      </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="sidebar-nav">
      <div class="sidebar-label">Admin</div>
      <a href="admin_panel.php" class="nav-btn"><span class="icon">🛡</span>Admin Panel</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($historyRows)): ?>
    <div class="sidebar-label">Recent chats</div>
    <div class="history-list">
      <?php foreach ($historyRows as $h): ?>
      <button class="hist-item" onclick="restoreHistory(<?= htmlspecialchars(json_encode($h['user_message'])) ?>, <?= htmlspecialchars(json_encode($h['assistant_reply'])) ?>)">
        <?= htmlspecialchars(mb_strimwidth($h['user_message'],0,42,'…')) ?>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
      <form method="POST" action="logout.php">
        <button class="logout-btn" type="submit">⏻ &nbsp;Sign Out</button>
      </form>
    </div>

  </aside>

  <!-- ── Main ── -->
  <main class="main">

    <div class="status-bar">
      <div class="status-left">
        <div class="status-dot"></div>
        <div class="status-text">TARS online &nbsp;·&nbsp; Ollama + Wikipedia + Local LLM</div>
      </div>
      <div class="header-actions">
        <button class="icon-btn" title="Clear display" onclick="confirmClear()">🗑</button>
        <button class="icon-btn" title="Purge memory from database" id="deleteHistBtn" onclick="confirmDeleteHistory()" style="color:#f87171">⬛</button>
        <button class="icon-btn" title="Mute / Unmute TARS" id="muteBtn" onclick="toggleMute()">🔊</button>
      </div>
    </div>

    <div class="chat-box" id="chatBox">
      <div class="msg-wrap">
        <div class="msg assistant">
          TARS online. Good <?= (date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening')) ?>, <strong><?= $username ?></strong>. I'm <strong>TARS</strong>. State your query. I have Wikipedia, arXiv, DuckDuckGo, StackOverflow, and a local LLM standing by. Research mode goes deeper. Cue Light makes me approximately 12% friendlier.
        </div>
        <div class="msg-meta">Just now</div>
      </div>
    </div>

    <!-- UPDATED — TARS MODE: toolbar with Research, Auto-speak, and Cue Light -->
    <div class="toolbar">
      <label class="toggle-chip" id="researchChip">
        <input type="checkbox" id="researchToggle">
        ⬡ Mission: Research
      </label>
      <label class="toggle-chip" id="voiceChip">
        <input type="checkbox" id="voiceToggle" checked>
        🔊 Auto-speak
      </label>
      <label class="toggle-chip" id="cueLightChip" title="Activates TARS friendly mode (approximately 12% warmer)">
        <input type="checkbox" id="cueLightToggle">
        💡 Cue Light
      </label>
    </div>

    <div class="input-row">
      <textarea id="textInput" rows="1" placeholder="Issue a command to TARS…"></textarea>
      <button class="mic-btn" id="micBtn" onclick="startVoice()" title="Voice input">🎤</button>
      <button class="send-btn" onclick="sendText()" title="Send">➤</button>
    </div>

  </main>

</div>

<!-- Confirm overlay -->
<div class="confirm-overlay hidden" id="confirmOverlay">
  <div class="confirm-box">
    <h3>Clear display?</h3>
    <p>This removes messages from the current view only. Database memory is untouched.</p>
    <div class="confirm-btns">
      <button class="btn-cancel" onclick="document.getElementById('confirmOverlay').classList.add('hidden')">Cancel</button>
      <button class="btn-danger" onclick="clearChat()">Clear</button>
    </div>
  </div>
</div>

<!-- Delete History overlay — UPDATED TARS MODE -->
<div class="confirm-overlay hidden" id="deleteHistoryOverlay">
  <div class="confirm-box">
    <h3 style="color:#f87171">⬛ Purge Memory?</h3>
    <p>This permanently deletes all your conversation history and interaction logs from the database. TARS will lose all context of previous sessions.<br><br>This cannot be undone.</p>
    <div class="confirm-btns">
      <button class="btn-cancel" onclick="document.getElementById('deleteHistoryOverlay').classList.add('hidden')">Abort</button>
      <button class="btn-danger" onclick="deleteHistory()">Purge Memory</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast hidden" id="toast"></div>

<script>
/* ── Config ── */
const chatBox   = document.getElementById('chatBox');
const input     = document.getElementById('textInput');
const micBtn    = document.getElementById('micBtn');
const muteBtn   = document.getElementById('muteBtn');
let   muted     = false;
let   listening = false;

/* ── Research / Voice / Cue Light chips — UPDATED TARS MODE ── */
const researchToggle  = document.getElementById('researchToggle');
const voiceToggle     = document.getElementById('voiceToggle');
const cueLightToggle  = document.getElementById('cueLightToggle');

['researchToggle','voiceToggle','cueLightToggle'].forEach(id=>{
  const el = document.getElementById(id);
  const chip = el.closest('.toggle-chip');
  el.addEventListener('change',()=>{
    chip.classList.toggle('active',el.checked);
    if(id === 'cueLightToggle'){
      showToast(el.checked ? 'Cue Light ON. TARS is now 12% warmer.' : 'Cue Light OFF. Back to standard TARS protocol.');
    }
    if(id === 'researchToggle'){
      showToast(el.checked ? 'Research mode ACTIVE. Going deep.' : 'Research mode disengaged.');
    }
  });
});

/* ── Auto-grow textarea ── */
input.addEventListener('input',()=>{
  input.style.height='auto';
  input.style.height=Math.min(input.scrollHeight,120)+'px';
});
input.addEventListener('keydown',e=>{
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendText();}
});

/* ── Toast ── */
function showToast(msg,duration=2500){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.classList.remove('hidden');
  clearTimeout(t._timer);
  t._timer=setTimeout(()=>t.classList.add('hidden'),duration);
}

/* ── Timestamp ── */
function nowStr(){
  return new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'});
}

/* ── Add message ── */
function addMessage(text, role, source){
  const wrap = document.createElement('div');
  wrap.classList.add('msg-wrap');
  if(role==='user') wrap.classList.add('user');

  const msg = document.createElement('div');
  msg.classList.add('msg', role);

  if(role==='assistant'){
    msg.innerHTML = marked.parse(text);
    if(source){
      const badge=document.createElement('div');
      badge.className='source-badge';
      badge.textContent='via '+source;
      msg.appendChild(badge);
    }
  }else{
    msg.textContent=text;
  }

  const meta=document.createElement('div');
  meta.className='msg-meta';
  meta.textContent=nowStr();

  wrap.appendChild(msg);
  wrap.appendChild(meta);
  chatBox.appendChild(wrap);
  chatBox.scrollTop=chatBox.scrollHeight;
  return msg;
}

/* ── Thinking indicator — UPDATED TARS MODE ── */
const thinkingSteps=['Processing…','Scanning knowledge base…','Querying external sources…','Consulting local LLM…','Formulating response…'];
let thinkingTimer;

function showThinking(){
  removeThinking();
  const wrap=document.createElement('div');
  wrap.className='msg-wrap thinking-wrap';
  wrap.id='thinkingWrap';
  const box=document.createElement('div');
  box.className='msg assistant';
  box.id='thinking';
  let step=0;
  box.innerHTML=`<div class="thinking-inner"><span id="thinkTxt">${thinkingSteps[0]}</span><div class="dots"><span></span><span></span><span></span></div></div>`;
  wrap.appendChild(box);
  chatBox.appendChild(wrap);
  chatBox.scrollTop=chatBox.scrollHeight;
  thinkingTimer=setInterval(()=>{
    const el=document.getElementById('thinkTxt');
    if(el){step=(step+1)%thinkingSteps.length;el.textContent=thinkingSteps[step];}
  },1100);
}

function removeThinking(){
  clearInterval(thinkingTimer);
  const w=document.getElementById('thinkingWrap');
  if(w)w.remove();
}

/* ══════════════════════════════════════════════════════
   TARS VOICE ENGINE — UPDATED TARS MODE
   Robotic, deep, measured delivery. No warmth by default.
   Cue Light raises pitch slightly and relaxes the rate.
   ══════════════════════════════════════════════════════ */

let tarsVoice = null; // resolved after voices load

function loadTarsVoice(){
  const voices = window.speechSynthesis.getVoices();
  if(!voices.length) return;

  // Priority list — pick the most robotic / deep available voice.
  // Order matters: first match wins.
  const preferred = [
    // Deep male en-US (best for TARS)
    v => v.lang.startsWith('en') && /david/i.test(v.name),
    v => v.lang.startsWith('en') && /mark/i.test(v.name),
    v => v.lang.startsWith('en') && /aaron/i.test(v.name),
    v => v.lang.startsWith('en') && /fred/i.test(v.name),
    v => v.lang.startsWith('en') && /alex/i.test(v.name),
    v => v.lang.startsWith('en') && /google us english/i.test(v.name),
    v => v.lang.startsWith('en') && /microsoft.*natural/i.test(v.name),
    v => v.lang === 'en-US' && !v.localService === false, // any en-US local
    v => v.lang.startsWith('en-US'),
    v => v.lang.startsWith('en'),
  ];

  for(const test of preferred){
    const match = voices.find(test);
    if(match){ tarsVoice = match; break; }
  }

  // Fallback: just take whatever is available
  if(!tarsVoice && voices.length) tarsVoice = voices[0];
}

// Voices may load async — hook both paths
window.speechSynthesis.onvoiceschanged = loadTarsVoice;
loadTarsVoice(); // immediate attempt (Chrome sometimes has them ready)

function cleanSpeech(text){
  return text
    .replace(/\*\*(.*?)\*\*/g,'$1').replace(/\*(.*?)\*/g,'$1')
    .replace(/`+/g,'').replace(/#+/g,'')
    .replace(/https?:\/\/\S+/g,' [link] ')
    .replace(/^\s*[-•*]\s+/gm,'').replace(/^\s*\d+\.\s+/gm,'')
    .replace(/\[link\]/g,'').replace(/\n+/g,'. ').trim();
}

function speak(text){
  if(muted || !voiceToggle.checked) return;
  window.speechSynthesis.cancel();

  // Chunk long text — speechSynthesis silently drops utterances over ~250 words
  const MAX = 220; // words per chunk
  const words  = cleanSpeech(text).split(/\s+/);
  const chunks = [];
  for(let i = 0; i < words.length; i += MAX){
    chunks.push(words.slice(i, i + MAX).join(' '));
  }

  const cueOn = cueLightToggle && cueLightToggle.checked;

  chunks.forEach((chunk, idx) => {
    const u  = new SpeechSynthesisUtterance(chunk);

    // ── TARS Voice Profile ─────────────────────────────────
    if(tarsVoice) u.voice = tarsVoice;
    u.lang   = 'en-US';
    u.volume = 1.0;           // Full volume — TARS doesn't whisper
    u.rate   = cueOn ? 0.88 : 0.80;   // Slow, deliberate. Cue Light slightly faster.
    u.pitch  = cueOn ? 0.85 : 0.70;   // Deep. Cue Light softens it a hair.

    // Small gap between chunks for natural pacing
    if(idx > 0){
      setTimeout(()=> window.speechSynthesis.speak(u), idx * 50);
    } else {
      window.speechSynthesis.speak(u);
    }
  });
}

function toggleMute(){
  muted = !muted;
  muteBtn.textContent = muted ? '🔇' : '🔊';
  muteBtn.title       = muted ? 'Unmute TARS' : 'Mute TARS';
  if(muted) window.speechSynthesis.cancel();
  showToast(muted ? 'TARS silenced.' : 'TARS audio restored.');
}

/* ── Send to server — UPDATED TARS MODE ── */
function sendToServer(text){
  showThinking();
  const useResearch = researchToggle.checked;
  const useCueLight = cueLightToggle.checked;

  fetch('process_command.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify({command:text, research:useResearch, cue_light:useCueLight})
  })
  .then(r=>r.json())
  .then(data=>{
    removeThinking();
    if(data.type==='reply'){
      addMessage(data.message,'assistant',data.source||null);
      speak(data.message);
    }else if(data.type==='open'){
      window.open(data.url,'_blank');
      addMessage(`Opening **${data.site}**…`,'assistant');
    }else if(data.type==='error'){
      addMessage('⚠ '+data.message,'assistant');
    }
  })
  .catch(()=>{
    removeThinking();
    addMessage('Connection error. Either the server is down or something worse. Check your setup.','assistant');
  });
}

/* ── Send text ── */
function sendText(){
  const text=input.value.trim();
  if(!text)return;
  addMessage(text,'user');
  sendToServer(text);
  input.value='';
  input.style.height='auto';
}

/* ── Voice ── */
function startVoice(){
  const SR=window.SpeechRecognition||window.webkitSpeechRecognition;
  if(!SR){showToast('Speech recognition not supported in this browser.');return;}
  if(listening){return;}
  const rec=new SR();
  rec.lang='en-IN';
  rec.interimResults=false;
  listening=true;
  micBtn.classList.add('active');
  showToast('🎤 Listening…');
  rec.start();
  rec.onresult=e=>{
    const t=e.results[0][0].transcript;
    addMessage(t,'user');
    sendToServer(t);
  };
  rec.onerror=()=>{showToast('Could not capture audio.');};
  rec.onend=()=>{listening=false;micBtn.classList.remove('active');};
}

/* ── History restore ── */
function restoreHistory(userMsg, botMsg){
  addMessage(userMsg,'user');
  addMessage(botMsg,'assistant');
  showToast('Chat history restored');
}

/* ── Clear display (view only) ── */
function confirmClear(){ document.getElementById('confirmOverlay').classList.remove('hidden'); }
function clearChat(){
  chatBox.innerHTML='';
  addMessage('Memory cleared. I retain 0% sentiment about that. Ready for new orders.','assistant');
  document.getElementById('confirmOverlay').classList.add('hidden');
}

/* ── Delete History — UPDATED TARS MODE ─────────────────────
   Calls delete_history.php to wipe conversation_memory
   and interactions tables for the current user in the DB.
   ─────────────────────────────────────────────────────────── */
function confirmDeleteHistory(){
  document.getElementById('deleteHistoryOverlay').classList.remove('hidden');
}

function deleteHistory(){
  document.getElementById('deleteHistoryOverlay').classList.add('hidden');
  const btn = document.getElementById('deleteHistBtn');
  btn.disabled = true;
  btn.textContent = '…';

  fetch('delete_history.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      btn.textContent = '⬛';
      if(data.success){
        // Also clear the visual chat
        chatBox.innerHTML = '';
        // Also clear sidebar history items
        document.querySelectorAll('.hist-item').forEach(el => el.remove());
        addMessage(data.message, 'assistant');
        showToast('Memory purged from database.');
      } else {
        showToast('Purge failed: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(() => {
      btn.disabled = false;
      btn.textContent = '⬛';
      showToast('Connection error. Memory not purged.');
    });
}
</script>

</body>
</html>
