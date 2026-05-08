<?php
require "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) exit("Access denied.");

$message = ""; $messageType = "";

if (isset($_POST['add'])) {
    $name = strtolower(trim($_POST['name'] ?? ''));
    $url  = trim($_POST['url'] ?? '');
    if ($name && $url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $message="Invalid URL."; $messageType="error";
        } else {
            $stmt = $conn->prepare("INSERT INTO websites (name,url) VALUES (?,?) ON DUPLICATE KEY UPDATE url=VALUES(url)");
            $stmt->bind_param("ss",$name,$url);
            $message     = $stmt->execute() ? "Website saved." : "Error.";
            $messageType = strpos($message,'saved')!==false ? "success" : "error";
            $stmt->close();
        }
    } else { $message="Fill in all fields."; $messageType="error"; }
}

if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM websites WHERE id=?");
    $stmt->bind_param("i",$id); $stmt->execute(); $stmt->close();
    header("Location: admin_websites.php"); exit();
}

$sites = $conn->query("SELECT * FROM websites ORDER BY name");
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>TARS — Websites</title>
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
.alert{padding:12px 16px;border-radius:10px;font-size:13px;margin-bottom:20px;}
.alert.error{background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);color:var(--error);}
.alert.success{background:rgba(52,211,153,.1);border:1px solid rgba(52,211,153,.25);color:var(--success);}
.add-form{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:24px;margin-bottom:24px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.form-group{flex:1;min-width:160px;}
.form-group label{display:block;font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
.form-group input{width:100%;padding:11px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;color:var(--text);font-family:var(--font);font-size:14px;outline:none;transition:border-color .2s;}
.form-group input:focus{border-color:rgba(96,165,250,.4);box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;}
table{width:100%;border-collapse:collapse;}
th{text-align:left;padding:14px 18px;font-size:11px;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--border);}
td{padding:13px 18px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);}
tr:last-child td{border:none;}
tr:hover td{background:rgba(255,255,255,.02);}
.kw-tag{font-family:var(--mono);font-size:12px;background:rgba(59,130,246,.12);border:1px solid rgba(96,165,250,.2);color:#fbbf24;padding:3px 8px;border-radius:6px;}
.url-link{color:var(--muted);font-size:12px;text-decoration:none;}
.url-link:hover{color:#fbbf24;}
.hint{font-size:11px;color:var(--muted);margin-top:5px;}
</style>
</head><body>
<div class="bg-canvas"></div><div class="grid"></div>
<div class="wrap">
  <div class="header">
    <div><div class="logo">TARS</div><div class="page-title">Website Shortcuts</div></div>
    <a href="admin_panel.php" class="btn btn-back">← Dashboard</a>
  </div>

  <?php if ($message): ?>
  <div class="alert <?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <form method="POST" class="add-form">
    <div class="form-group">
      <label>Trigger name</label>
      <input name="name" placeholder="e.g. youtube" required>
      <div class="hint">Used in "open youtube" voice command</div>
    </div>
    <div class="form-group" style="flex:2;">
      <label>URL</label>
      <input name="url" placeholder="https://youtube.com" type="url" required>
    </div>
    <button class="btn btn-primary" name="add" type="submit">Add</button>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Trigger</th><th>URL</th><th></th></tr></thead>
      <tbody>
      <?php while ($row=$sites->fetch_assoc()): ?>
      <tr>
        <td><span class="kw-tag"><?= htmlspecialchars($row['name']) ?></span></td>
        <td><a href="<?= htmlspecialchars($row['url']) ?>" target="_blank" class="url-link"><?= htmlspecialchars($row['url']) ?></a></td>
        <td><a href="admin_websites.php?delete=<?= $row['id'] ?>" class="btn btn-danger" onclick="return confirm('Delete this shortcut?')">Delete</a></td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body></html>
