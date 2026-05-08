<?php
// ============================================================
// config.php — UPDATED — TARS MODE
// Removed Gemini. Added Ollama + TARS branding config.
// ============================================================

// ── Secure session settings ─────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 3600);

session_start();

// ── Timezone ─────────────────────────────────────────────────
date_default_timezone_set("Asia/Kolkata");

// ── Database credentials ─────────────────────────────────────
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'voice_assistant';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

// ── TARS Branding ─────────────────────────────────────────────
define('ASSISTANT_NAME', 'TARS');

// ── Ollama Configuration ──────────────────────────────────────
// Make sure Ollama is running: `ollama serve`
// Pull your model first: `ollama pull llama3.2`
define('OLLAMA_HOST',  getenv('OLLAMA_HOST')  ?: 'http://localhost:11434');
define('OLLAMA_MODEL', getenv('OLLAMA_MODEL') ?: 'llama3.2');

// Number of past conversation turns to include as context (each turn = 1 user + 1 assistant msg)
define('OLLAMA_CONTEXT_TURNS', 7);

// Ollama timeout in seconds — increase if your hardware is slow
define('OLLAMA_TIMEOUT', 120);

// ── Cue Light (friendly mode) ─────────────────────────────────
// When enabled via UI, TARS softens its tone slightly.
// Default OFF — TARS is not here to make friends.
define('CUE_LIGHT_DEFAULT', false);

// ── Connect ───────────────────────────────────────────────────
$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['type' => 'error', 'message' => 'Database connection failed.']));
}
