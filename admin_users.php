<?php
// admin_users.php
require "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) exit("Access denied.");

// Toggle admin role
if (isset($_GET['toggle_admin'])) {
    $tid = intval($_GET['toggle_admin']);
    if ($tid != $_SESSION['user_id']) { // can't demote yourself
        $conn->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id = ?")->execute() || null;
        $stmt = $conn->prepare("UPDATE users SET is_admin = 1 - is_admin WHERE id = ?");
        $stmt->bind_param("i", $tid); $stmt->execute(); $stmt->close();
    }
    header("Location: admin_users.php"); exit();
}

// Delete user
if (isset($_GET['delete'])) {
    $tid = intval($_GET['delete']);
    if ($tid != $_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $tid); $stmt->execute(); $stmt->close();
    }
    header("Location: admin_users.php"); exit();
}

$result = $conn->query("SELECT id, username, is_admin, created_at FROM users ORDER BY id");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>TARS — Users</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--bg:#050811;--surface:rgba(255,255,255,.04);--border:rgba(255,255,255,.08);--accent:#f59e0b;--text:#e2e8f0;--muted:#64748b;--font:'Outfit',sans-serif;--mono:'Space Mono',monospace;}
body{min-height:100vh;background:var(--bg);font-family:var(--font);color:var(--text);}
.bg-canvas{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 60% 50% at 15% 10%,rgba(59,130,246,.12) 0%,transparent 60%);}
.grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:48px 48px;}
.wrap{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:32px 24px;}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;}
.logo{font-family:var(--mono);font-size:18px;letter-spacing:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.page-title{font-size:20px;font-weight:700;}
.btn{padding:9px 16px;border-radius:9px;font-family:var(--font);font-size:13px;cursor:pointer;text-decoration:none;transition:.2s;display:inline-flex;align-items:center;gap:6px;}
.btn-back{background:var(--surface);border:1px solid var(--border);color:var(--muted);}
.btn-back:hover{color:var(--text);}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#f87171;}
.btn-danger:hover{background:rgba(239,68,68,.2);}
.btn-accent{background:rgba(59,130,246,.15);border:1px solid rgba(96,165,250,.3);color:#fbbf24;}
.btn-accent:hover{background:rgba(59,130,246,.25);}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:14px 18px;font-size:11px;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--border);}
td{padding:14px 18px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);}
tr:last-child td{border:none;}
tr:hover td{background:rgba(255,255,255,.02);}
.actions{display:flex;gap:8px;}
.badge{font-size:10px;padding:2px 8px;border-radius:20px;}
.badge-admin{background:rgba(59,130,246,.15);border:1px solid rgba(96,165,250,.3);color:#fbbf24;}
.badge-user{background:rgba(100,116,139,.1);border:1px solid rgba(100,116,139,.2);color:var(--muted);}
</style>
</head><body>
<div class="bg-canvas"></div><div class="grid"></div>
<div class="wrap">
  <div class="header">
    <div>
      <div class="logo">TARS</div>
      <div class="page-title">Manage Users</div>
    </div>
    <a href="admin_panel.php" class="btn btn-back">← Dashboard</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ID</th><th>Username</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody>
      <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><?= htmlspecialchars($row['username']) ?></td>
        <td><span class="badge <?= $row['is_admin']?'badge-admin':'badge-user' ?>"><?= $row['is_admin']?'Admin':'User' ?></span></td>
        <td><?= htmlspecialchars($row['created_at']) ?></td>
        <td>
          <div class="actions">
            <?php if ($row['id'] != $_SESSION['user_id']): ?>
            <a href="admin_users.php?toggle_admin=<?= $row['id'] ?>" class="btn btn-accent" style="font-size:12px;padding:6px 10px;"><?= $row['is_admin']?'Demote':'Promote' ?></a>
            <a href="admin_users.php?delete=<?= $row['id'] ?>" class="btn btn-danger" style="font-size:12px;padding:6px 10px;" onclick="return confirm('Delete this user?')">Delete</a>
            <?php else: ?>
            <span style="color:var(--muted);font-size:12px;">You</span>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
