<?php
require "config.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password']       ?? '';
    $password2 = $_POST['password2']      ?? '';

    if ($username === '' || $password === '' || $password2 === '') {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } elseif (strlen($username) < 3 || strlen($username) > 30) {
        $message = "Username must be 3–30 characters.";
        $messageType = "error";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $message = "Username may only contain letters, numbers, and underscores.";
        $messageType = "error";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
        $messageType = "error";
    } elseif ($password !== $password2) {
        $message = "Passwords do not match.";
        $messageType = "error";
    } else {
        // Check uniqueness
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "That username is already taken.";
            $messageType = "error";
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);
            if ($stmt->execute()) {
                $message = "Account created! You can now log in.";
                $messageType = "success";
            } else {
                $message = "Registration failed. Please try again.";
                $messageType = "error";
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TARS — Register</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
  --bg-deep:    #050811;
  --bg-card:    rgba(255,255,255,0.04);
  --border:     rgba(255,255,255,0.10);
  --accent:     #f59e0b;
  --accent-glow:#fbbf24;
  --text:       #e2e8f0;
  --muted:      #64748b;
  --error:      #f87171;
  --success:    #34d399;
  --radius:     16px;
  --font-body:  'Outfit', sans-serif;
  --font-mono:  'Space Mono', monospace;
}

html, body { height: 100%; background: var(--bg-deep); font-family: var(--font-body); color: var(--text); overflow: hidden; }

.bg-canvas { position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 80% 60% at 20% 10%,rgba(59,130,246,.18) 0%,transparent 60%),radial-gradient(ellipse 60% 80% at 80% 90%,rgba(99,102,241,.15) 0%,transparent 60%); }
.grid-lines { position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:40px 40px;mask-image:radial-gradient(ellipse 70% 70% at 50% 50%,black 30%,transparent 100%); }
.orb { position:fixed;border-radius:50%;filter:blur(80px);z-index:0; }
.orb-1 { width:420px;height:420px;top:-10%;left:-8%;background:rgba(59,130,246,.18);animation:float 9s ease-in-out infinite; }
.orb-2 { width:300px;height:300px;bottom:-5%;right:-5%;background:rgba(99,102,241,.18);animation:float 12s ease-in-out infinite reverse; }
@keyframes float { 0%,100%{transform:translateY(0) scale(1);} 50%{transform:translateY(-30px) scale(1.06);} }

.wrap { position:relative;z-index:1;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px; }

.card { width:100%;max-width:420px;background:var(--bg-card);border:1px solid var(--border);border-radius:24px;padding:44px 40px;backdrop-filter:blur(24px);box-shadow:0 32px 80px rgba(0,0,0,.55);animation:slideUp .7s cubic-bezier(.16,1,.3,1) both; }
@keyframes slideUp { from{opacity:0;transform:translateY(32px) scale(.97);} to{opacity:1;transform:none;} }

.logo-wrap { display:flex;flex-direction:column;align-items:center;gap:10px;margin-bottom:36px; }
.logo-ring { width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,rgba(59,130,246,.3),rgba(99,102,241,.3));border:1px solid rgba(96,165,250,.4);display:flex;align-items:center;justify-content:center;font-size:26px;box-shadow:0 0 32px rgba(59,130,246,.25);animation:pulse-ring 3s ease-in-out infinite; }
@keyframes pulse-ring { 0%,100%{box-shadow:0 0 20px rgba(59,130,246,.25);} 50%{box-shadow:0 0 40px rgba(59,130,246,.45);} }
.logo-name { font-family:var(--font-mono);font-size:22px;font-weight:700;letter-spacing:4px;background:linear-gradient(90deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent; }
.logo-sub { font-size:12px;color:var(--muted);letter-spacing:1px; }

.field { position:relative;margin-bottom:16px; }
.field label { display:block;font-size:12px;font-weight:500;color:var(--muted);letter-spacing:.5px;margin-bottom:8px;text-transform:uppercase; }
.field input { width:100%;padding:13px 16px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;outline:none;color:var(--text);font-family:var(--font-body);font-size:15px;transition:border-color .25s,box-shadow .25s,background .25s; }
.field input::placeholder { color:var(--muted); }
.field input:focus { border-color:rgba(96,165,250,.5);background:rgba(255,255,255,.07);box-shadow:0 0 0 3px rgba(59,130,246,.15); }

.alert { padding:12px 14px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px; }
.alert.error   { background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--error); }
.alert.success { background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:var(--success); }

.btn-primary { width:100%;padding:14px;background:linear-gradient(135deg,var(--accent),#d97706);border:none;border-radius:10px;color:#fff;font-family:var(--font-body);font-size:15px;font-weight:600;cursor:pointer;letter-spacing:.3px;transition:opacity .2s,transform .15s,box-shadow .2s;box-shadow:0 4px 20px rgba(59,130,246,.35); }
.btn-primary:hover { opacity:.92;transform:translateY(-1px);box-shadow:0 8px 28px rgba(59,130,246,.45); }

.card-footer { margin-top:22px;text-align:center;font-size:13px;color:var(--muted); }
.card-footer a { color:var(--accent-glow);text-decoration:none;font-weight:500; }
.card-footer a:hover { text-decoration:underline; }

.hint { font-size:11px;color:var(--muted);margin-top:5px; }
</style>
</head>
<body>

<div class="bg-canvas"></div>
<div class="grid-lines"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="wrap">
  <div class="card">

    <div class="logo-wrap">
      <div class="logo-ring">▣</div>
      <div class="logo-name">TARS</div>
      <div class="logo-sub">Create your account</div>
    </div>

    <?php if ($message !== ''): ?>
    <div class="alert <?= $messageType ?>">
      <?= $messageType === 'error' ? '⚠' : '✓' ?> <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($messageType !== 'success'): ?>
    <form method="POST" autocomplete="off">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               placeholder="Choose a username" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <div class="hint">Letters, numbers, and underscores only (3–30 chars)</div>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Choose a password" required>
        <div class="hint">At least 6 characters</div>
      </div>
      <div class="field">
        <label for="password2">Confirm Password</label>
        <input type="password" id="password2" name="password2"
               placeholder="Repeat your password" required>
      </div>
      <button class="btn-primary" type="submit">Create Account →</button>
    </form>
    <?php endif; ?>

    <div class="card-footer">
      Already have an account? <a href="login.php">Sign in</a>
    </div>

  </div>
</div>

</body>
</html>
