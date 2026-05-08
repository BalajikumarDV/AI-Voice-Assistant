<?php
require "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) exit("Access denied.");

$message = ""; $messageType = "";

if (isset($_POST['add'])) {
    $kw  = trim($_POST['keyword']  ?? '');
    $res = trim($_POST['response'] ?? '');
    if ($kw && $res) {
        $stmt = $conn->prepare("INSERT INTO commands (keyword, response) VALUES (?,?) ON DUPLICATE KEY UPDATE response=VALUES(response)");
        $stmt->bind_param("ss", $kw, $res);
        $message     = $stmt->execute() ? "Command saved." : "Error: " . $conn->error;
        $messageType = $stmt->execute() || strpos($message,'saved') !== false ? "success" : "error";
        $stmt->close();
    } else { $message="Please fill in both fields."; $messageType="error"; }
}

if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM commands WHERE id=?");
    $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    header("Location: admin_commands.php"); exit();
}

$cmds = $conn->query("SELECT * FROM commands ORDER BY id DESC");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>TARS — Commands</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--bg:#050811;--surface:rgba(255,255,255,.04);--surface2:rgba(255,255,255,.07);--border:rgba(255,255,255,.08);--accent:#f59e0b;--text:#e2e8f0;--muted:#64748b;--error:#f87171;--success:#34d399;--font:'Outfit',sans-serif;--mono:'Space Mono',monospace;}
body{min-height:100vh;background:var(--bg);font-family:var(--font);color:var(--text);}
.bg-canvas{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 60% 50% at 15% 10%,rgba(59,130,246,.12) 0%,transparent 60%);}
.grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:48px 48px;}
.wrap{position:relative;z-index:1;max-width:900px;margin:0 auto;padding:32px 24px;}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;}
.logo{font-family:var(--mono);font-size:18px;letter-spacing:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.page-title{font-size:20px;font-weight:700;}
.btn{padding:9px 16px;border-radius:9px;font-family:var(--font);font-size:13px;cursor:pointer;text-decoration:none;border:none;transition:.2s;}
.btn-back{background:var(--surface);border:1px solid var(--border);color:var(--muted);display:inline-block;}
.btn-back:hover{color:var(--text);}
.btn-primary{background:linear-gradient(135deg,var(--accent),#d97706);color:#fff;font-weight:600;padding:11px 20px;}
.btn-danger{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#f87171;font-size:12px;padding:6px 12px;}
.btn-danger:hover{background:rgba(239,68,68,.2);}

.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.alert.error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--error);}
.alert.success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:var(--success);}

.add-form{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:24px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.form-group{flex:1;min-width:180px;}
.form-group label{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.form-group input,.form-group textarea{width:100%;padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:var(--font);font-size:14px;outline:none;transition:border-color .2s,box-shadow .2s;}
.form-group input:focus,.form-group textarea:focus{border-color:rgba(96,165,250,.4);box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.form-group textarea{resize:vertical;min-height:60px;}

.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:14px 18px;font-size:11px;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--border);}
td{padding:13px 18px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:top;}
tr:last-child td{border:none;}
tr:hover td{background:rgba(255,255,255,.02);}
.kw-tag{font-family:var(--mono);font-size:12px;background:rgba(59,130,246,.12);border:1px solid rgba(96,165,250,.2);color:#fbbf24;padding:3px 8px;border-radius:6px;}
</style>
</head><body>
<div class="bg-canvas"></div><div class="grid"></div>
<div class="wrap">
  <div class="header">
    <div><div class="logo">TARS</div><div class="page-title">Custom Commands</div></div>
    <a href="admin_panel.php" class="btn btn-back">← Dashboard</a>
  </div>

  <?php if ($message): ?>
  <div class="alert <?= $messageType ?>"><?= $messageType==='error'?'⚠':'✓' ?> <?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="POST" class="add-form">
    <div class="form-group">
      <label>Keyword (trigger phrase)</label>
      <input name="keyword" placeholder="e.g. who are you" required>
    </div>
    <div class="form-group" style="flex:2;">
      <label>Response</label>
      <textarea name="response" placeholder="TARS' response when keyword is matched" required></textarea>
    </div>
    <button class="btn btn-primary" name="add" type="submit">Add Command</button>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Keyword</th><th>Response</th><th></th></tr></thead>
      <tbody>
      <?php while ($row=$cmds->fetch_assoc()): ?>
      <tr>
        <td><span class="kw-tag"><?= htmlspecialchars($row['keyword']) ?></span></td>
        <td><?= htmlspecialchars(mb_strimwidth($row['response'],0,100,'…')) ?></td>
        <td><a href="admin_commands.php?delete=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this command?')">Delete</a></td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
