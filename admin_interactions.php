<?php
require "config.php";
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) exit("Access denied.");

$search  = trim($_GET['q'] ?? '');
$page    = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

if ($search) {
    $like   = "%$search%";
    $count  = $conn->prepare("SELECT COUNT(*) FROM interactions WHERE command LIKE ? OR response LIKE ?");
    $count->bind_param("ss",$like,$like); $count->execute();
    $total  = $count->get_result()->fetch_row()[0];

    $stmt   = $conn->prepare("
        SELECT i.id, u.username, i.command, i.response, i.created_at
        FROM interactions i JOIN users u ON i.user_id=u.id
        WHERE i.command LIKE ? OR i.response LIKE ?
        ORDER BY i.created_at DESC LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ssii",$like,$like,$perPage,$offset);
} else {
    $total  = $conn->query("SELECT COUNT(*) FROM interactions")->fetch_row()[0];
    $stmt   = $conn->prepare("
        SELECT i.id, u.username, i.command, i.response, i.created_at
        FROM interactions i JOIN users u ON i.user_id=u.id
        ORDER BY i.created_at DESC LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii",$perPage,$offset);
}
$stmt->execute();
$result = $stmt->get_result();
$pages  = ceil($total / $perPage);
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>TARS — Chat Logs</title>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Mono:wght@700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;}
:root{--bg:#050811;--surface:rgba(255,255,255,.04);--surface2:rgba(255,255,255,.07);--border:rgba(255,255,255,.08);--accent:#f59e0b;--text:#e2e8f0;--muted:#64748b;--font:'Outfit',sans-serif;--mono:'Space Mono',monospace;}
body{min-height:100vh;background:var(--bg);font-family:var(--font);color:var(--text);}
.bg-canvas{position:fixed;inset:0;z-index:0;background:radial-gradient(ellipse 60% 50% at 15% 10%,rgba(59,130,246,.12) 0%,transparent 60%);}
.grid{position:fixed;inset:0;z-index:0;background-image:linear-gradient(rgba(255,255,255,.018) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.018) 1px,transparent 1px);background-size:48px 48px;}
.wrap{position:relative;z-index:1;max-width:1100px;margin:0 auto;padding:32px 24px;}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
.logo{font-family:var(--mono);font-size:18px;letter-spacing:3px;background:linear-gradient(90deg,#fbbf24,#f59e0b);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.page-title{font-size:20px;font-weight:700;}
.btn{padding:9px 16px;border-radius:9px;font-family:var(--font);font-size:13px;cursor:pointer;text-decoration:none;border:1px solid var(--border);background:var(--surface);color:var(--muted);display:inline-block;transition:.2s;}
.btn:hover{color:var(--text);}

.search-row{display:flex;gap:10px;margin-bottom:20px;}
.search-input{flex:1;padding:11px 16px;background:var(--surface);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:var(--font);font-size:14px;outline:none;transition:border-color .2s;}
.search-input:focus{border-color:rgba(96,165,250,.4);box-shadow:0 0 0 3px rgba(59,130,246,.1);}
.btn-search{background:linear-gradient(135deg,var(--accent),#d97706);color:#fff;border:none;font-weight:600;padding:11px 20px;cursor:pointer;border-radius:10px;}

.meta{font-size:12px;color:var(--muted);margin-bottom:14px;}

.table-wrap{background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:auto;}
table{width:100%;border-collapse:collapse;min-width:700px;}
th{text-align:left;padding:13px 16px;font-size:11px;color:var(--muted);letter-spacing:.8px;text-transform:uppercase;border-bottom:1px solid var(--border);}
td{padding:12px 16px;font-size:13px;border-bottom:1px solid rgba(255,255,255,.04);vertical-align:top;}
tr:last-child td{border:none;}
tr:hover td{background:rgba(255,255,255,.02);}
.tag{font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(59,130,246,.12);border:1px solid rgba(96,165,250,.2);color:#fbbf24;white-space:nowrap;}
.cmd-text{max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.resp-text{max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);}

.pagination{display:flex;gap:8px;justify-content:center;margin-top:20px;flex-wrap:wrap;}
.page-btn{padding:7px 13px;border-radius:8px;border:1px solid var(--border);background:var(--surface);color:var(--muted);text-decoration:none;font-size:13px;transition:.2s;}
.page-btn:hover{color:var(--text);}
.page-btn.active{background:rgba(59,130,246,.2);border-color:rgba(96,165,250,.4);color:#fbbf24;}
</style>
</head><body>
<div class="bg-canvas"></div><div class="grid"></div>
<div class="wrap">
  <div class="header">
    <div><div class="logo">TARS</div><div class="page-title">Chat Logs</div></div>
    <a href="admin_panel.php" class="btn">← Dashboard</a>
  </div>

  <form method="GET" class="search-row">
    <input class="search-input" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search commands or responses…">
    <button class="btn-search" type="submit">Search</button>
    <?php if ($search): ?><a href="admin_interactions.php" class="btn">Clear</a><?php endif; ?>
  </form>

  <div class="meta">Showing <?= min($perPage,$total) ?> of <?= number_format($total) ?> interactions<?= $search ? " matching "<strong>".htmlspecialchars($search)."</strong>"":'' ?></div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>User</th><th>Command</th><th>Response</th><th>Time</th></tr></thead>
      <tbody>
      <?php while ($row=$result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['id'] ?></td>
        <td><span class="tag"><?= htmlspecialchars($row['username']) ?></span></td>
        <td class="cmd-text" title="<?= htmlspecialchars($row['command']) ?>"><?= htmlspecialchars($row['command']) ?></td>
        <td class="resp-text" title="<?= htmlspecialchars($row['response']) ?>"><?= htmlspecialchars(mb_strimwidth($row['response'],0,80,'…')) ?></td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($row['created_at']) ?></td>
      </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for($p=1;$p<=$pages;$p++): ?>
    <a href="?<?= $search?"q=".urlencode($search)."&":'' ?>page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div>
</body></html>
