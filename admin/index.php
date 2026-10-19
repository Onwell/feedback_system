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

if (($user['role'] ?? 'user') !== 'admin') {
    header('Location: ../user/index.php', true, 303);
    exit;
}

const DB_HOST = 'localhost';
const DB_NAME = 'ncc_feedback_db';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS feedback (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(24) NOT NULL UNIQUE,
            submitted_by INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL DEFAULT 'Anonymous',
            type ENUM('Complaint','Enquiry','Compliment','Suggestion') NOT NULL,
            priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium',
            summary TEXT NOT NULL,
            channel VARCHAR(40) NOT NULL DEFAULT 'Website',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (type), INDEX (priority), INDEX (submitted_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            k VARCHAR(64) PRIMARY KEY,
            v TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ts DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actor VARCHAR(120) NOT NULL,
            action VARCHAR(80) NOT NULL,
            ref VARCHAR(40) NULL,
            details TEXT NULL,
            INDEX (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $databaseEntries = $pdo->query(
        'SELECT id, reference, submitted_by, name, type, priority, summary, channel, created_at
         FROM feedback ORDER BY created_at DESC, id DESC'
    )->fetchAll();
    $databaseOfficers = $pdo->query('SELECT id, name, email, role FROM users ORDER BY name')->fetchAll();
    $databaseSettings = [
        'org' => 'Public Relations',
        'channels' => ['Email', 'Website', 'Phone', 'Walk-in', 'Social media'],
    ];
    foreach ($pdo->query('SELECT k, v FROM settings') as $setting) {
        if (in_array($setting['k'], ['org', 'channels'], true)) {
            $databaseSettings[$setting['k']] = $setting['v'];
        }
    }
    $channels = $databaseSettings['channels'];
    if (is_string($channels)) {
        $decodedChannels = json_decode($channels, true);
        $databaseSettings['channels'] = is_array($decodedChannels)
            ? $decodedChannels
            : array_values(array_filter(array_map('trim', explode(',', $channels))));
    }
    $databaseAudit = $pdo->query('SELECT id, ts, actor, action, ref, details FROM audit_log ORDER BY ts DESC, id DESC LIMIT 1000')->fetchAll();

    $apiToken = $_SESSION['admin_api_token'] ?? bin2hex(random_bytes(32));
    $_SESSION['admin_api_token'] = $apiToken;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
        && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === 0) {
        header('Content-Type: application/json; charset=utf-8');
        $input = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($input) || !hash_equals($apiToken, (string)($input['token'] ?? ''))) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request token']);
            exit;
        }

        $logDatabaseAction = static function (string $action, ?string $ref, string $details) use ($pdo, $name): void {
            $statement = $pdo->prepare('INSERT INTO audit_log (actor, action, ref, details) VALUES (?, ?, ?, ?)');
            $statement->execute([$name, $action, $ref, 'Administrator profile · ' . $details]);
        };

        $pdo->beginTransaction();
        try {
            $action = (string)($input['action'] ?? '');
            if ($action === 'save_entry') {
                $id = (int)($input['id'] ?? 0);
                $type = (string)($input['type'] ?? '');
                $entryName = trim((string)($input['name'] ?? ''));
                $summary = trim((string)($input['summary'] ?? ''));
                $date = (string)($input['date'] ?? '');
                $channel = trim((string)($input['channel'] ?? 'Website'));
                if (!in_array($type, ['Complaint', 'Enquiry', 'Compliment', 'Suggestion'], true)
                    || $entryName === '' || $summary === ''
                    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    throw new InvalidArgumentException('Complete all required feedback fields with valid values.');
                }

                if ($id > 0) {
                    $priorityQuery = $pdo->prepare('SELECT reference FROM feedback WHERE id = ?');
                    $priorityQuery->execute([$id]);
                    $existing = $priorityQuery->fetch();
                    if (!$existing) {
                        throw new RuntimeException('Feedback record not found.');
                    }
                    $statement = $pdo->prepare(
                        'UPDATE feedback SET name = ?, type = ?, summary = ?, channel = ?, created_at = ? WHERE id = ?'
                    );
                    $statement->execute([$entryName, $type, $summary, $channel, $date . ' 00:00:00', $id]);
                    $logDatabaseAction('Updated entry', (string)$existing['reference'], 'Feedback entry edited');
                } else {
                    $prefix = $type === 'Complaint' ? 'C' : 'K';
                    $year = substr($date, 0, 4);
                    $referenceQuery = $pdo->prepare(
                        'SELECT reference FROM feedback WHERE reference LIKE ? ORDER BY id DESC LIMIT 1'
                    );
                    $referenceQuery->execute(["FB-$year-$prefix%"]);
                    $lastReference = (string)($referenceQuery->fetchColumn() ?: '');
                    $sequence = $lastReference !== '' ? (int)substr($lastReference, -4) + 1 : 1;
                    $reference = sprintf('FB-%s-%s%04d', $year, $prefix, $sequence);
                    $statement = $pdo->prepare(
                        'INSERT INTO feedback (reference, submitted_by, name, type, summary, channel, created_at)
                         VALUES (?, NULL, ?, ?, ?, ?, ?)'
                    );
                    $statement->execute([$reference, $entryName, $type, $summary, $channel, $date . ' 00:00:00']);
                    $logDatabaseAction('Created entry', $reference, 'Feedback entry created by administrator');
                }
            } elseif ($action === 'delete_entries') {
                $ids = array_values(array_filter(array_map('intval', (array)($input['ids'] ?? [])), static fn(int $id): bool => $id > 0));
                if (!$ids) {
                    throw new InvalidArgumentException('Select at least one feedback record.');
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statement = $pdo->prepare("DELETE FROM feedback WHERE id IN ($placeholders)");
                $statement->execute($ids);
                $logDatabaseAction('Deleted entries', null, count($ids) . ' feedback record(s) deleted');
            } elseif ($action === 'save_settings') {
                $settingsToSave = [
                    'org' => trim((string)($input['org'] ?? 'Public Relations')) ?: 'Public Relations',
                    'channels' => json_encode(array_values(array_filter(array_map('trim', (array)($input['channels'] ?? []))))),
                ];
                $statement = $pdo->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
                foreach ($settingsToSave as $key => $value) {
                    $statement->execute([$key, $value]);
                }
                $logDatabaseAction('Updated settings', null, 'Portal settings updated');
            } else {
                throw new InvalidArgumentException('Unknown admin action.');
            }

            $pdo->commit();
            echo json_encode(['ok' => true]);
        } catch (InvalidArgumentException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(400);
            echo json_encode(['error' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo json_encode(['error' => 'The database operation failed: ' . $exception->getMessage()]);
        }
        exit;
    }
} catch (PDOException $exception) {
    http_response_code(500);
    exit(
        '<h2 style="font-family:system-ui;padding:24px">Database connection failed</h2>' .
        '<p style="font-family:system-ui;padding:0 24px;color:#b00">' .
        e($exception->getMessage()) . '</p>'
    );
}

/* =========================================================
   FLASH MESSAGE (set by index.php on login, shown once)
   ========================================================= */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

/* Avatar initials */
$initials = 'U';
$parts    = preg_split('/\s+/', trim($name));
if (!empty($parts[0])) {
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (!empty($parts[1])) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    }
}

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
.v0{display:none}.v0.on{display:block}.msg{
  padding:14px 16px;border-radius:10px;background:#f5f8ff;
  border:1px solid #e2ebff;border-left:4px solid var(--blue);
  margin:0 0 18px;font-weight:500;font-size:13px;color:#2c3557;
  animation:fadeIn .25s ease;
}
.msg.ok{background:#eefaf3;border-color:#cdeedb;border-left-color:var(--ok);color:#12613e}
.msg.err{background:#fff2f2;border-color:#ffd6d6;border-left-color:var(--ba);color:#7a1e1e}
.msg strong{font-weight:700}
@keyframes fadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.tx{max-width:300px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.em{text-align:center;color:var(--mu);padding:22px}
.row{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}.row>*{width:auto;min-width:130px}
dialog{border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--tx);width:min(560px,94vw);padding:22px;box-shadow:0 24px 60px #10204033}dialog::backdrop{background:#0f1e3526}

#adm{display:none;min-height:100vh}#adm aside{width:220px;background:#fff;border-right:1px solid var(--line);padding:22px 14px;display:flex;flex-direction:column;gap:4px;position:sticky;top:0;height:100vh;flex-shrink:0}
#adm aside .logo{padding:0 10px 18px;margin:0}#adm aside nav{flex-direction:column}#adm aside nav button{display:block;border-radius:8px}#adm aside nav button.on{background:#edf4ff;color:var(--blue)}
#adm .main{flex:1;min-width:0}#adm .main>main{max-width:none;margin:0}.lk{all:unset;cursor:pointer;color:var(--mu);font-size:13px;padding:8px 10px;border-radius:8px;font-weight:600}.lk:hover{background:#f7f9fc}
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
<div id="adm"><aside><div class="logo"><span class="brand-mark">F</span><span class="logo-text"><b>Public Relations</b><small>Administrator</small></span></div><nav id="anav"></nav>
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
<div class="side-note">Administrator console · live database records.</div></aside>
<div class="main"><main>
<?php if ($flash): $ft = (string)($flash['type'] ?? 'info'); ?>
  <div class="msg <?= $ft === 'error' ? 'err' : ($ft === 'success' ? 'ok' : '') ?>" role="status" aria-live="polite">
    <strong><?= e($flash['title'] ?? 'Notice') ?>:</strong> <?= e($flash['message'] ?? '') ?>
  </div>
<?php endif; ?>
<section class="v0" id="a-ov"><h1>Overview</h1><div class="sub" id="a-sub"></div>
<div class="g k" id="a-k"></div>
<div class="g c2"><div class="card"><h2>Monthly volume</h2><div id="a-m"></div></div><div class="card"><h2>By channel</h2><div id="a-sr"></div></div></div>
<div class="card"><h2>By quarter</h2><div id="a-q"></div></div>
<div class="card" style="margin-top:16px"><h2>Recent admin activity</h2><div id="a-recent"></div></div></section>

<section class="v0" id="a-reg">
<div class="row" style="justify-content:space-between;align-items:center">
<div><h1 style="margin:0">Register</h1><div class="sub" style="margin:0">Browse and monitor feedback records.</div></div>
<div class="act"><button class="btn" id="r-new">+ New entry</button><button class="btn s" id="r-exp">Export CSV</button></div>
</div>
<div class="toolbar">
<select id="r-t"><option value="">All types</option><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select>
<select id="r-q"><option value="">All quarters</option><option>1st</option><option>2nd</option><option>3rd</option><option>4th</option></select>
<select id="r-ch"><option value="">All channels</option></select>
<input type="date" id="r-df" aria-label="From date">
<input type="date" id="r-dt" aria-label="To date">
<input id="r-x" placeholder="Search client, message, ref…">
<select id="r-sort"><option value="date_desc">Newest first</option><option value="date_asc">Oldest first</option><option value="ref_asc">Reference A-Z</option><option value="client_asc">Client A-Z</option></select>
</div>
<div class="bulkbar" id="r-bulk" style="display:none">
<strong id="r-bulk-n">0 selected</strong>
<button class="btn s" id="bulk-del">Delete</button>
<button class="btn s" id="bulk-clear">Clear</button>
</div>
<div class="card tw" style="max-height:620px;overflow-y:auto"><div class="sub" id="r-c" style="margin:0 0 6px"></div><table id="r-tb"></table></div>
<div class="pg" id="r-pg"></div>
</section>

<section class="v0" id="a-off">
<div class="row" style="justify-content:space-between;align-items:center">
<div><h1 style="margin:0">Users</h1><div class="sub" style="margin:0">Accounts registered in the system.</div></div>
</div>
<div class="card tw"><table id="o-tb"></table></div>
</section>

<section class="v0" id="a-rep">
<h1>Reports</h1><div class="sub">Generate summaries and export data.</div>
<div class="card">
<div class="row">
<div><label>From</label><input type="date" id="rep-df"></div>
<div><label>To</label><input type="date" id="rep-dt"></div>
<div><label>Type</label><select id="rep-t"><option value="">All types</option><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select></div>
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
<label>Channels (comma separated)</label><input id="set-ch">
<div style="margin-top:14px"><button class="btn" id="set-save">Save settings</button></div>
</div>
</div>
<div class="card" style="margin-top:16px">
<h2>Data management</h2>
<p class="sub">Export a snapshot of the live database records.</p>
<div class="act"><button class="btn s" id="data-exp">Download JSON backup</button></div>
</div>
</section>
</main></div></div>

<dialog id="newDlg"><h2 id="n-h">New feedback entry</h2><div class="f">
<div><label>Type</label><select id="ne_type"><option>Complaint</option><option>Enquiry</option><option>Compliment</option><option>Suggestion</option></select></div>
<div><label>Date</label><input type="date" id="ne_date"></div>
<div><label>Client name</label><input id="ne_name"></div>
<div><label>Channel</label><select id="ne_src"></select></div>
<div class="w"><label>Message</label><textarea id="ne_text"></textarea></div>
<div class="w act"><button class="btn" id="ne_save">Save</button><button class="btn s" id="ne_cl">Cancel</button></div>
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
const qt=d=>['1st','2nd','3rd','4th'][Math.floor((+d.slice(5,7)-1)/3)];
const dd=(a,b)=>Math.round((new Date(b+'T00:00:00')-new Date(a+'T00:00:00'))/864e5);
const cnt=(a,f)=>Object.entries(a.reduce((m,r)=>{const k=f(r);m[k]=(m[k]||0)+1;return m},{})).sort((a,b)=>b[1]-a[1]);
const bars=(el,a,t)=>el.innerHTML=a.map(([n,v])=>`<div class="b"><div class="n">${E(n)}</div><div class="tr"><div class="fl" style="width:${t?v/t*100:0}%"></div></div><div class="v">${v}</div></div>`).join('')||'<div class="em">No data</div>';
const stat=(l,v)=>`<div class="statbox"><div class="l">${E(l)}</div><div class="v">${E(v)}</div></div>`;
const DATABASE_DATA=<?= json_encode([
    'entries' => $databaseEntries,
    'officers' => $databaseOfficers,
    'settings' => $databaseSettings,
    'audit' => $databaseAudit,
    'token' => $apiToken,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
async function databaseRequest(data){
  const response=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...data,token:DATABASE_DATA.token})});
  const result=await response.json();
  if(!response.ok)throw new Error(result.error||'Database request failed');
  return result;
}
async function saveToDatabase(data){
  try{
    await databaseRequest(data);
    location.reload();
  }catch(error){
    toast(error.message);
  }
}
let toastTimer;
function toast(msg){const t=$('#toast');t.textContent=msg;t.classList.add('on');clearTimeout(toastTimer);toastTimer=setTimeout(()=>t.classList.remove('on'),2600)}

const DEFAULT_SETTINGS={
  org:'Public Relations',
  channels:['Email','Website','Phone','Walk-in','Social media']
};


/* ============================================================
   State
   ============================================================ */
let entries=[],officers=[],settings={},audit=[];
let regPage=1,regSelected=new Set();
let editId=null;

const AT=[['a-ov','Overview'],['a-reg','Register'],['a-off','Users'],['a-rep','Reports'],['a-aud','Audit trail'],['a-set','Settings']];

/* ============================================================
   Data loading
   ============================================================ */
function normalizeEntry(e,i){
  const date=(e.created_at||'').slice(0,10)||today();
  return {
    id:String(e.id||i+1),
    type:e.type||'Complaint',n:+e.id||0,ref:e.reference||'',
    date,q:qt(date),
    client:e.name||'',text:e.summary||'',src:e.channel||'Website',
    by:e.submitted_by?'u'+e.submitted_by:'',createdAt:e.created_at||date
  };
}
function loadData(){
  settings=Object.assign({},DEFAULT_SETTINGS,DATABASE_DATA.settings);
  officers=DATABASE_DATA.officers.map(o=>({id:String(o.id),name:o.name,email:o.email,role:o.role==='admin'?'Administrator':'Member',active:true}));
  audit=DATABASE_DATA.audit.map(row=>({id:String(row.id),ts:row.ts,actor:row.actor,action:row.action,ref:row.ref||'',details:row.details||''}));
  entries=DATABASE_DATA.entries.map(normalizeEntry);
}
/* ============================================================
   Navigation
   ============================================================ */
function buildNav(){
  $('#anav').innerHTML=AT.map(([id,l])=>`<button data-v="${id}">${l}</button>`).join('');
  $$('#anav button').forEach(b=>b.onclick=()=>go(b.dataset.v));
}
function go(v){
  $$('.v0').forEach(e=>e.classList.toggle('on',e.id===v));
  $$('[data-v]').forEach(b=>b.classList.toggle('on',b.dataset.v===v));
  const fn={ 'a-ov':ov,'a-reg':reg,'a-off':off,'a-rep':rep,'a-aud':aud,'a-set':set };
  (fn[v]||(()=>{}))();
  window.scrollTo({top:0,behavior:'smooth'});
}

/* ============================================================
   Shared display helpers
   ============================================================ */
/* ============================================================
   Admin — overview
   ============================================================ */
function ov(){
  const A=entries.slice();
  const ds=A.map(e=>e.date).sort();
  $('#a-sub').textContent=ds.length?`Period: ${fd(ds[0])} to ${fd(ds[ds.length-1])} · ${A.length} entries`:'No entries yet';
  const kp=[
    ['Total feedback',A.length],
    ['Complaints',A.filter(e=>e.type==='Complaint').length],
    ['Enquiries',A.filter(e=>e.type==='Enquiry').length],
    ['Compliments',A.filter(e=>e.type==='Compliment').length],
    ['Suggestions',A.filter(e=>e.type==='Suggestion').length]
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

  bars($('#a-sr'),cnt(A,e=>e.src),A.length);
  bars($('#a-q'),cnt(A,e=>e.q),A.length);

  $('#a-recent').innerHTML=audit.slice(0,8).map(l=>`<div class="rc"><div><b>${E(l.action)}</b> ${E(l.ref||'')}<br><small>${new Date(l.ts).toLocaleString()} · ${E(l.actor)}</small></div><div><small>${E(l.details||'')}</small></div></div>`).join('')
    ||'<div class="em">No admin activity yet.</div>';
}

/* ============================================================
   Admin — register
   ============================================================ */
function populateFilters(){
  const chans=[...new Set(entries.map(e=>e.src))].sort();
  const curC=$('#r-ch').value;
  $('#r-ch').innerHTML='<option value="">All channels</option>'+chans.map(c=>`<option${c===curC?' selected':''}>${E(c)}</option>`).join('');
}
function filteredEntries(){
  const t=$('#r-t').value,q=$('#r-q').value,ch=$('#r-ch').value,df=$('#r-df').value,dt=$('#r-dt').value,x=($('#r-x').value||'').toLowerCase();
  return entries.filter(e=>
    (!t||e.type===t)&&
    (!q||e.q===q)&&
    (!ch||e.src===ch)&&
    (!df||e.date>=df)&&
    (!dt||e.date<=dt)&&
    (!x||(e.client+e.text+e.ref+(e.src||'')).toLowerCase().includes(x))
  );
}
function sortList(list,sort){
  const [key,dir]=sort.split('_');
  list.sort((a,b)=>{
    let va,vb;
    if(key==='date'){va=a.date;vb=b.date}
    else if(key==='ref'){va=a.ref;vb=b.ref}
    else if(key==='client'){va=(a.client||'').toLowerCase();vb=(b.client||'').toLowerCase()}
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
  $('#r-tb').innerHTML='<tr><th><input type="checkbox" class="chk" id="r-all" aria-label="Select all on page"></th><th>Ref</th><th>Type</th><th>Date</th><th>Client</th><th>Message</th><th>Channel</th><th></th></tr>'+
    (pageList.map(e=>{
      const ck=regSelected.has(e.id)?' checked':'';
      return `<tr>
        <td><input type="checkbox" class="chk r-sel" data-id="${e.id}"${ck}></td>
        <td>${E(e.ref)}</td><td>${E(e.type)}</td><td>${fd(e.date)}</td><td>${E(e.client)}</td>
        <td><div class="tx" title="${E(e.text)}">${E(e.text)}</div></td>
        <td>${E(e.src)}</td>
        <td><div class="act"><button class="btn s" data-edit="${e.id}">Edit</button><button class="btn s" data-del="${e.id}">Del</button></div></td>
      </tr>`;
    }).join('')||'<tr><td colspan="8" class="em">No matching entries</td></tr>');

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
function openNew(id){
  editId=id||null;
  $('#n-h').textContent=id?'Edit feedback entry':'New feedback entry';
  const e=id?entries.find(x=>x.id===id):null;
  $('#ne_type').value=e?e.type:'Complaint';
  $('#ne_date').value=e?e.date:today();
  $('#ne_name').value=e?e.client:'';
  $('#ne_src').innerHTML=settings.channels.map(c=>`<option${e&&e.src===c?' selected':''}>${E(c)}</option>`).join('');
  $('#ne_text').value=e?e.text:'';
  $('#newDlg').showModal();
}
function saveNew(){
  const type=$('#ne_type').value,date=$('#ne_date').value||today();
  const name=$('#ne_name').value.trim(),text=$('#ne_text').value.trim();
  if(!name||!text){toast('Name and message are required');return}
  saveToDatabase({
    action:'save_entry',id:editId?Number(editId):0,type,date,name,summary:text,
    channel:$('#ne_src').value
  });
}

/* ============================================================
   Admin — delete
   ============================================================ */
function deleteEntry(id){
  const e=entries.find(x=>x.id===id);
  if(!e)return;
  if(!confirm(`Delete ${e.ref} (${e.type} from ${e.client})? This cannot be undone.`))return;
  saveToDatabase({action:'delete_entries',ids:[Number(id)]});
}

/* ============================================================
   Admin — officers
   ============================================================ */
function off(){
  $('#o-tb').innerHTML='<tr><th>Name</th><th>Account role</th><th>Email</th></tr>'+
    (officers.map(o=>`<tr><td>${E(o.name)}</td><td>${E(o.role)}</td><td>${E(o.email||'')}</td></tr>`).join('')
    ||'<tr><td colspan="3" class="em">No users found in the database</td></tr>');
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
  const C=list.filter(e=>e.type==='Complaint');
  const compliments=list.filter(e=>e.type==='Compliment');
  $('#rep-out').innerHTML=`
    <div class="statgrid" style="margin-top:14px">
      ${stat('Records',list.length)}
      ${stat('Complaints',C.length)}
      ${stat('Compliments',compliments.length)}
    </div>
    <div class="g c2" style="margin-top:14px">
      <div><h2>By type</h2><div id="rep-ty"></div></div>
      <div><h2>By quarter</h2><div id="rep-q"></div></div>
      <div><h2>By channel</h2><div id="rep-ch"></div></div>
    </div>`;
  bars($('#rep-ty'),cnt(list,e=>e.type),list.length);
  bars($('#rep-q'),cnt(list,e=>e.q),list.length);
  bars($('#rep-ch'),cnt(list,e=>e.src),list.length);
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
  $('#set-ch').value=settings.channels.join(', ');
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
  const rows=[['Reference','Type','Date','Quarter','Client','Message','Channel'],
    ...list.map(e=>[e.ref,e.type,e.date,e.q,e.client,e.text,e.src])];
  downloadCSV(rows,name||'feedback-export.csv');
  toast('CSV export downloaded');
}

/* ============================================================
   Bindings
   ============================================================ */
function bindFilters(){
  ['#r-t','#r-q','#r-ch','#r-df','#r-dt','#r-sort'].forEach(sel=>{
    $(sel).onchange=()=>{regPage=1;reg()};
  });
  $('#r-x').oninput=()=>{regPage=1;reg()};
}
function bindAdmin(){
  $('#r-new').onclick=()=>openNew(null);
  $('#r-exp').onclick=()=>exportEntries(filteredEntries(),'feedback-register.csv');

  $('#bulk-del').onclick=()=>{
    if(!regSelected.size)return;
    if(!confirm(`Delete ${regSelected.size} selected entries?`))return;
    saveToDatabase({action:'delete_entries',ids:[...regSelected].map(Number)});
  };
  $('#bulk-clear').onclick=()=>{regSelected.clear();reg()};

  $('#ne_save').onclick=saveNew;
  $('#ne_cl').onclick=()=>$('#newDlg').close();

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
    const channels=$('#set-ch').value.split(',').map(s=>s.trim()).filter(Boolean);
    saveToDatabase({
      action:'save_settings',org:$('#set-org').value.trim()||'Public Relations',
      channels:channels.length?channels:['Website']
    });
  };

  $('#data-exp').onclick=()=>{
    const data={entries,officers,settings,audit};
    const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);a.download='feedback-backup.json';a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href),1000);
    toast('Backup downloaded');
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
  $('#adm').style.display='flex';
  go('a-ov');
}
init();
</script>
</body></html>