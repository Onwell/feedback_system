<?php
declare(strict_types=1);

session_start();

/* =========================================================
   DATABASE CONFIG
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
        CREATE TABLE IF NOT EXISTS users (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(120) NOT NULL,
            email      VARCHAR(190) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            role       ENUM('admin','user') NOT NULL DEFAULT 'user',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS audit_log (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ts         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actor      VARCHAR(120) NOT NULL,
            action     VARCHAR(80) NOT NULL,
            ref        VARCHAR(40) NULL,
            details    TEXT NULL,
            INDEX (ts)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER password");
        }
    } catch (PDOException $e) { /* ignore */ }

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

function go(string $query = ''): void
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    if ($path === false || $path === '') $path = '/';
    header('Location: ' . $path . ($query !== '' ? '?' . $query : ''), true, 303);
    exit;
}

function find_user_by_email(string $email): ?array
{
    $stmt = db()->prepare('SELECT id, name, email, password, role, created_at FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function count_users(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function log_audit(PDO $pdo, string $actor, string $role, string $action, ?string $ref = null, string $details = ''): void
{
    $stmt = $pdo->prepare('INSERT INTO audit_log (actor, action, ref, details) VALUES (?, ?, ?, ?)');
    $stmt->execute([$actor, $action, $ref, $role . ' profile · ' . $details]);
}

/* =========================================================
   REQUEST HANDLING
   ========================================================= */
$errors = ['login' => [], 'reg' => []];
$old    = [
    'login' => ['email' => ''],
    'reg'   => ['name' => '', 'email' => ''],
];
$tab = 'login';

$prefillEmail = (string)($_SESSION['prefill_email'] ?? '');
unset($_SESSION['prefill_email']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    $action = (string)($_POST['action'] ?? '');

    /* ---------------- LOGOUT ---------------- */
    if ($action === 'logout') {
        if (!empty($_SESSION['user'])) {
            $signedInUser = $_SESSION['user'];
            log_audit(
                db(),
                (string)($signedInUser['name'] ?? 'Unknown user'),
                (string)($signedInUser['role'] ?? 'user'),
                'Signed out',
                null,
                'User signed out'
            );
        }
        unset($_SESSION['user']);
        session_regenerate_id(true);
        flash_set('info', 'Signed out', 'You have been signed out successfully.');
        go();
    }

    /* ---------------- LOGIN ---------------- */
    if ($action === 'login') {
        $tab      = 'login';
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $old['login']['email'] = $email;

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['login']['email'] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors['login']['password'] = 'Enter your password.';
        }

        if (!$errors['login']) {
            try {
                $found = find_user_by_email($email);
                $valid = $found !== null && password_verify($password, (string)$found['password']);

                if (!$valid) {
                    $errors['login']['password'] = 'Incorrect email or password.';
                    flash_set('error', 'Sign in failed', 'The email or password you entered is incorrect. Please try again.');
                } else {
                    log_audit(
                        db(),
                        (string)$found['name'],
                        (string)($found['role'] ?: 'user'),
                        'Signed in',
                        null,
                        'Successful sign-in'
                    );
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id'    => (int)$found['id'],
                        'name'  => (string)$found['name'],
                        'email' => (string)$found['email'],
                        'role'  => (string)($found['role'] ?: 'user'),
                        'since' => time(),
                    ];

                    if ($_SESSION['user']['role'] === 'admin') {
                        flash_set('success', 'Signed in', 'Welcome back, ' . $found['name'] . '! Opening the admin console.');
                        header('Location: admin/index.php', true, 303);
                    } else {
                        flash_set('success', 'Signed in', 'Welcome back, ' . $found['name'] . '! Opening your dashboard.');
                        header('Location: user/index.php', true, 303);
                    }
                    exit;
                }
            } catch (PDOException $ex) {
                $errors['login']['password'] = 'A database error occurred. Please try again.';
                flash_set('error', 'Sign in failed', 'A database error occurred. Please try again.');
            }
        } else {
            flash_set('error', 'Sign in failed', 'Please correct the highlighted fields and try again.');
        }
    }

    /* ---------------- REGISTER ---------------- */
    if ($action === 'register') {
        $tab      = 'reg';
        $name     = trim((string)($_POST['name'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');
        $terms    = isset($_POST['terms']);

        $old['reg']['name']  = $name;
        $old['reg']['email'] = $email;

        if (strlen($name) < 2) {
            $errors['reg']['name'] = 'Enter your full name.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['reg']['email'] = 'Enter a valid email address.';
        }
        if (strlen($password) < 8) {
            $errors['reg']['password'] = 'Password must be at least 8 characters.';
        }
        if ($confirm === '' || !hash_equals($password, $confirm)) {
            $errors['reg']['confirm'] = 'Passwords do not match.';
        }
        if (!$terms) {
            $errors['reg']['terms'] = 'Please accept the terms to continue.';
        }

        if (!$errors['reg']) {
            try {
                if (find_user_by_email($email) !== null) {
                    $errors['reg']['email'] = 'An account with this email already exists.';
                }
            } catch (PDOException $ex) {
                $errors['reg']['email'] = 'Could not verify email availability. Please try again.';
            }
        }

        if (!$errors['reg']) {
            try {
                $isFirstUser = count_users() === 0;
                $role = $isFirstUser ? 'admin' : 'user';

                $pdo = db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)'
                );
                $stmt->execute([
                    ':name'     => $name,
                    ':email'    => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':role'     => $role,
                ]);
                log_audit($pdo, $name, $role, 'Registered account', null, 'Account created');
                $pdo->commit();

                $_SESSION['prefill_email'] = $email;
                flash_set(
                    'success',
                    'Account created',
                    $isFirstUser
                        ? 'Your account was created as the Administrator. Sign in to open the admin console.'
                        : 'Your account was created successfully. You can now sign in.'
                );
                go();
            } catch (PDOException $ex) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($ex->getCode() === '23000') {
                    $errors['reg']['email'] = 'An account with this email already exists.';
                    flash_set('error', 'Registration failed', 'That email address is already registered.');
                } else {
                    flash_set('error', 'Registration failed', 'The account could not be saved. ' . $ex->getMessage());
                }
            }
        } else {
            flash_set('error', 'Registration failed', 'Please correct the highlighted fields and try again.');
        }
    }
}

$user       = $_SESSION['user'] ?? null;
$flash      = flash_take();
$loginEmail = $old['login']['email'] !== '' ? $old['login']['email'] : $prefillEmail;
$isReg      = ($tab === 'reg');

function fc(array $errors, string $key): string
{
    return isset($errors[$key]) ? 'field bad' : 'field';
}

/* Icon map for flash banner */
function flash_icon(string $type): string
{
    return match ($type) {
        'success' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        'error'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.8v5.4M12 16.6v.01"/></svg>',
        default   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.2M12 7.8v.01"/></svg>',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sign in · Feedback Management Portal</title>
<link rel="icon" type="image/png" href="assets/feedback_favicon.png">
<link rel="apple-touch-icon" href="assets/feedback_favicon.png">
<meta name="theme-color" content="#0F1A3C">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --pri:#6C5CE7;--pri-d:#5A49D6;--ink:#0F1A3C;--pl:#F5F6FA;
  --bg:#FFFFFF;--tx:#1B1F2A;--mu:#6B7280;--bd:#ECEEF3;
  --ok:#2E7D5B;--wa:#C98A12;--ba:#E05A47;--navy:#0F1A3C;--indigo:#5B6EF5;
  --photo:url("assets/login_background.jpg");
  padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)
}
*{box-sizing:border-box}
html,body{min-height:100%}
body{margin:0;background:var(--bg);color:var(--tx);font:400 14px/1.7 Poppins,"Segoe UI",system-ui,Arial,sans-serif;-webkit-font-smoothing:antialiased}
a{color:var(--pri);text-decoration:none}
a:hover{text-decoration:underline}
button{font:inherit}
.shell{display:grid;grid-template-columns:0.95fr 1fr;min-height:100vh;min-height:100dvh}
.media{position:relative;color:#fff;padding:56px 52px;overflow:hidden;background-color:var(--navy);background-image:var(--photo);background-position:center center;background-size:cover;background-repeat:no-repeat}
.media::before{content:"";position:absolute;inset:0;background:linear-gradient(160deg,rgba(15,26,60,.94) 0%,rgba(15,26,60,.82) 42%,rgba(91,110,245,.72) 100%),radial-gradient(120% 80% at 100% 0%,rgba(108,92,231,.45),transparent 60%)}
.media::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(15,26,60,.55),transparent 55%)}
.media-inner{position:relative;z-index:1;display:flex;flex-direction:column;height:100%;min-height:100%}
.brand-head{display:flex;align-items:center;gap:14px}
.mark{width:46px;height:46px;border-radius:13px;background:linear-gradient(140deg,var(--indigo),var(--pri));display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 10px 24px rgba(10,16,40,.45);border:1px solid rgba(255,255,255,.16)}
.mark svg{width:24px;height:24px}
.brand-head strong{display:block;font-size:16px;font-weight:600;line-height:1.3}
.brand-head small{display:block;font-size:12px;color:#B7C0D8}
.media-copy{margin:auto 0;max-width:420px}
.media-copy h2{font-size:32px;line-height:1.25;font-weight:600;letter-spacing:-.02em;margin:0 0 16px;text-shadow:0 2px 18px rgba(0,0,0,.25)}
.media-copy p{margin:0;color:#DDE3F2;font-size:14.5px;line-height:1.75}
.media-foot{font-size:12px;color:#AEB8D0;padding-top:32px}
.panel{background:var(--bg);display:flex;align-items:center;justify-content:center;padding:56px 24px}
.form-wrap{width:100%;max-width:420px}

/* Flash banner */
.flash{
  display:flex;gap:12px;align-items:flex-start;
  padding:14px 16px;
  border-radius:12px;
  font-size:13.5px;line-height:1.5;font-weight:500;
  margin:0 0 22px;
  background:#f5f8ff;
  border:1px solid #e2ebff;
  border-left:4px solid var(--pri);
  color:#2c3557;
  box-shadow:0 4px 14px rgba(108,92,231,.08);
  animation:flashIn .3s cubic-bezier(.2,.9,.3,1);
  position:relative;
}
.flash.success{background:#eefaf3;border-color:#cdeedb;border-left-color:var(--ok);color:#12613e;box-shadow:0 4px 14px rgba(46,125,91,.08)}
.flash.error{background:#fff2f2;border-color:#ffd6d6;border-left-color:var(--ba);color:#7a1e1e;box-shadow:0 4px 14px rgba(224,90,71,.10)}
.flash.info{background:#f5f8ff;border-color:#e2ebff;border-left-color:var(--pri);color:#2c3557}
.flash-icon{width:22px;height:22px;flex-shrink:0;border-radius:6px;display:grid;place-items:center;margin-top:1px}
.flash.success .flash-icon{background:#cdeedb;color:var(--ok)}
.flash.error .flash-icon{background:#ffd6d6;color:var(--ba)}
.flash.info .flash-icon{background:#e2ebff;color:var(--pri)}
.flash-body{flex:1;min-width:0}
.flash strong{display:block;font-weight:600;margin-bottom:2px;font-size:13px}
.flash-close{
  all:unset;cursor:pointer;width:24px;height:24px;border-radius:6px;
  display:grid;place-items:center;color:currentColor;opacity:.55;
  flex-shrink:0;transition:opacity .15s,background .15s;
}
.flash-close:hover{opacity:1;background:rgba(0,0,0,.05)}
.flash.hiding{opacity:0;transform:translateY(-6px);transition:opacity .25s,transform .25s}
@keyframes flashIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:none}}

.tabs{display:flex;gap:6px;background:var(--pl);border-radius:12px;padding:5px;margin-bottom:36px}
.tab{all:unset;flex:1;text-align:center;cursor:pointer;padding:11px 12px;border-radius:9px;font-size:13px;font-weight:500;color:var(--mu);transition:background .15s,color .15s}
.tab:hover{color:var(--tx)}
.tab.on{background:var(--bg);color:var(--tx);box-shadow:0 1px 3px rgba(15,26,60,.10)}
.tab:focus-visible{outline:2px solid var(--pri);outline-offset:2px}

.view{display:none}
.view.on{display:block;animation:fade .22s ease}
@keyframes fade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.form-head h1{font-size:26px;font-weight:600;letter-spacing:-.02em;margin:0 0 8px}
.form-head p{color:var(--mu);font-size:13.5px;margin:0 0 32px;line-height:1.6}
.field{margin-bottom:20px}
label{display:block;font-size:12px;color:var(--mu);font-weight:500;margin:0 0 8px}
input[type=email],input[type=password],input[type=text]{width:100%;background:var(--bg);color:var(--tx);border:1px solid var(--bd);border-radius:10px;padding:12px 14px;font:inherit;transition:border-color .15s,box-shadow .15s}
input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(108,92,231,.16)}
input[aria-invalid="true"]{border-color:var(--ba);box-shadow:0 0 0 3px rgba(224,90,71,.14)}
.err{display:none;color:var(--ba);font-size:12px;margin-top:7px;font-weight:500;line-height:1.5}
.field.bad .err{display:block}
.field.bad input:not([type=checkbox]){border-color:var(--ba);box-shadow:0 0 0 3px rgba(224,90,71,.14)}
.row{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:6px 0 28px;flex-wrap:wrap}
.check{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--mu);cursor:pointer;margin:0;line-height:1.55}
.check input{width:16px;height:16px;accent-color:var(--pri);cursor:pointer;margin:2px 0 0;flex-shrink:0}
.field.terms{margin:4px 0 28px}
.field.terms.bad .check{color:var(--ba)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;width:100%;background:var(--pri);color:#fff;border:0;border-radius:10px;padding:13px 22px;font-weight:500;cursor:pointer;transition:background .15s,transform .05s;text-decoration:none}
.btn:hover{background:var(--pri-d);text-decoration:none}
.btn.ghost{background:transparent;color:var(--tx);border:1px solid var(--bd)}
.btn.ghost:hover{background:var(--pl);border-color:#DDE1EA}
.swap{margin:26px 0 0;text-align:center;font-size:13px;color:var(--mu)}
.swap button{all:unset;cursor:pointer;color:var(--pri);font-weight:500}
.swap button:hover{text-decoration:underline}
.note{margin:30px 0 0;text-align:center;font-size:12px;color:var(--mu);line-height:1.7}
.note b{color:var(--tx);font-weight:500}
.avatar{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:linear-gradient(140deg,var(--indigo),var(--pri));color:#fff;font-size:24px;font-weight:600;margin-bottom:22px;box-shadow:0 12px 28px rgba(108,92,231,.35)}
.stack{display:grid;gap:12px}
.badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;background:#EDF4FF;color:#2563EB;margin-left:6px}
.badge.admin{background:#F0EBFF;color:#7656D6}
@media(max-width:900px){
  .shell{grid-template-columns:1fr;min-height:100dvh}
  .media{position:relative;height:auto;padding:20px;min-height:0}
  .media-inner{height:auto;min-height:0}
  .media-copy,.media-foot{display:none}
  .panel{padding:40px 22px 64px;align-items:flex-start}
  .form-head h1{font-size:23px}
}
@media(max-width:420px){.panel{padding:32px 18px 56px}}
</style>
</head>
<body>
<div class="shell">
  <section class="media" aria-label="About the portal">
    <div class="media-inner">
      <div class="brand-head">
        <span class="mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.9-.9L3 21l1.9-4.9A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4z"/>
            <path d="M8.5 10.5h7M8.5 14h4.5"/>
          </svg>
        </span>
        <div>
          <strong>Public Relations</strong>
          <small>Feedback portal</small>
        </div>
      </div>
      <div class="media-copy">
        <h2>Every compliment and complaint, in one place.</h2>
        <p>Capture feedback as it arrives and monitor submissions across your channels from one place.</p>
      </div>
      <div class="media-foot">© 2026 Public Relations · Internal use only</div>
    </div>
  </section>

  <section class="panel">
    <main class="form-wrap">

    <?php if ($user): ?>

      <!-- =====================================================
           SIGNED-IN CARD
           ===================================================== -->
      <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>" role="status" aria-live="polite" data-flash>
          <span class="flash-icon"><?= flash_icon((string)$flash['type']) ?></span>
          <div class="flash-body">
            <strong><?= e($flash['title']) ?></strong>
            <?= e($flash['message']) ?>
          </div>
          <button type="button" class="flash-close" aria-label="Dismiss message" data-flash-close>&times;</button>
        </div>
      <?php endif; ?>

      <div class="form-head">
        <div class="avatar" aria-hidden="true"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div>
        <h1>Welcome, <?= e($user['name']) ?>
          <span class="badge <?= ($user['role'] ?? 'user') === 'admin' ? 'admin' : '' ?>">
            <?= ($user['role'] ?? 'user') === 'admin' ? 'Administrator' : 'Member' ?>
          </span>
        </h1>
        <p>You are signed in as <?= e($user['email']) ?>.</p>
      </div>

      <div class="stack">
        <?php if (($user['role'] ?? 'user') === 'admin'): ?>
          <a class="btn" href="admin/index.php">Open admin console</a>
          <a class="btn ghost" href="user/index.php">Open user dashboard</a>
        <?php else: ?>
          <a class="btn" href="user/index.php">Open feedback portal</a>
        <?php endif; ?>
        <form method="post" action="">
          <input type="hidden" name="action" value="logout">
          <button class="btn ghost" type="submit">Sign out</button>
        </form>
      </div>

      <p class="note">Session started <?= e(date('d M Y, H:i', (int)$user['since'])) ?>.</p>

    <?php else: ?>

      <!-- =====================================================
           LOGIN / REGISTER
           ===================================================== -->

      <?php if ($flash): ?>
        <div class="flash <?= e($flash['type']) ?>" role="status" aria-live="polite" data-flash>
          <span class="flash-icon"><?= flash_icon((string)$flash['type']) ?></span>
          <div class="flash-body">
            <strong><?= e($flash['title']) ?></strong>
            <?= e($flash['message']) ?>
          </div>
          <button type="button" class="flash-close" aria-label="Dismiss message" data-flash-close>&times;</button>
        </div>
      <?php endif; ?>

      <div class="tabs" role="tablist" aria-label="Authentication">
        <button class="tab <?= $isReg ? '' : 'on' ?>" role="tab" id="tab-login" data-v="login"
                aria-selected="<?= $isReg ? 'false' : 'true' ?>" aria-controls="v-login">Sign in</button>
        <button class="tab <?= $isReg ? 'on' : '' ?>" role="tab" id="tab-reg" data-v="reg"
                aria-selected="<?= $isReg ? 'true' : 'false' ?>" aria-controls="v-reg">Create account</button>
      </div>

      <!-- LOGIN VIEW -->
      <div class="view <?= $isReg ? '' : 'on' ?>" id="v-login" role="tabpanel" aria-labelledby="tab-login">
        <div class="form-head">
          <h1>Welcome back</h1>
          <p>Sign in to continue. Admins are taken to the console; members go straight to their dashboard.</p>
        </div>

        <form id="f-login" method="post" action="" novalidate>
          <input type="hidden" name="action" value="login">
          <div class="<?= fc($errors['login'], 'email') ?>">
            <label for="l-email">Email address</label>
            <input type="email" id="l-email" name="email" placeholder="you@organisation.com"
                   value="<?= e($loginEmail) ?>" autocomplete="username" required
                   <?= isset($errors['login']['email']) ? 'aria-invalid="true"' : '' ?>>
            <small class="err" role="alert"><?= e($errors['login']['email'] ?? 'Enter a valid email address.') ?></small>
          </div>
          <div class="<?= fc($errors['login'], 'password') ?>">
            <label for="l-pass">Password</label>
            <input type="password" id="l-pass" name="password" placeholder="Enter your password"
                   autocomplete="current-password" required
                   <?= isset($errors['login']['password']) ? 'aria-invalid="true"' : '' ?>>
            <small class="err" role="alert"><?= e($errors['login']['password'] ?? 'Enter your password.') ?></small>
          </div>
          <div class="row">
            <label class="check"><input type="checkbox" name="remember" checked> Keep me signed in</label>
          </div>
          <button class="btn" type="submit">Sign in</button>
        </form>

        <p class="swap">New here? <button type="button" data-v="reg">Create an account</button></p>
      </div>

      <!-- REGISTER VIEW -->
      <div class="view <?= $isReg ? 'on' : '' ?>" id="v-reg" role="tabpanel" aria-labelledby="tab-reg">
        <div class="form-head">
          <h1>Create your account</h1>
          <p>The first account created becomes the Administrator. Everyone after is a normal member.</p>
        </div>

        <form id="f-reg" method="post" action="" novalidate>
          <input type="hidden" name="action" value="register">
          <div class="<?= fc($errors['reg'], 'name') ?>">
            <label for="r-name">Full name</label>
            <input type="text" id="r-name" name="name" placeholder="e.g. Malachi Masaraure"
                   value="<?= e($old['reg']['name']) ?>" autocomplete="name" required
                   <?= isset($errors['reg']['name']) ? 'aria-invalid="true"' : '' ?>>
            <small class="err" role="alert"><?= e($errors['reg']['name'] ?? 'Enter your full name.') ?></small>
          </div>
          <div class="<?= fc($errors['reg'], 'email') ?>">
            <label for="r-email">Email address</label>
            <input type="email" id="r-email" name="email" placeholder="malachi@domain.co.zw"
                   value="<?= e($old['reg']['email']) ?>" autocomplete="email" required
                   <?= isset($errors['reg']['email']) ? 'aria-invalid="true"' : '' ?>>
            <small class="err" role="alert"><?= e($errors['reg']['email'] ?? 'Enter a valid email address.') ?></small>
          </div>
          <div class="<?= fc($errors['reg'], 'password') ?>">
            <label for="r-pass">Password</label>
            <input type="password" id="r-pass" name="password" placeholder="At least 8 characters"
                   autocomplete="new-password" required
                   <?= isset($errors['reg']['password']) ? 'aria-invalid="true"' : '' ?>>
            <small class="err" role="alert"><?= e($errors['reg']['password'] ?? 'Password must be at least 8 characters.') ?></small>
          </div>
          <div class="<?= fc($errors['reg'], 'confirm') ?>">
            <label for="r-confirm">Confirm password</label>
            <input type="password" id="r-confirm" name="confirm" placeholder="Re-enter your password"
                   autocomplete="new-password" required
                   <?= isset($errors['reg']['confirm']) ? 'aria-invalid="true"' : '' ?>>
            <small class="err" role="alert"><?= e($errors['reg']['confirm'] ?? 'Passwords do not match.') ?></small>
          </div>
          <div class="<?= fc($errors['reg'], 'terms') ?> terms">
            <label class="check" for="r-terms">
              <input type="checkbox" id="r-terms" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
              <span>I agree to the terms of use and privacy policy.</span>
            </label>
            <small class="err" role="alert"><?= e($errors['reg']['terms'] ?? 'Please accept the terms to continue.') ?></small>
          </div>
          <button class="btn" type="submit">Create account</button>
        </form>

        <p class="swap">Already registered? <button type="button" data-v="login">Sign in</button></p>
      </div>

    <?php endif; ?>

    </main>
  </section>
</div>

<script>
(function () {
  const $$ = s => [...document.querySelectorAll(s)];

  /* Tab switching */
  $$('[data-v]').forEach(b => b.addEventListener('click', () => {
    const v = b.dataset.v;
    $$('.view').forEach(el => el.classList.toggle('on', el.id === 'v-' + v));
    $$('.tab').forEach(t => {
      const on = t.dataset.v === v;
      t.classList.toggle('on', on);
      t.setAttribute('aria-selected', String(on));
    });
    $$('.field.bad').forEach(f => f.classList.remove('bad'));
    $$('input[aria-invalid="true"]').forEach(i => i.removeAttribute('aria-invalid'));
  }));

  /* Flash banner: close button + auto-dismiss + scroll-into-view */
  $$('[data-flash]').forEach(flash => {
    const closeBtn = flash.querySelector('[data-flash-close]');
    const dismiss = () => {
      flash.classList.add('hiding');
      setTimeout(() => flash.remove(), 260);
    };
    if (closeBtn) closeBtn.addEventListener('click', dismiss);
    setTimeout(dismiss, 6000);
    // Make sure it's visible even if user scrolled
    if (flash.getBoundingClientRect().top < 0) {
      flash.scrollIntoView({behavior: 'smooth', block: 'start'});
    }
  });

  /* Auto-clear field errors on input */
  $$('.field input, .field select, .field textarea').forEach(el => {
    const clear = () => {
      const field = el.closest('.field');
      if (field) field.classList.remove('bad');
      el.removeAttribute('aria-invalid');
    };
    el.addEventListener('input', clear);
    el.addEventListener('change', clear);
  });
  $$('.field.terms input[type=checkbox]').forEach(cb => {
    cb.addEventListener('change', () => cb.closest('.field')?.classList.remove('bad'));
  });

  /* Focus first invalid field */
  const firstBad = document.querySelector('.field.bad input, .field.bad select, .field.bad textarea');
  if (firstBad) setTimeout(() => firstBad.focus(), 80);

  /* After successful registration, focus the password field (email is prefilled) */
  const loginEmail = document.getElementById('l-email');
  const loginPass  = document.getElementById('l-pass');
  if (loginEmail && loginPass && loginEmail.value && !loginPass.value
      && document.getElementById('v-login').classList.contains('on')) {
    setTimeout(() => loginPass.focus(), 80);
  }
})();
</script>
</body>
</html>