<?php
declare(strict_types=1);

session_start();

/* =========================================================
   ADMIN CONFIG
   Leave ADMIN_EMAILS empty to let any signed-in user in
   (demo mode). Add emails to lock it down, e.g.:
       const ADMIN_EMAILS = ['admin@example.org', 'you@org.com'];
   ========================================================= */
const ADMIN_EMAILS = [];

/* =========================================================
   AUTH GUARD
   ========================================================= */
if (empty($_SESSION['user'])) {
    header('Location: ../index.php', true, 303);
    exit;
}

$user   = $_SESSION['user'];
$name   = (string)($user['name']  ?? 'User');
$email  = (string)($user['email'] ?? '');
$userId = (int)($user['id'] ?? 0);

/* =========================================================
   ADMIN ROLE CHECK
   ========================================================= */
if (count(ADMIN_EMAILS) > 0 && !in_array($email, ADMIN_EMAILS, true)) {
    header('Location: ../user/index.php', true, 303);
    exit;
}

/* Avatar initials */
$initials = 'U';
$parts    = preg_split('/\s+/', trim($name));
if (!empty($parts[0])) {
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (!empty($parts[1])) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    }
}

/* Time-of-day greeting */
$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* Escape helper (same signature as the login page) */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Admin Dashboard</title>
<link rel="icon" type="image/png" href="../assets/feedback_favicon.png">
<link rel="apple-touch-icon" href="../assets/feedback_favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
  --blue:#2563eb;--blue2:#1d4ed8;--navy:#12213a;
  --ink:#172033;--tx:#172033;
  --mu:#718096;--muted:#718096;
  --line:#e5eaf2;--bd:#e5eaf2;
  --bg:#ffffff;--bg2:#f5f7fb;
  --pl:#f0f3f7;
  --ok:#15966a;--wa:#c27a12;--ba:#d14343;
  --pri:#2563eb;
  --shadow:0 8px 24px rgba(20,42,80,.055)
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg2);color:var(--tx);font:400 14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
.top{background:var(--bg);border-bottom:1px solid var(--line);padding:14px 28px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;position:sticky;top:0;z-index:6}
.logo{display:flex;align-items:center;gap:10px;margin-right:auto}
.brand-mark{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:grid;place-items:center;color:#fff;font-weight:800;font-size:17px;box-shadow:0 5px 12px #2563eb30;flex-shrink:0}
.logo-text b{display:block;font-size:14px;font-weight:700;letter-spacing:-.2px;color:var(--ink)}
.logo-text small{display:block;font-weight:400;color:var(--mu);font-size:11px}
nav{display:flex;gap:4px;flex-wrap:wrap}
nav button,.role button{all:unset;cursor:pointer;padding:8px 14px;border-radius:8px;color:#65738a;font-weight:600;font-size:13px}
nav button:hover{background:#f7f9fc}
nav button.on{color:var(--blue);background:#edf4ff}
.role{display:flex;border:1px solid var(--line);border-radius:10px;padding:3px}.role button.on{background:var(--blue);color:#fff}
main{max-width:1180px;margin:0 auto;padding:26px 28px 60px}
h1{font-size:22px;font-weight:700;margin:0 0 2px;letter-spacing:-.4px}h2{font-size:15px;font-weight:700;margin:0 0 12px}.sub{color:var(--mu);font-size:13px;margin-bottom:20px}
.g{display:grid;gap:16px}.k{grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:16px}.c2{grid-template-columns:repeat(auto-fit,minmax(330px,1fr));margin-bottom:16px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;box-shadow:var(--shadow)}
.kp .l{font-size:12px;color:var(--mu)}.kp .v{font-size:26px;font-weight:700;letter-spacing:-.5px;margin-top:2px}
label{display:block;font-size:12px;color:var(--mu);margin:0 0 4px;font-weight:600}
input,select,textarea{width:100%;background:#fff;color:var(--tx);border:1px solid var(--line);border-radius:9px;padding:9px 11px;font:inherit;outline:none}
textarea{min-height:110px;resize:vertical}
input:focus,select:focus,textarea:focus{border-color:#a9c4f7;box-shadow:0 0 0 3px #2563eb14}
button:focus-visible{outline:2px solid var(--blue);outline-offset:1px}
.f{display:grid;grid-template-columns:1fr 1fr;gap:14px}.f .w{grid-column:1/-1}@media(max-width:640px){.f{grid-template-columns:1fr}main{padding:18px 14px}.top{padding:12px 14px}}
.btn{background:var(--blue);color:#fff;border:1px solid var(--blue);border-radius:9px;padding:10px 16px;font:inherit;font-weight:600;font-size:13px;cursor:pointer;width:auto;box-shadow:0 4px 10px #2563eb20}
.btn:hover{background:var(--blue2);border-color:var(--blue2)}
.btn.s{background:#fff;color:#475569;border:1px solid var(--line);padding:6px 12px;font-size:12px;box-shadow:none;font-weight:600}
.btn.s:hover{background:#f7f9fc}
.tg{display:inline-flex;border:1px solid var(--line);border-radius:9px;overflow:hidden}.tg label{margin:0;padding:9px 18px;cursor:pointer;color:#475569;font-size:13px;font-weight:600}.tg input{display:none}.tg label:has(input:checked){background:#edf4ff;color:var(--blue)}
.tw{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:13px}th{text-align:left;color:#8a96a8;font-weight:600;font-size:10px;letter-spacing:.6px;text-transform:uppercase;padding:11px 12px;border-bottom:1px solid #edf0f5;background:#fbfcfe;white-space:nowrap}td{padding:11px 12px;border-bottom:1px solid #f0f2f6;vertical-align:top}tr:last-child td{border:0}tbody tr:hover{background:#fafcff}
.t{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;background:#f0f3f7;color:#64748b}.t.ok{background:#e6f8f0;color:#14865d}.t.wa{background:#fff5df;color:#b7791f}.t.ba{background:#fff0f0;color:#c83232}.t.bl{background:#edf4ff;color:#2563eb}
.b{display:flex;align-items:center;gap:10px;margin:9px 0;font-size:13px}.b .n{width:120px;color:var(--mu)}.b .tr{flex:1;height:8px;background:#edf1f7;border-radius:20px;overflow:hidden}.b .fl{height:100%;background:linear-gradient(90deg,#60a5fa,#2563eb);border-radius:20px}.b .v{width:36px;text-align:right;font-weight:600}
svg text{fill:var(--mu);font-size:11px;font-family:inherit}
.v0{display:none}.v0.on{display:block}.msg{padding:12px 14px;border-radius:9px;background:#f5f8ff;border:1px solid #e2ebff;border-left:3px solid var(--blue);margin-top:14px;font-weight:500}
.tx{max-width:300px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.em{text-align:center;color:var(--mu);padding:22px}
.row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.row>*{width:auto;min-width:130px}
dialog{border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--tx);width:min(560px,94vw);padding:22px;box-shadow:0 24px 60px #10204033}dialog::backdrop{background:#0f1e3526}

#adm{display:none;min-height:100vh}#adm aside{width:220px;background:#fff;border-right:1px solid var(--line);padding:22px 14px;display:flex;flex-direction:column;gap:4px;position:sticky;top:0;height:100vh;flex-shrink:0}
#adm aside .logo{padding:0 10px 18px;margin:0}#adm aside nav{flex-direction:column}#adm aside nav button{display:block;border-radius:8px}#adm aside nav button.on{background:#edf4ff;color:var(--blue)}
#adm .main{flex:1;min-width:0}.lk{all:unset;cursor:pointer;color:var(--mu);font-size:13px;padding:8px 10px;border-radius:8px;font-weight:600}.lk:hover{background:#f7f9fc}
#adm-go{display:inline-block;border:1px solid var(--line)}#deny{display:none;max-width:420px;margin:90px auto;text-align:center}
@media(max-width:820px){#adm{flex-direction:column}#adm aside{width:auto;height:auto;position:static;flex-direction:row;flex-wrap:wrap;border-right:0;border-bottom:1px solid var(--line)}#adm aside nav{flex-direction:row}}
.av{width:64px;height:64px;border-radius:50%;background:#dbeafe;border:1px solid #dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;flex-shrink:0}.ph{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.ph .btn{margin-left:auto}.rc{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #f0f2f6}.rc:last-child{border:0}.rc small{color:var(--mu)}

/* ---- admin additions ---- */
.toolbar{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;align-items:end}
.toolbar input,.toolbar select{width:auto;min-width:120px}
.bulkbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin-bottom:12px;font-size:13px}
.bulkbar select,.bulkbar input{width:auto;min-width:130px}
.pg{display:flex;gap:6px;flex-wrap:wrap;justify-content:center;margin-top:12px;align-items:center}
.pg button{padding:6px 10px;border:1px solid var(--line);background:#fff;border-radius:8px;cursor:pointer;color:#475569;font-weight:600;font-size:12px}
.pg button.on{background:var(--blue);color:#fff;border-color:var(--blue)}
.pg button:disabled{opacity:.45;cursor:not-allowed}
.chk{width:16px;height:16px;accent-color:var(--blue)}
.tl{list-style:none;padding:0;margin:8px 0 0;font-size:12px}
.tl li{border-left:2px solid var(--line);padding:0 0 10px 12px;position:relative;color:var(--mu)}
.tl li:before{content:"";position:absolute;left:-5px;top:2px;width:8px;height:8px;border-radius:50%;background:var(--blue)}
.tl b{color:var(--tx)}
.toast{position:fixed;bottom:20px;right:20px;background:#14233b;color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;z-index:99;box-shadow:0 16px 40px #10204033;opacity:0;transform:translateY(10px);transition:.2s;pointer-events:none;max-width:min(420px,92vw)}
.toast.on{opacity:1;transform:none}
.statgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.statbox{border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#fbfcfe}
.statbox .l{font-size:11px;color:var(--mu)}.statbox .v{font-size:20px;font-weight:700}
.act{display:flex;gap:6px;flex-wrap:wrap}
.side-note{margin-top:0;padding:10px;font-size:11px;color:var(--mu);border-top:1px solid var(--line)}
.admin-profile{margin-top:auto;display:flex;align-items:center;gap:8px;padding:12px 6px;border-top:1px solid var(--line);font-size:12px}
</style></head><body>
<div id="usr"><div class="top"><div class="logo"><span class="brand-mark">F</span><span class="logo-text"><b>Public Relations</b><small>Feedback portal</small></span></div><nav id="unav"></nav><button class="lk" id="adm-go">Admin dashboard</button></div>
<main>
<section class="v0" id="u-dash"><div class="card ph" style="margin-bottom:16px"><div class="av" id="p-av"><?= e($initials) ?></div><div><h1 id="p-nm" style="margin:0">Welcome, <?= e($name) ?></h1><div class="sub" style="margin:0">Your profile dashboard</div></div><button class="btn" id="p-new">Record feedback</button></div>
<div class="g k" id="p-k"></div><div class="card"><h2>Recent activity</h2><div id="p-rc"></div></div></section>
<section class="v0" id="u-new"><h1>Record feedback</h1><div class="sub">Share a compliment or a complaint. You will receive a reference number to track it.</div>
<div class="card" style="max-width:720px"><div class="f">
<div class="w"><label>Type</label><div class="tg"><label><input type="radio" name="ty" value="Compliment" checked><span>Compliment</span></label><label><input type="radio" name="ty" value="Complaint"><span>Complaint</span></label></div></div>
<div><label>Full name</label><input id="n_name"></div><div><label>Date</label><input type="date" id="n_date"></div>
<div><label>Channel</label><select id="n_src"></select></div>
<div><label>Contact (optional)</label><input id="n_con"></div>
<div class="w"><label id="n_ml">Compliment</label><textarea id="n_msg"></textarea></div>
<div class="w"><button class="btn" id="n_go">Submit</button><div id="n_ok"></div></div></div></div></section>
<section class="v0" id="u-my"><h1>My submissions</h1><div class="sub">Status of the feedback you have recorded.</div>
<div class="g k" id="u-k"></div><div class="card tw"><table id="u-t"></table></div></section>
</main></div>

<div id="adm"><aside><div class="logo"><span class="brand-mark">F</span><span class="logo-text"><b>Public Relations</b><small>Administrator</small></span></div><nav id="anav"></nav><button class="lk" id="usr-go">Preview as user</button>
<div class="admin-profile">
  <div class="av" style="width:32px;height:32px;font-size:13px" title="<?= e($name) ?>"><?= e($initials) ?></div>
  <div style="flex:1;min-width:0;overflow:hidden">
    <b style="display:block;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($name) ?></b>
    <small style="display:block;color:var(--mu);font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($email) ?></small>
  </div>
  <form method="post" action="../index.php" style="margin:0">
    <input type="hidden" name="action" value="logout">
    <button type="submit" class="lk" title="Sign out" aria-label="Sign out" style="padding:4px 6px;font-size:14px">⏻</button>
  </form>
</div>
<div class="side-note">Admin preview · data saved locally in this browser.</div></aside>
<div class="main"><main>
<section class="v0" id="a-ov"><h1>Overview</h1><div class="sub" id="a-sub"></div>
<div class="g k" id="a-k"></div>
<div class="g c2"><div class="card"><h2>Monthly volume</h2><div id="a-m"></div></div><div class="card"><h2>Response status</h2><div id="a-st"></div><h2 style="margin-top:20px">By channel</h2><div id="a-sr"></div></div></div>
<div class="g c2"><div class="card"><h2>By quarter</h2><div id="a-q"></div></div><div class="card"><h2>By assignee</h2><div id="a-as"></div></div></div>
<div class="card tw"><h2>Needs attention</h2><table id="a-pn"></table></div>
<div class="card" style="margin-top:16px"><h2>Recent admin activity</h2><div id="a-recent"></div></div></section>

<section class="v0" id="a-reg">
<div class="row" style="justify-content:space-between;align-items:center">
<div><h1 style="margin:0">Register</h1><div class="sub" style="margin:0">Review, respond to and assign feedback.</div></div>
<div class="act"><button class="btn" id="r-new">+ New entry</button><button class="btn s" id="r-exp">Export CSV</button></div>
</div>
<div class="toolbar">
<select id="r-t"><option value="">All types</option><option>Compliment</option><option>Complaint</option></select>
<select id="r-q"><option value="">All quarters</option><option>1st</option><option>2nd</option><option>3rd</option><option>4th</option></select>
<select id="r-s"><option value="">All statuses</option></select>
<select id="r-as"><option value="">All assignees</option><option value="__unassigned">Unassigned</option></select>
<select id="r-ch"><option value="">All channels</option></select>
<input type="date" id="r-df" aria-label="From date">
<input type="date" id="r-dt" aria-label="To date">
<input id="r-x" placeholder="Search client, message, ref…">
<select id="r-sort"><option value="date_desc">Newest first</option><option value="date_asc">Oldest first</option><option value="ref_asc">Reference A-Z</option><option value="client_asc">Client A-Z</option><option value="status_asc">Status A-Z</option><option value="sla_asc">SLA due first</option></select>
</div>
<div class="bulkbar" id="r-bulk" style="display:none">
<strong id="r-bulk-n">0 selected</strong>
<select id="bulk-as"><option value="__none">Assign to…</option><option value="__unassigned">Unassigned</option></select>
<select id="bulk-st"><option value="__none">Set status…</option></select>
<button class="btn s" id="bulk-apply">Apply</button>
<button class="btn s" id="bulk-del">Delete</button>
<button class="btn s" id="bulk-clear">Clear</button>
</div>
<div class="card tw" style="max-height:620px;overflow-y:auto"><div class="sub" id="r-c" style="margin:0 0 6px"></div><table id="r-tb"></table></div>
<div class="pg" id="r-pg"></div>
</section>

<section class="v0" id="a-off">
<div class="row" style="justify-content:space-between;align-items:center">
<div><h1 style="margin:0">Officers</h1><div class="sub" style="margin:0">Manage the people who can be assigned feedback cases.</div></div>
<button class="btn" id="o-new">+ Add officer</button>
</div>
<div class="card tw"><table id="o-tb"></table></div>
</section>

<section class="v0" id="a-rep">
<h1>Reports</h1><div class="sub">Generate summaries and export data.</div>
<div class="card">
<div class="row">
<div><label>From</label><input type="date" id="rep-df"></div>
<div><label>To</label><input type="date" id="rep-dt"></div>
<div><label>Type</label><select id="rep-t"><option value="">All types</option><option>Compliment</option><option>Complaint</option></select></div>
<button class="btn" id="rep-go">Generate</button>
<button class="btn s" id="rep-exp">Export CSV</button>
</div>
<div id="rep-out"></div>
</div>
</section>

<section class="v0" id="a-aud">
<h1>Audit trail</h1><div class="sub">Every administrative action is recorded.</div>
<div class="row"><input id="aud-x" placeholder="Search actor, action, details…"><select id="aud-a"><option value="">All actions</option></select><button class="btn s" id="aud-exp">Export CSV</button></div>
<div class="card tw"><table id="aud-tb"></table></div>
</section>

<section class="v0" id="a-set">
<h1>Settings</h1><div class="sub">Configure the portal behaviour.</div>
<div class="g c2">
<div class="card">
<h2>General</h2>
<label>Organisation name</label><input id="set-org">
<label>SLA days for complaints</label><input type="number" id="set-sla" min="1">
<label>Channels (comma separated)</label><input id="set-ch">
<div style="margin-top:14px"><button class="btn" id="set-save">Save settings</button></div>
</div>
<div class="card">
<h2>Auto-acknowledgement templates</h2>
<label>Compliment</label><textarea id="set-tc"></textarea>
<label>Complaint</label><textarea id="set-tk"></textarea>
<div style="margin-top:14px"><button class="btn" id="set-save2">Save templates</button></div>
</div>
</div>
<div class="card" style="margin-top:16px">
<h2>Data management</h2>
<p class="sub">Export a backup or restore demo data.</p>
<div class="act"><button class="btn s" id="data-exp">Download JSON backup</button><button class="btn s" id="data-reset">Reset demo data</button></div>
<label style="margin-top:14px">Import JSON backup</label><input type="file" id="data-imp" accept="application/json">
</div>
</section>
</main></div></div>

<div class="card" id="deny"><h2>Administrator access only</h2><div class="sub">This dashboard is available to the page owner and invited editors.</div><button class="btn" id="dn">Go to user portal</button></div>

<dialog id="dlg"><h2 id="d-h"></h2><div class="sub" id="d-m"></div><div class="f">
<div><label>Status</label><select id="d_st"></select></div>
<div><label>Assignee</label><select id="d_as"></select></div>
<div class="w"><label>Response to client</label><textarea id="d_re"></textarea></div>
<div class="w"><label>Corrective action (internal)</label><input id="d_ac"></div>
<div class="w"><label>Internal note (optional)</label><textarea id="d_note" placeholder="Appended to the case history"></textarea></div>
<div class="w" id="d-tl"></div>
<div class="w act"><button class="btn" id="d_sv">Save changes</button><button class="btn s" id="d_edit">Edit entry</button><button class="btn s" id="d_del">Delete</button><button class="btn s" id="d_cl">Close</button></div>
</div></dialog>

<dialog id="newDlg"><h2 id="n-h">New feedback entry</h2><div class="f">
<div><label>Type</label><select id="ne_type"><option>Compliment</option><option>Complaint</option></select></div>
<div><label>Date</label><input type="date" id="ne_date"></div>
<div><label>Client name</label><input id="ne_name"></div>
<div><label>Contact (optional)</label><input id="ne_con"></div>
<div><label>Channel</label><select id="ne_src"></select></div>
<div><label>Assignee</label><select id="ne_as"></select></div>
<div><label>Status</label><select id="ne_st"></select></div>
<div class="w"><label>Message</label><textarea id="ne_text"></textarea></div>
<div class="w act"><button class="btn" id="ne_save">Save</button><button class="btn s" id="ne_cl">Cancel</button></div>
</div></dialog>

<dialog id="offDlg"><h2 id="of-h">Add officer</h2><div class="f">
<div class="w"><label>Name</label><input id="of-name"></div>
<div class="w"><label>Email (optional)</label><input id="of-email" type="email"></div>
<div class="w"><label>Role</label><select id="of-role"><option>Admin</option><option>Officer</option></select></div>
<div class="w"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" id="of-active" class="chk" style="width:auto" checked> Active</label></div>
<div class="w act"><button class="btn" id="of-save">Save</button><button class="btn s" id="of-cl">Cancel</button></div>
</div></dialog>

<div class="toast" id="toast"></div>

<script>
/* ============================================================
   Helpers
   ============================================================ */
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const E=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const MN=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const fd=d=>d?`${+d.slice(8)} ${MN[+d.slice(5,7)-1]} ${d.slice(0,4)}`:'—';
const today=()=>new Date().toLocaleDateString('en-CA');
const nowISO=()=>new Date().toISOString();
const qt=d=>['1st','2nd','3rd','4th'][Math.floor((+d.slice(5,7)-1)/3)];
const dd=(a,b)=>Math.round((new Date(b+'T00:00:00')-new Date(a+'T00:00:00'))/864e5);
const uid=p=>(p||'x')+Date.now().toString(36)+Math.random().toString(36).slice(2,6);
const cnt=(a,f)=>Object.entries(a.reduce((m,r)=>{const k=f(r);m[k]=(m[k]||0)+1;return m},{})).sort((a,b)=>b[1]-a[1]);
const bars=(el,a,t)=>el.innerHTML=a.map(([n,v])=>`<div class="b"><div class="n">${E(n)}</div><div class="tr"><div class="fl" style="width:${t?v/t*100:0}%"></div></div><div class="v">${v}</div></div>`).join('')||'<div class="em">No data</div>';
const stat=(l,v)=>`<div class="statbox"><div class="l">${E(l)}</div><div class="v">${E(v)}</div></div>`;
let toastTimer;
function toast(msg){const t=$('#toast');t.textContent=msg;t.classList.add('on');clearTimeout(toastTimer);toastTimer=setTimeout(()=>t.classList.remove('on'),2600)}

/* ============================================================
   Storage
   ============================================================ */
const store={
  get(k,d){try{const v=localStorage.getItem(k);return v?JSON.parse(v):d}catch(e){return d}},
  set(k,v){try{localStorage.setItem(k,JSON.stringify(v))}catch(e){console.warn('Storage unavailable',e)}}
};
const KEY={entries:'fiq_entries_v1',officers:'fiq_officers_v1',settings:'fiq_settings_v1',audit:'fiq_audit_v1'};

const SEED_SETTINGS={
  org:'Public Relations',
  slaDays:5,
  channels:['Facebook','LinkedIn','Email','Phone','Walk-in','Other'],
  replyCompliment:'Thank you for your feedback. We appreciate you taking the time to share your thoughts.',
  replyComplaint:'Thank you for reaching out. We have received your concern and will respond shortly.'
};
const SEED_OFFICERS=[
  {id:'of1',name:'Communications Team',email:'comms@example.org',role:'Admin',active:true},
  {id:'of2',name:'M. Chari',email:'mchari@example.org',role:'Officer',active:true},
  {id:'of3',name:'R. Dube',email:'rdube@example.org',role:'Officer',active:true},
  {id:'of4',name:'P. Ncube',email:'pncube@example.org',role:'Officer',active:true}
];

const SEED_ENTRIES=[
{"type":"Compliment","n":1,"ref":"c001","date":"2026-02-11","q":"1st","client":"Kara Hongolani","text":"thank you","src":"Facebook","fwd":"Communications","resp":"Thank you","rdate":"2026-02-18","status":"Responded","action":"","assignee":"","id":"e001"},
{"type":"Compliment","n":2,"ref":"c002","date":"2026-02-11","q":"1st","client":"Evans Musa","text":"Zikomo kwambiri Bambo. Great works there.","src":"Facebook","fwd":"Communications","resp":"Thank you","rdate":"2026-02-18","status":"Responded","action":"","assignee":"","id":"e002"},
{"type":"Compliment","n":3,"ref":"c003","date":"2026-02-11","q":"1st","client":"Naseem Javed","text":"Well done, Dear Phiri and National Competitiveness Commission Zimbabwe (NCCzimbabwe)","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-02-18","status":"Responded","action":"","assignee":"","id":"e003"},
{"type":"Compliment","n":4,"ref":"c004","date":"2026-02-11","q":"1st","client":"Louis John Herbst","text":"Keep the motivation and determination going good sir!","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-02-18","status":"Responded","action":"","assignee":"","id":"e004"},
{"type":"Compliment","n":5,"ref":"c005","date":"2026-02-20","q":"1st","client":"Alexio Maseko","text":"Quality at NCC","src":"Facebook","fwd":"Communications","resp":"thank you so much.","rdate":"2026-02-21","status":"Responded","action":"","assignee":"","id":"e005"},
{"type":"Compliment","n":6,"ref":"c006","date":"2026-02-27","q":"1st","client":"Ndumiso Mgutshini","text":"Braaaa Hugh Masikela","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e006"},
{"type":"Compliment","n":7,"ref":"c007","date":"2026-03-10","q":"1st","client":"Charles Motsi","text":"Zimbabwe\u2019s economic reform agenda continues to receive growing recognition from international financial institutions, with the African Development Bank (AfDB) commending the country\u2019s progress under the International Monetary Fund Staff-Monitored Programme (SMP). ...........","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e007"},
{"type":"Compliment","n":8,"ref":"c008","date":"2026-03-12","q":"1st","client":"Jingaz Jingaz","text":"Good going NCC for participating in such an important dialogue","src":"Facebook","fwd":"Communications","resp":"Thank you so much.","rdate":"2026-03-12","status":"Responded","action":"","assignee":"","id":"e008"},
{"type":"Compliment","n":9,"ref":"c009","date":"2026-03-12","q":"1st","client":"Mai Tayana","text":"Great work","src":"Facebook","fwd":"Communications","resp":"Thank you","rdate":"2026-03-13","status":"Responded","action":"","assignee":"","id":"e009"},
{"type":"Compliment","n":10,"ref":"c010","date":"2026-03-23","q":"1st","client":"Shadreck Muchena","text":"NCC doing a tremendous job","src":"Facebook","fwd":"Communications","resp":"Thank you so much","rdate":"2026-03-23","status":"Responded","action":"","assignee":"","id":"e010"},
{"type":"Compliment","n":11,"ref":"c011","date":"2026-03-23","q":"1st","client":"Jingaz Jingaz","text":"Let's keep pushing. We will gettjere","src":"Facebook","fwd":"Communications","resp":"True that, thank you so much","rdate":"2026-03-23","status":"Responded","action":"","assignee":"","id":"e011"},
{"type":"Compliment","n":12,"ref":"c012","date":"2026-03-23","q":"1st","client":"Alec Chumujakazi","text":"Banning shows a lack of planning and a lack of a clear path of our policies.........","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e012"},
{"type":"Compliment","n":13,"ref":"c013","date":"2026-03-23","q":"1st","client":"Shadreck Muchena","text":"Thank you Mr Douglas","src":"Facebook","fwd":"Communications","resp":"Thank you so much","rdate":"2026-03-23","status":"Responded","action":"","assignee":"","id":"e013"},
{"type":"Compliment","n":14,"ref":"c014","date":"2026-04-03","q":"2nd","client":"Emily Mwonzora","text":"Thank you NCC","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e014"},
{"type":"Compliment","n":15,"ref":"c015","date":"2026-04-17","q":"2nd","client":"Jingaz Jingaz","text":"Halala that's the way to go. Celebrating with the nation","src":"Facebook","fwd":"Communications","resp":"Yes, we proudly celebrate Zimbabwe at 46!","rdate":"2026-04-17","status":"Responded","action":"","assignee":"","id":"e015"},
{"type":"Compliment","n":16,"ref":"c016","date":"2026-04-17","q":"2nd","client":"Patience Chikwiriro","text":"Wow\u2026","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e016"},
{"type":"Compliment","n":17,"ref":"c017","date":"2026-04-21","q":"2nd","client":"Jingaz Jingaz","text":"Thanks to the PS of our line ministry. Indeed his support is visible all the time.","src":"Facebook","fwd":"Communications","resp":"Very true.","rdate":"2026-04-21","status":"Responded","action":"","assignee":"","id":"e017"},
{"type":"Compliment","n":18,"ref":"c018","date":"2026-04-22","q":"2nd","client":"Cleopas Manunure","text":"Wonderful","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-04-22","status":"Responded","action":"","assignee":"","id":"e018"},
{"type":"Compliment","n":19,"ref":"c019","date":"2026-04-23","q":"2nd","client":"Jingaz Jingaz","text":"Indeed he remains a true link to our interaction with our policy makers.","src":"Facebook","fwd":"Communications","resp":"That's very true.","rdate":"2026-04-23","status":"Responded","action":"","assignee":"","id":"e019"},
{"type":"Compliment","n":20,"ref":"c020","date":"2026-04-29","q":"2nd","client":"Nassem Javed","text":"Why governments fear AI: What will force them to embrace it","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e020"},
{"type":"Compliment","n":21,"ref":"c021","date":"2026-04-29","q":"2nd","client":"Jingaz Jingaz","text":"Here is hoping to a constructive participation that will positively help shape the nation towards a truely competitive edge in the region","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e021"},
{"type":"Compliment","n":22,"ref":"c022","date":"2026-04-30","q":"2nd","client":"Cleopas Manunure","text":"Wonderful","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-04-30","status":"Responded","action":"","assignee":"","id":"e022"},
{"type":"Compliment","n":23,"ref":"c023","date":"2026-04-30","q":"2nd","client":"Simbarashe Mangadza","text":"Lyie Lynee","src":"Facebook","fwd":"Communications","resp":"Like","rdate":"2026-04-30","status":"Responded","action":"","assignee":"","id":"e023"},
{"type":"Compliment","n":24,"ref":"c024","date":"2026-04-30","q":"2nd","client":"Jingaz Jingaz","text":"Audio not quite clear maybe it's my network","src":"Facebook","fwd":"Communications","resp":"Like","rdate":"2026-04-30","status":"Responded","action":"","assignee":"","id":"e024"},
{"type":"Compliment","n":25,"ref":"c025","date":"2026-05-01","q":"2nd","client":"Jingaz Jingaz","text":"There is nothing that beats a local diagnosis to a local problem and prescribing therefore a local solution.","src":"Facebook","fwd":"Communications","resp":"Like","rdate":"","status":"Responded","action":"","assignee":"","id":"e025"},
{"type":"Compliment","n":26,"ref":"c026","date":"2026-05-07","q":"2nd","client":"Christopher Muzota","text":"A warm welcome to Mr Pfumvuti.\u00a0.....","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e026"},
{"type":"Compliment","n":27,"ref":"c027","date":"2026-05-13","q":"2nd","client":"Sharon M Mtonga","text":"noted","src":"Facebook","fwd":"Communications","resp":"You are welcome","rdate":"2026-05-13","status":"Responded","action":"","assignee":"","id":"e027"},
{"type":"Compliment","n":28,"ref":"c028","date":"2026-05-13","q":"2nd","client":"Cleopas Manunure","text":"Good opportunity","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-05-13","status":"Responded","action":"","assignee":"","id":"e028"},
{"type":"Compliment","n":29,"ref":"c029","date":"2026-05-14","q":"2nd","client":"Sharon M Mtonga","text":"Good publicity, thumbs up","src":"Facebook","fwd":"Communications","resp":"Thank you so much.","rdate":"","status":"Responded","action":"","assignee":"","id":"e029"},
{"type":"Compliment","n":30,"ref":"c030","date":"2026-05-14","q":"2nd","client":"Jingaz Jingaz","text":"Team hombe iyo","src":"Facebook","fwd":"Communications","resp":"Thank you","rdate":"","status":"Responded","action":"","assignee":"","id":"e030"},
{"type":"Compliment","n":31,"ref":"c031","date":"2026-05-19","q":"2nd","client":"Wadzie Christie","text":"Best manager of the year","src":"Facebook","fwd":"Communications","resp":"Thank you so much.","rdate":"2026-05-19","status":"Responded","action":"","assignee":"","id":"e031"},
{"type":"Compliment","n":32,"ref":"c032","date":"2026-05-22","q":"2nd","client":"Sharon M Mtonga","text":"Yes yes , your perceptions on Competitiveness matters","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e032"},
{"type":"Compliment","n":33,"ref":"c033","date":"2026-05-25","q":"2nd","client":"Sharon M Mtonga","text":"A competitive continent # Africa","src":"Facebook","fwd":"Communications","resp":"You\u2019re right. Let\u2019s build a competitive\u00a0#Africa\u00a0together.","rdate":"2026-05-25","status":"Responded","action":"","assignee":"","id":"e033"},
{"type":"Compliment","n":34,"ref":"c034","date":"2026-05-26","q":"2nd","client":"Bridget Utete","text":"Rest in peace","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e034"},
{"type":"Compliment","n":35,"ref":"c035","date":"2026-05-26","q":"2nd","client":"PRAZ Zimbabwe","text":"May His Soul Rest in Eternal Peace","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e035"},
{"type":"Compliment","n":36,"ref":"c036","date":"2026-06-02","q":"2nd","client":"Jingaz Jingaz","text":"When I came to join NCC as a board member. The person I met first was the Executive Director","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e036"},
{"type":"Compliment","n":37,"ref":"c037","date":"2026-06-02","q":"2nd","client":"Nyasha Musikambesa","text":"Rest well Mr phiri","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e037"},
{"type":"Compliment","n":38,"ref":"c038","date":"2026-06-04","q":"2nd","client":"Nexus Staffing","text":"People. Jobs. Growth.","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e038"},
{"type":"Compliment","n":39,"ref":"c039","date":"2026-06-20","q":"2nd","client":"Yvonne Hamadziripi","text":"Good one team","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e039"},
{"type":"Compliment","n":40,"ref":"c040","date":"2026-06-22","q":"2nd","client":"Tafadzwa Mupingashato","text":"Interesting, will be looking forward to it","src":"LinkedIn","fwd":"Communications","resp":"Follow us:","rdate":"2026-06-22","status":"Responded","action":"","assignee":"","id":"e040"},
{"type":"Compliment","n":41,"ref":"c041","date":"2026-07-09","q":"3rd","client":"Tapiwa Nashe","text":"Good day is there an exception for email applications for non Harare residents?","src":"Facebook","fwd":"Communications","resp":"No. Interested applicants should hand-deliver their applications. Thank you.","rdate":"2026-07-14","status":"Responded","action":"","assignee":"","id":"e041"},
{"type":"Compliment","n":42,"ref":"c042","date":"2026-06-30","q":"2nd","client":"Nyasha Musikambesa","text":"kindly explore","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e042"},
{"type":"Compliment","n":43,"ref":"c043","date":"2026-07-09","q":"3rd","client":"Baba Elroy","text":"Basa sebasa","src":"Facebook","fwd":"Communications","resp":"Yeah","rdate":"2026-07-09","status":"Responded","action":"","assignee":"","id":"e043"},
{"type":"Compliment","n":44,"ref":"c044","date":"2026-07-09","q":"3rd","client":"Himanshu Singh","text":"Is this remote opportunity?","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e044"},
{"type":"Compliment","n":45,"ref":"c045","date":"2026-07-09","q":"3rd","client":"Tafadzwa Mupingashato","text":"Would the data be publicly available?","src":"LinkedIn","fwd":"Communications","resp":"The Rural and Urban Councils Competitiveness Report, featuring council rankings by category, will be published in the public domain.","rdate":"2026-07-09","status":"Responded","action":"","assignee":"","id":"e045"},
{"type":"Compliment","n":46,"ref":"c046","date":"2026-07-10","q":"3rd","client":"Zondai Tsonzeni","text":"Great work hashtag#NCCZimbabwe","src":"LinkedIn","fwd":"Communications","resp":"Thank you so much.","rdate":"2026-07-10","status":"Responded","action":"","assignee":"","id":"e046"},
{"type":"Compliment","n":47,"ref":"c047","date":"2026-07-14","q":"3rd","client":"Zondai Tsonzeni","text":"Great work National Competitiveness Commission Zimbabwe. Sustainable development begins with understanding ........","src":"LinkedIn","fwd":"Communications","resp":"Thank you for sharing this valuable perspective. Much appreciated.","rdate":"2026-07-15","status":"Responded","action":"","assignee":"","id":"e047"},
{"type":"Compliment","n":48,"ref":"c048","date":"2026-07-20","q":"3rd","client":"Zondai Tsonzeni","text":"Indeed. Inclusive community participation provides more reliable data for evidence-based and impactful policymaking.","src":"LinkedIn","fwd":"Communications","resp":"Absolutely. Thank you for your valuable insight. Inclusive participation is key to developing informed and sustainable policies.","rdate":"2026-07-20","status":"Responded","action":"","assignee":"","id":"e048"},
{"type":"Compliment","n":49,"ref":"c049","date":"2026-07-20","q":"3rd","client":"Jingaz Jingaz","text":"Making sure no one is left behind in playing their part in bringing the vision home.","src":"Facebook","fwd":"Communications","resp":"Definitely","rdate":"2026-07-20","status":"Responded","action":"","assignee":"","id":"e049"},
{"type":"Compliment","n":50,"ref":"c050","date":"2026-07-20","q":"3rd","client":"Jingaz Jingaz","text":"Weldone team","src":"Facebook","fwd":"Communications","resp":"thank you so much Commissioner","rdate":"2026-07-20","status":"Responded","action":"","assignee":"","id":"e050"},
{"type":"Compliment","n":51,"ref":"c051","date":"2026-07-25","q":"3rd","client":"Zondai Tsonzeni","text":"Great initiative there National Competitiveness Commission Zimbabwe. Staff development enhances organisational performance and delivers the best results.","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e051"},
{"type":"Compliment","n":52,"ref":"c052","date":"2026-07-28","q":"3rd","client":"Cleophas Manunure","text":"Wonderful NCC.","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-07-28","status":"Responded","action":"","assignee":"","id":"e052"},
{"type":"Compliment","n":53,"ref":"c053","date":"2026-07-28","q":"3rd","client":"Zondai Tsonzeni","text":"A change of environment can be a powerful catalyst for collaboration, renewed energy, and improved performance. Great initiative National Competitiveness Commission Zimbabwe","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e053"},
{"type":"Compliment","n":54,"ref":"c054","date":"2026-07-28","q":"3rd","client":"Jingaz Jingaz","text":"The one and only team","src":"LinkedIn","fwd":"Communications","resp":"Thank you so much","rdate":"2026-07-28","status":"Responded","action":"","assignee":"","id":"e054"},
{"type":"Compliment","n":55,"ref":"c055","date":"2026-08-02","q":"3rd","client":"Zondai Tsonzeni","text":"A conscious and informed community can meaningfully participate in and contribute to competitiveness-focused initiatives. A great initiative there National Competitiveness Commission Zimbabwe","src":"LinkedIn","fwd":"Communications","resp":"thank you","rdate":"2026-08-02","status":"Responded","action":"","assignee":"","id":"e055"},
{"type":"Compliment","n":56,"ref":"c056","date":"2026-07-29","q":"3rd","client":"Buhle Nkomazana","text":"The A team","src":"Facebook","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e056"},
{"type":"Compliment","n":57,"ref":"c057","date":"2026-07-29","q":"3rd","client":"Joylene Magora","text":"Awesome","src":"Facebook","fwd":"Communications","resp":"Thank you","rdate":"2026-07-29","status":"Responded","action":"","assignee":"","id":"e057"},
{"type":"Compliment","n":58,"ref":"c058","date":"2026-08-02","q":"3rd","client":"Jingaz Jingaz","text":"Leaving no place and no one behind.","src":"Facebook","fwd":"Communications","resp":"Indeed! Progress is meaningful only when everyone is included. Together, we can build a future where no community is left behind.","rdate":"2026-08-02","status":"Responded","action":"","assignee":"","id":"e058"},
{"type":"Compliment","n":59,"ref":"c059","date":"2026-08-02","q":"3rd","client":"Oscar Denhere","text":"Everytime l see your page l am reminded my friend and mentor\u00a0Phillip Phiri\u00a0may his soul rest in Peace...keep up the good work","src":"Facebook","fwd":"Communications","resp":"He was a remarkable leader whose legacy will continue to inspire us. May his soul continue to rest in eternal peace.","rdate":"2026-08-02","status":"Responded","action":"","assignee":"","id":"e059"},
{"type":"Compliment","n":60,"ref":"c060","date":"2026-08-13","q":"3rd","client":"Nyasha Musikambesa","text":"Great progress, how ever the contact details are not functional National Competitiveness Commission Zimbabwe","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e060"},
{"type":"Compliment","n":60,"ref":"c061","date":"2026-08-24","q":"3rd","client":"Naseem Javed","text":"Economic Dystopia and Biology of true value creation","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e061"},
{"type":"Compliment","n":62,"ref":"c062","date":"2026-08-24","q":"3rd","client":"Jingaz Jingaz","text":"This is surely a must attend for anyone who is serious about doing business in Zimbabwe","src":"Facebook","fwd":"Communications","resp":"Definitely","rdate":"2026-08-24","status":"Responded","action":"","assignee":"","id":"e062"},
{"type":"Compliment","n":63,"ref":"c063","date":"2026-09-09","q":"3rd","client":"Itai Zimunya","text":"This is a masterclass. The foundation for prosperity and an upper-middle-class economy by 18 April 2030: Zimbabwe's golden jubilee! Zimbabwe Investment & Development Agency - ZIDA","src":"LinkedIn","fwd":"Communications","resp":"Thank you so much","rdate":"2026-09-09","status":"Responded","action":"","assignee":"","id":"e063"},
{"type":"Compliment","n":64,"ref":"c064","date":"2026-09-02","q":"3rd","client":"Nyasha Musikambesa","text":"Douglas Muzimba looking forwad homeboy! We need more for the masses to be educated on the need to ease the doing of business.","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e064"},
{"type":"Compliment","n":65,"ref":"c065","date":"2026-09-09","q":"3rd","client":"Wyonna Jean Britten","text":"Looking forward to this ! hashtag#Byo","src":"LinkedIn","fwd":"Communications","resp":"See you there","rdate":"2026-09-09","status":"Responded","action":"","assignee":"","id":"e065"},
{"type":"Compliment","n":66,"ref":"c066","date":"2026-09-13","q":"3rd","client":"Nyasha Musikambesa","text":"Well elaborated homeboy....this is a great game changer...keep it up National Competitiveness Commission Zimbabwe let's push the ease of doing business agenda.","src":"LinkedIn","fwd":"Communications","resp":"Thank you so much","rdate":"2026-09-13","status":"Responded","action":"","assignee":"","id":"e066"},
{"type":"Compliment","n":67,"ref":"c067","date":"2026-09-20","q":"3rd","client":"Cleopas Manunure","text":"Well done NCC. Thanks for such a summit. It will help our country.","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-09-20","status":"Responded","action":"","assignee":"","id":"e067"},
{"type":"Compliment","n":68,"ref":"c068","date":"2026-09-21","q":"3rd","client":"Cleopas Manunure","text":"Well done NCC.","src":"LinkedIn","fwd":"Communications","resp":"Thank you","rdate":"2026-09-21","status":"Responded","action":"","assignee":"","id":"e068"},
{"type":"Compliment","n":69,"ref":"c069","date":"2026-09-22","q":"3rd","client":"Nyasha Musikambesa","text":"Greatwork team, now we really need to invest more in checking the sectors of the economy that needs more intersectoral collaboration, if possible regulation goes across ministries to lessen investiment period.","src":"LinkedIn","fwd":"Communications","resp":"Thank you so much","rdate":"2026-09-22","status":"Responded","action":"","assignee":"","id":"e069"},
{"type":"Compliment","n":70,"ref":"c070","date":"2026-09-28","q":"3rd","client":"Tinashe Daniel","text":"Phillip is dearly missed. He served his country with deligence. RIP.","src":"LinkedIn","fwd":"Communications","resp":"Sadly missed","rdate":"2026-09-28","status":"Responded","action":"","assignee":"","id":"e070"},
{"type":"Compliment","n":71,"ref":"c071","date":"2026-10-12","q":"4th","client":"Gerald Fadzai Mukere","text":"is there going to be microdata available for researchers' access?","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e071"},
{"type":"Compliment","n":72,"ref":"c072","date":"2026-10-14","q":"4th","client":"Nyasha Musikambesa","text":"Is it availqble online Audrey Galawu","src":"LinkedIn","fwd":"Communications","resp":"","rdate":"","status":"Awaiting response","action":"","assignee":"","id":"e072"},
{"type":"Complaint","n":1,"ref":"c001","date":"2026-08-13","q":"3rd","client":"Nyasha Musikambesa","text":"The one with extension -8","src":"LinkedIn","fwd":"Communications","resp":"Apologies, we have just realised that our phones are down. However, you may inbox us for any enquiries.","rdate":"2026-08-13","status":"Resolved","action":"Engaged Liquid","assignee":"Communications","id":"e073"}
];

/* ============================================================
   State
   ============================================================ */
let entries=[],officers=[],settings={},audit=[];
let isAdmin=true;
let regPage=1,regSelected=new Set();
let editId=null,caseId=null,officerId=null;
const uidUser=<?= json_encode('u' . $userId) ?>;

const UT=[['u-dash','Dashboard'],['u-new','Record feedback'],['u-my','My submissions']];
const AT=[['a-ov','Overview'],['a-reg','Register'],['a-off','Officers'],['a-rep','Reports'],['a-aud','Audit trail'],['a-set','Settings']];

/* ============================================================
   Data loading
   ============================================================ */
function normalizeEntry(e,i){
  return {
    id:e.id||('e'+String(i+1).padStart(3,'0')),
    type:e.type||'Compliment',n:e.n||0,ref:e.ref||('x'+i),
    date:e.date||today(),q:e.q||qt(e.date||today()),
    client:e.client||'',text:e.text||'',src:e.src||'Other',contact:e.contact||'',
    fwd:e.fwd||'Communications',resp:e.resp||'',rdate:e.rdate||'',
    status:e.status||(e.type==='Complaint'?'Open':'Awaiting response'),
    action:e.action||'',assignee:e.assignee||'',by:e.by||'seed',
    createdAt:e.createdAt||nowISO(),updatedAt:e.updatedAt||nowISO(),
    notes:e.notes||[],history:e.history||[]
  };
}
function loadData(){
  settings=Object.assign({},SEED_SETTINGS,store.get(KEY.settings,{}));
  officers=store.get(KEY.officers,SEED_OFFICERS).map(o=>({...o}));
  audit=store.get(KEY.audit,[]);
  const raw=store.get(KEY.entries,null);
  entries=(raw||SEED_ENTRIES).map(normalizeEntry);
  if(!raw)saveEntries();
}
function saveEntries(){store.set(KEY.entries,entries)}
function saveOfficers(){store.set(KEY.officers,officers)}
function saveSettings(){store.set(KEY.settings,settings)}
function saveAudit(){store.set(KEY.audit,audit)}
function logAction(action,ref,details,actor){
  audit.unshift({id:uid('a'),ts:nowISO(),actor:actor||<?= json_encode($name) ?>,action,ref:ref||'',details:details||''});
  if(audit.length>1000)audit.length=1000;
  saveAudit();
}

/* ============================================================
   Navigation
   ============================================================ */
function buildNav(){
  $('#unav').innerHTML=UT.map(([id,l])=>`<button data-v="${id}">${l}</button>`).join('');
  $('#anav').innerHTML=AT.map(([id,l])=>`<button data-v="${id}">${l}</button>`).join('');
  $$('#unav button,#anav button').forEach(b=>b.onclick=()=>go(b.dataset.v));
}
function go(v){
  $$('.v0').forEach(e=>e.classList.toggle('on',e.id===v));
  $$('[data-v]').forEach(b=>b.classList.toggle('on',b.dataset.v===v));
  const fn={ 'u-dash':dash,'u-new':()=>{},'u-my':my,'a-ov':ov,'a-reg':reg,'a-off':off,'a-rep':rep,'a-aud':aud,'a-set':set };
  (fn[v]||(()=>{}))();
  window.scrollTo({top:0,behavior:'smooth'});
}
function area(a){
  const asAdmin=a==='admin';
  if(asAdmin&&!isAdmin){$('#usr').style.display='none';$('#adm').style.display='none';$('#deny').style.display='block';return}
  $('#deny').style.display='none';
  $('#usr').style.display=asAdmin?'none':'block';
  $('#adm').style.display=asAdmin?'flex':'none';
  history.replaceState(null,'',asAdmin?'#admin':'#');
  go(asAdmin?'a-ov':'u-dash');
}

/* ============================================================
   User portal
   ============================================================ */
let pname=<?= json_encode($name) ?>;
function fillUserChannels(){
  $('#n_src').innerHTML=settings.channels.map(c=>`<option>${E(c)}</option>`).join('');
}
function dash(){
  const A=entries.filter(e=>e.by===uidUser).sort((a,b)=>b.date.localeCompare(a.date));
  $('#p-nm').textContent=pname?'Welcome, '+pname:'Welcome';
  $('#p-av').textContent=(pname||'U')[0].toUpperCase();
  $('#p-k').innerHTML=[['Submitted',A.length],['Compliments',A.filter(e=>e.type==='Compliment').length],['Complaints',A.filter(e=>e.type==='Complaint').length],['Awaiting response',A.filter(e=>!e.resp).length]]
    .map(([l,v])=>`<div class="card kp"><div class="l">${l}</div><div class="v">${v}</div></div>`).join('');
  $('#p-rc').innerHTML=A.slice(0,5).map(e=>`<div class="rc"><div><b>${E(e.ref)}</b> ${E(e.type)}<br><small>${fd(e.date)} — ${E(e.text).slice(0,90)}</small></div><div>${tag(e.status)}</div></div>`).join('')
    ||'<div class="em">No activity yet. Record your first compliment or complaint.</div>';
}
function my(){
  const A=entries.filter(e=>e.by===uidUser).sort((a,b)=>b.date.localeCompare(a.date));
  $('#u-k').innerHTML=[['Submitted',A.length],['Awaiting response',A.filter(e=>!e.resp).length],['Answered or resolved',A.filter(e=>e.resp).length]]
    .map(([l,v])=>`<div class="card kp"><div class="l">${l}</div><div class="v">${v}</div></div>`).join('');
  $('#u-t').innerHTML='<tr><th>Ref</th><th>Type</th><th>Date</th><th>Message</th><th>Status</th><th>Response</th></tr>'+
    (A.map(e=>`<tr><td>${E(e.ref)}</td><td>${E(e.type)}</td><td>${fd(e.date)}</td><td><div class="tx">${E(e.text)}</div></td><td>${tag(e.status)}</td><td><div class="tx">${E(e.resp)||'—'}</div></td></tr>`).join('')
    ||'<tr><td colspan="6" class="em">You have not submitted any feedback yet</td></tr>');
}

/* ============================================================
   Shared display helpers
   ============================================================ */
function statusesFor(type){return type==='Complaint'?['Open','In progress','Resolved']:['Awaiting response','Responded'];}
function tag(s){
  const cls=(s==='Resolved'||s==='Responded')?'ok'
    :s==='In progress'?'bl'
    :(s==='Awaiting response'||s==='Open')?'wa'
    :'';
  return `<span class="t ${cls}">${E(s)}</span>`;
}
function slaDue(e){
  if(e.type!=='Complaint')return null;
  const d=new Date(e.date+'T00:00:00');
  d.setDate(d.getDate()+(+settings.slaDays||5));
  return d.toLocaleDateString('en-CA');
}
function slaInfo(e){
  if(e.type!=='Complaint')return {label:'—',cls:''};
  if(e.status==='Resolved')return {label:'Resolved',cls:'ok'};
  const due=slaDue(e),t=today();
  if(t>due)return {label:'Overdue '+dd(due,t)+'d',cls:'ba'};
  const days=dd(t,due);
  return {label:days<=1?'Due soon':days+'d left',cls:days<=1?'wa':''};
}

/* ============================================================
   Admin — overview
   ============================================================ */
function ov(){
  const A=entries.slice();
  const R=A.filter(e=>e.resp);
  const lag=R.filter(e=>e.rdate).map(e=>dd(e.date,e.rdate));
  const C=A.filter(e=>e.type==='Complaint');
  const openC=C.filter(e=>e.status!=='Resolved');
  const overdue=openC.filter(e=>today()>slaDue(e));
  const ds=A.map(e=>e.date).sort();
  $('#a-sub').textContent=ds.length?`Period: ${fd(ds[0])} to ${fd(ds[ds.length-1])} · ${A.length} entries`:'No entries yet';
  const kp=[
    ['Total feedback',A.length],
    ['Compliments',A.length-C.length],
    ['Complaints',C.length],
    ['Response rate',(A.length?Math.round(R.length/A.length*100):0)+'%'],
    ['Avg response (days)',lag.length?(lag.reduce((a,b)=>a+b,0)/lag.length).toFixed(1):'—'],
    ['Open complaints',openC.length],
    ['Overdue complaints',overdue.length],
    ['Awaiting response',A.length-R.length]
  ];
  $('#a-k').innerHTML=kp.map(([l,v])=>`<div class="card kp"><div class="l">${l}</div><div class="v">${v}</div></div>`).join('');

  const m=[...Array(12)].map((_,i)=>A.filter(e=>+e.date.slice(5,7)-1===i).length),mx=Math.max(5,...m),W=560,H=220;
  let g='';
  m.forEach((n,i)=>{
    const bw=W/12,h=(H-30)*n/mx;
    g+=`<rect x="${i*bw+8}" y="${H-22-h}" width="${bw-16}" height="${h}" rx="3" fill="#2563eb"><title>${MN[i]}: ${n}</title></rect>`
      +(n?`<text x="${i*bw+bw/2}" y="${H-26-h}" text-anchor="middle">${n}</text>`:'')
      +`<text x="${i*bw+bw/2}" y="${H-6}" text-anchor="middle">${MN[i]}</text>`;
  });
  $('#a-m').innerHTML=`<svg viewBox="0 0 ${W} ${H}" width="100%">${g}</svg>`;

  bars($('#a-st'),cnt(A,e=>e.status),A.length);
  bars($('#a-sr'),cnt(A,e=>e.src),A.length);
  bars($('#a-q'),cnt(A,e=>e.q),A.length);
  bars($('#a-as'),cnt(A,e=>e.assignee||'Unassigned'),A.length);

  const P=A.filter(e=>e.type==='Complaint'?e.status!=='Resolved':!e.resp)
    .sort((a,b)=>(slaDue(a)||a.date).localeCompare(slaDue(b)||b.date)).slice(0,8);
  $('#a-pn').innerHTML='<tr><th>Ref</th><th>Type</th><th>Client</th><th>Age</th><th>SLA</th><th></th></tr>'+
    (P.map(e=>{const si=slaInfo(e);return `<tr><td>${E(e.ref)}</td><td>${E(e.type)}</td><td>${E(e.client)}</td><td>${dd(e.date,today())} d</td><td><span class="t ${si.cls}">${E(si.label)}</span></td><td><button class="btn s" data-o="${e.id}">Respond</button></td></tr>`}).join('')
    ||'<tr><td colspan="6" class="em">Nothing outstanding</td></tr>');
  $$('#a-pn [data-o]').forEach(b=>b.onclick=()=>{go('a-reg');openCase(b.dataset.o)});

  $('#a-recent').innerHTML=audit.slice(0,8).map(l=>`<div class="rc"><div><b>${E(l.action)}</b> ${E(l.ref||'')}<br><small>${new Date(l.ts).toLocaleString()} · ${E(l.actor)}</small></div><div><small>${E(l.details||'')}</small></div></div>`).join('')
    ||'<div class="em">No admin activity yet.</div>';
}

/* ============================================================
   Admin — register
   ============================================================ */
function populateFilters(){
  const statuses=[...new Set(entries.map(e=>e.status))].sort();
  const curS=$('#r-s').value;
  $('#r-s').innerHTML='<option value="">All statuses</option>'+statuses.map(s=>`<option${s===curS?' selected':''}>${E(s)}</option>`).join('');
  const assigns=[...new Set(entries.map(e=>e.assignee).filter(Boolean))].sort();
  const curA=$('#r-as').value;
  $('#r-as').innerHTML='<option value="">All assignees</option><option value="__unassigned">Unassigned</option>'+assigns.map(a=>`<option${a===curA?' selected':''}>${E(a)}</option>`).join('');
  const chans=[...new Set(entries.map(e=>e.src))].sort();
  const curC=$('#r-ch').value;
  $('#r-ch').innerHTML='<option value="">All channels</option>'+chans.map(c=>`<option${c===curC?' selected':''}>${E(c)}</option>`).join('');

  const bulkStatuses=[...new Set([...statuses,'Open','In progress','Resolved','Awaiting response','Responded'])];
  $('#bulk-st').innerHTML='<option value="__none">Set status…</option>'+bulkStatuses.map(s=>`<option>${E(s)}</option>`).join('');
  $('#bulk-as').innerHTML='<option value="__none">Assign to…</option><option value="__unassigned">Unassigned</option>'+officers.filter(o=>o.active).map(o=>`<option>${E(o.name)}</option>`).join('');
}
function filteredEntries(){
  const t=$('#r-t').value,q=$('#r-q').value,s=$('#r-s').value,as=$('#r-as').value,ch=$('#r-ch').value,df=$('#r-df').value,dt=$('#r-dt').value,x=($('#r-x').value||'').toLowerCase();
  return entries.filter(e=>
    (!t||e.type===t)&&
    (!q||e.q===q)&&
    (!s||e.status===s)&&
    (!as||(as==='__unassigned'?!e.assignee:e.assignee===as))&&
    (!ch||e.src===ch)&&
    (!df||e.date>=df)&&
    (!dt||e.date<=dt)&&
    (!x||(e.client+e.text+e.ref+(e.assignee||'')+(e.src||'')).toLowerCase().includes(x))
  );
}
function sortList(list,sort){
  const [key,dir]=sort.split('_');
  list.sort((a,b)=>{
    let va,vb;
    if(key==='date'){va=a.date;vb=b.date}
    else if(key==='ref'){va=a.ref;vb=b.ref}
    else if(key==='client'){va=(a.client||'').toLowerCase();vb=(b.client||'').toLowerCase()}
    else if(key==='status'){va=a.status;vb=b.status}
    else if(key==='sla'){va=slaDue(a)||'9999';vb=slaDue(b)||'9999'}
    if(va<vb)return dir==='asc'?-1:1;
    if(va>vb)return dir==='asc'?1:-1;
    return 0;
  });
}
function reg(){
  const list=filteredEntries();
  sortList(list,$('#r-sort').value);
  const per=12,pages=Math.max(1,Math.ceil(list.length/per));
  if(regPage>pages)regPage=pages;
  const start=(regPage-1)*per,pageList=list.slice(start,start+per);
  $('#r-c').textContent=`${list.length} of ${entries.length} entries`;
  $('#r-tb').innerHTML='<tr><th><input type="checkbox" class="chk" id="r-all" aria-label="Select all on page"></th><th>Ref</th><th>Type</th><th>Date</th><th>Client</th><th>Message</th><th>Channel</th><th>Assignee</th><th>Status</th><th>SLA</th><th></th></tr>'+
    (pageList.map(e=>{
      const si=slaInfo(e),ck=regSelected.has(e.id)?' checked':'';
      return `<tr>
        <td><input type="checkbox" class="chk r-sel" data-id="${e.id}"${ck}></td>
        <td>${E(e.ref)}</td><td>${E(e.type)}</td><td>${fd(e.date)}</td><td>${E(e.client)}</td>
        <td><div class="tx" title="${E(e.text)}">${E(e.text)}</div></td>
        <td>${E(e.src)}</td><td>${E(e.assignee)||'<span class="t">Unassigned</span>'}</td>
        <td>${tag(e.status)}</td><td><span class="t ${si.cls}">${E(si.label)}</span></td>
        <td><div class="act"><button class="btn s" data-open="${e.id}">Open</button><button class="btn s" data-edit="${e.id}">Edit</button><button class="btn s" data-del="${e.id}">Del</button></div></td>
      </tr>`;
    }).join('')||'<tr><td colspan="11" class="em">No matching entries</td></tr>');

  const all=$('#r-all');
  if(all){
    all.checked=pageList.length&&pageList.every(e=>regSelected.has(e.id));
    all.onchange=()=>{
      pageList.forEach(e=>{all.checked?regSelected.add(e.id):regSelected.delete(e.id)});
      reg();
    };
  }
  $$('#r-tb .r-sel').forEach(c=>c.onchange=()=>{
    if(c.checked)regSelected.add(c.dataset.id);else regSelected.delete(c.dataset.id);
    updateBulk();
  });
  $$('#r-tb [data-open]').forEach(b=>b.onclick=()=>openCase(b.dataset.open));
  $$('#r-tb [data-edit]').forEach(b=>b.onclick=()=>openNew(b.dataset.edit));
  $$('#r-tb [data-del]').forEach(b=>b.onclick=()=>deleteEntry(b.dataset.del));

  renderPg(list.length,pages);
  updateBulk();
}
function renderPg(total,pages){
  let html=`<button ${regPage<=1?'disabled':''} data-p="1">« First</button>`;
  html+=`<button ${regPage<=1?'disabled':''} data-p="${regPage-1}">Prev</button>`;
  const start=Math.max(1,regPage-2),end=Math.min(pages,start+4);
  for(let i=start;i<=end;i++)html+=`<button class="${i===regPage?'on':''}" data-p="${i}">${i}</button>`;
  html+=`<button ${regPage>=pages?'disabled':''} data-p="${regPage+1}">Next</button>`;
  html+=`<button ${regPage>=pages?'disabled':''} data-p="${pages}">Last »</button>`;
  html+=`<span class="sub" style="margin-left:8px">Page ${regPage} of ${pages} · ${total} records</span>`;
  $('#r-pg').innerHTML=html;
  $$('#r-pg [data-p]').forEach(b=>b.onclick=()=>{regPage=+b.dataset.p;reg()});
}
function updateBulk(){
  const n=regSelected.size;
  $('#r-bulk').style.display=n?'flex':'none';
  $('#r-bulk-n').textContent=n+' selected';
}

/* ============================================================
   Admin — entry create / edit
   ============================================================ */
function updateNewStatus(e){
  const type=$('#ne_type').value,sts=statusesFor(type),cur=e?e.status:'';
  $('#ne_st').innerHTML=sts.map(s=>`<option${s===cur?' selected':''}>${E(s)}</option>`).join('');
}
function openNew(id){
  editId=id||null;
  $('#n-h').textContent=id?'Edit feedback entry':'New feedback entry';
  const e=id?entries.find(x=>x.id===id):null;
  $('#ne_type').value=e?e.type:'Compliment';
  $('#ne_date').value=e?e.date:today();
  $('#ne_name').value=e?e.client:'';
  $('#ne_con').value=e?e.contact:'';
  $('#ne_src').innerHTML=settings.channels.map(c=>`<option${e&&e.src===c?' selected':''}>${E(c)}</option>`).join('');
  $('#ne_as').innerHTML='<option value="">Unassigned</option>'+officers.filter(o=>o.active).map(o=>`<option${e&&e.assignee===o.name?' selected':''}>${E(o.name)}</option>`).join('');
  updateNewStatus(e);
  $('#ne_text').value=e?e.text:'';
  $('#newDlg').showModal();
}
function saveNew(){
  const type=$('#ne_type').value,date=$('#ne_date').value||today();
  const name=$('#ne_name').value.trim(),text=$('#ne_text').value.trim();
  if(!name||!text){toast('Name and message are required');return}
  if(editId){
    const e=entries.find(x=>x.id===editId);
    if(!e)return;
    const changes=[];
    if(e.type!==type)changes.push('type changed');
    if(e.client!==name)changes.push('client changed');
    if(e.text!==text)changes.push('message changed');
    Object.assign(e,{type,date,q:qt(date),client:name,text,src:$('#ne_src').value,contact:$('#ne_con').value.trim(),assignee:$('#ne_as').value,status:$('#ne_st').value,updatedAt:nowISO()});
    (e.history=e.history||[]).push({ts:nowISO(),action:'Entry edited',details:changes.join('; ')||'saved'});
    logAction('Updated entry',e.ref,`Edited ${e.type} for ${name}`);
    toast('Entry updated');
  }else{
    const n=Math.max(0,...entries.filter(e=>e.type===type).map(e=>e.n||0))+1;
    const ref=(type==='Complaint'?'k':'c')+String(n).padStart(3,'0');
    const e={id:uid('e'),type,n,ref,date,q:qt(date),client:name,text,src:$('#ne_src').value,contact:$('#ne_con').value.trim(),assignee:$('#ne_as').value,status:$('#ne_st').value,resp:'',rdate:'',action:'',by:'admin',createdAt:nowISO(),updatedAt:nowISO(),notes:[],history:[]};
    entries.push(e);
    logAction('Created entry',ref,`${type} recorded by admin for ${name}`);
    toast('Entry created');
  }
  saveEntries();
  $('#newDlg').close();
  populateFilters();
  reg();
}

/* ============================================================
   Admin — case detail
   ============================================================ */
function openCase(id){
  caseId=id;
  const e=entries.find(x=>x.id===id);
  if(!e)return;
  $('#d-h').textContent=e.ref+' · '+e.type;
  $('#d-m').textContent=`${e.client} · ${fd(e.date)} · ${e.src}`;
  $('#d_st').innerHTML=statusesFor(e.type).map(s=>`<option${s===e.status?' selected':''}>${E(s)}</option>`).join('');
  $('#d_as').innerHTML='<option value="">Unassigned</option>'+officers.filter(o=>o.active).map(o=>`<option${e.assignee===o.name?' selected':''}>${E(o.name)}</option>`).join('');
  $('#d_re').value=e.resp||'';
  $('#d_ac').value=e.action||'';
  $('#d_note').value='';
  const notes=(e.notes||[]).map(n=>`<li><b>${fd(n.ts.slice(0,10))}</b> ${E(n.text)}</li>`).join('');
  const hist=(e.history||[]).slice().reverse().map(h=>`<li><b>${fd(h.ts.slice(0,10))}</b> ${E(h.action)}${h.details?' · '+E(h.details):''}</li>`).join('');
  $('#d-tl').innerHTML='<label>Case history</label><ul class="tl">'+(hist||notes||'<li>No history yet.</li>')+'</ul>';
  $('#dlg').showModal();
}
function saveCase(){
  const e=entries.find(x=>x.id===caseId);
  if(!e)return;
  const oldStatus=e.status,oldResp=e.resp,oldAs=e.assignee;
  const resp=$('#d_re').value.trim();
  let status=$('#d_st').value;
  if(e.type==='Compliment'){
    if(resp&&status==='Awaiting response')status='Responded';
    if(!resp&&status==='Responded')status='Awaiting response';
  }
  const assignee=$('#d_as').value;
  const action=$('#d_ac').value.trim();
  const note=$('#d_note').value.trim();
  const changes=[];
  if(oldStatus!==status)changes.push(`status ${oldStatus} → ${status}`);
  if(oldResp!==resp)changes.push('response updated');
  if(oldAs!==assignee)changes.push(`assignee → ${assignee||'Unassigned'}`);
  if(action!==e.action)changes.push('corrective action updated');
  e.resp=resp;
  e.status=status;
  e.assignee=assignee;
  e.action=action;
  e.rdate=resp?(e.rdate||today()):'';
  e.updatedAt=nowISO();
  if(note){e.notes.push({ts:nowISO(),text:note});changes.push('note added')}
  (e.history=e.history||[]).push({ts:nowISO(),action:'Case updated',details:changes.join('; ')||'saved'});
  logAction('Updated case',e.ref,changes.join('; ')||'saved');
  saveEntries();
  $('#dlg').close();
  toast('Case updated');
  populateFilters();
  reg();
  if($('#a-ov').classList.contains('on'))ov();
}

/* ============================================================
   Admin — delete
   ============================================================ */
function deleteEntry(id){
  const e=entries.find(x=>x.id===id);
  if(!e)return;
  if(!confirm(`Delete ${e.ref} (${e.type} from ${e.client})? This cannot be undone.`))return;
  entries=entries.filter(x=>x.id!==id);
  regSelected.delete(id);
  logAction('Deleted entry',e.ref,`${e.type} removed`);
  saveEntries();
  populateFilters();
  reg();
  toast('Entry deleted');
}

/* ============================================================
   Admin — officers
   ============================================================ */
function off(){
  const counts={};
  entries.forEach(e=>{if(e.assignee)counts[e.assignee]=(counts[e.assignee]||0)+1});
  $('#o-tb').innerHTML='<tr><th>Name</th><th>Role</th><th>Email</th><th>Active</th><th>Assigned cases</th><th></th></tr>'+
    (officers.map(o=>`<tr><td>${E(o.name)}</td><td>${E(o.role)}</td><td>${E(o.email||'')}</td><td>${o.active?'Yes':'No'}</td><td>${counts[o.name]||0}</td><td><div class="act"><button class="btn s" data-o="${o.id}">Edit</button><button class="btn s" data-odel="${o.id}">Delete</button></div></td></tr>`).join('')
    ||'<tr><td colspan="6" class="em">No officers yet</td></tr>');
  $$('#o-tb [data-o]').forEach(b=>b.onclick=()=>openOfficer(b.dataset.o));
  $$('#o-tb [data-odel]').forEach(b=>b.onclick=()=>deleteOfficer(b.dataset.odel));
}
function openOfficer(id){
  officerId=id||null;
  const o=id?officers.find(x=>x.id===id):null;
  $('#of-h').textContent=id?'Edit officer':'Add officer';
  $('#of-name').value=o?o.name:'';
  $('#of-email').value=o?o.email:'';
  $('#of-role').value=o?o.role:'Officer';
  $('#of-active').checked=o?o.active:true;
  $('#offDlg').showModal();
}
function saveOfficer(){
  const name=$('#of-name').value.trim();
  if(!name){toast('Officer name is required');return}
  if(officerId){
    const o=officers.find(x=>x.id===officerId);
    if(!o)return;
    const old=o.name;
    Object.assign(o,{name,email:$('#of-email').value.trim(),role:$('#of-role').value,active:$('#of-active').checked});
    if(old!==name)entries.forEach(e=>{if(e.assignee===old){e.assignee=name}});
    logAction('Updated officer',name,'Officer details updated');
    toast('Officer updated');
  }else{
    officers.push({id:uid('of'),name,email:$('#of-email').value.trim(),role:$('#of-role').value,active:$('#of-active').checked});
    logAction('Added officer',name,'New officer added');
    toast('Officer added');
  }
  saveOfficers();saveEntries();
  $('#offDlg').close();
  off();populateFilters();
}
function deleteOfficer(id){
  const o=officers.find(x=>x.id===id);
  if(!o)return;
  if(!confirm(`Delete officer "${o.name}"? Cases assigned to them will become unassigned.`))return;
  entries.forEach(e=>{
    if(e.assignee===o.name){
      e.assignee='';e.updatedAt=nowISO();
      (e.history=e.history||[]).push({ts:nowISO(),action:'Officer removed',details:'Reassigned to Unassigned'});
    }
  });
  officers=officers.filter(x=>x.id!==id);
  saveOfficers();saveEntries();
  logAction('Deleted officer',o.name,'Cases unassigned');
  off();populateFilters();reg();
  toast('Officer deleted');
}

/* ============================================================
   Admin — reports
   ============================================================ */
function repList(){
  const df=$('#rep-df').value,dt=$('#rep-dt').value,t=$('#rep-t').value;
  return entries.filter(e=>(!df||e.date>=df)&&(!dt||e.date<=dt)&&(!t||e.type===t));
}
function rep(){
  const list=repList();
  const R=list.filter(e=>e.resp),lag=R.filter(e=>e.rdate).map(e=>dd(e.date,e.rdate));
  const C=list.filter(e=>e.type==='Complaint');
  const openC=C.filter(e=>e.status!=='Resolved');
  const overdue=openC.filter(e=>today()>slaDue(e));
  $('#rep-out').innerHTML=`
    <div class="statgrid" style="margin-top:14px">
      ${stat('Records',list.length)}
      ${stat('Complaints',C.length)}
      ${stat('Compliments',list.length-C.length)}
      ${stat('Response rate',list.length?Math.round(R.length/list.length*100)+'%':'0%')}
      ${stat('Avg response (days)',lag.length?(lag.reduce((a,b)=>a+b,0)/lag.length).toFixed(1):'—')}
      ${stat('Open complaints',openC.length)}
      ${stat('Overdue complaints',overdue.length)}
    </div>
    <div class="g c2" style="margin-top:14px">
      <div><h2>By type</h2><div id="rep-ty"></div></div>
      <div><h2>By quarter</h2><div id="rep-q"></div></div>
      <div><h2>By channel</h2><div id="rep-ch"></div></div>
      <div><h2>By status</h2><div id="rep-st"></div></div>
    </div>`;
  bars($('#rep-ty'),cnt(list,e=>e.type),list.length);
  bars($('#rep-q'),cnt(list,e=>e.q),list.length);
  bars($('#rep-ch'),cnt(list,e=>e.src),list.length);
  bars($('#rep-st'),cnt(list,e=>e.status),list.length);
}

/* ============================================================
   Admin — audit
   ============================================================ */
function aud(){
  const x=($('#aud-x').value||'').toLowerCase(),a=$('#aud-a').value;
  const actions=[...new Set(audit.map(l=>l.action))].sort();
  const cur=$('#aud-a').value;
  $('#aud-a').innerHTML='<option value="">All actions</option>'+actions.map(z=>`<option${z===cur?' selected':''}>${E(z)}</option>`).join('');
  const list=audit.filter(l=>(!a||l.action===a)&&(!x||(l.actor+l.action+l.ref+l.details).toLowerCase().includes(x)));
  $('#aud-tb').innerHTML='<tr><th>When</th><th>Actor</th><th>Action</th><th>Record</th><th>Details</th></tr>'+
    (list.slice(0,300).map(l=>`<tr><td>${new Date(l.ts).toLocaleString()}</td><td>${E(l.actor)}</td><td>${E(l.action)}</td><td>${E(l.ref||'—')}</td><td>${E(l.details||'')}</td></tr>`).join('')
    ||'<tr><td colspan="5" class="em">No audit events yet</td></tr>');
}

/* ============================================================
   Admin — settings
   ============================================================ */
function set(){
  $('#set-org').value=settings.org;
  $('#set-sla').value=settings.slaDays;
  $('#set-ch').value=settings.channels.join(', ');
  $('#set-tc').value=settings.replyCompliment;
  $('#set-tk').value=settings.replyComplaint;
}

/* ============================================================
   CSV / JSON helpers
   ============================================================ */
function downloadCSV(rows,name){
  const csv=rows.map(r=>r.map(v=>'"'+String(v??'').replace(/"/g,'""')+'"').join(',')).join('\r\n');
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const a=document.createElement('a');
  a.href=URL.createObjectURL(blob);a.download=name;a.click();
  setTimeout(()=>URL.revokeObjectURL(a.href),1000);
}
function exportEntries(list,name){
  const rows=[['Reference','Type','Date','Quarter','Client','Message','Channel','Status','Assignee','Response','Response date','Corrective action'],
    ...list.map(e=>[e.ref,e.type,e.date,e.q,e.client,e.text,e.src,e.status,e.assignee||'',e.resp||'',e.rdate||'',e.action||''])];
  downloadCSV(rows,name||'feedback-export.csv');
  toast('CSV export downloaded');
}

/* ============================================================
   Bindings
   ============================================================ */
function bindFilters(){
  ['#r-t','#r-q','#r-s','#r-as','#r-ch','#r-df','#r-dt','#r-sort'].forEach(sel=>{
    $(sel).onchange=()=>{regPage=1;reg()};
  });
  $('#r-x').oninput=()=>{regPage=1;reg()};
}
function bindAdmin(){
  $('#usr-go').onclick=()=>area('user');
  $('#adm-go').onclick=()=>area('admin');
  $('#dn').onclick=()=>area('user');

  $('#r-new').onclick=()=>openNew(null);
  $('#r-exp').onclick=()=>exportEntries(filteredEntries(),'feedback-register.csv');

  $('#bulk-apply').onclick=()=>{
    if(!regSelected.size)return;
    const st=$('#bulk-st').value,as=$('#bulk-as').value;
    if(st==='__none'&&as==='__none'){toast('Choose a status or assignee');return}
    [...regSelected].forEach(id=>{
      const e=entries.find(x=>x.id===id);
      if(!e)return;
      const changes=[];
      if(st!=='__none'){changes.push(`status ${e.status} → ${st}`);e.status=st}
      if(as!=='__none'){const name=as==='__unassigned'?'':as;changes.push(`assignee → ${name||'Unassigned'}`);e.assignee=name}
      e.updatedAt=nowISO();
      (e.history=e.history||[]).push({ts:nowISO(),action:'Bulk update',details:changes.join('; ')});
      logAction('Bulk update',e.ref,changes.join('; '));
    });
    saveEntries();
    regSelected.clear();
    populateFilters();
    reg();
    toast('Bulk update applied');
  };
  $('#bulk-del').onclick=()=>{
    if(!regSelected.size)return;
    if(!confirm(`Delete ${regSelected.size} selected entries?`))return;
    [...regSelected].forEach(id=>{
      const e=entries.find(x=>x.id===id);
      if(!e)return;
      logAction('Deleted entry',e.ref,'Bulk delete');
    });
    entries=entries.filter(e=>!regSelected.has(e.id));
    regSelected.clear();
    saveEntries();
    populateFilters();
    reg();
    toast('Selected entries deleted');
  };
  $('#bulk-clear').onclick=()=>{regSelected.clear();reg()};

  $('#d_sv').onclick=saveCase;
  $('#d_cl').onclick=()=>$('#dlg').close();
  $('#d_del').onclick=()=>{
    const e=entries.find(x=>x.id===caseId);
    if(!e)return;
    if(!confirm(`Delete ${e.ref}?`))return;
    entries=entries.filter(x=>x.id!==caseId);
    logAction('Deleted entry',e.ref,'Deleted from case view');
    saveEntries();
    $('#dlg').close();
    populateFilters();reg();
    toast('Entry deleted');
  };
  $('#d_edit').onclick=()=>{
    const id=caseId;
    $('#dlg').close();
    openNew(id);
  };

  $('#ne_type').onchange=()=>updateNewStatus(null);
  $('#ne_save').onclick=saveNew;
  $('#ne_cl').onclick=()=>$('#newDlg').close();

  $('#o-new').onclick=()=>openOfficer(null);
  $('#of-save').onclick=saveOfficer;
  $('#of-cl').onclick=()=>$('#offDlg').close();

  $('#rep-go').onclick=rep;
  $('#rep-exp').onclick=()=>exportEntries(repList(),'feedback-report.csv');

  $('#aud-x').oninput=aud;
  $('#aud-a').onchange=aud;
  $('#aud-exp').onclick=()=>{
    const rows=[['When','Actor','Action','Record','Details'],...audit.map(l=>[new Date(l.ts).toLocaleString(),l.actor,l.action,l.ref||'',l.details||''])];
    downloadCSV(rows,'audit-trail.csv');
    toast('Audit log exported');
  };

  $('#set-save').onclick=()=>{
    settings.org=$('#set-org').value.trim()||'Public Relations';
    settings.slaDays=Math.max(1,+$('#set-sla').value||5);
    settings.channels=$('#set-ch').value.split(',').map(s=>s.trim()).filter(Boolean);
    if(!settings.channels.length)settings.channels=['Other'];
    saveSettings();
    fillUserChannels();populateFilters();
    toast('Settings saved');
  };
  $('#set-save2').onclick=()=>{
    settings.replyCompliment=$('#set-tc').value;
    settings.replyComplaint=$('#set-tk').value;
    saveSettings();
    toast('Templates saved');
  };

  $('#data-exp').onclick=()=>{
    const data={entries,officers,settings,audit};
    const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);a.download='feedback-backup.json';a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href),1000);
    toast('Backup downloaded');
  };
  $('#data-reset').onclick=()=>{
    if(!confirm('Reset all local data and restore the demo dataset? This cannot be undone.'))return;
    Object.values(KEY).forEach(k=>localStorage.removeItem(k));
    location.reload();
  };
  $('#data-imp').onchange=e=>{
    const f=e.target.files[0];
    if(!f)return;
    const r=new FileReader();
    r.onload=()=>{
      try{
        const d=JSON.parse(r.result);
        if(!Array.isArray(d.entries))throw new Error('Invalid backup file');
        entries=d.entries.map(normalizeEntry);
        officers=Array.isArray(d.officers)?d.officers:officers;
        settings=Object.assign({},SEED_SETTINGS,d.settings||{});
        audit=Array.isArray(d.audit)?d.audit:[];
        saveEntries();saveOfficers();saveSettings();saveAudit();
        populateFilters();fillUserChannels();
        go('a-reg');reg();
        toast('Backup imported');
      }catch(err){toast('Import failed: '+err.message)}
    };
    r.readAsText(f);
  };
}
function bindUser(){
  $('#p-new').onclick=()=>go('u-new');
  $('#n_date').value=today();
  $$('[name=ty]').forEach(r=>r.onchange=()=>{$('#n_ml').textContent=document.querySelector('[name=ty]:checked').value});
  $('#n_go').onclick=()=>{
    const name=$('#n_name').value.trim(),msg=$('#n_msg').value.trim();
    const T=document.querySelector('[name=ty]:checked').value;
    if(!name||!msg){toast('Name and message are required');return}
    const n=Math.max(0,...entries.filter(e=>e.type===T).map(e=>e.n||0))+1;
    const d=$('#n_date').value||today();
    const ref=(T==='Complaint'?'k':'c')+String(n).padStart(3,'0');
    const e={id:uid('e'),type:T,n,ref,date:d,q:qt(d),client:name,text:msg,src:$('#n_src').value,contact:$('#n_con').value.trim(),fwd:'Communications',resp:'',rdate:'',status:T==='Complaint'?'Open':'Awaiting response',action:'',assignee:'',by:uidUser,createdAt:nowISO(),updatedAt:nowISO(),notes:[],history:[]};
    entries.push(e);
    logAction('Submitted feedback',ref,`${T} submitted via user portal`,'Public user');
    saveEntries();
    $('#n_ok').innerHTML=`<div class="msg">Submitted. Your reference number is ${E(ref)}.</div>`;
    $('#n_name').value=$('#n_msg').value=$('#n_con').value='';
    toast('Feedback submitted');
  };
}

/* ============================================================
   Init
   ============================================================ */
function init(){
  loadData();
  buildNav();
  populateFilters();
  bindFilters();
  bindAdmin();
  bindUser();
  fillUserChannels();
  area('admin');
}
init();
</script>
</body></html>