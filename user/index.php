<?php
declare(strict_types=1);

session_start();

/* =========================================================
   AUTH GUARD — only signed-in users may view the dashboard
   (login lives one folder up, at ../index.php)
   ========================================================= */
if (empty($_SESSION['user'])) {
    header('Location: ../index.php', true, 303);
    exit;
}

$user   = $_SESSION['user'];
$name   = (string)($user['name']  ?? 'User');
$email  = (string)($user['email'] ?? '');
$userId = (int)($user['id'] ?? 0);

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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Feedback Intelligence | Management Portal</title>
<link rel="icon" type="image/png" href="../assets/feedback_favicon.png">
<link rel="apple-touch-icon" href="../assets/feedback_favicon.png">
<style>
:root{--blue:#2563eb;--blue2:#1d4ed8;--navy:#12213a;--ink:#172033;--muted:#718096;--line:#e5eaf2;--bg:#f5f7fb;--white:#fff;--green:#15966a;--amber:#c27a12;--red:#d14343;--purple:#7656d6;--shadow:0 8px 24px rgba(20,42,80,.055)}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}button,input,select,textarea{font:inherit}button{cursor:pointer}.app{display:flex;min-height:100vh}.sidebar{width:248px;background:#fff;border-right:1px solid var(--line);padding:22px 15px;display:flex;flex-direction:column;flex-shrink:0}.brand{display:flex;align-items:center;gap:11px;padding:0 9px 25px}.brand-mark{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);display:grid;place-items:center;color:white;font-weight:800;font-size:18px;box-shadow:0 5px 12px #2563eb30}.brand b{display:block;font-size:15px;letter-spacing:-.3px}.brand small{color:var(--muted);font-size:11px}.nav-label{color:#9aa5b5;font-size:10px;font-weight:800;letter-spacing:1.3px;padding:14px 12px 8px}.nav{display:grid;gap:4px}.nav button{border:0;background:transparent;text-align:left;display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:10px;color:#65738a;font-weight:600}.nav button .ico{width:19px;text-align:center;font-size:16px}.nav button.active{background:#edf4ff;color:var(--blue)}.nav button:hover:not(.active){background:#f7f9fc}.nav .count{margin-left:auto;background:#fee2e2;color:#b91c1c;font-size:10px;border-radius:20px;padding:2px 7px}.sidebar-bottom{margin-top:auto}.help-card{background:#f3f7ff;border:1px solid #e2ebff;padding:13px;border-radius:12px;margin:18px 4px}.help-card b{font-size:12px}.help-card p{font-size:11px;color:var(--muted);margin:5px 0 10px}.help-card button{background:#fff;border:1px solid #dbe5f8;color:var(--blue);border-radius:7px;padding:7px 10px;font-size:11px;font-weight:700;width:100%}.profile{display:flex;align-items:center;gap:10px;border-top:1px solid var(--line);padding:17px 7px 0}.avatar{height:34px;width:34px;border-radius:50%;display:grid;place-items:center;background:#dbeafe;color:#1d4ed8;font-weight:800;flex-shrink:0}.profile b{font-size:12px;display:block}.profile small{font-size:11px;color:var(--muted)}.main{min-width:0;flex:1}.topbar{height:74px;background:#fff;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;padding:0 30px;gap:20px;position:sticky;top:0;z-index:10}.crumb{font-size:12px;color:var(--muted)}.crumb b{color:var(--ink)}.top-actions{display:flex;align-items:center;gap:10px}.search{position:relative}.search input{width:245px;padding:10px 13px 10px 36px;border:1px solid var(--line);border-radius:9px;background:#fbfcfe;outline:none}.search span{position:absolute;left:12px;top:8px;color:#8a96a8;font-size:17px}.icon-btn{width:38px;height:38px;border:1px solid var(--line);background:white;border-radius:10px;position:relative;color:#59667b;font-size:17px}.dot{position:absolute;right:7px;top:6px;width:7px;height:7px;background:#ef4444;border-radius:50%;border:1px solid white}.content{padding:28px 30px 42px;max-width:1600px;margin:auto}.page{display:none}.page.active{display:block}.page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:22px}.eyebrow{color:var(--blue);font-size:11px;font-weight:800;letter-spacing:1.1px;text-transform:uppercase}.page-head h1{font-size:25px;letter-spacing:-.7px;margin:4px 0 5px}.page-head p{margin:0;color:var(--muted);font-size:13px}.actions{display:flex;gap:9px;flex-wrap:wrap}.btn{border:1px solid var(--line);background:#fff;color:#475569;padding:10px 13px;border-radius:9px;font-weight:700;font-size:12px;display:inline-flex;align-items:center;gap:7px}.btn.primary{background:var(--blue);color:white;border-color:var(--blue);box-shadow:0 4px 10px #2563eb20}.btn.primary:hover{background:var(--blue2)}.btn.small{padding:7px 10px;font-size:11px}.btn[disabled]{opacity:.55;cursor:not-allowed}.grid{display:grid;gap:16px}.metrics{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:17px}.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);min-width:0}.metric{padding:18px 19px;position:relative;overflow:hidden}.metric-top{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:12px;font-weight:600}.metric-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:10px;font-size:17px}.i-blue{background:#edf4ff;color:#2563eb}.i-green{background:#e6f8f0;color:#15966a}.i-amber{background:#fff5df;color:#bd7b14}.i-red{background:#fff0f0;color:#d14343}.metric-num{font-size:29px;font-weight:800;letter-spacing:-1px;margin:12px 0 5px}.metric-foot{font-size:11px;color:var(--muted)}.positive{color:var(--green);font-weight:800}.negative{color:var(--red);font-weight:800}.midgrid{grid-template-columns:minmax(0,1.55fr) minmax(300px,1fr);margin-bottom:17px}.card-head{padding:18px 20px 0;display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.card-head h2{font-size:14px;margin:0;font-weight:800;letter-spacing:-.2px}.card-head p{font-size:11px;color:var(--muted);margin:4px 0 0}.select{border:1px solid var(--line);border-radius:8px;background:#fff;padding:7px 9px;color:#64748b;font-size:11px;outline:none}.chart-area{padding:12px 17px 16px}.chart-legend{display:flex;gap:16px;font-size:11px;color:var(--muted);padding:2px 3px 10px}.legend-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px}.linechart{width:100%;height:200px;display:block}.chart-label{font-size:10px;fill:#8b97a8}.chart-grid{stroke:#edf0f5;stroke-width:1}.donut-wrap{display:flex;align-items:center;justify-content:center;gap:25px;padding:20px 15px 22px;min-height:190px}.donut{width:138px;height:138px;border-radius:50%;background:conic-gradient(#2563eb 0 44%,#f59e0b 44% 72%,#15a36d 72% 91%,#9aa9bd 91% 100%);position:relative;flex-shrink:0}.donut:after{content:"";position:absolute;inset:21px;background:white;border-radius:50%}.donut-center{position:absolute;inset:0;z-index:1;display:flex;align-items:center;justify-content:center;flex-direction:column}.donut-center strong{font-size:23px;letter-spacing:-1px}.donut-center span{font-size:10px;color:var(--muted)}.legend-list{display:grid;gap:12px;min-width:120px}.legend-row{display:flex;align-items:center;gap:7px;font-size:11px;color:#66758a}.legend-row b{margin-left:auto;color:var(--ink);font-size:11px}.legend-row .legend-dot{flex-shrink:0}.bottomgrid{grid-template-columns:minmax(0,1.5fr) minmax(290px,1fr)}.table-card{overflow:hidden}.table-scroll{overflow:auto}.table-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:15px 20px 12px}.table-tools input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;min-width:190px;outline:none}.table-tools select{padding:8px;border:1px solid var(--line);border-radius:8px;background:white;color:#64748b;font-size:11px}.table{width:100%;border-collapse:collapse;white-space:nowrap}.table th{text-align:left;color:#8a96a8;background:#fbfcfe;font-size:10px;letter-spacing:.6px;text-transform:uppercase;padding:12px 17px;border-top:1px solid #f0f2f6;border-bottom:1px solid #edf0f5}.table td{padding:14px 17px;border-bottom:1px solid #f0f2f6;font-size:11px}.table tr:last-child td{border-bottom:0}.table tbody tr:hover{background:#fafcff}.ref{color:var(--blue);font-weight:800}.person{display:flex;align-items:center;gap:9px}.mini-avatar{width:29px;height:29px;border-radius:9px;display:grid;place-items:center;background:#edf2ff;color:#405acb;font-size:10px;font-weight:800}.person b{display:block;font-size:11px}.person small{display:block;color:var(--muted);font-size:10px}.pill{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 8px;font-size:10px;font-weight:800}.pill:before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor}.p-open{background:#fff5df;color:#b7791f}.p-progress{background:#edf4ff;color:#2563eb}.p-resolved{background:#e6f8f0;color:#14865d}.p-urgent{background:#fff0f0;color:#c83232}.p-low{background:#f0f3f7;color:#64748b}.attention{padding:0 20px 18px}.attention-item{display:flex;gap:11px;padding:14px 0;border-bottom:1px solid #f0f2f6}.attention-item:last-child{border:0}.attention-icon{width:31px;height:31px;flex-shrink:0;border-radius:9px;background:#fff0f0;color:#d14343;display:grid;place-items:center}.attention-item b{font-size:11px;display:block}.attention-item p{margin:3px 0 5px;font-size:10px;color:var(--muted)}.attention-item small{font-size:10px;color:#b7791f;font-weight:700}.insight-grid{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:17px}.insight{padding:18px}.insight .label{font-size:11px;color:var(--muted);font-weight:700}.insight .big{font-size:22px;font-weight:800;margin:8px 0 3px}.insight p{font-size:11px;color:var(--muted);margin:0}.insight .tagline{margin-top:12px;font-size:10px;color:var(--green);font-weight:800}.bar-list{padding:18px 20px}.bar-row{display:grid;grid-template-columns:125px 1fr 30px;align-items:center;gap:10px;margin:15px 0;font-size:11px}.bar-track{height:8px;background:#edf1f7;border-radius:20px;overflow:hidden}.bar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#60a5fa,#2563eb)}.ai-note{background:#f5f8ff;border:1px solid #e2ebff;padding:15px;border-radius:11px;display:flex;gap:11px;margin:15px 20px 20px}.ai-spark{width:30px;height:30px;display:grid;place-items:center;border-radius:9px;background:#e4edff;color:var(--blue);flex-shrink:0}.ai-note b{font-size:11px}.ai-note p{font-size:11px;color:#62718a;margin:4px 0 0}.sectiongrid{grid-template-columns:1fr 1fr}.report-card{padding:20px;display:flex;gap:15px;align-items:flex-start}.report-icon{width:44px;height:44px;border-radius:12px;background:#edf4ff;color:var(--blue);display:grid;place-items:center;font-size:20px;flex-shrink:0}.report-card h3{margin:1px 0 5px;font-size:13px}.report-card p{margin:0 0 12px;font-size:11px;color:var(--muted)}.report-card .btn{margin-right:5px;margin-bottom:5px}.notification-panel{display:none;position:absolute;right:30px;top:63px;width:330px;background:white;border:1px solid var(--line);box-shadow:0 16px 40px #10204020;border-radius:13px;padding:15px;z-index:20}.notification-panel.open{display:block}.notification-panel h3{margin:0 0 9px;font-size:13px}.notification{padding:11px 2px;border-top:1px solid #eef1f6;display:flex;gap:9px}.notification b{font-size:11px;display:block}.notification p{font-size:10px;color:var(--muted);margin:3px 0}.notification .n-dot{width:7px;height:7px;border-radius:50%;background:var(--blue);margin-top:5px;flex-shrink:0}.toast{position:fixed;right:24px;bottom:24px;background:#14233b;color:white;padding:12px 16px;border-radius:10px;box-shadow:var(--shadow);z-index:99;display:none;font-size:12px;max-width:min(420px,calc(100vw - 32px))}.toast.show{display:block}.mobile-menu{display:none}.empty{padding:28px;text-align:center;color:var(--muted)}.muted{color:var(--muted)}.nowrap{white-space:nowrap}
.modal-backdrop{position:fixed;inset:0;background:#0f1e3526;display:none;align-items:center;justify-content:center;z-index:60;padding:18px}
.modal-backdrop.open{display:flex}
.modal{background:#fff;border-radius:14px;box-shadow:0 24px 60px #10204033;width:min(540px,100%);max-height:calc(100vh - 36px);overflow:auto}
.modal-head{padding:18px 20px 0;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.modal-head h2{font-size:15px;margin:0}
.modal-head p{font-size:11px;color:var(--muted);margin:4px 0 0}
.modal-body{padding:16px 20px;display:grid;gap:12px}
.modal-foot{padding:0 20px 18px;display:flex;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.field label{display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:5px}
.field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:9px 11px;outline:none;background:#fbfcfe;font-size:12px;color:var(--ink)}
.field textarea{resize:vertical;min-height:74px}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#a9c4f7;background:#fff;box-shadow:0 0 0 3px #2563eb14}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.detail{font-size:12px;padding:9px 11px;background:#f8fafc;border:1px solid var(--line);border-radius:9px;color:#334155;word-break:break-word}
.close-x{border:0;background:transparent;color:#94a3b8;font-size:20px;line-height:1;padding:4px 8px;border-radius:8px}
.close-x:hover{background:#f1f5f9;color:#475569}
@media(max-width:1120px){.sidebar{width:215px}.content{padding:23px 20px}.topbar{padding:0 20px}.metrics{grid-template-columns:repeat(2,1fr)}.midgrid,.bottomgrid{grid-template-columns:1fr}.search input{width:190px}}
@media(max-width:720px){.app{display:block}.sidebar{display:none;position:fixed;inset:0 auto 0 0;z-index:30;width:260px;box-shadow:12px 0 35px #10204025}.sidebar.show{display:flex}.topbar{height:64px;padding:0 14px}.mobile-menu{display:inline-flex}.crumb{display:none}.search{display:none}.content{padding:19px 13px 32px}.page-head{display:block}.page-head h1{font-size:22px}.page-head .actions{margin-top:15px}.metrics{gap:10px}.metric{padding:14px}.metric-num{font-size:25px}.metric-top{font-size:10px}.metric-icon{width:29px;height:29px}.midgrid,.bottomgrid,.insight-grid,.sectiongrid{grid-template-columns:1fr}.donut-wrap{gap:16px}.table-tools{padding:13px}.table-tools input{min-width:100%;width:100%}.table-tools select{flex:1}.notification-panel{right:8px;top:57px;width:min(330px,calc(100vw - 16px))}.brand{padding-bottom:15px}.form-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar" id="sidebar">
  <div class="brand"><div class="brand-mark">F</div><div><b>FeedbackIQ</b><small>Management Portal</small></div></div>
  <div class="nav-label">WORKSPACE</div>
  <nav class="nav">
    <button class="active" data-page="overview"><span class="ico">▦</span> Executive dashboard</button>
    <button data-page="cases"><span class="ico">▤</span> Feedback register <span class="count">8</span></button>
    <button data-page="insights"><span class="ico">✦</span> AI insights</button>
    <button data-page="reports"><span class="ico">▥</span> Reports centre</button>
    <button data-page="notifications"><span class="ico">♧</span> Notifications <span class="count" id="notifCount">3</span></button>
  </nav>
  <div class="sidebar-bottom">
    <div class="help-card"><b>Need a hand?</b><p>Explore the portal guide or contact your system administrator.</p><button onclick="toast('Help guide preview opened.')">View help guide ↗</button></div>
    <div class="profile">
      <div class="avatar" title="<?= e($name) ?>"><?= e($initials) ?></div>
      <div style="min-width:0;flex:1;overflow:hidden">
        <b style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($name) ?></b>
        <small style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($email) ?></small>
      </div>
      <form method="post" action="../index.php" style="margin:0">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="icon-btn" title="Sign out" aria-label="Sign out" style="width:32px;height:32px;font-size:14px">⏻</button>
      </form>
    </div>
  </div>
</aside>
<div class="main">
<header class="topbar">
  <div style="display:flex;align-items:center;gap:12px"><button class="icon-btn mobile-menu" id="menuBtn" aria-label="Open menu">☰</button><div class="crumb">Workspace&nbsp; / &nbsp;<b id="crumb">Executive dashboard</b></div></div>
  <div class="top-actions">
    <div class="search"><span>⌕</span><input id="globalSearch" placeholder="Search feedback, reference..." aria-label="Search feedback"></div>
    <button class="icon-btn" id="notifBtn" aria-label="Notifications">♧<span class="dot" id="topDot"></span></button>
    <div class="avatar" style="width:36px;height:36px" title="<?= e($name) ?> (<?= e($email) ?>)"><?= e($initials) ?></div>
  </div>
  <div class="notification-panel" id="notificationPanel">
    <div style="display:flex;justify-content:space-between;align-items:center"><h3>Notifications</h3><button class="btn small" onclick="markRead()">Mark all read</button></div>
    <div class="notification"><span class="n-dot"></span><div><b>Complaint nearing deadline</b><p>FB-2026-0142 · Due in 4 hours</p></div></div>
    <div class="notification"><span class="n-dot"></span><div><b>New feedback assigned to you</b><p>FB-2026-0146 · 18 minutes ago</p></div></div>
    <div class="notification"><span class="n-dot"></span><div><b>Monthly report is ready</b><p>September 2026 report · Today</p></div></div>
  </div>
</header>
<main class="content">
<section class="page active" id="page-overview">
  <div class="page-head"><div><div class="eyebrow">Performance at a glance</div><h1><?= e($greeting) ?>, <?= e($name) ?></h1><p>Here’s what’s happening across your feedback channels.</p></div><div class="actions"><button class="btn primary" onclick="openFeedback()">＋ Record feedback</button></div></div>
  <div class="grid metrics">
    <div class="card metric"><div class="metric-top">Total feedback <span class="metric-icon i-blue">▤</span></div><div class="metric-num">1,284</div><div class="metric-foot"><span class="positive">↗ 12.8%</span> vs previous period</div></div>
    <div class="card metric"><div class="metric-top">Open complaints <span class="metric-icon i-red">⚑</span></div><div class="metric-num">38</div><div class="metric-foot"><span class="negative">↑ 4 cases</span> require attention</div></div>
    <div class="card metric"><div class="metric-top">Resolution rate <span class="metric-icon i-green">✓</span></div><div class="metric-num">86.4%</div><div class="metric-foot"><span class="positive">↗ 5.2%</span> vs previous period</div></div>
    <div class="card metric"><div class="metric-top">Avg. resolution time <span class="metric-icon i-amber">◷</span></div><div class="metric-num">2.6 <span style="font-size:14px;color:#718096;font-weight:600">days</span></div><div class="metric-foot"><span class="positive">↓ 0.8 days</span> faster this period</div></div>
  </div>
  <div class="grid midgrid">
    <div class="card"><div class="card-head"><div><h2>Feedback volume</h2><p>Submission trends across the selected period</p></div><select class="select" id="trendRange"><option value="week">Last 7 days</option><option value="month" selected>Last 30 days</option><option value="year">This year</option></select></div><div class="chart-area"><div class="chart-legend"><span><i class="legend-dot" style="background:#2563eb"></i>Submissions</span><span><i class="legend-dot" style="background:#14a36d"></i>Resolved</span></div><svg class="linechart" viewBox="0 0 650 205" preserveAspectRatio="none" role="img" aria-label="Feedback volume and resolved trends"><g class="chart-grid"><line x1="40" y1="25" x2="630" y2="25"/><line x1="40" y1="65" x2="630" y2="65"/><line x1="40" y1="105" x2="630" y2="105"/><line x1="40" y1="145" x2="630" y2="145"/><line x1="40" y1="180" x2="630" y2="180"/></g><g class="chart-label"><text x="8" y="29">80</text><text x="8" y="69">60</text><text x="8" y="109">40</text><text x="8" y="149">20</text><text x="17" y="184">0</text><text x="40" y="199">1 Oct</text><text x="140" y="199">6 Oct</text><text x="240" y="199">11 Oct</text><text x="340" y="199">16 Oct</text><text x="440" y="199">21 Oct</text><text x="540" y="199">26 Oct</text><text x="610" y="199">30 Oct</text></g><path d="M40 124 L95 108 L150 117 L205 83 L260 96 L315 58 L370 73 L425 45 L480 66 L535 35 L590 52 L630 28" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M40 147 L95 138 L150 143 L205 119 L260 130 L315 94 L370 110 L425 83 L480 101 L535 74 L590 87 L630 63" fill="none" stroke="#14a36d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M40 124 L95 108 L150 117 L205 83 L260 96 L315 58 L370 73 L425 45 L480 66 L535 35 L590 52 L630 28 L630 180 L40 180Z" fill="#2563eb" opacity=".06"/></svg></div></div>
    <div class="card"><div class="card-head"><div><h2>Feedback breakdown</h2><p>Distribution by category</p></div><button class="btn small" onclick="showPage('insights')">View insights ↗</button></div><div class="donut-wrap"><div class="donut"><div class="donut-center"><strong>1,284</strong><span>Total cases</span></div></div><div class="legend-list"><div class="legend-row"><i class="legend-dot" style="background:#2563eb"></i>Complaints <b>44%</b></div><div class="legend-row"><i class="legend-dot" style="background:#f59e0b"></i>Enquiries <b>28%</b></div><div class="legend-row"><i class="legend-dot" style="background:#15a36d"></i>Compliments <b>19%</b></div><div class="legend-row"><i class="legend-dot" style="background:#9aa9bd"></i>Suggestions <b>9%</b></div></div></div></div>
  </div>
  <div class="grid bottomgrid">
    <div class="card table-card"><div class="card-head"><div><h2>Recent feedback</h2><p>Latest submissions requiring review</p></div><button class="btn small" onclick="showPage('cases')">View register ↗</button></div><div class="table-scroll"><table class="table"><thead><tr><th>Reference / Category</th><th>Submitted by</th><th>Priority</th><th>Status</th><th>Received</th></tr></thead><tbody id="recentRows"></tbody></table></div></div>
    <div class="card"><div class="card-head"><div><h2>Needs attention</h2><p>Items that may need action</p></div><span class="pill p-urgent">3 urgent</span></div><div class="attention"><div class="attention-item"><div class="attention-icon">!</div><div><b>Complaint approaching SLA</b><p>FB-2026-0142 · Service delivery</p><small>Due in 4 hours</small></div></div><div class="attention-item"><div class="attention-icon" style="background:#fff5df;color:#b7791f">◷</div><div><b>Response overdue</b><p>FB-2026-0138 · General enquiry</p><small>Overdue by 1 day</small></div></div><div class="attention-item"><div class="attention-icon" style="background:#edf4ff;color:#2563eb">↗</div><div><b>Unassigned feedback</b><p>3 cases awaiting an owner</p><small style="color:#2563eb">Assign a responsible officer</small></div></div></div></div>
  </div>
</section>
<section class="page" id="page-cases">
  <div class="page-head"><div><div class="eyebrow">Case management</div><h1>Feedback register</h1><p>Search, prioritise and track feedback from receipt through resolution.</p></div><div class="actions"><button class="btn" onclick="exportCSV()">⇩ Export CSV</button><button class="btn primary" onclick="openFeedback()">＋ Record feedback</button></div></div>
  <div class="grid metrics">
    <div class="card metric"><div class="metric-top">All cases <span class="metric-icon i-blue">▤</span></div><div class="metric-num">1,284</div><div class="metric-foot">Across all channels</div></div><div class="card metric"><div class="metric-top">Open <span class="metric-icon i-amber">◷</span></div><div class="metric-num">38</div><div class="metric-foot">Awaiting resolution</div></div><div class="card metric"><div class="metric-top">High priority <span class="metric-icon i-red">⚑</span></div><div class="metric-num">12</div><div class="metric-foot">Require prompt attention</div></div><div class="card metric"><div class="metric-top">Unassigned <span class="metric-icon" style="background:#f0ebff;color:#7656d6">◎</span></div><div class="metric-num">3</div><div class="metric-foot">Awaiting an owner</div></div>
  </div>
  <div class="card table-card"><div class="table-tools"><input id="caseSearch" placeholder="Search reference, name or topic…"><select id="typeFilter"><option value="">All categories</option><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select><select id="statusFilter"><option value="">All statuses</option><option>Open</option><option>In progress</option><option>Resolved</option></select><select id="priorityFilter"><option value="">All priorities</option><option>High</option><option>Medium</option><option>Low</option></select></div><div class="table-scroll"><table class="table"><thead><tr><th>Reference</th><th>Feedback</th><th>Category</th><th>Priority</th><th>Assigned officer</th><th>Status</th><th>SLA due</th><th>Action</th></tr></thead><tbody id="caseRows"></tbody></table></div><div style="padding:13px 18px;border-top:1px solid #f0f2f6;display:flex;justify-content:space-between;gap:10px;color:#8793a5;font-size:11px"><span id="caseCount">Showing cases</span><span>All matching records shown</span></div></div>
</section>
<section class="page" id="page-insights">
  <div class="page-head"><div><div class="eyebrow">AI-assisted analysis</div><h1>Feedback insights</h1><p>Understand sentiment, recurring themes and emerging service issues.</p></div><div class="actions"><button class="btn" onclick="toast('Insight summary prepared for review.')">⇩ Export summary</button><button class="btn primary" onclick="toast('Analysis refreshed in this preview.')">✦ Refresh analysis</button></div></div>
  <div class="ai-note" style="margin:0 0 17px"><div class="ai-spark">✦</div><div><b>AI insights are illustrative in this preview</b><p>These sample findings demonstrate the proposed experience. A live system needs an approved AI service, access controls and human review before acting on generated insights.</p></div></div>
  <div class="grid insight-grid"><div class="card insight"><div class="label">Overall sentiment</div><div class="big">Mixed-positive</div><p>Based on the illustrative sample feedback.</p><div class="tagline">↗ Positive mentions up 6%</div></div><div class="card insight"><div class="label">Most common theme</div><div class="big">Response delays</div><p>Frequently mentioned in recent complaints.</p><div class="tagline" style="color:#b7791f">Needs operational review</div></div><div class="card insight"><div class="label">Emerging issue</div><div class="big">Follow-up clarity</div><p>Users want clearer status updates after submitting.</p><div class="tagline" style="color:#2563eb">Suggested improvement</div></div></div>
  <div class="grid midgrid"><div class="card"><div class="card-head"><div><h2>Recurring feedback themes</h2><p>Illustrative topic frequency</p></div><span class="pill p-progress">AI-assisted</span></div><div class="bar-list"><div class="bar-row"><span>Response delays</span><div class="bar-track"><div class="bar-fill" style="width:84%"></div></div><b>84</b></div><div class="bar-row"><span>Service access</span><div class="bar-track"><div class="bar-fill" style="width:67%"></div></div><b>67</b></div><div class="bar-row"><span>Communication</span><div class="bar-track"><div class="bar-fill" style="width:53%"></div></div><b>53</b></div><div class="bar-row"><span>Process clarity</span><div class="bar-track"><div class="bar-fill" style="width:41%"></div></div><b>41</b></div><div class="bar-row"><span>Staff courtesy</span><div class="bar-track"><div class="bar-fill" style="width:28%"></div></div><b>28</b></div></div></div><div class="card"><div class="card-head"><div><h2>Recommended actions</h2><p>Suggestions for staff review</p></div></div><div class="attention"><div class="attention-item"><div class="attention-icon" style="background:#edf4ff;color:#2563eb">1</div><div><b>Review cases near their SLA deadline</b><p>Prioritise open complaints and notify owners before due dates.</p><button class="btn small" onclick="showPage('cases')">Review cases →</button></div></div><div class="attention-item"><div class="attention-icon" style="background:#e6f8f0;color:#15966a">2</div><div><b>Improve status updates</b><p>Send a clear acknowledgement and expected response timeframe.</p></div></div><div class="attention-item"><div class="attention-icon" style="background:#fff5df;color:#b7791f">3</div><div><b>Review recurring service issues</b><p>Discuss frequent themes with the responsible departments.</p></div></div></div></div></div>
</section>
<section class="page" id="page-reports">
  <div class="page-head"><div><div class="eyebrow">Data & performance</div><h1>Reports centre</h1><p>Prepare management-ready summaries for operational and executive review.</p></div><div class="actions"><button class="btn primary" onclick="exportCSV()">⇩ Export current register</button></div></div>
  <div class="grid sectiongrid">
    <div class="card report-card"><div class="report-icon">▥</div><div><h3>Executive performance report</h3><p>Summary of submissions, response rate, resolution time and key trends.</p><button class="btn small" onclick="reportPreview('Executive performance report')">Preview report</button><button class="btn small" onclick="exportCSV()">Export CSV</button></div></div>
    <div class="card report-card"><div class="report-icon" style="background:#e6f8f0;color:#15966a">✓</div><div><h3>Complaint resolution report</h3><p>Open, resolved and overdue complaints with ownership and status.</p><button class="btn small" onclick="reportPreview('Complaint resolution report')">Preview report</button><button class="btn small" onclick="exportCSV()">Export CSV</button></div></div>
    <div class="card report-card"><div class="report-icon" style="background:#fff5df;color:#b7791f">◷</div><div><h3>SLA compliance report</h3><p>Cases due, overdue and resolved within the target timeframe.</p><button class="btn small" onclick="reportPreview('SLA compliance report')">Preview report</button></div></div>
    <div class="card report-card"><div class="report-icon" style="background:#f0ebff;color:#7656d6">✦</div><div><h3>Feedback themes & sentiment</h3><p>Recurring themes and sentiment trends for review by management.</p><button class="btn small" onclick="showPage('insights')">Open insights</button></div></div>
  </div>
  <div class="card" style="margin-top:17px"><div class="card-head"><div><h2>Report parameters</h2><p>Choose the reporting period for your next report.</p></div></div><div style="padding:18px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:end"><div><label for="reportFrom" style="display:block;font-size:11px;color:#718096;margin-bottom:5px">From date</label><input type="date" id="reportFrom" class="select" style="padding:9px"></div><div><label for="reportTo" style="display:block;font-size:11px;color:#718096;margin-bottom:5px">To date</label><input type="date" id="reportTo" class="select" style="padding:9px"></div><button class="btn primary" onclick="generateReport()">Generate report</button></div></div>
</section>
<section class="page" id="page-notifications">
  <div class="page-head"><div><div class="eyebrow">Stay informed</div><h1>Notifications</h1><p>Important case updates and reminders for your team.</p></div><div class="actions"><button class="btn" onclick="markRead()">✓ Mark all as read</button></div></div>
  <div class="card"><div class="card-head"><div><h2>Recent activity</h2><p>Sample notifications for this dashboard preview</p></div><select class="select"><option>All notifications</option><option>Unread only</option></select></div><div class="attention" id="fullNotifications"><div class="attention-item"><div class="attention-icon">!</div><div><b>Complaint nearing deadline</b><p>FB-2026-0142 · Service delivery complaint needs attention.</p><small>Today · 10:42 AM</small></div></div><div class="attention-item"><div class="attention-icon" style="background:#edf4ff;color:#2563eb">↗</div><div><b>New feedback assigned to you</b><p>FB-2026-0146 · A new case was assigned to your queue.</p><small>Today · 10:24 AM</small></div></div><div class="attention-item"><div class="attention-icon" style="background:#e6f8f0;color:#15966a">✓</div><div><b>Monthly report is ready</b><p>September performance report is ready for review.</p><small>Today · 09:15 AM</small></div></div></div></div>
</section>
</main>
</div>
</div>

<!-- Record feedback modal -->
<div class="modal-backdrop" id="feedbackModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="feedbackTitle">
    <div class="modal-head">
      <div><h2 id="feedbackTitle">Record feedback</h2><p>Log feedback received through any channel.</p></div>
      <button class="close-x" type="button" onclick="closeFeedback()" aria-label="Close">×</button>
    </div>
    <form id="feedbackForm">
      <div class="modal-body">
        <div class="field"><label for="fName">Submitted by (optional)</label><input id="fName" placeholder="Leave blank to record as Anonymous" autocomplete="off"></div>
        <div class="form-grid">
          <div class="field"><label for="fType">Category</label><select id="fType"><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select></div>
          <div class="field"><label for="fPriority">Priority</label><select id="fPriority"><option>Medium</option><option>High</option><option>Low</option></select></div>
        </div>
        <div class="field"><label for="fSummary">Feedback summary</label><textarea id="fSummary" required placeholder="What was the feedback about?"></textarea></div>
        <div class="field"><label for="fChannel">Channel</label><select id="fChannel"><option>Email</option><option>Website</option><option>Phone</option><option>Walk-in</option><option>Social media</option></select></div>
      </div>
      <div class="modal-foot"><button class="btn" type="button" onclick="closeFeedback()">Cancel</button><button class="btn primary" type="submit">Save feedback</button></div>
    </form>
  </div>
</div>

<!-- Case detail modal -->
<div class="modal-backdrop" id="caseModal">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="caseModalTitle">
    <div class="modal-head">
      <div><h2 id="caseModalTitle">Case details</h2><p id="caseModalRef">—</p></div>
      <button class="close-x" type="button" onclick="closeCase()" aria-label="Close">×</button>
    </div>
    <div class="modal-body" id="caseModalBody"></div>
    <div class="modal-foot"><button class="btn" type="button" onclick="closeCase()">Close</button><button class="btn primary" type="button" id="caseAdvance" onclick="advanceCase()">Mark in progress</button></div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script>
const cases=[
 {ref:"FB-2026-0146",name:"T. Moyo",initials:"TM",summary:"Request for information on service access",type:"Enquiry",priority:"Medium",assignee:"R. Dube",status:"Open",due:"Today, 16:00",date:"18 Oct 2026",channel:"Email"},
 {ref:"FB-2026-0142",name:"P. Ndlovu",initials:"PN",summary:"Delayed response to service request",type:"Complaint",priority:"High",assignee:"M. Chari",status:"In progress",due:"Today, 14:00",date:"18 Oct 2026",channel:"Website"},
 {ref:"FB-2026-0138",name:"Anonymous",initials:"AN",summary:"Follow-up on unresolved enquiry",type:"Complaint",priority:"High",assignee:"Unassigned",status:"Open",due:"Overdue 1 day",date:"17 Oct 2026",channel:"Walk-in"},
 {ref:"FB-2026-0135",name:"S. Dube",initials:"SD",summary:"Positive feedback on staff assistance",type:"Compliment",priority:"Low",assignee:"P. Ncube",status:"Resolved",due:"Completed",date:"17 Oct 2026",channel:"Phone"},
 {ref:"FB-2026-0131",name:"K. Sibanda",initials:"KS",summary:"Suggestion to improve online guidance",type:"Suggestion",priority:"Medium",assignee:"R. Dube",status:"In progress",due:"20 Oct 2026",date:"16 Oct 2026",channel:"LinkedIn"},
 {ref:"FB-2026-0129",name:"J. Muchengeti",initials:"JM",summary:"Concern about process clarity",type:"Complaint",priority:"High",assignee:"Unassigned",status:"Open",due:"21 Oct 2026",date:"16 Oct 2026",channel:"Email"},
 {ref:"FB-2026-0124",name:"A. Chikowore",initials:"AC",summary:"Thank you for the helpful guidance",type:"Compliment",priority:"Low",assignee:"P. Ncube",status:"Resolved",due:"Completed",date:"15 Oct 2026",channel:"Facebook"},
 {ref:"FB-2026-0119",name:"M. Moyo",initials:"MM",summary:"Request for clarification on a procedure",type:"Enquiry",priority:"Medium",assignee:"M. Chari",status:"In progress",due:"22 Oct 2026",date:"14 Oct 2026",channel:"Website"}
];
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]))}
function initialsOf(name){const parts=String(name||'').trim().split(/\s+/).filter(Boolean);if(!parts.length)return 'AN';return (parts[0][0]+(parts[1]?parts[1][0]:'')).toUpperCase()}
function todayLabel(){return new Date().toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'})}
function statusPill(s){return `<span class="pill ${s==='Resolved'?'p-resolved':s==='In progress'?'p-progress':'p-open'}">${escapeHtml(s)}</span>`}
function priorityPill(p){return `<span class="pill ${p==='High'?'p-urgent':p==='Medium'?'p-open':'p-low'}">${escapeHtml(p)}</span>`}
function renderRecent(){
  document.getElementById('recentRows').innerHTML=cases.slice(0,5).map(c=>`<tr>
    <td><span class="ref">${escapeHtml(c.ref)}</span><div class="muted" style="font-size:10px;margin-top:3px">${escapeHtml(c.type)}</div></td>
    <td><div class="person"><div class="mini-avatar">${escapeHtml(c.initials)}</div><div><b>${escapeHtml(c.name)}</b><small>${escapeHtml(c.channel)}</small></div></div></td>
    <td>${priorityPill(c.priority)}</td><td>${statusPill(c.status)}</td><td>${escapeHtml(c.date)}</td></tr>`).join('')
    ||'<tr><td colspan="5" class="empty">No feedback recorded yet.</td></tr>';
}
function renderCases(){
  const q=(document.getElementById('caseSearch').value||'').toLowerCase();
  const type=document.getElementById('typeFilter').value;
  const status=document.getElementById('statusFilter').value;
  const priority=document.getElementById('priorityFilter').value;
  const list=cases.filter(c=>(!q||[c.ref,c.name,c.summary,c.assignee,c.type].join(' ').toLowerCase().includes(q))
    &&(!type||c.type===type)&&(!status||c.status===status)&&(!priority||c.priority===priority));
  document.getElementById('caseRows').innerHTML=list.map(c=>`<tr>
    <td><span class="ref">${escapeHtml(c.ref)}</span><div class="muted" style="font-size:10px;margin-top:3px">${escapeHtml(c.date)}</div></td>
    <td style="min-width:210px;max-width:260px;white-space:normal">${escapeHtml(c.summary)}</td>
    <td>${escapeHtml(c.type)}</td><td>${priorityPill(c.priority)}</td><td>${escapeHtml(c.assignee)}</td>
    <td>${statusPill(c.status)}</td>
    <td><span style="color:${c.due.indexOf('Overdue')===0?'#d14343':'#64748b'}">${escapeHtml(c.due)}</span></td>
    <td><button class="btn small" onclick="openCase('${escapeHtml(c.ref)}')">Open case</button></td></tr>`).join('')
    ||'<tr><td colspan="8" class="empty">No cases match your filters.</td></tr>';
  document.getElementById('caseCount').textContent=`Showing ${list.length} of ${cases.length} cases`;
}
function showPage(name){
  document.querySelectorAll('.page').forEach(p=>p.classList.toggle('active',p.id==='page-'+name));
  document.querySelectorAll('[data-page]').forEach(b=>b.classList.toggle('active',b.dataset.page===name));
  const labels={overview:'Executive dashboard',cases:'Feedback register',insights:'AI insights',reports:'Reports centre',notifications:'Notifications'};
  document.getElementById('crumb').textContent=labels[name]||name;
  document.getElementById('sidebar').classList.remove('show');
  document.getElementById('notificationPanel').classList.remove('open');
  if(name==='cases')renderCases();
  window.scrollTo({top:0,behavior:'smooth'});
}
document.querySelectorAll('[data-page]').forEach(b=>b.addEventListener('click',()=>showPage(b.dataset.page)));
['caseSearch','typeFilter','statusFilter','priorityFilter'].forEach(id=>{
  document.getElementById(id).addEventListener(id==='caseSearch'?'input':'change',renderCases);
});
document.getElementById('globalSearch').addEventListener('input',e=>{
  document.getElementById('caseSearch').value=e.target.value;
  showPage('cases');
  renderCases();
});
document.getElementById('notifBtn').addEventListener('click',()=>document.getElementById('notificationPanel').classList.toggle('open'));
document.getElementById('menuBtn').addEventListener('click',()=>document.getElementById('sidebar').classList.toggle('show'));
document.addEventListener('click',e=>{
  if(!e.target.closest('#notifBtn')&&!e.target.closest('#notificationPanel'))document.getElementById('notificationPanel').classList.remove('open');
});
function markRead(){
  document.querySelectorAll('.notification .n-dot').forEach(n=>n.style.display='none');
  const c=document.getElementById('notifCount'); if(c)c.style.display='none';
  const d=document.getElementById('topDot'); if(d)d.style.display='none';
  toast('Notifications marked as read.');
}
let nextRef=147;
function openFeedback(){
  document.getElementById('feedbackModal').classList.add('open');
  setTimeout(()=>document.getElementById('fName').focus(),30);
}
function closeFeedback(){
  document.getElementById('feedbackModal').classList.remove('open');
  document.getElementById('feedbackForm').reset();
}
document.getElementById('feedbackForm').addEventListener('submit',e=>{
  e.preventDefault();
  const name=document.getElementById('fName').value.trim();
  const summary=document.getElementById('fSummary').value.trim();
  if(!summary){toast('Please add a short summary of the feedback.');return}
  const ref='FB-2026-'+String(nextRef++).padStart(4,'0');
  cases.unshift({
    ref,
    name:name||'Anonymous',
    initials:initialsOf(name),
    summary,
    type:document.getElementById('fType').value,
    priority:document.getElementById('fPriority').value,
    assignee:'Unassigned',
    status:'Open',
    due:'Within 5 days',
    date:todayLabel(),
    channel:document.getElementById('fChannel').value
  });
  closeFeedback();
  renderCases();
  renderRecent();
  showPage('cases');
  toast(`${ref} recorded and added to the register.`);
});
let activeRef=null;
function openCase(ref){
  const c=cases.find(x=>x.ref===ref);
  if(!c)return;
  activeRef=ref;
  document.getElementById('caseModalRef').textContent=`${c.ref} · ${c.type} · received ${c.date}`;
  document.getElementById('caseModalBody').innerHTML=`
    <div class="field"><label>Summary</label><div class="detail">${escapeHtml(c.summary)}</div></div>
    <div class="form-grid">
      <div class="field"><label>Submitted by</label><div class="detail">${escapeHtml(c.name)} (${escapeHtml(c.channel)})</div></div>
      <div class="field"><label>Priority</label><div class="detail">${escapeHtml(c.priority)}</div></div>
      <div class="field"><label>Assigned officer</label><div class="detail">${escapeHtml(c.assignee)}</div></div>
      <div class="field"><label>Status</label><div class="detail">${escapeHtml(c.status)}</div></div>
      <div class="field"><label>SLA due</label><div class="detail">${escapeHtml(c.due)}</div></div>
      <div class="field"><label>Received</label><div class="detail">${escapeHtml(c.date)}</div></div>
    </div>`;
  const btn=document.getElementById('caseAdvance');
  btn.disabled=c.status==='Resolved';
  btn.textContent=c.status==='Resolved'?'Resolved':c.status==='Open'?'Mark in progress':'Mark resolved';
  document.getElementById('caseModal').classList.add('open');
}
function closeCase(){
  document.getElementById('caseModal').classList.remove('open');
  activeRef=null;
}
function advanceCase(){
  const c=cases.find(x=>x.ref===activeRef);
  if(!c)return;
  if(c.status==='Open'){
    c.status='In progress';
    if(c.assignee==='Unassigned')c.assignee='Communications Team';
  }else if(c.status==='In progress'){
    c.status='Resolved';
    c.due='Completed';
  }
  renderCases();
  renderRecent();
  openCase(c.ref);
  toast(`${c.ref} updated to ${c.status}.`);
}
function toast(message){
  const t=document.getElementById('toast');
  t.textContent=message;
  t.classList.add('show');
  clearTimeout(window.toastTimer);
  window.toastTimer=setTimeout(()=>t.classList.remove('show'),2800);
}
function exportCSV(){
  const rows=[['Reference','Name','Summary','Category','Priority','Assignee','Status','Due','Date','Channel'],
    ...cases.map(c=>[c.ref,c.name,c.summary,c.type,c.priority,c.assignee,c.status,c.due,c.date,c.channel])];
  downloadCSV(rows,'feedback-register.csv');
  toast('CSV export downloaded.');
}
function downloadCSV(rows,name){
  const csv=rows.map(r=>r.map(v=>'"'+String(v).replace(/"/g,'""')+'"').join(',')).join('\r\n');
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob);
  const a=document.createElement('a');
  a.href=url;a.download=name;a.click();
  URL.revokeObjectURL(url);
}
function reportPreview(name){toast(`${name}: preview uses the sample data in this dashboard.`)}
function generateReport(){
  const from=document.getElementById('reportFrom').value;
  const to=document.getElementById('reportTo').value;
  if(from&&to&&from>to){toast('The "from" date must be before the "to" date.');return}
  exportCSV();
  toast(`Report generated${from?` from ${from}`:''}${to?` to ${to}`:''}.`);
}
document.getElementById('trendRange').addEventListener('change',e=>{
  toast(`Trend selector set to ${e.target.options[e.target.selectedIndex].text}. Chart uses illustrative sample values.`);
});
document.addEventListener('keydown',e=>{
  if(e.key!=='Escape')return;
  closeFeedback();closeCase();
  document.getElementById('notificationPanel').classList.remove('open');
  document.getElementById('sidebar').classList.remove('show');
});
renderRecent();
renderCases();
</script>
</body>
</html>