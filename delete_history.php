<?php
// ============================================================
// delete_history.php — TARS MODE
// Wipes conversation_memory + interactions for the current user.
// Called via POST from the UI. Returns JSON.
// ============================================================
require "config.php";
header("Content-Type: application/json; charset=utf-8");

// ── Auth check ───────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "message" => "Not authenticated."]);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Wipe conversation memory ──────────────────────────────────
$stmt1 = $conn->prepare("DELETE FROM conversation_memory WHERE user_id = ?");
$stmt1->bind_param("i", $userId);
$stmt1->execute();
$deletedMemory = $stmt1->affected_rows;
$stmt1->close();

// ── Wipe interaction log ──────────────────────────────────────
$stmt2 = $conn->prepare("DELETE FROM interactions WHERE user_id = ?");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$deletedLogs = $stmt2->affected_rows;
$stmt2->close();

echo json_encode([
    "success"         => true,
    "deleted_memory"  => $deletedMemory,
    "deleted_logs"    => $deletedLogs,
    "message"         => "Memory purged. {$deletedMemory} conversation records and {$deletedLogs} log entries erased. I retain 0% of that.",
]);
