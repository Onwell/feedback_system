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

/* =========================================================
   DATABASE CONNECTION (auto-creates DB + table)
   ========================================================= */
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
        $unknownDb = ($ex->getCode() === '1049')
            || (strpos($ex->getMessage(), 'Unknown database') !== false);

        if ($unknownDb) {
            try {
                $server = new PDO(
                    'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
                    DB_USER, DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $server->exec(
                    'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` ' .
                    'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
                $server = null;
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $opts);
            } catch (PDOException $ex2) {
                db_fail($ex2->getMessage());
            }
        } else {
            db_fail($ex->getMessage());
        }
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(120) NOT NULL,
            email      VARCHAR(190) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    return $pdo;
}

function db_fail(string $msg): void
{
    http_response_code(500);
    exit(
        '<h2 style="font-family:system-ui;padding:24px">Database connection failed</h2>' .
        '<p style="font-family:system-ui;padding:0 24px;color:#b00">' .
        htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') .
        '</p><p style="font-family:system-ui;padding:0 24px;color:#666">' .
        'Check that MySQL is running and that DB_USER / DB_PASS are correct. ' .
        'The database <b>' . DB_NAME . '</b> will be created automatically on first successful run.' .
        '</p>'
    );
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
    $stmt = db()->prepare('SELECT id, name, email, password, created_at FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    return $row ?: null;
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
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'id'    => (int)$found['id'],
                        'name'  => (string)$found['name'],
                        'email' => (string)$found['email'],
                        'since' => time(),
                    ];
                    flash_set('success', 'Signed in', 'Welcome back, ' . $found['name'] . '! You are now signed in.');
                    go();
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
        // Always set confirm error explicitly if empty OR mismatched
        if ($confirm === '') {
            $errors['reg']['confirm'] = 'Please confirm your password.';
        } elseif (!hash_equals($password, $confirm)) {
            $errors['reg']['confirm'] = 'Passwords do not match.';
        }
        // Always set terms error explicitly if not checked
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
                $stmt = db()->prepare(
                    'INSERT INTO users (name, email, password) VALUES (:name, :email, :password)'
                );
                $stmt->execute([
                    ':name'     => $name,
                    ':email'    => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                ]);

                $_SESSION['prefill_email'] = $email;
                flash_set('success', 'Account created', 'Your account was created successfully. You can now sign in.');
                go();
            } catch (PDOException $ex) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Sign in · Feedback Management Portal</title>

<link rel="icon" type="image/png" href="assets/feedback_favicon.png">
<link rel="icon" type="image/x-icon" href="assets/feedback_favicon.png">
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
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){
  --pri:#8B7CF6;--pri-d:#7A69EF;--ink:#E8EBF2;--pl:#1D2540;
  --bg:#161B25;--tx:#E8EBF2;--mu:#98A1B3;--bd:#262D3C;
  --ok:#5CC49A;--wa:#E0B04D;--ba:#E27A87;--navy:#0B1430;--indigo:#7C8CFF;color-scheme:dark}}
:root[data-theme="dark"]{
  --pri:#8B7CF6;--pri-d:#7A69EF;--ink:#E8EBF2;--pl:#1D2540;
  --bg:#161B25;--tx:#E8EBF2;--mu:#98A1B3;--bd:#262D3C;
  --ok:#5CC49A;--wa:#E0B04D;--ba:#E27A87;--navy:#0B1430;--indigo:#7C8CFF;color-scheme:dark}
*{box-sizing:border-box}
html,body{min-height:100%}
body{margin:0;background:var(--bg);color:var(--tx);font:400 14px/1.7 Poppins,"Segoe UI",system-ui,Arial,sans-serif;-webkit-font-smoothing:antialiased}
a{color:var(--pri);text-decoration:none}
a:hover{text-decoration:underline}
button{font:inherit}

/* ---------- LAYOUT ---------- */
.shell{display:grid;grid-template-columns:0.95fr 1fr;min-height:100vh;min-height:100dvh}

/* ---------- PHOTO PANEL WITH BLUE OVERLAY ---------- */
.media{
  position:relative;color:#fff;padding:56px 52px;overflow:hidden;
  background-color:var(--navy);
  background-image:var(--photo);
  background-position:center center;
  background-size:cover;
  background-repeat:no-repeat;
}
.media::before{
  content:"";position:absolute;inset:0;
  background:
    linear-gradient(160deg,
      rgba(15,26,60,.94) 0%,
      rgba(15,26,60,.82) 42%,
      rgba(91,110,245,.72) 100%),
    radial-gradient(120% 80% at 100% 0%, rgba(108,92,231,.45), transparent 60%);
}
.media::after{
  content:"";position:absolute;inset:0;
  background:linear-gradient(to top, rgba(15,26,60,.55), transparent 55%);
}
.media-inner{position:relative;z-index:1;display:flex;flex-direction:column;height:100%;min-height:100%}

.brand-head{display:flex;align-items:center;gap:14px}
.mark{width:46px;height:46px;border-radius:13px;background:linear-gradient(140deg,var(--indigo),var(--pri));
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  box-shadow:0 10px 24px rgba(10,16,40,.45);border:1px solid rgba(255,255,255,.16)}
.mark svg{width:24px;height:24px}
.brand-head strong{display:block;font-size:16px;font-weight:600;line-height:1.3}
.brand-head small{display:block;font-size:12px;color:#B7C0D8}

.media-copy{margin:auto 0;max-width:420px}
.media-copy h2{font-size:32px;line-height:1.25;font-weight:600;letter-spacing:-.02em;margin:0 0 16px;text-shadow:0 2px 18px rgba(0,0,0,.25)}
.media-copy p{margin:0;color:#DDE3F2;font-size:14.5px;line-height:1.75}

.feats{list-style:none;margin:40px 0 0;padding:0;display:grid;gap:18px}
.feats li{display:flex;align-items:flex-start;gap:14px;font-size:13.5px;color:#EAF0FB;line-height:1.55}
.feats .ic{width:30px;height:30px;border-radius:9px;background:rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;backdrop-filter:blur(4px)}
.feats .ic svg{width:15px;height:15px}

.media-foot{font-size:12px;color:#AEB8D0;padding-top:32px}

/* ---------- WHITE FORM PANEL ---------- */
.panel{background:var(--bg);display:flex;align-items:center;justify-content:center;padding:56px 24px}
.form-wrap{width:100%;max-width:420px}

.tabs{display:flex;gap:6px;background:var(--pl);border-radius:12px;padding:5px;margin-bottom:36px}
.tab{all:unset;flex:1;text-align:center;cursor:pointer;padding:11px 12px;border-radius:9px;
  font-size:13px;font-weight:500;color:var(--mu);transition:background .15s,color .15s}
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
input[type=email],input[type=password],input[type=text]{
  width:100%;background:var(--bg);color:var(--tx);border:1px solid var(--bd);
  border-radius:10px;padding:12px 14px;font:inherit;transition:border-color .15s,box-shadow .15s}
input::placeholder{color:#A6ADBB}
input:focus{outline:none;border-color:var(--pri);box-shadow:0 0 0 3px rgba(108,92,231,.16)}
input:focus-visible{outline:2px solid var(--pri);outline-offset:1px}

.pw{position:relative}
.pw input{padding-right:48px}
.peek{all:unset;cursor:pointer;position:absolute;top:50%;right:8px;transform:translateY(-50%);
  width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--mu)}
.peek:hover{background:var(--pl);color:var(--tx)}
.peek:focus-visible{outline:2px solid var(--pri);outline-offset:1px}
.peek svg{width:18px;height:18px}
.peek .eye-off{display:none}
.peek.on .eye{display:none}
.peek.on .eye-off{display:block}

.err{display:none;color:var(--ba);font-size:12px;margin-top:7px;font-weight:500;line-height:1.5}
.field.bad .err{display:block}
.field.bad input:not([type=checkbox]){border-color:var(--ba);box-shadow:0 0 0 3px rgba(224,90,71,.14)}

/* strength meter */
.meter{display:flex;gap:6px;margin-top:10px}
.meter i{flex:1;height:4px;border-radius:2px;background:var(--bd);transition:background .2s}
.meter[data-s="1"] i:nth-child(-n+1){background:var(--ba)}
.meter[data-s="2"] i:nth-child(-n+2){background:var(--wa)}
.meter[data-s="3"] i:nth-child(-n+3){background:var(--indigo)}
.meter[data-s="4"] i:nth-child(-n+4){background:var(--ok)}
.hint{font-size:12px;color:var(--mu);margin-top:7px}

.row{display:flex;align-items:center;justify-content:space-between;gap:14px;margin:6px 0 28px;flex-wrap:wrap}
.check{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:var(--mu);cursor:pointer;margin:0;line-height:1.55}
.check input{width:16px;height:16px;accent-color:var(--pri);cursor:pointer;margin:2px 0 0;flex-shrink:0}
.field.terms{margin:4px 0 28px}
.field.terms.bad .check{color:var(--ba)}
.row a{font-size:13px;font-weight:500}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;width:100%;
  background:var(--pri);color:#fff;border:0;border-radius:10px;padding:13px 22px;
  font-weight:500;cursor:pointer;transition:background .15s,transform .05s;text-decoration:none}
.btn:hover{background:var(--pri-d);text-decoration:none}
.btn:active{transform:translateY(1px)}
.btn:disabled{opacity:.72;cursor:default;transform:none}
.btn:focus-visible{outline:2px solid var(--pri);outline-offset:2px}
.btn .spin{width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;
  border-radius:50%;animation:spin .7s linear infinite;display:none}
.btn.loading .spin{display:block}
@keyframes spin{to{transform:rotate(360deg)}}
.btn.ghost{background:transparent;color:var(--tx);border:1px solid var(--bd)}
.btn.ghost:hover{background:var(--pl);border-color:#DDE1EA}

.divider{display:flex;align-items:center;gap:14px;color:var(--mu);font-size:12px;margin:26px 0}
.divider::before,.divider::after{content:"";flex:1;height:1px;background:var(--bd)}

.msg{display:none;margin-top:20px;padding:14px 16px;border-radius:10px;background:var(--pl);
  border-left:3px solid var(--pri);font-size:13px;font-weight:500;line-height:1.6}
.msg.on{display:block}
.msg.ok{background:rgba(46,125,91,.10);border-left-color:var(--ok);color:var(--ok)}

.swap{margin:26px 0 0;text-align:center;font-size:13px;color:var(--mu)}
.swap button{all:unset;cursor:pointer;color:var(--pri);font-weight:500}
.swap button:hover{text-decoration:underline}
.swap button:focus-visible{outline:2px solid var(--pri);outline-offset:2px;border-radius:4px}

.note{margin:30px 0 0;text-align:center;font-size:12px;color:var(--mu);line-height:1.7}
.note b{color:var(--tx);font-weight:500}

/* ---------- SIGNED-IN CARD ---------- */
.avatar{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  background:linear-gradient(140deg,var(--indigo),var(--pri));color:#fff;font-size:24px;font-weight:600;
  margin-bottom:22px;box-shadow:0 12px 28px rgba(108,92,231,.35)}
.stack{display:grid;gap:12px}

/* ---------- POPUP / MODAL ---------- */
.modal-backdrop{
  position:fixed;inset:0;z-index:90;display:none;align-items:center;justify-content:center;
  padding:24px;background:rgba(9,14,32,.58);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)
}
.modal-backdrop.on{display:flex;animation:fadeIn .18s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.modal{
  width:100%;max-width:390px;background:var(--bg);color:var(--tx);border-radius:18px;
  padding:30px 26px 24px;text-align:center;border:1px solid var(--bd);
  box-shadow:0 28px 70px rgba(9,14,32,.4);
  animation:pop .24s cubic-bezier(.2,.9,.3,1.15)
}
@keyframes pop{from{opacity:0;transform:translateY(12px) scale(.96)}to{opacity:1;transform:none}}
.modal .ic{width:58px;height:58px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center}
.modal .ic svg{width:27px;height:27px}
.modal.success .ic{background:rgba(46,125,91,.13);color:var(--ok)}
.modal.error .ic{background:rgba(224,90,71,.13);color:var(--ba)}
.modal.info .ic{background:rgba(108,92,231,.13);color:var(--pri)}
.modal h3{margin:0 0 8px;font-size:19px;font-weight:600;letter-spacing:-.01em}
.modal p{margin:0 0 22px;color:var(--mu);font-size:13.5px;line-height:1.65}
.modal .btn{width:100%}

/* ---------- RESPONSIVE ---------- */
@media(min-width:901px){
  .media{position:sticky;top:0;height:100vh;height:100dvh;align-self:start}
}
@media(max-width:900px){
  .shell{grid-template-columns:1fr;min-height:100dvh}
  .media{position:relative;height:auto;padding:20px;min-height:0;border-radius:0}
  .media::after{display:none}
  .media-inner{height:auto;min-height:0}
  .media-copy,.media-foot,.feats{display:none}
  .brand-head strong{font-size:15px}
  .brand-head small{font-size:11px}
  .mark{width:40px;height:40px;border-radius:11px}
  .mark svg{width:21px;height:21px}
  .panel{padding:40px 22px 64px;align-items:flex-start}
  .form-head h1{font-size:23px}
  .form-head p{margin-bottom:28px}
  .tabs{margin-bottom:30px}
}
@media(max-width:420px){
  .panel{padding:32px 18px 56px}
  .btn{padding:13px 18px}
  .row{gap:10px}
}
</style>
</head>
<body>
<div class="shell">

  <!-- ============ PHOTO + BLUE OVERLAY PANEL ============ -->
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
        <p>Capture feedback as it arrives, follow each case from open to resolved, and give every stakeholder a clear answer.</p>
        <ul class="feats">
          <li>
            <span class="ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
            <span>A central register for every submission</span>
          </li>
          <li>
            <span class="ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg></span>
            <span>Live status tracking from open to resolved</span>
          </li>
          <li>
            <span class="ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg></span>
            <span>Clear ownership with assignees and actions</span>
          </li>
        </ul>
      </div>

      <div class="media-foot">© 2026 Public Relations · Internal use only</div>
    </div>
  </section>

  <!-- ============ WHITE FORM PANEL ============ -->
  <section class="panel">
    <main class="form-wrap">

    <?php if ($user): ?>

      <!-- ---------- SIGNED IN ---------- -->
      <div class="form-head">
        <div class="avatar" aria-hidden="true"><?= e(strtoupper(substr($user['name'], 0, 1))) ?></div>
        <h1>Welcome, <?= e($user['name']) ?></h1>
        <p>You are signed in as <?= e($user['email']) ?>.</p>
      </div>

      <div class="stack">
        <a class="btn" href="http://localhost/SMUG/feedback_system/user/index.php">Open feedback portal</a>
        <form method="post" action="">
          <input type="hidden" name="action" value="logout">
          <button class="btn ghost" type="submit">Sign out</button>
        </form>
      </div>

      <p class="note">Session started <?= e(date('d M Y, H:i', (int)$user['since'])) ?>.</p>

    <?php else: ?>

      <div class="tabs" role="tablist" aria-label="Authentication">
        <button class="tab <?= $isReg ? '' : 'on' ?>" role="tab" id="tab-login" data-v="login"
                aria-selected="<?= $isReg ? 'false' : 'true' ?>" aria-controls="v-login">Sign in</button>
        <button class="tab <?= $isReg ? 'on' : '' ?>" role="tab" id="tab-reg" data-v="reg"
                aria-selected="<?= $isReg ? 'true' : 'false' ?>" aria-controls="v-reg">Create account</button>
      </div>

      <!-- ---------- LOGIN ---------- -->
      <div class="view <?= $isReg ? '' : 'on' ?>" id="v-login" role="tabpanel" aria-labelledby="tab-login">
        <div class="form-head">
          <h1>Welcome back</h1>
          <p>Sign in to continue to your feedback dashboard.</p>
        </div>

        <form id="f-login" method="post" action="" novalidate>
          <input type="hidden" name="action" value="login">

          <div class="<?= fc($errors['login'], 'email') ?>" id="l-fe">
            <label for="l-email">Email address</label>
            <input type="email" id="l-email" name="email" placeholder="you@organisation.com"
                   value="<?= e($loginEmail) ?>" autocomplete="username" inputmode="email" aria-describedby="l-eemail">
            <small class="err" id="l-eemail" role="alert"><?= e($errors['login']['email'] ?? 'Enter a valid email address.') ?></small>
          </div>

          <div class="<?= fc($errors['login'], 'password') ?>" id="l-fp">
            <label for="l-pass">Password</label>
            <div class="pw">
              <input type="password" id="l-pass" name="password" placeholder="Enter your password"
                     autocomplete="current-password" aria-describedby="l-epass">
              <button type="button" class="peek" data-for="l-pass" aria-label="Show password" aria-pressed="false">
                <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>
              </button>
            </div>
            <small class="err" id="l-epass" role="alert"><?= e($errors['login']['password'] ?? 'Enter your password.') ?></small>
          </div>

          <div class="row">
            <label class="check"><input type="checkbox" id="l-remember" name="remember" checked> Keep me signed in</label>
            <a href="#" id="forgot">Forgot password?</a>
          </div>

          <button class="btn" type="submit">
            <span class="spin" aria-hidden="true"></span><span class="btn-label">Sign in</span>
          </button>

          <div class="msg" id="l-msg" role="status" aria-live="polite"></div>
        </form>

        <p class="swap">New here? <button type="button" data-v="reg">Create an account</button></p>
      </div>

      <!-- ---------- REGISTER ---------- -->
      <div class="view <?= $isReg ? 'on' : '' ?>" id="v-reg" role="tabpanel" aria-labelledby="tab-reg">
        <div class="form-head">
          <h1>Create your account</h1>
          <p>Set up access to record and track feedback.</p>
        </div>

        <form id="f-reg" method="post" action="" novalidate>
          <input type="hidden" name="action" value="register">

          <div class="<?= fc($errors['reg'], 'name') ?>" id="r-fn">
            <label for="r-name">Full name</label>
            <input type="text" id="r-name" name="name" placeholder="e.g. Malachi Masaraure"
                   value="<?= e($old['reg']['name']) ?>" autocomplete="name" aria-describedby="r-ename">
            <small class="err" id="r-ename" role="alert"><?= e($errors['reg']['name'] ?? 'Enter your full name.') ?></small>
          </div>

          <div class="<?= fc($errors['reg'], 'email') ?>" id="r-fe">
            <label for="r-email">Email address</label>
            <input type="email" id="r-email" name="email" placeholder="malachi@domain.co.zw"
                   value="<?= e($old['reg']['email']) ?>" autocomplete="email" inputmode="email" aria-describedby="r-eemail">
            <small class="err" id="r-eemail" role="alert"><?= e($errors['reg']['email'] ?? 'Enter a valid email address.') ?></small>
          </div>

          <div class="<?= fc($errors['reg'], 'password') ?>" id="r-fp">
            <label for="r-pass">Password</label>
            <div class="pw">
              <input type="password" id="r-pass" name="password" placeholder="At least 8 characters"
                     autocomplete="new-password" aria-describedby="r-epass r-hint">
              <button type="button" class="peek" data-for="r-pass" aria-label="Show password" aria-pressed="false">
                <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>
              </button>
            </div>
            <div class="meter" id="r-meter" data-s="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <div class="hint" id="r-hint">Use 8+ characters with letters, numbers and a symbol.</div>
            <small class="err" id="r-epass" role="alert"><?= e($errors['reg']['password'] ?? 'Password must be at least 8 characters.') ?></small>
          </div>

          <div class="<?= fc($errors['reg'], 'confirm') ?>" id="r-fc">
            <label for="r-confirm">Confirm password</label>
            <div class="pw">
              <input type="password" id="r-confirm" name="confirm" placeholder="Re-enter your password"
                     autocomplete="new-password" aria-describedby="r-econf">
              <button type="button" class="peek" data-for="r-confirm" aria-label="Show password" aria-pressed="false">
                <svg class="eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>
              </button>
            </div>
            <small class="err" id="r-econf" role="alert"><?= e($errors['reg']['confirm'] ?? 'Passwords do not match.') ?></small>
          </div>

          <div class="<?= fc($errors['reg'], 'terms') ?> terms" id="r-ft">
            <label class="check" for="r-terms">
              <input type="checkbox" id="r-terms" name="terms" value="1" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
              <span>I agree to the <a href="#">terms of use</a> and <a href="#">privacy policy</a>.</span>
            </label>
            <small class="err" id="r-eterms" role="alert"><?= e($errors['reg']['terms'] ?? 'Please accept the terms to continue.') ?></small>
          </div>

          <button class="btn" type="submit">
            <span class="spin" aria-hidden="true"></span><span class="btn-label">Create account</span>
          </button>

          <div class="msg" id="r-msg" role="status" aria-live="polite"></div>
        </form>

        <p class="swap">Already registered? <button type="button" data-v="login">Sign in</button></p>
        <p class="note">Accounts are stored in the <b>ncc_feedback_db</b> MySQL database.</p>
      </div>

    <?php endif; ?>

    </main>
  </section>
</div>

<!-- ============ POPUP ============ -->
<div class="modal-backdrop" id="popup">
  <div class="modal" id="popup-box" role="dialog" aria-modal="true" aria-labelledby="popup-title" aria-describedby="popup-text">
    <div class="ic" id="popup-ic" aria-hidden="true"></div>
    <h3 id="popup-title"></h3>
    <p id="popup-text"></p>
    <button class="btn" type="button" id="popup-ok">Continue</button>
  </div>
</div>

<?php if ($flash): ?>
<script>
window.__FLASH__ = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php endif; ?>

<script>
(function () {
  const $  = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];

  /* ---------------- POPUP ---------------- */
  const ICONS = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.8v5.4M12 16.6v.01"/></svg>',
    info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5.2M12 7.8v.01"/></svg>'
  };

  const back = $('#popup');
  const box  = $('#popup-box');

  function showPopup(type, title, text) {
    if (!back) return;
    type = ICONS[type] ? type : 'info';
    box.className = 'modal ' + type;
    $('#popup-ic').innerHTML = ICONS[type];
    $('#popup-title').textContent = title || '';
    $('#popup-text').textContent = text || '';
    back.classList.add('on');
    document.body.style.overflow = 'hidden';
    setTimeout(() => $('#popup-ok').focus(), 60);
  }

  function closePopup() {
    if (!back) return;
    back.classList.remove('on');
    document.body.style.overflow = '';
  }

  if (back) {
    $('#popup-ok').addEventListener('click', closePopup);
    back.addEventListener('click', e => { if (e.target === back) closePopup(); });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && back.classList.contains('on')) closePopup();
    });
  }

  if (window.__FLASH__) {
    showPopup(window.__FLASH__.type, window.__FLASH__.title, window.__FLASH__.message);
  }

  /* ---------------- FORMS ---------------- */
  if (!$('#f-login')) return;

  function show(v, focus) {
    $$('.view').forEach(el => el.classList.toggle('on', el.id === 'v-' + v));
    $$('.tab').forEach(t => {
      const on = t.dataset.v === v;
      t.classList.toggle('on', on);
      t.setAttribute('aria-selected', String(on));
    });
    if (focus) {
      const first = document.querySelector('#v-' + v + ' input');
      if (first) setTimeout(() => first.focus(), 60);
    }
  }
  $$('[data-v]').forEach(b => b.addEventListener('click', () => show(b.dataset.v, true)));

  $$('.peek').forEach(b => {
    const input = document.getElementById(b.dataset.for);
    if (!input) return;
    b.addEventListener('click', () => {
      const showPw = input.type === 'password';
      input.type = showPw ? 'text' : 'password';
      b.classList.toggle('on', showPw);
      b.setAttribute('aria-pressed', String(showPw));
      b.setAttribute('aria-label', showPw ? 'Hide password' : 'Show password');
      input.focus();
    });
  });

  const isEmail = v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
  const setBad  = (id, bad) => { const el = $(id); if (el) el.classList.toggle('bad', bad); };

  /* Clear a field's error state only when the user actually edits it */
  const bindClear = (inputSel, fieldSel) => {
    const i = $(inputSel);
    if (!i) return;
    const ev = i.type === 'checkbox' ? 'change' : 'input';
    i.addEventListener(ev, () => {
      const f = $(fieldSel);
      if (f) f.classList.remove('bad');
    });
  };

  bindClear('#l-email', '#l-fe');
  bindClear('#l-pass',  '#l-fp');
  bindClear('#r-name',  '#r-fn');
  bindClear('#r-email', '#r-fe');
  bindClear('#r-pass',  '#r-fp');
  bindClear('#r-confirm', '#r-fc');
  bindClear('#r-terms', '#r-ft');

  /* Real-time password match feedback */
  function checkPasswordMatch(showError) {
    const pass = $('#r-pass');
    const conf = $('#r-confirm');
    if (!pass || !conf) return true;
    if (!conf.value) {
      if (showError) {
        setBad('#r-fc', true);
        const err = $('#r-econf');
        if (err) err.textContent = 'Please confirm your password.';
      }
      return false;
    }
    if (conf.value !== pass.value) {
      setBad('#r-fc', true);
      const err = $('#r-econf');
      if (err) err.textContent = 'Passwords do not match.';
      return false;
    }
    setBad('#r-fc', false);
    return true;
  }

  const rp = $('#r-pass');
  const rc = $('#r-confirm');
  if (rp) rp.addEventListener('blur', () => { if (rc && rc.value) checkPasswordMatch(false); });
  if (rc) rc.addEventListener('blur', () => checkPasswordMatch(false));

  /* Password strength meter */
  function score(p) {
    let s = 0;
    if (p.length >= 8) s++;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/\d/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return p ? Math.max(1, s) : 0;
  }
  const LABEL = { 0: '', 1: 'Weak', 2: 'Fair', 3: 'Good', 4: 'Strong' };

  if (rp) {
    rp.addEventListener('input', e => {
      const p = e.target.value, s = score(p);
      const meter = $('#r-meter'), hint = $('#r-hint');
      if (!meter || !hint) return;
      meter.dataset.s = String(s);
      hint.textContent = p
        ? LABEL[s] + ' password' + (s < 4 ? ' — add ' +
            (p.length < 8 ? 'more characters' : (/[A-Z]/.test(p) && /[a-z]/.test(p) ? '' : 'mixed case, ')) +
            (/[^A-Za-z0-9]/.test(p) ? '' : 'a symbol') : '')
        : 'Use 8+ characters with letters, numbers and a symbol.';
      hint.style.color = s === 1 ? 'var(--ba)' : s === 2 ? 'var(--wa)' : s >= 3 ? 'var(--ok)' : 'var(--mu)';
    });
  }

  const forgot = $('#forgot');
  if (forgot) {
    forgot.addEventListener('click', e => {
      e.preventDefault();
      showPopup('info', 'Reset your password', 'Contact your administrator to reset your password.');
    });
  }

  function markBusy(form, label) {
    const btn = form.querySelector('.btn[type=submit]');
    if (!btn) return;
    btn.classList.add('loading');
    btn.disabled = true;
    const lbl = btn.querySelector('.btn-label');
    if (lbl) lbl.textContent = label;
  }

  $('#f-login').addEventListener('submit', e => {
    const email = $('#l-email').value.trim();
    const pass  = $('#l-pass').value;

    const badE = !isEmail(email);
    const badP = pass === '';

    setBad('#l-fe', badE);
    setBad('#l-fp', badP);

    if (badE || badP) {
      e.preventDefault();
      (badE ? $('#l-email') : $('#l-pass')).focus();
      showPopup('error', 'Sign in failed', badE
        ? 'Please enter a valid email address.'
        : 'Please enter your password.');
      return;
    }
    markBusy(e.target, 'Signing in…');
  });

  $('#f-reg').addEventListener('submit', e => {
    const name = $('#r-name').value.trim();
    const email = $('#r-email').value.trim();
    const pass  = $('#r-pass').value;
    const conf  = $('#r-confirm').value;
    const terms = $('#r-terms').checked;

    const badN = name.length < 2;
    const badE = !isEmail(email);
    const badP = pass.length < 8;
    const badC = !conf || conf !== pass;
    const badT = !terms;

    setBad('#r-fn', badN);
    setBad('#r-fe', badE);
    setBad('#r-fp', badP);
    setBad('#r-fc', badC);
    setBad('#r-ft', badT);

    /* Update confirm error message text explicitly */
    if (badC) {
      const err = $('#r-econf');
      if (err) err.textContent = !conf ? 'Please confirm your password.' : 'Passwords do not match.';
    }

    if (badN || badE || badP || badC || badT) {
      e.preventDefault();
      const first = badN ? '#r-name' : badE ? '#r-email' : badP ? '#r-pass' : badC ? '#r-confirm' : '#r-terms';
      const target = $(first);
      if (target) target.focus();
      showPopup('error', 'Registration failed',
        badN ? 'Please enter your full name.'
        : badE ? 'Please enter a valid email address.'
        : badP ? 'Your password must be at least 8 characters.'
        : badC ? (!conf ? 'Please confirm your password.' : 'The passwords you entered do not match.')
        : 'Please accept the terms to continue.');
      return;
    }
    markBusy(e.target, 'Creating account…');
  });
})();
</script>
</body>
</html>