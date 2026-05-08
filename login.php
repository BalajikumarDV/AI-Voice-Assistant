<?php
require "config.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = "Please fill in all fields.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Regenerate session ID to prevent session fixation
                session_regenerate_id(true);
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                header("Location: index.php");
                exit();
            } else {
                $message = "Incorrect password.";
                $messageType = "error";
            }
        } else {
            $message = "No account found with that username.";
            $messageType = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TARS — Login</title>
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

html, body {
  height: 100%;
  background: var(--bg-deep);
  font-family: var(--font-body);
  color: var(--text);
  overflow: hidden;
}

/* ── Animated background ── */
.bg-canvas {
  position: fixed; inset: 0; z-index: 0;
  background:
    radial-gradient(ellipse 80% 60% at 20% 10%,  rgba(59,130,246,.18) 0%, transparent 60%),
    radial-gradient(ellipse 60% 80% at 80% 90%,  rgba(99,102,241,.15) 0%, transparent 60%),
    radial-gradient(ellipse 50% 50% at 50% 50%,  rgba(14,165,233,.08) 0%, transparent 70%);
}

.grid-lines {
  position: fixed; inset: 0; z-index: 0;
  background-image:
    linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px);
  background-size: 40px 40px;
  mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black 30%, transparent 100%);
}

/* floating orbs */
.orb { position: fixed; border-radius: 50%; filter: blur(80px); z-index: 0; }
.orb-1 { width:420px;height:420px;top:-10%;left:-8%;  background:rgba(59,130,246,.2);  animation:float 9s ease-in-out infinite; }
.orb-2 { width:300px;height:300px;bottom:-5%;right:-5%;background:rgba(99,102,241,.18); animation:float 12s ease-in-out infinite reverse; }
.orb-3 { width:200px;height:200px;top:40%;right:15%;  background:rgba(14,165,233,.12); animation:float 7s ease-in-out infinite 2s; }

@keyframes float {
  0%,100%{transform:translateY(0) scale(1);}
  50%     {transform:translateY(-30px) scale(1.06);}
}

/* ── Card ── */
.wrap {
  position: relative; z-index: 1;
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 24px;
}

.card {
  width: 100%; max-width: 400px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 44px 40px;
  backdrop-filter: blur(24px);
  box-shadow: 0 32px 80px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.04) inset;
  animation: slideUp .7s cubic-bezier(.16,1,.3,1) both;
}

@keyframes slideUp {
  from{opacity:0;transform:translateY(32px) scale(.97);}
  to  {opacity:1;transform:translateY(0)   scale(1);}
}

/* ── Logo ── */
.logo-wrap {
  display: flex; flex-direction: column; align-items: center;
  gap: 10px; margin-bottom: 36px;
}

.logo-ring {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(59,130,246,.3), rgba(99,102,241,.3));
  border: 1px solid rgba(96,165,250,.4);
  display: flex; align-items: center; justify-content: center;
  font-size: 26px;
  box-shadow: 0 0 32px rgba(59,130,246,.25);
  animation: pulse-ring 3s ease-in-out infinite;
}

@keyframes pulse-ring {
  0%,100% { box-shadow: 0 0 20px rgba(59,130,246,.25); }
  50%      { box-shadow: 0 0 40px rgba(59,130,246,.45); }
}

.logo-name {
  font-family: var(--font-mono);
  font-size: 22px; font-weight: 700;
  letter-spacing: 4px;
  background: linear-gradient(90deg, #fbbf24, #f59e0b);
  -webkit-background-clip: text; -webkit-text-fill-color: transparent;
}

.logo-sub { font-size: 12px; color: var(--muted); letter-spacing: 1px; }

/* ── Form ── */
h2 { font-size: 20px; font-weight: 600; margin-bottom: 24px; color: var(--text); }

.field { position: relative; margin-bottom: 18px; }

.field label {
  display: block; font-size: 12px; font-weight: 500;
  color: var(--muted); letter-spacing: .5px;
  margin-bottom: 8px; text-transform: uppercase;
}

.field input {
  width: 100%; padding: 13px 16px;
  background: rgba(255,255,255,.05);
  border: 1px solid var(--border);
  border-radius: 10px; outline: none;
  color: var(--text); font-family: var(--font-body); font-size: 15px;
  transition: border-color .25s, box-shadow .25s, background .25s;
}

.field input::placeholder { color: var(--muted); }

.field input:focus {
  border-color: rgba(96,165,250,.5);
  background: rgba(255,255,255,.07);
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}

/* ── Alert ── */
.alert {
  padding: 12px 14px; border-radius: 10px;
  font-size: 13px; margin-bottom: 20px;
  display: flex; align-items: center; gap: 8px;
}
.alert.error   { background:rgba(248,113,113,.1); border:1px solid rgba(248,113,113,.25); color:var(--error); }
.alert.success { background:rgba(52,211,153,.1);  border:1px solid rgba(52,211,153,.25);  color:var(--success); }

/* ── Button ── */
.btn-primary {
  width: 100%; padding: 14px;
  background: linear-gradient(135deg, var(--accent), #d97706);
  border: none; border-radius: 10px;
  color: #fff; font-family: var(--font-body); font-size: 15px; font-weight: 600;
  cursor: pointer; letter-spacing: .3px;
  transition: opacity .2s, transform .15s, box-shadow .2s;
  box-shadow: 0 4px 20px rgba(59,130,246,.35);
}
.btn-primary:hover { opacity:.92; transform:translateY(-1px); box-shadow:0 8px 28px rgba(59,130,246,.45); }
.btn-primary:active { transform:translateY(0); }

/* ── Footer ── */
.card-footer {
  margin-top: 22px; text-align: center;
  font-size: 13px; color: var(--muted);
}
.card-footer a { color: var(--accent-glow); text-decoration: none; font-weight: 500; }
.card-footer a:hover { text-decoration: underline; }
</style>
</head>
<body>

<div class="bg-canvas"></div>
<div class="grid-lines"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="wrap">
  <div class="card">

    <div class="logo-wrap">
      <div class="logo-ring">▣</div>
      <div class="logo-name">TARS</div>
      <div class="logo-sub">Tactical Autonomous Robotic System</div>
    </div>

    <?php if ($message !== ''): ?>
    <div class="alert <?= $messageType ?>">
      <?= $messageType === 'error' ? '⚠' : '✓' ?> <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               placeholder="Enter your username" required
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               placeholder="Enter your password" required>
      </div>
      <button class="btn-primary" type="submit">Sign In →</button>
    </form>

    <div class="card-footer">
      No account? <a href="register.php">Create one free</a>
    </div>

  </div>
</div>

</body>
</html>
