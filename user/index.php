<?php
declare(strict_types=1);

session_start();

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

$initials = 'U';
$parts    = preg_split('/\s+/', trim($name));
if (!empty($parts[0])) {
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (!empty($parts[1])) $initials .= strtoupper(substr($parts[1], 0, 1));
}

$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

/* =========================================================
   DATABASE
   ========================================================= */
const DB_HOST    = 'localhost';
const DB_NAME    = 'ncc_feedback_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn  = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
    } catch (PDOException $ex) {
        $unknown = ($ex->getCode() === '1049') || (strpos($ex->getMessage(), 'Unknown database') !== false);
        if ($unknown) {
            try {
                $srv = new PDO('mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET, DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $srv->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` ' .
                    'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                $srv = null;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            } catch (PDOException $ex2) { db_fail($ex2->getMessage()); }
        } else { db_fail($ex->getMessage()); }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference    VARCHAR(24) NOT NULL UNIQUE,
            submitted_by INT UNSIGNED NULL,
            name         VARCHAR(120) NOT NULL DEFAULT 'Anonymous',
            type         ENUM('Complaint','Enquiry','Compliment','Suggestion') NOT NULL,
            priority     ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
            summary      TEXT NOT NULL,
            forwarded_to VARCHAR(120) NOT NULL DEFAULT '',
            courtesy_response TEXT NULL,
            channel      VARCHAR(40) NOT NULL DEFAULT 'Website',
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (type), INDEX (priority), INDEX (submitted_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $feedbackColumns = array_column($pdo->query('SHOW COLUMNS FROM feedback')->fetchAll(), 'Field');
    foreach ([
        'forwarded_to' => "VARCHAR(120) NOT NULL DEFAULT ''",
        'courtesy_response' => 'TEXT NULL',
    ] as $column => $definition) {
        if (!in_array($column, $feedbackColumns, true)) {
            $pdo->exec("ALTER TABLE feedback ADD COLUMN $column $definition");
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) PRIMARY KEY,
            v TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ts      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actor   VARCHAR(120) NOT NULL,
            action  VARCHAR(80) NOT NULL,
            ref     VARCHAR(40) NULL,
            details TEXT NULL,
            INDEX (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    return $pdo;
}

function db_fail(string $msg): void
{
    http_response_code(500);
    exit('<h2 style="font-family:system-ui;padding:24px">Database connection failed</h2>' .
         '<p style="font-family:system-ui;padding:0 24px;color:#b00">' .
         htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>');
}

/* =========================================================
   HELPERS
   ========================================================= */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function flash_set(string $type, string $title, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'title' => $title, 'message' => $message];
}

function flash_take(): ?array
{
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

function get_settings(PDO $pdo): array
{
    $out = [
        'org'             => 'Public Relations',
        'channels'        => ['Email','Website','Phone','Walk-in','Social media'],
        'replyCompliment' => '',
        'replyComplaint'  => '',
    ];

    foreach ($pdo->query("SELECT k, v FROM settings") as $row) {
        $out[$row['k']] = $row['v'];
    }

    $raw = $out['channels'] ?? '';
    if (is_array($raw)) {
        $list = $raw;
    } elseif (is_string($raw) && $raw !== '' && $raw[0] === '[') {
        $decoded = json_decode($raw, true);
        $list = is_array($decoded) ? $decoded : [];
    } else {
        $list = is_string($raw) ? explode(',', $raw) : [];
    }
    $out['channels'] = array_values(array_filter(
        array_map(fn($c) => is_scalar($c) ? trim((string)$c) : '', $list),
        fn($c) => $c !== ''
    ));
    if (!$out['channels']) {
        $out['channels'] = ['Website'];
    }

    return $out;
}

function log_action(PDO $pdo, string $actor, string $action, ?string $ref, string $details): void
{
    $st = $pdo->prepare("INSERT INTO audit_log (actor, action, ref, details) VALUES (:a,:ac,:r,:d)");
    $role = (string)($_SESSION['user']['role'] ?? 'user');
    $profile = $role === 'admin' ? 'Administrator' : 'User';
    $st->execute([':a'=>$actor, ':ac'=>$action, ':r'=>$ref, ':d'=>$profile . ' profile · ' . $details]);
}

function next_reference(PDO $pdo, string $type): string
{
    $prefix = $type === 'Complaint' ? 'C' : 'K';
    $year   = date('Y');
    $st = $pdo->prepare("SELECT reference FROM feedback WHERE reference LIKE :p ORDER BY id DESC LIMIT 1");
    $st->execute([':p' => "FB-$year-$prefix%"]);
    $last = (string)($st->fetchColumn() ?: '');
    $n = $last !== '' ? (int)substr($last, -4) + 1 : 1;
    return sprintf('FB-%s-%s%04d', $year, $prefix, $n);
}

function format_date(?string $d): string
{
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d M Y', $ts) : '—';
}

function pill_priority(string $p): string
{
    $cls = $p === 'High' ? 'p-urgent' : ($p === 'Medium' ? 'p-open' : 'p-low');
    return '<span class="pill ' . $cls . '">' . e($p) . '</span>';
}

/* =========================================================
   EXPORT ROUTES
   ========================================================= */
$pdo = db();

if (($_GET['export'] ?? '') === 'csv') {
    $rows = $pdo->query("SELECT reference, name, type, priority, summary, forwarded_to, courtesy_response, channel, created_at FROM feedback ORDER BY created_at DESC")->fetchAll() ?: [];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="feedback-register-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Reference','Client name','Type','Priority','Summary','Forwarded to','Courtesy response','Channel','Created']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['reference'], $r['name'], $r['type'], $r['priority'],
            $r['summary'], $r['forwarded_to'], $r['courtesy_response'], $r['channel'], $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

if (($_GET['export'] ?? '') === 'report') {
    $df = $_GET['df'] ?? '';
    $dt = $_GET['dt'] ?? '';
    $where = '1';
    $params = [];
    if ($df !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
        $where .= ' AND DATE(created_at) >= :df';
        $params[':df'] = $df;
    }
    if ($dt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
        $where .= ' AND DATE(created_at) <= :dt';
        $params[':dt'] = $dt;
    }
    $stmt = $pdo->prepare("SELECT reference, name, type, priority, summary, forwarded_to, courtesy_response, channel, created_at FROM feedback WHERE $where ORDER BY created_at DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $total    = count($rows);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="feedback-report-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Feedback report']);
    fputcsv($out, ['Period', ($df ?: 'start'), 'to', ($dt ?: 'today')]);
    fputcsv($out, []);
    fputcsv($out, ['Total records', $total]);
    fputcsv($out, []);
    fputcsv($out, ['Reference','Client name','Type','Priority','Summary','Forwarded to','Courtesy response','Channel','Created']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['reference'], $r['name'], $r['type'], $r['priority'],
            $r['summary'], $r['forwarded_to'], $r['courtesy_response'], $r['channel'], $r['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

$settings = get_settings($pdo);

/* =========================================================
   POST ACTIONS
   ========================================================= */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $fName    = trim((string)($_POST['name'] ?? '')) ?: 'Anonymous';
        $type     = (string)($_POST['type'] ?? 'Complaint');
        $priority = (string)($_POST['priority'] ?? 'Medium');
        $summary  = trim((string)($_POST['summary'] ?? ''));
        $forwardedTo = trim((string)($_POST['forwarded_to'] ?? ''));
        $courtesyResponse = trim((string)($_POST['courtesy_response'] ?? ''));
        $channel  = (string)($_POST['channel'] ?? 'Website');

        $allowedTypes = ['Complaint','Enquiry','Compliment','Suggestion'];
        $allowedPrio  = ['Low','Medium','High'];
        if (!in_array($type, $allowedTypes, true))     $type = 'Complaint';
        if ($type === 'Complaint' || $type === 'Compliment' || !in_array($priority, $allowedPrio, true)) {
            $priority = 'Medium';
        }

        if ($summary !== '' && mb_strlen($forwardedTo, 'UTF-8') <= 120 && mb_strlen($courtesyResponse, 'UTF-8') <= 10000) {
            $pdo->beginTransaction();
            try {
                $ref = next_reference($pdo, $type);
                $stmt = $pdo->prepare(
                    'INSERT INTO feedback (reference, submitted_by, name, type, priority, summary, forwarded_to, courtesy_response, channel)
                     VALUES (:ref, :by, :name, :type, :priority, :summary, :forwarded_to, :courtesy_response, :channel)'
                );
                $stmt->execute([
                    ':ref'      => $ref,
                    ':by'       => $userId ?: null,
                    ':name'     => $fName,
                    ':type'     => $type,
                    ':priority' => $priority,
                    ':summary'  => $summary,
                    ':forwarded_to' => $forwardedTo,
                    ':courtesy_response' => $courtesyResponse !== '' ? $courtesyResponse : null,
                    ':channel'  => $channel,
                ]);
                log_action($pdo, $name, 'Submitted feedback', $ref, "$type via user portal");
                $pdo->commit();
                flash_set('success', 'Feedback recorded', $ref . ' has been added to the register.');
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                flash_set('error', 'Could not save feedback', $ex->getMessage());
            }
        } elseif ($summary === '') {
            flash_set('error', 'Missing summary', 'Please describe the feedback before submitting.');
        } else {
            flash_set('error', 'Feedback field too long', 'Forwarded to must be 120 characters or fewer and the courtesy response must be 10,000 characters or fewer.');
        }
        header('Location: ?page=cases', true, 303);
        exit;
    }

    if ($action === 'mark_read') {
        log_action($pdo, $name, 'Marked notifications read', null, 'Notifications marked as read');
        $_SESSION['notif_read_at'] = time();
        flash_set('info', 'Notifications', 'All notifications marked as read.');
        header('Location: ?page=notifications', true, 303);
        exit;
    }

    if ($action === 'logout') {
        log_action($pdo, $name, 'Signed out', null, 'User signed out');
        unset($_SESSION['user']);
        session_regenerate_id(true);
        flash_set('info', 'Signed out', 'You have been signed out successfully.');
        header('Location: ../index.php', true, 303);
        exit;
    }
}

/* =========================================================
   LOAD DATA
   ========================================================= */
$all = $pdo->query('SELECT reference, name, type, priority, summary, forwarded_to, courtesy_response, channel, created_at FROM feedback ORDER BY created_at DESC')->fetchAll() ?: [];

$recent = array_slice($all, 0, 5);
$total  = count($all);
$high   = count(array_filter($all, fn($r) => $r['priority'] === 'High'));

$byType = ['Complaint'=>0,'Enquiry'=>0,'Compliment'=>0,'Suggestion'=>0];
foreach ($all as $r) if (isset($byType[$r['type']])) $byType[$r['type']]++;
$typeTotal = max(1, array_sum($byType));
$pct = fn(int $n) => (int)round($n / $typeTotal * 100);

$attention = [];
foreach ($all as $r) {
    if ($r['priority'] === 'High') {
        $attention[] = ['ref'=>$r['reference'],'reason'=>'High priority','note'=>$r['summary'],'kind'=>'urgent'];
    }
}
$attention = array_slice($attention, 0, 3);

$flash     = flash_take();
$notifRead = (int)($_SESSION['notif_read_at'] ?? 0);

$page = (string)($_GET['page'] ?? 'overview');
$validPages = ['overview','cases','insights','ai-insights','reports','notifications'];
if (!in_array($page, $validPages, true)) $page = 'overview';
$crumbLabels = [
    'overview'=>'Executive dashboard','cases'=>'Feedback register',
    'insights'=>'Insights','ai-insights'=>'AI Insights','reports'=>'Reports centre','notifications'=>'Notifications',
];

/* Icons */
$IC = [
    'dashboard'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
    'register' =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16M4 12h16M4 19h10"/></svg>',
    'insights' =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3.4"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg>',
    'reports'  =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="M8 16v-4M12 16v-7M16 16v-2"/></svg>',
    'bell'     =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 5 2 6 2 6H4s2-1 2-6"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
    'search'   =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>',
    'menu'     =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>',
    'power'    =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v9"/><path d="M6.5 6.5a8 8 0 1 0 11 0"/></svg>',
    'plus'     =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
    'download' =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 21h16"/></svg>',
    'check'    =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5 9-10"/></svg>',
    'flag'     =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3v18"/><path d="M5 4h12l-2 4 2 4H5"/></svg>',
    'clock'    =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'layers'   =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/><path d="m3 17 9 5 9-5"/></svg>',
    'x'        =>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
];

/* Flash icons */
$FLASH_ICON = [
    'success' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    'error'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.8v5.4M12 16.6v.01"/></svg>',
    'info'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.2M12 7.8v.01"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Feedback Portal · <?= e($crumbLabels[$page]) ?></title>
<link rel="icon" type="image/png" href="../assets/feedback_favicon.png">
<link rel="apple-touch-icon" href="../assets/feedback_favicon.png">
<style>
:root{
  --blue:#2563eb;--blue2:#1d4ed8;--ink:#172033;--muted:#718096;--line:#e5eaf2;
  --bg:#f5f7fb;--green:#15966a;--red:#d14343;--shadow:0 8px 24px rgba(20,42,80,.06);
}
*{box-sizing:border-box}
html,body{min-height:100%}
body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.5 Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;-webkit-font-smoothing:antialiased}
button,input,select,textarea{font:inherit}
button{cursor:pointer}
svg{display:block}
.icon svg,.ico svg{width:100%;height:100%}

.app{display:flex;min-height:100vh;min-height:100dvh}

.sidebar{
  width:248px;background:#fff;border-right:1px solid var(--line);
  padding:22px 15px;display:flex;flex-direction:column;flex-shrink:0;
  position:sticky;top:0;height:100vh;height:100dvh;overflow-y:auto;
}
.brand{display:flex;align-items:center;gap:11px;padding:0 9px 25px}
.brand-mark{
  width:38px;height:38px;border-radius:12px;flex-shrink:0;
  background:linear-gradient(135deg,#3b82f6,#1d4ed8);
  display:grid;place-items:center;color:#fff;font-weight:800;font-size:18px;
  box-shadow:0 5px 12px rgba(37,99,235,.25);
}
.brand b{display:block;font-size:15px;letter-spacing:-.3px}
.brand small{color:var(--muted);font-size:11px}
.nav-label{color:#9aa5b5;font-size:10px;font-weight:800;letter-spacing:1.3px;padding:14px 12px 8px}
.nav{display:grid;gap:4px}
.nav button{
  border:0;background:transparent;text-align:left;
  display:flex;align-items:center;gap:12px;
  padding:11px 12px;border-radius:10px;color:#65738a;font-weight:600;
}
.nav button .ico{width:19px;height:19px;flex-shrink:0}
.nav button.active{background:#edf4ff;color:var(--blue)}
.nav button:hover:not(.active){background:#f7f9fc}
.nav .count{
  margin-left:auto;background:#fee2e2;color:#b91c1c;
  font-size:10px;border-radius:20px;padding:2px 7px;font-weight:700;
}
.sidebar-bottom{margin-top:auto;padding-top:20px}
.help-card{background:#f3f7ff;border:1px solid #e2ebff;padding:13px;border-radius:12px;margin:0 4px 14px}
.help-card b{font-size:12px}
.help-card p{font-size:11px;color:var(--muted);margin:5px 0 10px;line-height:1.5}
.help-card button{background:#fff;border:1px solid #dbe5f8;color:var(--blue);border-radius:7px;padding:7px 10px;font-size:11px;font-weight:700;width:100%}
.profile{display:flex;align-items:center;gap:10px;border-top:1px solid var(--line);padding:14px 7px 0}
.avatar{
  height:34px;width:34px;border-radius:50%;display:grid;place-items:center;
  background:#dbeafe;color:#1d4ed8;font-weight:800;flex-shrink:0;font-size:12px;
}
.profile-info{min-width:0;flex:1;overflow:hidden}
.profile-info b{display:block;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.profile-info small{display:block;font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.icon-btn{
  width:38px;height:38px;border:1px solid var(--line);background:#fff;
  border-radius:10px;position:relative;color:#59667b;
  display:grid;place-items:center;flex-shrink:0;padding:0;
}
.icon-btn .icon{width:18px;height:18px}
.icon-btn:hover{background:#f7f9fc}

.main{min-width:0;flex:1;display:flex;flex-direction:column}
.topbar{
  height:74px;background:#fff;border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;
  padding:0 30px;gap:20px;position:sticky;top:0;z-index:10;
}
.top-left{display:flex;align-items:center;gap:12px;min-width:0}
.crumb{font-size:12px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.crumb b{color:var(--ink)}
.top-actions{display:flex;align-items:center;gap:10px}
.search{position:relative}
.search .icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#8a96a8;pointer-events:none}
.search input{
  width:245px;padding:10px 13px 10px 34px;border:1px solid var(--line);
  border-radius:9px;background:#fbfcfe;outline:none;font-size:13px;
}
.search input:focus{border-color:#a9c4f7;background:#fff}
.dot{
  position:absolute;right:7px;top:6px;width:7px;height:7px;
  background:#ef4444;border-radius:50%;border:1px solid #fff;
}
.mobile-menu{display:none}

/* Content */
.content{padding:28px 30px 42px;max-width:1600px;margin:0 auto;width:100%}
.page{display:none}
.page.active{display:block}
.page-head{display:flex;justify-content:space-between;align-items:flex-start;gap:18px;margin-bottom:22px;flex-wrap:wrap}
.eyebrow{color:var(--blue);font-size:11px;font-weight:800;letter-spacing:1.1px;text-transform:uppercase}
.page-head h1{font-size:25px;letter-spacing:-.7px;margin:4px 0 5px;line-height:1.2}
.page-head p{margin:0;color:var(--muted);font-size:13px}
.actions{display:flex;gap:9px;flex-wrap:wrap}
.btn{
  border:1px solid var(--line);background:#fff;color:#475569;
  padding:10px 13px;border-radius:9px;font-weight:700;font-size:12px;
  display:inline-flex;align-items:center;gap:7px;text-decoration:none;
}
.btn:hover{background:#f7f9fc;text-decoration:none}
.btn.primary{background:var(--blue);color:#fff;border-color:var(--blue);box-shadow:0 4px 10px rgba(37,99,235,.22)}
.btn.primary:hover{background:var(--blue2)}
.btn.small{padding:7px 10px;font-size:11px}
.btn .icon{width:14px;height:14px}
.btn[disabled]{opacity:.55;cursor:not-allowed}

/* Flash banner */
.flash-banner{
  display:flex;gap:12px;align-items:flex-start;
  padding:14px 16px;border-radius:12px;margin-bottom:18px;
  font-size:13.5px;line-height:1.5;font-weight:500;
  background:#f5f8ff;border:1px solid #e2ebff;border-left:4px solid var(--blue);color:#2c3557;
  box-shadow:0 4px 14px rgba(37,99,235,.08);
  animation:fadeIn .3s cubic-bezier(.2,.9,.3,1);
  position:relative;transition:opacity .25s,transform .25s;
}
.flash-banner.success{background:#eefaf3;border-color:#cdeedb;border-left-color:var(--green);color:#12613e;box-shadow:0 4px 14px rgba(21,150,106,.10)}
.flash-banner.error{background:#fff2f2;border-color:#ffd6d6;border-left-color:var(--red);color:#7a1e1e;box-shadow:0 4px 14px rgba(209,67,67,.10)}
.flash-banner.info{background:#f5f8ff;border-color:#e2ebff;border-left-color:var(--blue);color:#2c3557}
.flash-ico{
  width:22px;height:22px;flex-shrink:0;border-radius:6px;
  display:grid;place-items:center;margin-top:1px;
}
.flash-banner.success .flash-ico{background:#cdeedb;color:var(--green)}
.flash-banner.error .flash-ico{background:#ffd6d6;color:var(--red)}
.flash-banner.info .flash-ico{background:#e2ebff;color:var(--blue)}
.flash-ico svg{width:14px;height:14px}
.flash-body{flex:1;min-width:0}
.flash-body strong{display:block;font-weight:600;margin-bottom:2px;font-size:13px}
.flash-close{
  all:unset;cursor:pointer;width:24px;height:24px;border-radius:6px;
  display:grid;place-items:center;color:currentColor;opacity:.55;
  flex-shrink:0;transition:opacity .15s,background .15s;
}
.flash-close:hover{opacity:1;background:rgba(0,0,0,.05)}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}

/* Grid / cards */
.grid{display:grid;gap:16px}
.metrics{grid-template-columns:repeat(4,minmax(0,1fr));margin-bottom:17px}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow);min-width:0}
.metric{padding:18px 19px;position:relative;overflow:hidden}
.metric-top{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:12px;font-weight:600;gap:8px}
.metric-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:10px}
.metric-icon .icon{width:18px;height:18px}
.i-blue{background:#edf4ff;color:#2563eb}
.i-green{background:#e6f8f0;color:#15966a}
.i-amber{background:#fff5df;color:#bd7b14}
.i-red{background:#fff0f0;color:#d14343}
.metric-num{font-size:29px;font-weight:800;letter-spacing:-1px;margin:12px 0 5px;line-height:1.1}
.metric-num small{font-size:14px;color:var(--muted);font-weight:600;margin-left:4px}
.metric-foot{font-size:11px;color:var(--muted)}
.positive{color:var(--green);font-weight:800}
.negative{color:var(--red);font-weight:800}

.midgrid{grid-template-columns:minmax(0,1.55fr) minmax(280px,1fr);margin-bottom:17px}
.bottomgrid{grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr)}
.card-head{padding:18px 20px 0;display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}
.card-head h2{font-size:14px;margin:0;font-weight:800;letter-spacing:-.2px}
.card-head p{font-size:11px;color:var(--muted);margin:4px 0 0}
.select{border:1px solid var(--line);border-radius:8px;background:#fff;padding:7px 9px;color:#64748b;font-size:11px;outline:none}

.chart-area{padding:12px 17px 16px}
.chart-legend{display:flex;gap:16px;font-size:11px;color:var(--muted);padding:2px 3px 10px;flex-wrap:wrap}
.legend-dot{display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:5px;vertical-align:middle}
.linechart{width:100%;height:200px;display:block}
.chart-label{font-size:10px;fill:#8b97a8}
.chart-grid{stroke:#edf0f5;stroke-width:1}

.donut-wrap{display:flex;align-items:center;justify-content:center;gap:25px;padding:20px 15px 22px;min-height:190px;flex-wrap:wrap}
.donut{width:138px;height:138px;border-radius:50%;position:relative;flex-shrink:0}
.donut:after{content:"";position:absolute;inset:21px;background:white;border-radius:50%}
.donut-center{position:absolute;inset:0;z-index:1;display:flex;align-items:center;justify-content:center;flex-direction:column}
.donut-center strong{font-size:23px;letter-spacing:-1px}
.donut-center span{font-size:10px;color:var(--muted)}
.legend-list{display:grid;gap:12px;min-width:150px}
.legend-row{display:flex;align-items:center;gap:7px;font-size:11px;color:#66758a}
.legend-row b{margin-left:auto;color:var(--ink);font-size:11px}

/* Tables */
.table-card{overflow:hidden}
.table-scroll{overflow-x:auto}
.table-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:15px 20px 12px}
.table-tools input{padding:8px 10px;border:1px solid var(--line);border-radius:8px;min-width:190px;outline:none;font-size:12px}
.table-tools select{padding:8px;border:1px solid var(--line);border-radius:8px;background:#fff;color:#64748b;font-size:11px}
.table{width:100%;border-collapse:collapse;white-space:nowrap;min-width:640px}
.table th{text-align:left;color:#8a96a8;background:#fbfcfe;font-size:10px;letter-spacing:.6px;text-transform:uppercase;padding:12px 17px;border-top:1px solid #f0f2f6;border-bottom:1px solid #edf0f5}
.table td{padding:14px 17px;border-bottom:1px solid #f0f2f6;font-size:11px}
.table tr:last-child td{border-bottom:0}
.table tbody tr:hover{background:#fafcff}
.ref{color:var(--blue);font-weight:800}
.person{display:flex;align-items:center;gap:9px}
.mini-avatar{width:29px;height:29px;border-radius:9px;display:grid;place-items:center;background:#edf2ff;color:#405acb;font-size:10px;font-weight:800;flex-shrink:0}
.person b{display:block;font-size:11px}
.person small{display:block;color:var(--muted);font-size:10px}

.pill{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 8px;font-size:10px;font-weight:800}
.pill:before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor}
.p-open{background:#fff5df;color:#b7791f}
.p-progress{background:#edf4ff;color:#2563eb}
.p-urgent{background:#fff0f0;color:#c83232}
.p-low{background:#f0f3f7;color:#64748b}

.attention{padding:0 20px 18px}
.attention-item{display:flex;gap:11px;padding:14px 0;border-bottom:1px solid #f0f2f6}
.attention-item:last-child{border:0}
.attention-icon{width:31px;height:31px;flex-shrink:0;border-radius:9px;display:grid;place-items:center;background:#fff0f0;color:#d14343}
.attention-icon .icon{width:16px;height:16px}
.attention-icon.warn{background:#fff5df;color:#b7791f}
.attention-icon.info{background:#edf4ff;color:#2563eb}
.attention-item b{font-size:11px;display:block}
.attention-item p{margin:3px 0 5px;font-size:10px;color:var(--muted)}
.attention-item small{font-size:10px;color:#b7791f;font-weight:700}

/* Insights */
.insight-grid{grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:17px}
.insight{padding:18px}
.insight .label{font-size:11px;color:var(--muted);font-weight:700}
.insight .big{font-size:22px;font-weight:800;margin:8px 0 3px}
.insight p{font-size:11px;color:var(--muted);margin:0}
.insight .tagline{margin-top:12px;font-size:10px;color:var(--green);font-weight:800}
.bar-list{padding:18px 20px}
.bar-row{display:grid;grid-template-columns:125px 1fr 36px;align-items:center;gap:10px;margin:15px 0;font-size:11px}
.bar-track{height:8px;background:#edf1f7;border-radius:20px;overflow:hidden}
.bar-fill{height:100%;border-radius:20px;background:linear-gradient(90deg,#60a5fa,#2563eb)}

/* Reports */
.sectiongrid{grid-template-columns:1fr 1fr}
.report-card{padding:20px;display:flex;gap:15px;align-items:flex-start}
.report-icon{width:44px;height:44px;border-radius:12px;background:#edf4ff;color:var(--blue);display:grid;place-items:center;flex-shrink:0}
.report-icon .icon{width:20px;height:20px}
.report-card h3{margin:1px 0 5px;font-size:13px}
.report-card p{margin:0 0 12px;font-size:11px;color:var(--muted)}
.report-card .btn{margin-right:5px;margin-bottom:5px}

/* Notification dropdown */
.notification-panel{
  display:none;position:absolute;right:30px;top:63px;width:330px;
  background:#fff;border:1px solid var(--line);
  box-shadow:0 16px 40px rgba(16,32,64,.14);
  border-radius:13px;padding:15px;z-index:20;
}
.notification-panel.open{display:block}
.notification-panel h3{margin:0 0 9px;font-size:13px}
.notification{padding:11px 2px;border-top:1px solid #eef1f6;display:flex;gap:9px}
.notification b{font-size:11px;display:block}
.notification p{font-size:10px;color:var(--muted);margin:3px 0}
.notification .n-dot{width:7px;height:7px;border-radius:50%;background:var(--blue);margin-top:5px;flex-shrink:0}

/* Toast */
.toast{
  position:fixed;right:24px;bottom:24px;background:#14233b;color:#fff;
  padding:12px 16px;border-radius:10px;box-shadow:var(--shadow);z-index:99;
  display:none;font-size:12px;max-width:min(420px,calc(100vw - 32px));
}
.toast.show{display:block}

/* AI-assisted local feedback analysis */
.ai-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin-bottom:16px}
.ai-card{border:1px solid var(--line);border-radius:12px;padding:15px;background:#fff;box-shadow:var(--shadow)}
.ai-card .label{font-size:11px;color:var(--muted)}.ai-card .value{font-size:20px;font-weight:700;margin-top:4px}
.ai-note{font-size:12px;color:var(--muted);margin:5px 0 0}
.ai-list{display:grid;gap:10px}
.ai-item{padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#fbfcfe}
.ai-item b{display:block;font-size:13px;margin-bottom:3px}.ai-item p{margin:0;color:var(--muted);font-size:12px}

/* Modal */
.modal-backdrop{
  position:fixed;inset:0;background:rgba(15,30,53,.15);
  display:none;align-items:center;justify-content:center;z-index:60;padding:18px;
}
.modal-backdrop.open{display:flex}
.modal{background:#fff;border-radius:14px;box-shadow:0 24px 60px rgba(16,32,64,.22);width:min(540px,100%);max-height:calc(100vh - 36px);overflow:auto}
.modal-head{padding:18px 20px 0;display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.modal-head h2{font-size:15px;margin:0}
.modal-head p{font-size:11px;color:var(--muted);margin:4px 0 0}
.modal-body{padding:16px 20px;display:grid;gap:12px}
.modal-foot{padding:0 20px 18px;display:flex;justify-content:flex-end;gap:9px;flex-wrap:wrap}
.field label{display:block;font-size:11px;font-weight:700;color:#64748b;margin-bottom:5px}
.field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:9px;padding:9px 11px;outline:none;background:#fbfcfe;font-size:12px;color:var(--ink)}
.field textarea{resize:vertical;min-height:74px}
.field input:focus,.field select:focus,.field textarea:focus{border-color:#a9c4f7;background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.08)}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.close-x{border:0;background:transparent;color:#94a3b8;padding:0;border-radius:8px;width:30px;height:30px;display:grid;place-items:center}
.close-x:hover{background:#f1f5f9;color:#475569}
.close-x .icon{width:18px;height:18px}

/* Sidebar overlay */
.sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(15,30,53,.4);z-index:25}
.sidebar-backdrop.show{display:block}

@media(max-width:1120px){
  .sidebar{width:215px}
  .content{padding:23px 20px}
  .topbar{padding:0 20px}
  .metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
  .midgrid,.bottomgrid,.sectiongrid,.insight-grid{grid-template-columns:1fr}
  .search input{width:190px}
}
@media(max-width:900px){
  .app{display:block}
  .sidebar{display:none;position:fixed;inset:0 auto 0 0;z-index:30;width:260px;box-shadow:12px 0 35px rgba(16,32,64,.15);border-right:1px solid var(--line);height:100dvh}
  .sidebar.show{display:flex}
  .mobile-menu{display:inline-flex}
  .topbar{height:64px;padding:0 14px}
  .crumb{display:none}
  .search{display:none}
  .content{padding:18px 14px 40px}
  .page-head{display:block}
  .page-head h1{font-size:21px}
  .page-head .actions{margin-top:14px}
  .actions{width:100%}
  .actions .btn{flex:1;justify-content:center}
  .notification-panel{right:8px;left:8px;top:57px;width:auto}
  .donut-wrap{gap:16px}
  .table-tools{padding:13px}
  .table-tools input{min-width:100%;width:100%}
  .table-tools select{flex:1;min-width:0}
  .form-grid{grid-template-columns:1fr}
  .modal{width:100%;border-radius:12px}
  .modal-backdrop{padding:10px}
}
@media(max-width:520px){
  .metrics{grid-template-columns:1fr;gap:10px}
  .metric{padding:14px 16px}
  .metric-num{font-size:25px}
  .metric-top{font-size:11px}
  .metric-icon{width:30px;height:30px}
  .content{padding:16px 12px 32px}
  .page-head h1{font-size:19px}
  .btn{padding:10px 12px}
  .btn.small{padding:7px 9px}
  .pill{padding:3px 7px;font-size:9px}
  .table{min-width:560px}
  .table th,.table td{padding:10px 12px}
  .insight .big{font-size:19px}
  .bar-row{grid-template-columns:100px 1fr 30px;font-size:10px}
  .toast{left:12px;right:12px;bottom:12px;max-width:none}
}
</style>
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><div class="brand-mark">F</div><div><b>FeedbackIQ</b><small>Management Portal</small></div></div>
    <div class="nav-label">WORKSPACE</div>
    <nav class="nav" aria-label="Workspace">
      <button class="<?= $page === 'overview' ? 'active' : '' ?>" data-page="overview"><span class="ico"><?= $IC['dashboard'] ?></span>Executive dashboard</button>
      <button class="<?= $page === 'cases' ? 'active' : '' ?>" data-page="cases"><span class="ico"><?= $IC['register'] ?></span>Feedback register</button>
      <button class="<?= $page === 'insights' ? 'active' : '' ?>" data-page="insights"><span class="ico"><?= $IC['insights'] ?></span>Insights</button>
      <button class="<?= $page === 'ai-insights' ? 'active' : '' ?>" data-page="ai-insights"><span class="ico"><?= $IC['insights'] ?></span>AI Insights</button>
      <button class="<?= $page === 'reports' ? 'active' : '' ?>" data-page="reports"><span class="ico"><?= $IC['reports'] ?></span>Reports centre</button>
      <button class="<?= $page === 'notifications' ? 'active' : '' ?>" data-page="notifications"><span class="ico"><?= $IC['bell'] ?></span>Notifications<?php if (count($attention) > 0 && !$notifRead): ?><span class="count"><?= count($attention) ?></span><?php endif; ?></button>
    </nav>
    <div class="sidebar-bottom">
      <div class="help-card">
        <b>Need a hand?</b>
        <p>Explore the portal guide or contact your system administrator.</p>
        <button type="button" onclick="openHelp()">View help guide</button>
      </div>
      <div class="profile">
        <div class="avatar" title="<?= e($name) ?>"><?= e($initials) ?></div>
        <div class="profile-info">
          <b><?= e($name) ?></b>
          <small><?= e($email) ?></small>
        </div>
        <form method="post" action="" style="margin:0">
          <input type="hidden" name="action" value="logout">
          <button type="submit" class="icon-btn" title="Sign out" aria-label="Sign out" style="width:32px;height:32px">
            <span class="icon"><?= $IC['power'] ?></span>
          </button>
        </form>
      </div>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <div class="top-left">
        <button class="icon-btn mobile-menu" id="menuBtn" aria-label="Open menu">
          <span class="icon"><?= $IC['menu'] ?></span>
        </button>
        <div class="crumb">Workspace / <b id="crumb"><?= e($crumbLabels[$page]) ?></b></div>
      </div>
      <div class="top-actions">
        <div class="search">
          <span class="icon"><?= $IC['search'] ?></span>
          <input id="globalSearch" placeholder="Search feedback or reference" aria-label="Search feedback">
        </div>
        <button class="icon-btn" id="notifBtn" aria-label="Notifications">
          <span class="icon"><?= $IC['bell'] ?></span>
          <?php if (count($attention) > 0 && !$notifRead): ?><span class="dot" id="topDot"></span><?php endif; ?>
        </button>
        <div class="avatar" style="width:36px;height:36px;font-size:12px" title="<?= e($name) ?>"><?= e($initials) ?></div>
      </div>
      <div class="notification-panel" id="notificationPanel">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <h3>Notifications</h3>
          <form method="post" action="" style="margin:0">
            <input type="hidden" name="action" value="mark_read">
            <button type="submit" class="btn small">Mark all read</button>
          </form>
        </div>
        <?php if ($attention): foreach ($attention as $a): ?>
          <div class="notification"><span class="n-dot"<?= $notifRead ? ' style="display:none"' : '' ?>></span><div><b><?= e($a['reason']) ?>: <?= e($a['ref']) ?></b><p><?= e(mb_substr($a['note'], 0, 90)) ?></p></div></div>
        <?php endforeach; else: ?>
          <div class="notification" style="color:#8a96a8">You're all caught up.</div>
        <?php endif; ?>
      </div>
    </header>

    <main class="content">

      <?php if ($flash): $ft = (string)($flash['type'] ?? 'info'); ?>
        <div class="flash-banner <?= e($ft) ?>" role="status" aria-live="polite" data-flash>
          <span class="flash-ico"><?= $FLASH_ICON[$ft] ?? $FLASH_ICON['info'] ?></span>
          <div class="flash-body">
            <strong><?= e($flash['title'] ?? 'Notice') ?></strong>
            <?= e($flash['message'] ?? '') ?>
          </div>
          <button type="button" class="flash-close" aria-label="Dismiss message" data-flash-close>&times;</button>
        </div>
      <?php endif; ?>

      <section class="page <?= $page === 'overview' ? 'active' : '' ?>" id="page-overview">
        <div class="page-head">
          <div>
            <div class="eyebrow">Performance at a glance</div>
            <h1><?= e($greeting) ?>, <?= e($name) ?></h1>
            <p>Here is what is happening across your feedback channels.</p>
          </div>
          <div class="actions">
            <button class="btn primary" onclick="openFeedback()"><span class="icon"><?= $IC['plus'] ?></span>Record feedback</button>
          </div>
        </div>

        <div class="grid metrics">
          <div class="card metric">
            <div class="metric-top">Total feedback<span class="metric-icon i-blue"><span class="icon"><?= $IC['layers'] ?></span></span></div>
            <div class="metric-num"><?= number_format($total) ?></div>
            <div class="metric-foot"><span class="positive"><?= count(array_filter($byType, fn($n) => $n > 0)) ?> categories</span> in register</div>
          </div>
          <div class="card metric">
            <div class="metric-top">Complaints<span class="metric-icon i-red"><span class="icon"><?= $IC['flag'] ?></span></span></div>
            <div class="metric-num"><?= number_format($byType['Complaint']) ?></div>
            <div class="metric-foot">Feedback category</div>
          </div>
          <div class="card metric">
            <div class="metric-top">High priority<span class="metric-icon i-green"><span class="icon"><?= $IC['flag'] ?></span></span></div>
            <div class="metric-num"><?= number_format($high) ?></div>
            <div class="metric-foot">Priority submissions</div>
          </div>
          <div class="card metric">
            <div class="metric-top">Enquiries<span class="metric-icon i-amber"><span class="icon"><?= $IC['insights'] ?></span></span></div>
            <div class="metric-num"><?= number_format($byType['Enquiry']) ?></div>
            <div class="metric-foot">Feedback category</div>
          </div>
        </div>

        <div class="grid midgrid">
          <div class="card">
            <div class="card-head">
              <div><h2>Feedback volume</h2><p>Submission trends across the last months</p></div>
              <select class="select" id="trendRange"><option value="week">Last 7 days</option><option value="month" selected>Last 30 days</option><option value="year">This year</option></select>
            </div>
            <div class="chart-area">
              <div class="chart-legend">
                <span><i class="legend-dot" style="background:#2563eb"></i>Submissions</span>
              </div>
              <?php
                $mc = array_fill(1,12,0);
                foreach ($all as $r) {
                    $m = (int)date('n', strtotime($r['created_at']));
                    $mc[$m]++;
                }
                $maxM = max(1, ...$mc);
                $W=650; $H=205; $x0=40; $x1=630; $y0=25; $y1=180;
                $step = ($x1 - $x0) / 11;
                $sp = [];
                for ($i=1; $i<=12; $i++) {
                    $x = $x0 + ($i-1) * $step;
                    $sp[] = round($x,1) . ' ' . round($y1 - ($mc[$i]/$maxM)*($y1-$y0), 1);
                }
                $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                $gridY  = [25,65,105,145,180];
              ?>
              <svg class="linechart" viewBox="0 0 650 205" preserveAspectRatio="none" role="img" aria-label="Feedback submission trend">
                <g class="chart-grid"><?php foreach ($gridY as $y): ?><line x1="40" y1="<?= $y ?>" x2="630" y2="<?= $y ?>"/><?php endforeach; ?></g>
                <g class="chart-label">
                  <?php foreach ([0,25,50,75,100] as $idx => $v): $yy = 180 - ($idx/4)*155; ?>
                    <text x="8" y="<?= round($yy+4,1) ?>"><?= (int)round($maxM*$v/100) ?></text>
                  <?php endforeach; ?>
                  <?php foreach ($labels as $i => $lab): ?>
                    <text x="<?= round($x0 + $i*$step, 1) ?>" y="199"><?= $lab ?></text>
                  <?php endforeach; ?>
                </g>
                <polyline points="<?= implode(' ', $sp) ?>" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                <polygon points="<?= implode(' ', $sp) ?> <?= $x1 ?> <?= $y1 ?> <?= $x0 ?> <?= $y1 ?>" fill="#2563eb" opacity=".06"/>
              </svg>
            </div>
          </div>
          <div class="card">
            <div class="card-head">
              <div><h2>Feedback breakdown</h2><p>Distribution by category</p></div>
              <button class="btn small" onclick="showPage('insights')">View insights</button>
            </div>
            <div class="donut-wrap">
              <?php
                $pC=$pct($byType['Complaint']); $pE=$pct($byType['Enquiry']);
                $pP=$pct($byType['Compliment']); $pS=max(0, 100-$pC-$pE-$pP);
                $pE2=$pC+$pE; $pP2=$pE2+$pP;
                $conic = "conic-gradient(#2563eb 0 {$pC}%,#f59e0b {$pC}% {$pE2}%,#15a36d {$pE2}% {$pP2}%,#9aa9bd {$pP2}% 100%)";
              ?>
              <div class="donut" style="background:<?= $conic ?>">
                <div class="donut-center"><strong><?= number_format($total) ?></strong><span>Total feedback</span></div>
              </div>
              <div class="legend-list">
                <div class="legend-row"><i class="legend-dot" style="background:#2563eb"></i>Complaints <b><?= $pC ?>%</b></div>
                <div class="legend-row"><i class="legend-dot" style="background:#f59e0b"></i>Enquiries <b><?= $pE ?>%</b></div>
                <div class="legend-row"><i class="legend-dot" style="background:#15a36d"></i>Compliments <b><?= $pP ?>%</b></div>
                <div class="legend-row"><i class="legend-dot" style="background:#9aa9bd"></i>Suggestions <b><?= $pS ?>%</b></div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid bottomgrid">
          <div class="card table-card">
            <div class="card-head">
              <div><h2>Recent feedback</h2><p>Latest submissions in the register</p></div>
              <button class="btn small" onclick="showPage('cases')">View register</button>
            </div>
            <div class="table-scroll">
              <table class="table">
                <thead><tr><th>Reference / Category</th><th>Submitted by</th><th>Priority</th><th>Received</th></tr></thead>
                <tbody>
                <?php if (!$recent): ?>
                  <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:28px">No feedback recorded yet.</td></tr>
                <?php else: foreach ($recent as $r): ?>
                  <tr>
                    <td><span class="ref"><?= e($r['reference']) ?></span><div style="font-size:10px;margin-top:3px;color:var(--muted)"><?= e($r['type']) ?></div></td>
                    <td><div class="person"><div class="mini-avatar"><?= e(strtoupper(substr($r['name'],0,1))) ?></div><div><b><?= e($r['name']) ?></b><small><?= e($r['channel']) ?></small></div></div></td>
                    <td><?= pill_priority($r['priority']) ?></td>
                    <td><?= e(format_date($r['created_at'])) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="card">
            <div class="card-head">
              <div><h2>Priority submissions</h2><p>High-priority feedback for visibility</p></div>
              <?php if ($high > 0): ?><span class="pill p-urgent"><?= $high ?> high</span><?php endif; ?>
            </div>
            <div class="attention">
              <?php if (!$attention): ?>
                <div class="attention-item" style="border:0;color:var(--muted)">
                  <div class="attention-icon info"><span class="icon"><?= $IC['check'] ?></span></div>
                <div><b>No priority feedback</b><p>There are no high-priority submissions to highlight.</p></div>
                </div>
              <?php else: foreach ($attention as $a): ?>
                <div class="attention-item">
                  <div class="attention-icon <?= $a['kind']==='urgent'?'':'warn' ?>"><span class="icon"><?= $a['kind']==='urgent'?$IC['flag']:$IC['clock'] ?></span></div>
                  <div><b><?= e($a['reason']) ?></b><p><?= e($a['ref']) ?> &middot; <?= e(mb_substr($a['note'],0,60)) ?></p><small>Review in the register</small></div>
                </div>
              <?php endforeach; endif; ?>
            </div>
          </div>
        </div>
      </section>

      <section class="page <?= $page === 'cases' ? 'active' : '' ?>" id="page-cases">
        <div class="page-head">
          <div>
            <div class="eyebrow">Feedback monitoring</div>
            <h1>Feedback register</h1>
            <p>Browse and monitor submissions by category, priority and date.</p>
          </div>
          <div class="actions">
            <a class="btn" href="?export=csv"><span class="icon"><?= $IC['download'] ?></span>Export CSV</a>
            <button class="btn primary" onclick="openFeedback()"><span class="icon"><?= $IC['plus'] ?></span>Record feedback</button>
          </div>
        </div>

        <div class="grid metrics">
          <div class="card metric"><div class="metric-top">Total feedback<span class="metric-icon i-blue"><span class="icon"><?= $IC['layers'] ?></span></span></div><div class="metric-num"><?= $total ?></div><div class="metric-foot">Across all channels</div></div>
          <div class="card metric"><div class="metric-top">Complaints<span class="metric-icon i-amber"><span class="icon"><?= $IC['flag'] ?></span></span></div><div class="metric-num"><?= $byType['Complaint'] ?></div><div class="metric-foot">Feedback category</div></div>
          <div class="card metric"><div class="metric-top">High priority<span class="metric-icon i-red"><span class="icon"><?= $IC['flag'] ?></span></span></div><div class="metric-num"><?= $high ?></div><div class="metric-foot">Require prompt attention</div></div>
          <div class="card metric"><div class="metric-top">Enquiries<span class="metric-icon i-green"><span class="icon"><?= $IC['insights'] ?></span></span></div><div class="metric-num"><?= $byType['Enquiry'] ?></div><div class="metric-foot">Feedback category</div></div>
        </div>

        <div class="card table-card">
          <div class="table-tools">
            <input id="caseSearch" placeholder="Search reference, name or feedback">
            <select id="typeFilter"><option value="">All categories</option><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select>
            <select id="priorityFilter"><option value="">All priorities</option><option>High</option><option>Medium</option><option>Low</option></select>
          </div>
          <div class="table-scroll">
            <table class="table">
              <thead><tr><th>Reference</th><th>Client name</th><th>Feedback</th><th>Category</th><th>Priority</th><th>Forwarded to</th><th>Courtesy response</th><th>Received</th></tr></thead>
              <tbody id="caseRows">
              <?php if (!$all): ?>
              <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:28px">No feedback yet. Click Record feedback to create the first submission.</td></tr>
              <?php else: foreach ($all as $r): ?>
                <tr data-ref="<?= e($r['reference']) ?>" data-name="<?= e($r['name']) ?>" data-summary="<?= e($r['summary']) ?>" data-forwarded="<?= e($r['forwarded_to']) ?>" data-courtesy="<?= e($r['courtesy_response']) ?>" data-type="<?= e($r['type']) ?>" data-priority="<?= e($r['priority']) ?>">
                  <td><span class="ref"><?= e($r['reference']) ?></span></td>
                  <td><?= e($r['name']) ?></td>
                  <td style="min-width:210px;max-width:280px;white-space:normal"><?= e($r['summary']) ?></td>
                  <td><?= e($r['type']) ?></td>
                  <td><?= pill_priority($r['priority']) ?></td>
                  <td><?= e($r['forwarded_to'] ?: '—') ?></td>
                  <td style="min-width:190px;max-width:260px;white-space:normal"><?= e($r['courtesy_response'] ?: '—') ?></td>
                  <td><?= e(format_date($r['created_at'])) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <div style="padding:13px 18px;border-top:1px solid #f0f2f6;display:flex;justify-content:space-between;gap:10px;color:#8793a5;font-size:11px;flex-wrap:wrap">
            <span id="caseCount">Showing all <?= $total ?> records</span>
            <span>Live database</span>
          </div>
        </div>
      </section>

      <section class="page <?= $page === 'insights' ? 'active' : '' ?>" id="page-insights">
        <div class="page-head">
          <div><div class="eyebrow">Analysis</div><h1>Feedback insights</h1><p>Understand volume, sentiment and emerging service issues.</p></div>
          <div class="actions">
            <a class="btn" href="?export=report"><span class="icon"><?= $IC['download'] ?></span>Export summary</a>
            <button type="button" class="btn primary" onclick="location.reload()"><span class="icon"><?= $IC['insights'] ?></span>Refresh</button>
          </div>
        </div>

        <?php
          $topType = '—';
          $mx = 0;
          foreach ($byType as $t => $n) { if ($n > $mx) { $mx = $n; $topType = $t; } }
          $topChannel = '—';
          $chCounts = [];
          foreach ($all as $r) $chCounts[$r['channel']] = ($chCounts[$r['channel']] ?? 0) + 1;
          if ($chCounts) { arsort($chCounts); $topChannel = array_key_first($chCounts); }
        ?>
        <div class="grid insight-grid">
          <div class="card insight"><div class="label">Most common category</div><div class="big"><?= e($topType) ?></div><p><?= $mx ?> of <?= $total ?> feedback entries.</p><div class="tagline" style="color:#b7791f">Top channel: <?= e($topChannel) ?></div></div>
          <div class="card insight"><div class="label">High-priority feedback</div><div class="big"><?= $high ?></div><p>Submissions marked high priority.</p><div class="tagline" style="color:#2563eb"><?= $total ? round($high / $total * 100) : 0 ?>% of total</div></div>
        </div>

        <div class="grid midgrid">
          <div class="card">
            <div class="card-head"><div><h2>Category distribution</h2><p>Live from the register</p></div></div>
            <div class="bar-list">
              <?php foreach ($byType as $t => $n): $w = $total ? round($n / $total * 100) : 0; ?>
                <div class="bar-row"><span><?= e($t) ?></span><div class="bar-track"><div class="bar-fill" style="width:<?= $w ?>%"></div></div><b><?= $n ?></b></div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="card">
            <div class="card-head"><div><h2>Monitoring highlights</h2><p>Based on current submissions</p></div></div>
            <div class="attention">
              <?php if ($high > 0): ?>
                <div class="attention-item"><div class="attention-icon info"><span class="icon"><?= $IC['flag'] ?></span></div><div><b>High-priority submissions</b><p><?= $high ?> feedback entries are marked high priority.</p><button class="btn small" onclick="showPage('cases')">View register</button></div></div>
              <?php endif; ?>
              <div class="attention-item"><div class="attention-icon info" style="background:#e6f8f0;color:#15966a"><span class="icon"><?= $IC['layers'] ?></span></div><div><b>Most common category</b><p><?= e($topType) ?> accounts for the largest share of feedback.</p></div></div>
            </div>
          </div>
        </div>
      </section>

      <section class="page <?= $page === 'ai-insights' ? 'active' : '' ?>" id="page-ai-insights">
        <div class="page-head">
          <div><div class="eyebrow">AI-assisted analysis</div><h1>AI Insights</h1><p>Explore patterns and recommended follow-ups from the live feedback register.</p></div>
          <div class="actions"><button type="button" class="btn primary" id="aiRefresh"><span class="icon"><?= $IC['insights'] ?></span>Refresh analysis</button></div>
        </div>
        <div class="card" style="margin-bottom:16px">
          <b>Privacy and method</b>
          <p class="sub" style="margin:4px 0 0">Analysis runs locally in your browser using category, priority, channel, date and recurring words in feedback summaries. No feedback text is sent to an external AI service.</p>
        </div>
        <div class="ai-grid" id="aiSummary" aria-live="polite"></div>
        <div class="grid midgrid">
          <div class="card"><div class="card-head"><div><h2>Recurring themes</h2><p>Topics found in feedback summaries</p></div></div><div class="ai-list" id="aiThemes"></div></div>
          <div class="card"><div class="card-head"><div><h2>Suggested actions</h2><p>Recommended next steps from current patterns</p></div></div><div class="ai-list" id="aiActions"></div></div>
        </div>
      </section>

      <section class="page <?= $page === 'reports' ? 'active' : '' ?>" id="page-reports">
        <div class="page-head">
          <div><div class="eyebrow">Data &amp; performance</div><h1>Reports centre</h1><p>Prepare management-ready summaries for review.</p></div>
          <div class="actions"><a class="btn primary" href="?export=csv"><span class="icon"><?= $IC['download'] ?></span>Export current register</a></div>
        </div>

        <div class="grid sectiongrid">
          <div class="card report-card"><div class="report-icon"><span class="icon"><?= $IC['reports'] ?></span></div><div><h3>Feedback submissions report</h3><p>Download feedback records for a selected reporting period.</p><a class="btn small" href="?export=report">Download report</a></div></div>
          <div class="card report-card"><div class="report-icon" style="background:#e6f8f0;color:#15966a"><span class="icon"><?= $IC['layers'] ?></span></div><div><h3>Category and channel summary</h3><p>Explore submission patterns by feedback category and channel.</p><button class="btn small" onclick="showPage('insights')">Open insights</button></div></div>
        </div>

        <div class="card" style="margin-top:17px">
          <div class="card-head"><div><h2>Report parameters</h2><p>Choose the reporting period.</p></div></div>
          <form method="get" action="" style="padding:18px 20px;display:flex;gap:12px;flex-wrap:wrap;align-items:end">
            <input type="hidden" name="export" value="report">
            <div><label style="display:block;font-size:11px;color:#718096;margin-bottom:5px">From date</label><input type="date" name="df" id="reportFrom" class="select" style="padding:9px" value="<?= e($_GET['df'] ?? '') ?>"></div>
            <div><label style="display:block;font-size:11px;color:#718096;margin-bottom:5px">To date</label><input type="date" name="dt" id="reportTo" class="select" style="padding:9px" value="<?= e($_GET['dt'] ?? '') ?>"></div>
            <button class="btn primary" type="submit">Generate report</button>
          </form>
        </div>
      </section>

      <section class="page <?= $page === 'notifications' ? 'active' : '' ?>" id="page-notifications">
        <div class="page-head">
          <div><div class="eyebrow">Stay informed</div><h1>Notifications</h1><p>High-priority feedback highlights.</p></div>
          <div class="actions">
            <form method="post" action="" style="margin:0">
              <input type="hidden" name="action" value="mark_read">
              <button type="submit" class="btn"><span class="icon"><?= $IC['check'] ?></span>Mark all as read</button>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div><h2>Recent activity</h2><p>Derived from your live register</p></div></div>
          <div class="attention">
            <?php if (!$attention): ?>
              <div class="attention-item" style="border:0;color:var(--muted)"><div class="attention-icon info"><span class="icon"><?= $IC['check'] ?></span></div><div><b>No high-priority feedback</b><p>There are no priority submissions to highlight.</p></div></div>
            <?php else: foreach ($attention as $a): ?>
              <div class="attention-item"><div class="attention-icon <?= $a['kind']==='urgent'?'':'warn' ?>"><span class="icon"><?= $a['kind']==='urgent'?$IC['flag']:$IC['clock'] ?></span></div><div><b><?= e($a['reason']) ?> &middot; <?= e($a['ref']) ?></b><p><?= e($a['note']) ?></p><small>Today</small></div></div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </section>

    </main>
  </div>
</div>

<!-- Record feedback modal -->
<div class="modal-backdrop" id="feedbackModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="feedbackTitle">
    <div class="modal-head">
      <div><h2 id="feedbackTitle">Record feedback</h2><p>Log feedback received through any channel.</p></div>
      <button class="close-x" type="button" aria-label="Close" onclick="closeFeedback()"><span class="icon"><?= $IC['x'] ?></span></button>
    </div>
    <form id="feedbackForm" method="post" action="">
      <input type="hidden" name="action" value="create">
      <div class="modal-body">
        <div class="field"><label for="fName">Client name (optional)</label><input id="fName" name="name" placeholder="Leave blank to record as Anonymous" autocomplete="off"></div>
        <div class="form-grid">
          <div class="field"><label for="fType">Category</label><select id="fType" name="type"><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select></div>
          <div class="field" id="priorityField"><label for="fPriority">Priority</label><select id="fPriority" name="priority"><option>Medium</option><option>High</option><option>Low</option></select></div>
        </div>
        <div class="field"><label for="fSummary">Feedback summary</label><textarea id="fSummary" name="summary" required placeholder="What was the feedback about?"></textarea></div>
        <div class="field"><label for="fForwarded">Forwarded to</label><input id="fForwarded" name="forwarded_to" maxlength="120" placeholder="Officer or department"></div>
        <div class="field"><label for="fCourtesy">Courtesy response</label><textarea id="fCourtesy" name="courtesy_response" maxlength="10000" placeholder="Response given to the client, if any"></textarea></div>
        <div class="field"><label for="fChannel">Channel</label>
          <select id="fChannel" name="channel">
            <?php foreach ($settings['channels'] as $c): ?>
              <option><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-foot"><button class="btn" type="button" onclick="closeFeedback()">Cancel</button><button class="btn primary" type="submit">Save feedback</button></div>
    </form>
  </div>
</div>

<!-- Help modal -->
<div class="modal-backdrop" id="helpModal" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="helpTitle">
    <div class="modal-head">
      <div><h2 id="helpTitle">Quick reference</h2><p>What the portal can do for you.</p></div>
      <button class="close-x" type="button" aria-label="Close" onclick="closeHelp()"><span class="icon"><?= $IC['x'] ?></span></button>
    </div>
    <div class="modal-body">
      <div class="field"><label>Record feedback</label><div style="font-size:12px;color:#334155">Open <strong>Record feedback</strong> from the dashboard or register. Add the client name, category, feedback summary, forwarding destination and any courtesy response. Priority is available for enquiries and suggestions. You'll receive a unique reference (e.g. FB-2026-C0001).</div></div>
      <div class="field"><label>Monitor feedback</label><div style="font-size:12px;color:#334155">All submissions appear in the <strong>Feedback register</strong>. Use search and filters to browse records by category and priority.</div></div>
      <div class="field"><label>Export &amp; reports</label><div style="font-size:12px;color:#334155">Use <strong>Export CSV</strong> for the full register, or <strong>Reports centre</strong> for a filtered summary by date range.</div></div>
      <div class="field"><label>Need more help?</label><div style="font-size:12px;color:#334155">Contact your system administrator.</div></div>
    </div>
    <div class="modal-foot"><button class="btn primary" type="button" onclick="closeHelp()">Got it</button></div>
  </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
(function () {
  const $  = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const AI_FEEDBACK = <?= json_encode($all, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const escapeHTML = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

  function renderAIInsights() {
    const total = AI_FEEDBACK.length;
    const countBy = key => Object.entries(AI_FEEDBACK.reduce((counts, row) => {
      const value = row[key] || 'Unknown';
      counts[value] = (counts[value] || 0) + 1;
      return counts;
    }, {})).sort((a, b) => b[1] - a[1]);
    const types = countBy('type');
    const channels = countBy('channel');
    const high = AI_FEEDBACK.filter(row => row.priority === 'High').length;
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const tomorrow = new Date(today); tomorrow.setDate(tomorrow.getDate() + 1);
    const recentStart = new Date(today); recentStart.setDate(recentStart.getDate() - 29);
    const previousStart = new Date(today); previousStart.setDate(previousStart.getDate() - 59);
    const recentCount = AI_FEEDBACK.filter(row => {
      const date = new Date(String(row.created_at).replace(' ', 'T'));
      return date >= recentStart && date < tomorrow;
    }).length;
    const previousCount = AI_FEEDBACK.filter(row => {
      const date = new Date(String(row.created_at).replace(' ', 'T'));
      return date >= previousStart && date < recentStart;
    }).length;
    const trend = previousCount
      ? `${recentCount >= previousCount ? '+' : ''}${Math.round((recentCount - previousCount) / previousCount * 100)}% vs previous 30 days`
      : (recentCount ? 'New activity in the last 30 days' : 'No activity in the last 30 days');
    const topicRules = [
      ['Response and communication', ['response','respond','reply','communication','update','information']],
      ['Waiting and delays', ['wait','waiting','delay','queue','late','slow']],
      ['Staff and service', ['staff','service','attitude','helpful','professional','rude']],
      ['Digital services', ['website','online','portal','system','application','login']],
      ['Access and facilities', ['access','accessible','wheelchair','entrance','facility','building']]
    ];
    const themes = topicRules.map(([label, words]) => ({
      label,
      count: AI_FEEDBACK.filter(row => words.some(word => String(row.summary || '').toLowerCase().includes(word))).length
    })).filter(theme => theme.count > 0).sort((a, b) => b.count - a.count);

    const summary = [
      ['Records analysed', total, 'All feedback in the live register'],
      ['Leading category', types[0] ? `${types[0][0]} · ${types[0][1]}` : 'No category yet', 'Most frequently submitted feedback type'],
      ['High priority', `${high} · ${total ? Math.round(high / total * 100) : 0}%`, 'Share of records marked high priority'],
      ['Last 30 days', recentCount, trend],
      ['Leading channel', channels[0] ? `${channels[0][0]} · ${channels[0][1]}` : 'No channel yet', 'Most common submission channel']
    ];
    $('#aiSummary').innerHTML = summary.map(([label, value, note]) =>
      `<div class="ai-card"><div class="label">${escapeHTML(label)}</div><div class="value">${escapeHTML(value)}</div><p class="ai-note">${escapeHTML(note)}</p></div>`
    ).join('');
    $('#aiThemes').innerHTML = themes.length
      ? themes.slice(0, 5).map(theme => `<div class="ai-item"><b>${escapeHTML(theme.label)}</b><p>Matched in ${theme.count} ${theme.count === 1 ? 'record' : 'records'}.</p></div>`).join('')
      : '<div class="em">No recurring themes detected yet. Feedback summaries will help reveal patterns.</div>';

    const actions = [];
    if (high) actions.push([`Review ${high} high-priority ${high === 1 ? 'record' : 'records'}`, 'Open the register and prioritize these submissions for follow-up.']);
    if (types[0] && types[0][0] === 'Complaint') actions.push(['Investigate complaint drivers', `Complaints lead the register (${types[0][1]} records); review cases for service improvements.`]);
    if (themes[0]) actions.push([`Follow up on ${themes[0].label.toLowerCase()}`, `This theme appears in ${themes[0].count} ${themes[0].count === 1 ? 'record' : 'records'}; check whether an operational change could address it.`]);
    if (channels[0]) actions.push([`Monitor the ${channels[0][0]} channel`, `It accounts for ${channels[0][1]} ${channels[0][1] === 1 ? 'record' : 'records'}; ensure submissions there are consistently reviewed.`]);
    if (!total) actions.push(['Start collecting feedback', 'Insights will appear after feedback is submitted.']);
    $('#aiActions').innerHTML = actions.map(([title, description]) =>
      `<div class="ai-item"><b>${escapeHTML(title)}</b><p>${escapeHTML(description)}</p></div>`
    ).join('');
  }

  /* Page navigation */
  window.showPage = function (name) {
    $$('.page').forEach(p => p.classList.toggle('active', p.id === 'page-' + name));
    $$('.nav button[data-page]').forEach(b => b.classList.toggle('active', b.dataset.page === name));
    const labels = {overview:'Executive dashboard',cases:'Feedback register',insights:'Insights','ai-insights':'AI Insights',reports:'Reports centre',notifications:'Notifications'};
    const crumb = document.getElementById('crumb'); if (crumb) crumb.textContent = labels[name] || name;
    const sb = document.getElementById('sidebar'); if (sb) sb.classList.remove('show');
    const bk = document.getElementById('sidebarBackdrop'); if (bk) bk.classList.remove('show');
    const np = document.getElementById('notificationPanel'); if (np) np.classList.remove('open');
    if (name === 'cases') renderCases();
    if (name === 'ai-insights') renderAIInsights();
    history.replaceState(null, '', '?page=' + name);
    window.scrollTo({top:0,behavior:'smooth'});
  };
  $$('.nav button[data-page]').forEach(b => b.addEventListener('click', () => showPage(b.dataset.page)));
  const aiRefresh = document.getElementById('aiRefresh');
  if (aiRefresh) aiRefresh.addEventListener('click', renderAIInsights);
  if (document.getElementById('page-ai-insights').classList.contains('active')) renderAIInsights();

  /* Sidebar (mobile) */
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('sidebarBackdrop');
  const menuBtn = document.getElementById('menuBtn');
  if (menuBtn) menuBtn.addEventListener('click', () => { sidebar.classList.add('show'); backdrop.classList.add('show'); });
  if (backdrop) backdrop.addEventListener('click', () => { sidebar.classList.remove('show'); backdrop.classList.remove('show'); });

  /* Notifications dropdown */
  const notifBtn = document.getElementById('notifBtn');
  if (notifBtn) notifBtn.addEventListener('click', e => {
    e.stopPropagation();
    document.getElementById('notificationPanel').classList.toggle('open');
  });
  document.addEventListener('click', e => {
    const panel = document.getElementById('notificationPanel');
    if (panel && !e.target.closest('#notifBtn') && !e.target.closest('#notificationPanel')) panel.classList.remove('open');
  });

  /* Global search -> cases */
  const gsearch = document.getElementById('globalSearch');
  if (gsearch) gsearch.addEventListener('input', e => {
    const v = e.target.value;
    const cs = document.getElementById('caseSearch');
    if (cs) cs.value = v;
    showPage('cases');
    renderCases();
  });

  /* Case filtering */
  function renderCases() {
    const s = document.getElementById('caseSearch');
    if (!s) return;
    const q = (s.value || '').toLowerCase();
    const t = document.getElementById('typeFilter').value;
    const p = document.getElementById('priorityFilter').value;
    let shown = 0;
    $$('#caseRows tr').forEach(tr => {
      const hay = [tr.dataset.ref, tr.dataset.name, tr.dataset.summary, tr.dataset.forwarded, tr.dataset.courtesy, tr.dataset.type].join(' ').toLowerCase();
      const ok = (!q || hay.includes(q)) && (!t || tr.dataset.type === t) && (!p || tr.dataset.priority === p);
      tr.style.display = ok ? '' : 'none';
      if (ok) shown++;
    });
    const cnt = document.getElementById('caseCount');
    if (cnt) cnt.textContent = `Showing ${shown} of ${$$('#caseRows tr').length} records`;
  }
  ['caseSearch','typeFilter','priorityFilter'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', renderCases);
  });

  /* Modals */
  window.openFeedback = function () {
    const m = document.getElementById('feedbackModal');
    m.classList.add('open');
    m.setAttribute('aria-hidden','false');
    setTimeout(() => document.getElementById('fName').focus(), 30);
  };
  window.closeFeedback = function () {
    const m = document.getElementById('feedbackModal');
    m.classList.remove('open');
    m.setAttribute('aria-hidden','true');
  };
  window.openHelp = function () {
    const m = document.getElementById('helpModal');
    m.classList.add('open');
    m.setAttribute('aria-hidden','false');
  };
  window.closeHelp = function () {
    const m = document.getElementById('helpModal');
    m.classList.remove('open');
    m.setAttribute('aria-hidden','true');
  };

  const form = document.getElementById('feedbackForm');
  const typeSelect = document.getElementById('fType');
  const priorityField = document.getElementById('priorityField');
  const prioritySelect = document.getElementById('fPriority');
  const updatePriorityVisibility = () => {
    const priorityHidden = ['Complaint','Compliment'].includes(typeSelect.value);
    priorityField.hidden = priorityHidden;
    prioritySelect.disabled = priorityHidden;
    if (priorityHidden) prioritySelect.value = 'Medium';
  };
  typeSelect.addEventListener('change', updatePriorityVisibility);
  updatePriorityVisibility();
  if (form) form.addEventListener('submit', e => {
    if (!document.getElementById('fSummary').value.trim()) {
      e.preventDefault();
      toast('Please add a short summary.');
    }
  });

  const trend = document.getElementById('trendRange');
  if (trend) trend.addEventListener('change', e => {
    toast(`Trend selector set to ${e.target.options[e.target.selectedIndex].text}. Chart uses live monthly data.`);
  });

  /* Flash banner: close + auto-dismiss + scroll into view */
  $$('[data-flash]').forEach(banner => {
    const closeBtn = banner.querySelector('[data-flash-close]');
    const dismiss = () => {
      banner.style.opacity = '0';
      banner.style.transform = 'translateY(-6px)';
      setTimeout(() => banner.remove(), 260);
    };
    if (closeBtn) closeBtn.addEventListener('click', dismiss);
    setTimeout(dismiss, 8000);
    if (banner.getBoundingClientRect().top < 0) {
      banner.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  });

  /* Toast */
  let toastTimer;
  window.toast = function (msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
  };

  /* Escape */
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeFeedback(); closeHelp();
    document.getElementById('notificationPanel').classList.remove('open');
    sidebar.classList.remove('show');
    backdrop.classList.remove('show');
  });
})();
</script>
</body>
</html>