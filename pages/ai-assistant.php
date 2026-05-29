<?php
require_once __DIR__ . '/../includes/layout.php';
$user = Auth::getCurrentUser();
if (!$user) { header('Location: /v1/pages/login.php'); exit; }
$lang = I18n::getCurrentLang();
$th   = $lang === 'th';
Layout::head($th ? 'ChemBot — ผู้ช่วยค้นหาสารเคมี AI' : 'ChemBot — Chemical Search AI');
?>
<style>
/* ═══ Chat layout ═══ */
.chembot-wrap{display:flex;flex-direction:column;height:calc(100vh - 108px);overflow:hidden;background:var(--card);border-radius:16px;border:1px solid var(--border);box-shadow:var(--shadow)}
.chembot-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border);background:linear-gradient(135deg,rgba(99,102,241,.06),rgba(124,58,237,.04));flex-shrink:0}
.chembot-avatar{width:40px;height:40px;background:linear-gradient(135deg,#6366f1,#7c3aed);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:16px;box-shadow:0 4px 12px rgba(99,102,241,.35)}
.chembot-status{display:flex;align-items:center;gap:5px;font-size:11px;color:#22c55e}
.chembot-status-dot{width:7px;height:7px;background:#22c55e;border-radius:50%;animation:pulse-dot 2s infinite}
@keyframes pulse-dot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(.85)}}
.chembot-messages{flex:1;overflow-y:auto;padding:18px;display:flex;flex-direction:column;gap:14px;scroll-behavior:smooth}
.chembot-messages::-webkit-scrollbar{width:4px}
.chembot-messages::-webkit-scrollbar-track{background:transparent}
.chembot-messages::-webkit-scrollbar-thumb{background:#e2e8f0;border-radius:4px}
/* ═══ Message bubbles ═══ */
.msg-row{display:flex;gap:10px;animation:msgIn .25s ease-out}
.msg-row.user-row{flex-direction:row-reverse}
@keyframes msgIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.msg-avatar{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;flex-shrink:0;margin-top:2px}
.msg-avatar.bot-av{background:linear-gradient(135deg,#6366f1,#7c3aed)}
.msg-avatar.user-av{background:linear-gradient(135deg,var(--accent),#c026d3)}
.msg-bubble{max-width:82%;border-radius:14px;padding:12px 16px;font-size:13px;line-height:1.6}
.msg-bubble.bot-bubble{background:#f8fafc;border:1px solid #e2e8f0;border-top-left-radius:2px;color:#1e293b}
.msg-bubble.user-bubble{background:linear-gradient(135deg,var(--accent),#7c3aed);color:#fff;border-top-right-radius:2px;box-shadow:0 3px 12px rgba(99,102,241,.3)}
.msg-bubble.html-bubble{padding:0;background:transparent;border:none;max-width:92%}
/* ═══ Typing indicator ═══ */
.typing-dot{width:8px;height:8px;background:#94a3b8;border-radius:50%;animation:blink 1.4s infinite both}
.typing-dot:nth-child(2){animation-delay:.2s}.typing-dot:nth-child(3){animation-delay:.4s}
@keyframes blink{0%,100%{opacity:.25;transform:scale(.85)}50%{opacity:1;transform:scale(1)}}
/* ═══ Quick chips ═══ */
.chips-bar{padding:10px 18px 4px;border-top:1px solid var(--border);background:rgba(248,250,252,.9);flex-shrink:0}
.chips-label{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.6px;margin-bottom:7px;display:flex;align-items:center;gap:5px}
.chips-scroll{display:flex;gap:7px;overflow-x:auto;padding-bottom:6px;scrollbar-width:none}
.chips-scroll::-webkit-scrollbar{display:none}
.chip{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:20px;font-size:11px;font-weight:600;border:1px solid var(--border);background:#fff;color:var(--c2);cursor:pointer;white-space:nowrap;transition:all .18s ease;user-select:none}
.chip:hover{background:var(--accent);color:#fff;border-color:var(--accent);transform:translateY(-1px);box-shadow:0 3px 10px rgba(99,102,241,.3)}
.chip i{font-size:10px;opacity:.75}
.chip.chip-danger:hover{background:#dc2626;border-color:#dc2626;box-shadow:0 3px 10px rgba(220,38,38,.3)}
.chip.chip-green:hover{background:#16a34a;border-color:#16a34a;box-shadow:0 3px 10px rgba(22,163,74,.3)}
.chip.chip-blue:hover{background:#0ea5e9;border-color:#0ea5e9;box-shadow:0 3px 10px rgba(14,165,233,.3)}
.chip.chip-orange:hover{background:#ea580c;border-color:#ea580c;box-shadow:0 3px 10px rgba(234,88,12,.3)}
/* ═══ Input bar ═══ */
.input-bar{display:flex;gap:10px;padding:12px 16px;border-top:1px solid var(--border);background:#fff;flex-shrink:0;align-items:center}
.input-bar input{flex:1;border:1px solid var(--border);border-radius:24px;padding:10px 16px;font-size:13px;outline:none;background:#f8fafc;transition:border-color .18s,box-shadow .18s}
.input-bar input:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(99,102,241,.12);background:#fff}
.send-btn{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--accent),#7c3aed);border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;transition:transform .18s,box-shadow .18s;box-shadow:0 3px 10px rgba(99,102,241,.35)}
.send-btn:hover{transform:scale(1.08);box-shadow:0 5px 18px rgba(99,102,241,.5)}
.send-btn:disabled{opacity:.45;cursor:not-allowed;transform:none}
.voice-btn{width:36px;height:36px;border-radius:50%;border:1px solid var(--border);background:#f8fafc;color:#94a3b8;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0;transition:all .18s}
.voice-btn:hover{border-color:var(--accent);color:var(--accent)}
/* ═══ Welcome card ═══ */
.welcome-card{background:linear-gradient(135deg,#f0f4ff,#faf5ff);border:1px solid #e0e7ff;border-radius:16px;padding:20px;max-width:94%}
.welcome-title{font-size:15px;font-weight:800;color:#4338ca;margin-bottom:4px}
.welcome-sub{font-size:12px;color:#6366f1;margin-bottom:14px}
.capabilities-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.cap-item{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#fff;border:1px solid #e0e7ff;border-radius:10px;cursor:pointer;transition:all .18s}
.cap-item:hover{background:#eef2ff;border-color:#a5b4fc;transform:translateX(2px)}
.cap-icon{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0}
.cap-text{font-size:11px;font-weight:600;color:#4338ca;line-height:1.3}
.cap-text small{display:block;font-weight:400;color:#6b7280;font-size:10px;margin-top:1px}
/* ═══ Stats bar ═══ */
.stat-mini-bar{display:flex;gap:10px;margin-top:12px;flex-wrap:wrap}
.stat-mini{display:flex;align-items:center;gap:5px;padding:5px 10px;background:#fff;border:1px solid #e0e7ff;border-radius:20px;font-size:11px;font-weight:700;color:#4338ca}
.stat-mini i{color:#6366f1;font-size:10px}
/* ═══ Section toggle init ═══ */
.section-toggle{cursor:pointer}
/* ═══ 3D Model Cards (GLB inline model-viewer) ═══ */
.local3d-wrapper{display:flex;flex-direction:column;gap:10px;margin-bottom:6px}
.glb-list{display:flex;flex-direction:column;gap:10px}
.glb-card{border-radius:14px;overflow:hidden;border:1.5px solid #c7d2fe;background:#0f172a;box-shadow:0 4px 20px rgba(0,0,0,.12)}
.glb-mv-slot{width:100%;height:280px;background:linear-gradient(135deg,#0f172a,#1e1b4b);display:flex;align-items:center;justify-content:center;position:relative}
.glb-mv-slot model-viewer{display:block;width:100%;height:280px;background:transparent;--poster-color:#0f172a}
.glb-mv-placeholder{display:flex;flex-direction:column;align-items:center;gap:10px;color:rgba(255,255,255,.3);pointer-events:none}
.glb-mv-placeholder svg{opacity:.25}
.glb-mv-placeholder span{font-size:10px;letter-spacing:.8px;text-transform:uppercase;opacity:.5}
.glb-mv-loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a,#1e1b4b);flex-direction:column;gap:10px}
.glb-mv-spinner{width:36px;height:36px;border:3px solid rgba(99,102,241,.2);border-top-color:#6366f1;border-radius:50%;animation:mvSpin .8s linear infinite}
@keyframes mvSpin{to{transform:rotate(360deg)}}
.glb-mv-loading-txt{font-size:10px;color:rgba(165,180,252,.6);letter-spacing:.5px}
.glb-footer{display:flex;align-items:center;gap:8px;padding:8px 12px;background:linear-gradient(135deg,#1e1b4b,#0f172a);border-top:1px solid rgba(255,255,255,.06)}
.glb-footer-label{font-size:12px;font-weight:700;color:rgba(255,255,255,.8);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.glb-badge{padding:2px 9px;border-radius:8px;font-size:9px;font-weight:800;letter-spacing:.4px;background:rgba(16,185,129,.18);color:#6ee7b7;border:1px solid rgba(16,185,129,.3);flex-shrink:0}
.glb-ar-btn{display:flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:10px;font-weight:700;background:rgba(99,102,241,.25);color:#a5b4fc;border:1px solid rgba(99,102,241,.3);text-decoration:none;transition:background .15s;flex-shrink:0}
.glb-ar-btn:hover{background:rgba(99,102,241,.45);color:#c7d2fe}
/* ═══ Embed 3D (Kiri / Sketchfab) ═══ */
.embed3d-container{border-radius:14px;overflow:hidden;border:1.5px solid #e0e7ff;margin-top:2px;box-shadow:0 4px 20px rgba(0,0,0,.1)}
.embed3d-header{padding:9px 14px;font-size:11px;font-weight:700;color:#fff;display:flex;align-items:center;gap:7px}
.embed3d-provider{font-weight:800;letter-spacing:.2px}
.embed3d-model-name{opacity:.8;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.embed3d-fullscreen-btn{margin-left:auto;width:26px;height:26px;border-radius:8px;background:rgba(255,255,255,.15);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;text-decoration:none;transition:background .18s;flex-shrink:0}
.embed3d-fullscreen-btn:hover{background:rgba(255,255,255,.3)}
.embed3d-frame-wrap{background:#0f172a}
/* ═══ model-viewer overlay ═══ */
#mv-overlay{display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.85);align-items:center;justify-content:center;padding:16px}
#mv-overlay.open{display:flex}
#mv-overlay-inner{width:100%;max-width:600px;border-radius:18px;overflow:hidden;background:#0f172a;box-shadow:0 20px 60px rgba(0,0,0,.6);position:relative}
#mv-overlay-header{display:flex;align-items:center;gap:10px;padding:12px 16px;background:linear-gradient(135deg,#1e1b4b,#312e81)}
#mv-overlay-title{font-size:13px;font-weight:700;color:#e0e7ff;flex:1}
#mv-overlay-badge{padding:2px 9px;border-radius:8px;font-size:9px;font-weight:800;background:rgba(16,185,129,.2);color:#6ee7b7;border:1px solid rgba(16,185,129,.25)}
#mv-overlay-close{width:30px;height:30px;border-radius:10px;background:rgba(255,255,255,.1);border:none;color:#e0e7ff;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
#mv-overlay-close:hover{background:rgba(255,255,255,.25)}
#mv-overlay-viewer{display:block;width:100%;height:420px;background:transparent}
/* ═══ Fallback 3D ═══ */
.fallback3d-section{margin-bottom:10px}
.fallback3d-title{font-size:11px;font-weight:700;color:#6366f1;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:flex;align-items:center;gap:5px}
.fallback3d-note{font-size:10px;color:#94a3b8;margin-bottom:8px;font-style:italic}
@keyframes mvLoad{0%{background-position:100% 0}100%{background-position:-100% 0}}
/* ═══ External links ═══ */
.ext-links-section{}
.ext-links-title{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
/* ═══ Responsive ═══ */
@media(max-width:768px){
  .chembot-wrap{height:calc(100vh - 60px - 48px - 20px)!important;border-radius:10px}
  .msg-bubble{max-width:90%}
  .msg-bubble.html-bubble{max-width:97%}
  .capabilities-grid{grid-template-columns:1fr}
  .glb-mv-slot,.glb-mv-slot model-viewer{height:220px!important}
}

/* ═══ ChemBot Response Styles (Pro) ═══ */
.chembot-response { font-family: 'Inter', sans-serif; color: #1e293b; animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

.chem-hero { display: flex; gap: 16px; align-items: flex-start; margin-bottom: 12px; background: #fff; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.chem-hero-img { width: 80px; height: 80px; flex-shrink: 0; background: #f8fafc; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid #f1f5f9; padding: 4px; }
.chem-hero-img img { max-width: 100%; max-height: 100%; object-fit: contain; mix-blend-mode: multiply; }
.chem-hero-info { flex: 1; min-width: 0; }
.chem-hero-name { font-size: 16px; font-weight: 700; color: #0f172a; margin-bottom: 8px; line-height: 1.3; }
.chem-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.pill { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; letter-spacing: 0.3px; text-transform: uppercase; }
.pill i { font-size: 9px; opacity: 0.7; }
.pill.cas { background: #eff6ff; color: #1d4ed8; border-color: #dbeafe; }
.pill.formula { background: #f5f3ff; color: #7c3aed; border-color: #ede9fe; }
.pill.cat { background: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.pill.danger { background: #fef2f2; color: #dc2626; border-color: #fee2e2; }
.pill.warning { background: #fffbeb; color: #d97706; border-color: #fef3c7; }
.chem-hero-desc { font-size: 12px; color: #64748b; line-height: 1.5; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.phys-table { width: 100%; font-size: 12px; border-collapse: separate; border-spacing: 0; margin-top: 8px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.phys-table td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; background: #fff; }
.phys-table tr:last-child td { border-bottom: none; }
.phys-table td:first-child { color: #64748b; width: 40%; font-weight: 500; background: #f8fafc; border-right: 1px solid #f1f5f9; }
.phys-table td:last-child { color: #334155; font-weight: 600; }

.section-toggle { background: #fff; padding: 10px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; color: #334155; cursor: pointer; display: flex; align-items: center; justify-content: space-between; margin-top: 8px; border: 1px solid #e2e8f0; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
.section-toggle:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-1px); }
.section-toggle i:first-child { width: 20px; text-align: center; color: #6366f1; margin-right: 8px; }
.section-body { padding: 10px; display: block; border: 1px solid #e2e8f0; border-top: none; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px; background: #fff; margin-top: -4px; position: relative; z-index: 0; padding-top: 14px; }
.section-body.collapsed { display: none; }

.no-stock { padding: 12px; background: #fff1f2; color: #be123c; font-size: 12px; font-weight: 600; border-radius: 10px; border: 1px solid #fecdd3; margin-top: 8px; display: flex; align-items: center; gap: 10px; }
.no-stock i { font-size: 16px; opacity: 0.8; }
</style>
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.5.0/model-viewer.min.js"></script>
<body>
<?php Layout::sidebar('ai-assistant'); Layout::beginContent(); ?>

<div class="chembot-wrap">

  <!-- ── Header ─────────────────────────────────────────────── -->
  <div class="chembot-header">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="chembot-avatar"><i class="fas fa-atom"></i></div>
      <div>
        <div style="font-weight:800;font-size:14px;color:var(--c1)">ChemBot <span style="font-size:11px;font-weight:600;color:#6366f1">AI</span></div>
        <div class="chembot-status"><span class="chembot-status-dot"></span> <?php echo $th?'พร้อมให้บริการ':'Online &amp; Ready'; ?></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:8px">
      <div id="hdrStats" style="display:flex;gap:8px"></div>
      <button onclick="clearChat()" title="<?php echo $th?'ล้างการสนทนา':'Clear chat'; ?>" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:transparent;cursor:pointer;color:#94a3b8;display:flex;align-items:center;justify-content:center;font-size:12px;transition:all .18s" onmouseover="this.style.background='#fee2e2';this.style.color='#dc2626'" onmouseout="this.style.background='transparent';this.style.color='#94a3b8'"><i class="fas fa-trash-alt"></i></button>
    </div>
  </div>

  <!-- ── Messages ───────────────────────────────────────────── -->
  <div id="chatMessages" class="chembot-messages">
    <!-- Welcome message injected by JS -->
  </div>

  <!-- ── Quick Chips ────────────────────────────────────────── -->
  <div class="chips-bar">
    <div class="chips-label"><i class="fas fa-bolt"></i> <?php echo $th?'ลองค้นหา':'Quick Search'; ?></div>
    <div class="chips-scroll" id="chipsScroll">
<?php
$chips = $th ? [
  ['fas fa-hashtag',       '67-56-1',                   'chip-blue',   'CAS Number'],
  ['fas fa-flask',         'Ethanol',                   '',            'ชื่อสาร'],
  ['fas fa-atom',          'H2O',                       'chip-blue',   'สูตรเคมี'],
  ['fas fa-map-marker-alt','HCl อยู่ที่ไหน',            'chip-green',  'ตำแหน่ง'],
  ['fas fa-file-shield',   'SDS Acetone',               '',            'SDS'],
  ['fas fa-exclamation-triangle','อันตราย Methanol',    'chip-danger', 'อันตราย GHS'],
  ['fas fa-calendar-times','สารใกล้หมดอายุ',            'chip-orange', 'หมดอายุ'],
  ['fas fa-boxes-stacked', 'สารใกล้หมดสต็อก',          'chip-orange', 'สต็อก'],
  ['fas fa-list',          'สารทั้งหมดในคลัง',         'chip-green',  'คลังสินค้า'],
] : [
  ['fas fa-hashtag',       '67-56-1',                   'chip-blue',   'CAS'],
  ['fas fa-flask',         'Ethanol',                   '',            'Name'],
  ['fas fa-atom',          'H2SO4',                     'chip-blue',   'Formula'],
  ['fas fa-map-marker-alt','Where is HCl',              'chip-green',  'Location'],
  ['fas fa-file-shield',   'SDS Acetone',               '',            'SDS'],
  ['fas fa-exclamation-triangle','hazard Methanol',     'chip-danger', 'GHS Hazard'],
  ['fas fa-calendar-times','expiring chemicals',        'chip-orange', 'Expiry'],
  ['fas fa-boxes-stacked', 'low stock chemicals',       'chip-orange', 'Low Stock'],
];
foreach ($chips as [$icon, $query, $cls, $label]):
?>
      <button class="chip <?php echo $cls; ?>" onclick="sendMsg('<?php echo htmlspecialchars($query, ENT_QUOTES); ?>')">
        <i class="fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($label); ?>
      </button>
<?php endforeach; ?>
    </div>
  </div>

  <!-- ── Input bar ──────────────────────────────────────────── -->
  <div class="input-bar">
    <input type="text" id="msgInput"
      placeholder="<?php echo $th?'พิมพ์ชื่อสาร, CAS, สูตร หรือถามอะไรก็ได้…':'Type chemical name, CAS, formula, or ask anything…'; ?>"
      onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}">
    <button class="voice-btn" id="voiceBtn" title="<?php echo $th?'ค้นหาด้วยเสียง':'Voice search'; ?>" onclick="startVoice()"><i class="fas fa-microphone"></i></button>
    <button class="send-btn" id="sendBtn" onclick="sendMsg()" title="<?php echo $th?'ส่ง':'Send'; ?>"><i class="fas fa-paper-plane"></i></button>
  </div>

</div>

<?php Layout::endContent(); ?>
<script>
const TH = <?php echo $th?'true':'false'; ?>;
let session = null, typing = false;

// ── Stats in header ──────────────────────────────────────────────
async function loadStats(){
  try{
    const d = await apiFetch('/v1/api/ai_assistant.php?action=suggest',{method:'GET'}).catch(()=>null);
    const s = await apiFetch('/v1/api/ai_assistant.php',{method:'POST',body:JSON.stringify({action:'get_stats'})}).catch(()=>null);
    if(s?.success && s.data){
      const bar = document.getElementById('hdrStats');
      bar.innerHTML = `
        <div style="display:flex;align-items:center;gap:4px;padding:4px 9px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:20px;font-size:10px;font-weight:700;color:#16a34a">
          <i class="fas fa-flask" style="font-size:9px"></i> ${s.data.chemicals.toLocaleString()}
        </div>
        <div style="display:flex;align-items:center;gap:4px;padding:4px 9px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:20px;font-size:10px;font-weight:700;color:#1d4ed8">
          <i class="fas fa-boxes-stacked" style="font-size:9px"></i> ${s.data.containers.toLocaleString()}
        </div>`;
    }
  }catch(e){}
}

// ── Welcome screen ───────────────────────────────────────────────
function renderWelcome(){
  const caps = TH ? [
    {icon:'fas fa-hashtag',bg:'#eff6ff',color:'#1d4ed8',q:'67-56-1',      title:'ค้นหา CAS Number',    sub:'เช่น 67-56-1, 64-17-5'},
    {icon:'fas fa-flask',  bg:'#f0fdf4',color:'#16a34a',q:'Ethanol',      title:'ค้นหาชื่อสาร',        sub:'ทั้งภาษาไทยและอังกฤษ'},
    {icon:'fas fa-atom',   bg:'#faf5ff',color:'#7c3aed',q:'H2SO4',        title:'ค้นหาสูตรเคมี',       sub:'เช่น H2O, NaOH, C2H5OH'},
    {icon:'fas fa-map-marker-alt',bg:'#f0fdf4',color:'#15803d',q:'HCl อยู่ที่ไหน',title:'ตำแหน่งจัดเก็บ',sub:'อาคาร/ห้อง/ตู้/ชั้น'},
    {icon:'fas fa-file-shield',bg:'#fff7ed',color:'#c2410c',q:'SDS Acetone',title:'ข้อมูล SDS',         sub:'Safety Data Sheet'},
    {icon:'fas fa-exclamation-triangle',bg:'#fef2f2',color:'#dc2626',q:'อันตราย Methanol',title:'GHS & อันตราย',sub:'Pictogram, H/P-Statements'},
    {icon:'fas fa-calendar-times',bg:'#fffbeb',color:'#d97706',q:'สารใกล้หมดอายุ',title:'สารใกล้หมดอายุ',sub:'แจ้งเตือนล่วงหน้า 90 วัน'},
    {icon:'fas fa-boxes-stacked',bg:'#f0fdf4',color:'#16a34a',q:'สารทั้งหมดในคลัง',title:'สถิติคลังสินค้า',sub:'จำนวน/ปริมาณ/ที่เก็บ'},
  ] : [
    {icon:'fas fa-hashtag',bg:'#eff6ff',color:'#1d4ed8',q:'67-56-1',      title:'Search by CAS',       sub:'e.g. 67-56-1, 64-17-5'},
    {icon:'fas fa-flask',  bg:'#f0fdf4',color:'#16a34a',q:'Ethanol',      title:'Search by Name',      sub:'English or Thai names'},
    {icon:'fas fa-atom',   bg:'#faf5ff',color:'#7c3aed',q:'H2SO4',        title:'Search by Formula',   sub:'e.g. H2O, NaOH, H2SO4'},
    {icon:'fas fa-map-marker-alt',bg:'#f0fdf4',color:'#15803d',q:'where is HCl',title:'Find Location',sub:'Building/Room/Cabinet'},
    {icon:'fas fa-file-shield',bg:'#fff7ed',color:'#c2410c',q:'SDS Acetone',title:'SDS / Safety Sheet',sub:'Download or view online'},
    {icon:'fas fa-exclamation-triangle',bg:'#fef2f2',color:'#dc2626',q:'hazard Methanol',title:'GHS & Hazards',sub:'Pictograms, H/P-Statements'},
    {icon:'fas fa-calendar-times',bg:'#fffbeb',color:'#d97706',q:'expiring chemicals',title:'Near-Expiry Alert',sub:'90-day advance notice'},
    {icon:'fas fa-boxes-stacked',bg:'#f0fdf4',color:'#16a34a',q:'low stock',title:'Stock Summary',sub:'Quantities & inventory'},
  ];
  let grid = caps.map(c=>`
    <div class="cap-item" onclick="sendMsg('${c.q.replace(/'/g,"\\'") }')">
      <div class="cap-icon" style="background:${c.bg};color:${c.color}"><i class="${c.icon}"></i></div>
      <div class="cap-text">${c.title}<small>${c.sub}</small></div>
    </div>`).join('');
  const html = `<div class="welcome-card">
    <div class="welcome-title">👋 ${ TH?'สวัสดี! ผม ChemBot ผู้ช่วย AI ของคุณ':'Hello! I\'m ChemBot, your AI assistant' }</div>
    <div class="welcome-sub">${ TH?'ถามหรือค้นหาอะไรก็ได้เกี่ยวกับสารเคมีในคลัง':'Ask or search anything about chemicals in your inventory' }</div>
    <div class="capabilities-grid">${grid}</div>
  </div>`;
  appendBotHTML(html);
}

// ── Send message ─────────────────────────────────────────────────
async function sendMsg(forceText) {
  const inp = document.getElementById('msgInput');
  const msg = (forceText !== undefined ? forceText : inp.value).trim();
  if (!msg || typing) return;
  if (!forceText) inp.value = '';
  else inp.value = '';
  appendUserMsg(msg);
  showTyping();
  document.getElementById('sendBtn').disabled = true;
  try {
    const d = await apiFetch('/v1/api/ai_assistant.php', {
      method: 'POST',
      body: JSON.stringify({action:'chat', message: msg, session_id: session})
    });
    hideTyping();
    document.getElementById('sendBtn').disabled = false;
    if (d.success) {
      session = d.data.session_id;
      if (d.data.html) appendBotHTML(d.data.html);
      else appendBotText(d.data.response || (TH?'ไม่พบข้อมูล':'No data found'));
      attachToggleHandlers();
    } else {
      appendBotText(TH?'ขออภัย เกิดข้อผิดพลาด':'Sorry, an error occurred.');
    }
  } catch(e) {
    hideTyping();
    document.getElementById('sendBtn').disabled = false;
    appendBotText(TH?'ไม่สามารถเชื่อมต่อได้ กรุณาลองใหม่':'Connection error. Please try again.');
  }
}

// ── model-viewer inject (CDN loaded statically in <head>) ─────────
function attachModelViewers(container){
  const slots = (container||document).querySelectorAll('.glb-mv-slot:not([data-mv-attached])');
  if(!slots.length) return;
  customElements.whenDefined('model-viewer').then(()=>{
    slots.forEach(slot=>{
      if(slot.hasAttribute('data-mv-attached')) return;
      slot.setAttribute('data-mv-attached','1');
      const src = slot.dataset.src;
      const usdz = slot.dataset.usdz || '';
      if(!src) return;
      slot.innerHTML = '';
      const mv = document.createElement('model-viewer');
      mv.setAttribute('src', src);
      mv.setAttribute('camera-controls','');
      mv.setAttribute('auto-rotate','');
      mv.setAttribute('auto-rotate-delay','2000');
      mv.setAttribute('shadow-intensity','1');
      mv.setAttribute('exposure','1');
      mv.setAttribute('loading','eager');
      mv.setAttribute('reveal','auto');
      mv.style.cssText = 'display:block;width:100%;height:280px;background:transparent;--poster-color:#0f172a';
      if(usdz){
        mv.setAttribute('ar','');
        mv.setAttribute('ar-modes','webxr scene-viewer quick-look');
        mv.setAttribute('ios-src', usdz);
      }
      slot.appendChild(mv);
    });
  });
}
function openGLBOverlay(url, label){
  document.getElementById('mv-overlay-title').textContent = label || '3D Model';
  const slot = document.getElementById('mv-viewer-slot');
  slot.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:460px"><div class="glb-mv-spinner"></div></div>';
  customElements.whenDefined('model-viewer').then(()=>{
    slot.innerHTML = '';
    const mv = document.createElement('model-viewer');
    mv.setAttribute('src', url);
    mv.setAttribute('camera-controls','');
    mv.setAttribute('auto-rotate','');
    mv.setAttribute('shadow-intensity','1.2');
    mv.setAttribute('exposure','0.95');
    mv.setAttribute('loading','eager');
    mv.setAttribute('reveal','auto');
    mv.style.cssText = 'display:block;width:100%;height:460px;background:transparent';
    slot.appendChild(mv);
  });
  document.getElementById('mv-overlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeMVOverlay(){
  document.getElementById('mv-overlay').classList.remove('open');
  document.getElementById('mv-viewer-slot').innerHTML = '';
  document.body.style.overflow = '';
}
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeMVOverlay(); });

// ── Append helpers ───────────────────────────────────────────────
function appendUserMsg(text){
  const c = document.getElementById('chatMessages');
  const row = document.createElement('div');
  row.className = 'msg-row user-row';
  row.innerHTML = `
    <div class="msg-avatar user-av"><i class="fas fa-user"></i></div>
    <div class="msg-bubble user-bubble">${esc(text)}</div>`;
  c.appendChild(row);
  c.scrollTop = c.scrollHeight;
}
function appendBotText(text){
  const c = document.getElementById('chatMessages');
  const row = document.createElement('div');
  row.className = 'msg-row';
  row.innerHTML = `
    <div class="msg-avatar bot-av"><i class="fas fa-atom"></i></div>
    <div class="msg-bubble bot-bubble" style="white-space:pre-line">${esc(text)}</div>`;
  c.appendChild(row);
  c.scrollTop = c.scrollHeight;
}
function appendBotHTML(html){
  const c = document.getElementById('chatMessages');
  const row = document.createElement('div');
  row.className = 'msg-row';
  row.innerHTML = `
    <div class="msg-avatar bot-av" style="margin-top:6px"><i class="fas fa-atom"></i></div>
    <div class="msg-bubble html-bubble">${html}</div>`;
  c.appendChild(row);
  c.scrollTop = c.scrollHeight;
  // Inject model-viewer into any GLB slots in this message
  attachModelViewers(row);
}
function showTyping(){
  typing = true;
  const c = document.getElementById('chatMessages');
  const d = document.createElement('div');
  d.id = 'typingEl'; d.className = 'msg-row';
  d.innerHTML = `
    <div class="msg-avatar bot-av"><i class="fas fa-atom"></i></div>
    <div class="msg-bubble bot-bubble" style="padding:12px 16px">
      <div style="display:flex;gap:5px;align-items:center">
        <span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>
      </div>
    </div>`;
  c.appendChild(d); c.scrollTop = c.scrollHeight;
}
function hideTyping(){ typing = false; document.getElementById('typingEl')?.remove(); }
function clearChat(){
  if(!confirm(TH?'ล้างการสนทนาทั้งหมด?':'Clear all conversation?')) return;
  document.getElementById('chatMessages').innerHTML = '';
  session = null;
  renderWelcome();
}
function esc(t){ const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

// ── Section toggles ──────────────────────────────────────────────
function attachToggleHandlers(){
  document.querySelectorAll('.section-toggle:not([data-bound])').forEach(el=>{
    el.setAttribute('data-bound','1');
    el.addEventListener('click',()=>{
      const body = el.nextElementSibling;
      if(!body) return;
      const open = !body.classList.contains('collapsed');
      body.classList.toggle('collapsed', open);
      el.classList.toggle('collapsed', open);
      const icon = el.querySelector('.toggle-icon');
      if(icon) icon.style.transform = open?'rotate(-90deg)':'';
    });
  });
  // Also handle loc-building expand
  document.querySelectorAll('.loc-building-hdr:not([data-bound])').forEach(el=>{
    el.setAttribute('data-bound','1');
    el.addEventListener('click',()=>{
      const rooms = el.nextElementSibling;
      if(rooms){
        const hidden = rooms.style.display==='none';
        rooms.style.display = hidden?'block':'none';
        el.classList.toggle('open', hidden);
      }
    });
  });
}

// ── Voice search ─────────────────────────────────────────────────
function startVoice(){
  if(!('webkitSpeechRecognition' in window||'SpeechRecognition' in window)){
    alert(TH?'เบราว์เซอร์ไม่รองรับการค้นหาด้วยเสียง':'Voice search not supported in this browser');
    return;
  }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  const rec = new SR();
  rec.lang = TH?'th-TH':'en-US';
  rec.onstart = ()=>{ document.getElementById('voiceBtn').style.color='#dc2626'; };
  rec.onend   = ()=>{ document.getElementById('voiceBtn').style.color=''; };
  rec.onresult = e=>{ const t=e.results[0][0].transcript; document.getElementById('msgInput').value=t; sendMsg(); };
  rec.start();
}

// ── Init ─────────────────────────────────────────────────────────
renderWelcome();
loadStats();
document.getElementById('msgInput').focus();
</script>

<!-- model-viewer overlay -->
<div id="mv-overlay" onclick="if(event.target===this)closeMVOverlay()">
  <div id="mv-overlay-inner">
    <div id="mv-overlay-header">
      <span id="mv-overlay-title">3D Model</span>
      <span id="mv-overlay-badge">GLB</span>
      <button id="mv-overlay-close" onclick="closeMVOverlay()" title="ปิด">&#x2715;</button>
    </div>
    <div id="mv-viewer-slot"></div>
  </div>
</div>

</body></html>
