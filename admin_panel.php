<?php
require "config.php";

if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: login.php");
    exit();
}

// ── Quick stats ──────────────────────────────────────────────
$totalUsers   = $conn->query("SELECT COUNT(*) AS n FROM users")->fetch_assoc()['n'];
$totalChats   = $conn->query("SELECT COUNT(*) AS n FROM interactions")->fetch_assoc()['n'];
$totalCmds    = $conn->query("SELECT COUNT(*) AS n FROM commands")->fetch_assoc()['n'];
$totalKB      = $conn->query("SELECT COUNT(*) AS n FROM knowledge_base")->fetch_assoc()['n'];

// ── Recent 5 interactions ────────────────────────────────────
$recent = $conn->query("
    SELECT u.username, i.command, i.created_at
    FROM interactions i JOIN users u ON i.user_id=u.id
    ORDER BY i.created_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TARS Admin Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
:root{
  --bg:#050811;--surface:rgba(255,255,255,.04);--border:rgba(255,255,255,.08);
  --accent:#f59e0b;--text:#e2e8f0;--muted:#64748b;--font:'Outfit',sans-serif;--mono:'Space Mono',monospace;
}
body{min-height:100vh;background:var(--bg);font-family:var(--font);color:var(--text);}

.bg-canvas{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 60% 50% at 15% 10%,rgba(59,130,246,.12) 0%,transparent 60%),radial-gradient(ellipse 50% 60% at 85% 90%,rgba(99,102,241,.10) 0%,transparent 60%);}
.grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:48px 48px;mask-image:radial-gradient(ellipse 80% 80% at 50% 50%,black 20%,transparent 100%);}

.wrap{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:32px 24px;}

.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;}
.title-group{display:flex;align-items:center;gap:12px;}
.logo{font-family:var(--mono);font-size:20px;letter-spacing:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.badge{background:rgba(59,130,246,.15);border:1px solid rgba(96,165,250,.3);color:#fbbf24;font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600;}
.btn-back{padding:9px 16px;border-radius:9px;background:var(--surface);border:1px solid var(--border);color:var(--muted);font-family:var(--font);font-size:13px;cursor:pointer;text-decoration:none;transition:color .2s,background .2s;}
.btn-back:hover{color:var(--text);background:rgba(255,255,255,.07);}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:32px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;display:flex;flex-direction:column;gap:6px;}
.stat-icon{font-size:22px;}
.stat-value{font-size:28px;font-weight:700;font-family:var(--mono);background:linear-gradient(90deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.stat-label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}

/* Nav */
.nav-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:32px;}
.nav-card{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px 22px;text-decoration:none;display:flex;align-items:center;gap:14px;transition:background .2s,border-color .2s,transform .15s;}
.nav-card:hover{background:rgba(255,255,255,.07);border-color:rgba(96,165,250,.3);transform:translateY(-2px);}
.nav-icon{width:42px;height:42px;border-radius:10px;background:rgba(59,130,246,.15);border:1px solid rgba(96,165,250,.2);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.nav-title{font-weight:600;font-size:14px;margin-bottom:2px;color:var(--text);}
.nav-desc{font-size:12px;color:var(--muted);}

/* Recent */
.section-title{font-size:14px;font-weight:600;color:var(--muted);letter-spacing:.5px;text-transform:uppercase;margin-bottom:12px;}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:13px 18px;font-size:11px;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--border);}
td{padding:13px 18px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);}
tr:last-child td{border:none;}
.tag{font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(59,130,246,.12);border:1px solid rgba(96,165,250,.2);color:#fbbf24;}
</style>
</head>
<body>
<div class="bg-canvas"></div>
<div class="grid"></div>

<div class="wrap">
  <div class="header">
    <div class="title-group">
      <div class="logo">TARS</div>
      <div class="badge">Admin</div>
    </div>
    <a href="index.php" class="btn-back">← Back to Chat</a>
  </div>

  <!-- Stats -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon">👤</div>
      <div class="stat-value"><?= $totalUsers ?></div>
      <div class="stat-label">Registered Users</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">💬</div>
      <div class="stat-value"><?= $totalChats ?></div>
      <div class="stat-label">Total Interactions</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">⚡</div>
      <div class="stat-value"><?= $totalCmds ?></div>
      <div class="stat-label">Custom Commands</div>
    </div>
    <div class="stat-card">
      <div class="stat-icon">🧠</div>
      <div class="stat-value"><?= $totalKB ?></div>
      <div class="stat-label">Cached Knowledge</div>
    </div>
  </div>

  <!-- Navigation -->
  <div class="nav-grid">
    <a href="admin_users.php" class="nav-card">
      <div class="nav-icon">👥</div>
      <div><div class="nav-title">Manage Users</div><div class="nav-desc">View and manage user accounts</div></div>
    </a>
    <a href="admin_commands.php" class="nav-card">
      <div class="nav-icon">⚡</div>
      <div><div class="nav-title">Custom Commands</div><div class="nav-desc">Add keyword → response rules</div></div>
    </a>
    <a href="admin_websites.php" class="nav-card">
      <div class="nav-icon">🌐</div>
      <div><div class="nav-title">Website Shortcuts</div><div class="nav-desc">Manage "open X" commands</div></div>
    </a>
    <a href="admin_interactions.php" class="nav-card">
      <div class="nav-icon">📊</div>
      <div><div class="nav-title">Chat Logs</div><div class="nav-desc">View all conversations</div></div>
    </a>
  </div>

  <!-- Recent interactions -->
  <div class="section-title">Recent Activity</div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>User</th><th>Command</th><th>Time</th></tr>
      </thead>
      <tbody>
        <?php while ($row = $recent->fetch_assoc()): ?>
        <tr>
          <td><span class="tag"><?= htmlspecialchars($row['username']) ?></span></td>
          <td><?= htmlspecialchars(mb_strimwidth($row['command'],0,80,'…')) ?></td>
          <td><?= htmlspecialchars($row['created_at']) ?></td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
