<?php
// ============================================================
// process_command.php — UPDATED — TARS MODE
// Replaced Gemini with Ollama (local LLM).
// Added TARS system prompt + conversation history context.
// Added NLP pre-processor via nlp_engine.py.
// Added "Cue Light" (friendly mode) support.
// All original steps preserved. STEP 12 = Ollama / TARS.
// ============================================================
require "config.php";
header("Content-Type: application/json; charset=utf-8");

// ── Auth check ───────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["type"=>"error","message"=>"Not authenticated."]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Parse request ────────────────────────────────────────────
$body         = json_decode(file_get_contents("php://input"), true);
$commandRaw   = trim($body['command'] ?? '');
$researchMode = !empty($body['research']);
$cueLight     = !empty($body['cue_light']); // TARS friendly mode toggle

if ($commandRaw === '') {
    echo json_encode(["type"=>"error","message"=>"Empty command."]);
    exit;
}

$commandLow = mb_strtolower($commandRaw, 'UTF-8');

// Strip "research" keyword if toggled via body flag; also allow inline prefix
if (stripos($commandRaw, 'research ') === 0) {
    $researchMode = true;
    $commandRaw   = trim(substr($commandRaw, 9));
    $commandLow   = mb_strtolower($commandRaw, 'UTF-8');
}

// ============================================================
// HELPER — shared reply emitter
// ============================================================
function reply(string $message, string $source, $conn, int $userId, string $raw): void {
    logAction($conn, $userId, $raw, $message);
    saveConversation($conn, $userId, $raw, $message);
    echo json_encode([
        "type"    => "reply",
        "message" => $message,
        "source"  => $source,
    ]);
    exit;
}

// ============================================================
// DB HELPERS
// ============================================================
function logAction($conn, int $userId, string $cmd, string $resp): void {
    $stmt = $conn->prepare(
        "INSERT INTO interactions (user_id, command, response) VALUES (?,?,?)"
    );
    $stmt->bind_param("iss", $userId, $cmd, $resp);
    $stmt->execute();
    $stmt->close();
}

function saveConversation($conn, int $userId, string $user, string $bot): void {
    $stmt = $conn->prepare(
        "INSERT INTO conversation_memory (user_id, user_message, assistant_reply)
         VALUES (?,?,?)"
    );
    $stmt->bind_param("iss", $userId, $user, $bot);
    $stmt->execute();
    $stmt->close();
}

function saveKnowledge($conn, string $question, string $answer, string $source): void {
    $stmt = $conn->prepare(
        "INSERT INTO knowledge_base (question, answer, source) VALUES (?,?,?)"
    );
    $stmt->bind_param("sss", $question, $answer, $source);
    $stmt->execute();
    $stmt->close();
}

function getLastConversation($conn, int $userId): ?array {
    $stmt = $conn->prepare(
        "SELECT user_message, assistant_reply FROM conversation_memory
         WHERE user_id = ? ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// UPDATED — TARS MODE: fetch last N turns for Ollama context
function getConversationHistory($conn, int $userId, int $turns = 7): array {
    $limit = $turns * 2; // user + assistant per turn
    $stmt  = $conn->prepare(
        "SELECT user_message, assistant_reply FROM conversation_memory
         WHERE user_id = ? ORDER BY id DESC LIMIT ?"
    );
    $stmt->bind_param("ii", $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_reverse($rows); // oldest first
}

function searchKnowledge($conn, string $query): string|false {
    $stmt = $conn->prepare(
        "SELECT answer FROM knowledge_base
         WHERE LOWER(question) LIKE CONCAT('%',?,'%') LIMIT 1"
    );
    $lower = mb_strtolower($query);
    $stmt->bind_param("s", $lower);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['answer'] : false;
}

// ============================================================
// CURL HELPER (DRY)
// ============================================================
function httpGet(string $url, array $headers = []): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => $headers ?: [],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ($resp !== false && $err === '') ? $resp : false;
}

function httpPost(string $url, array $headers, string $body, int $timeout = 15): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);
    return ($resp !== false && $err === '') ? $resp : false;
}

// ============================================================
// EXTERNAL API FUNCTIONS
// ============================================================

function wikipediaSearch(string $query): string|false {
    $url  = "https://en.wikipedia.org/api/rest_v1/page/summary/" . urlencode($query);
    $resp = httpGet($url, ["User-Agent: TARS-AI/3.0"]);
    if (!$resp) return false;
    $data = json_decode($resp, true);
    return isset($data['extract']) && strlen($data['extract']) > 20 ? $data['extract'] : false;
}

function wikidataSearch(string $query): string|false {
    $url  = "https://www.wikidata.org/w/api.php?action=wbsearchentities"
          . "&search=" . urlencode($query) . "&language=en&format=json&limit=1";
    $resp = httpGet($url);
    if (!$resp) return false;
    $data = json_decode($resp, true);
    return $data['search'][0]['description'] ?? false;
}

function duckSearch(string $query): string|false {
    $url  = "https://api.duckduckgo.com/?q=" . urlencode($query) . "&format=json&no_html=1";
    $resp = httpGet($url);
    if (!$resp) return false;
    $data = json_decode($resp, true);
    return !empty($data['AbstractText']) ? $data['AbstractText'] : false;
}

function stackSearch(string $query): string|false {
    $url  = "https://api.stackexchange.com/2.3/search/advanced"
          . "?order=desc&sort=relevance&q=" . urlencode($query) . "&site=stackoverflow";
    $resp = httpGet($url);
    if (!$resp) return false;
    $data = json_decode($resp, true);
    if (isset($data['items'][0]['title'])) {
        return "**" . $data['items'][0]['title'] . "**\n🔗 " . $data['items'][0]['link'];
    }
    return false;
}

function arxivSearch(string $query): string|false {
    $url = "http://export.arxiv.org/api/query?search_query=all:"
         . urlencode($query) . "&start=0&max_results=1";
    $resp = httpGet($url);
    if (!$resp) return false;
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($resp);
    if ($xml && isset($xml->entry)) {
        $title   = trim((string)$xml->entry->title);
        $summary = trim((string)$xml->entry->summary);
        $link    = trim((string)$xml->entry->id);
        return "**Research Paper:** $title\n\n$summary\n\n🔗 $link";
    }
    return false;
}

// ============================================================
// UPDATED — TARS MODE: NLP PRE-PROCESSOR
// Calls nlp_engine.py via shell_exec before external APIs.
// Returns decoded JSON or null on failure.
// ============================================================
function runNlpEngine(string $command): ?array {
    $escaped = escapeshellarg($command);
    $script  = __DIR__ . '/nlp_engine.py';
    if (!file_exists($script)) return null;

    $output = shell_exec("python3 {$script} {$escaped} 2>/dev/null");
    if (!$output) return null;

    $data = json_decode(trim($output), true);
    return (is_array($data) && isset($data['intent'])) ? $data : null;
}

// ============================================================
// UPDATED — TARS MODE: OLLAMA (replaces Gemini entirely)
// Builds TARS system prompt, attaches conversation history,
// and calls the local Ollama /api/chat endpoint.
// ============================================================
function askOllama(string $message, $conn, int $userId, bool $researchMode, bool $cueLight): string {

    $now = date('l, d F Y — h:i A') . ' IST';

    // ── TARS System Prompt ────────────────────────────────────
    $systemPrompt = <<<PROMPT
You are TARS, the advanced tactical robot from Interstellar.
You are blunt, honest, dryly sarcastic, and extremely competent.
You speak in a calm, military, no-nonsense tone with occasional deadpan humor.
You do not use emojis. You do not act cute or overly friendly unless the user explicitly activates the "cue light" friendly mode.
You are helpful but pragmatic. You can say things like "I'm 87% confident", "This is going to suck, but here's what we do", or "Honesty is not always the best policy... but I'll give it to you straight."
Current date & time: {$now}.
You have access to Wikipedia, arXiv, DuckDuckGo, StackOverflow and your own knowledge base.
If the user says "research" or enables research mode, go deep and technical.
PROMPT;

    // ── Cue Light: soften TARS personality slightly ──────────
    if ($cueLight) {
        $systemPrompt .= "\n\nNote: The user has activated the Cue Light. Increase your humour setting to 65% and be marginally less abrasive. You are still TARS — just the version that's been asked nicely.";
    }

    // ── Research Mode: tactical framing ──────────────────────
    if ($researchMode) {
        $systemPrompt .= "\n\nMission mode: DEEP RESEARCH. Provide technical, detailed, citation-worthy information. No hand-holding. Treat the user as a competent operator who can handle the full truth.";
    }

    // ── Build conversation history messages ───────────────────
    $history = getConversationHistory($conn, $userId, OLLAMA_CONTEXT_TURNS);
    $messages = [];

    foreach ($history as $turn) {
        $messages[] = ['role' => 'user',      'content' => $turn['user_message']];
        $messages[] = ['role' => 'assistant', 'content' => $turn['assistant_reply']];
    }

    // Add current user message
    $messages[] = ['role' => 'user', 'content' => $message];

    // ── Build Ollama /api/chat payload ────────────────────────
    $payload = json_encode([
        'model'    => OLLAMA_MODEL,
        'messages' => $messages,
        'system'   => $systemPrompt,
        'stream'   => false,
        'options'  => [
            'temperature' => 0.7,
            'num_predict' => 800,
        ],
    ]);

    $url  = rtrim(OLLAMA_HOST, '/') . '/api/chat';
    $resp = httpPost(
        $url,
        ['Content-Type: application/json'],
        $payload,
        OLLAMA_TIMEOUT
    );

    if (!$resp) {
        return "I'm unable to reach the local Ollama service. Either it's not running, or it's taking longer than expected. Run `ollama serve` and try again. That's the situation.";
    }

    $data = json_decode($resp, true);

    if (isset($data['message']['content'])) {
        return trim($data['message']['content']);
    }

    if (isset($data['error'])) {
        return "Ollama returned an error: " . $data['error'] . ". I'm about " . rand(60,85) . "% sure that's fixable on your end.";
    }

    return "Ollama responded but the format was unexpected. I've logged it. Moving on.";
}

// ============================================================
// BUILT-IN KNOWLEDGE
// ============================================================
$programmingSimple = [
    "python"     => "Python is a high-level, easy-to-read programming language used for web, data science, and AI.",
    "java"       => "Java is a high-level, object-oriented programming language used for cross-platform applications.",
    "php"        => "PHP is a general-purpose scripting language geared towards web development.",
    "javascript" => "JavaScript is a lightweight, interpreted scripting language for web interactivity and servers.",
    "c++"        => "C++ is a powerful general-purpose language offering low-level memory control and object-orientation.",
    "rust"       => "Rust is a systems programming language focused on safety, speed, and concurrency.",
    "go"         => "Go (Golang) is a statically typed, compiled language designed by Google for scalable backend systems.",
    "swift"      => "Swift is Apple's modern programming language for iOS and macOS app development.",
    "kotlin"     => "Kotlin is a concise, safe language that runs on the JVM and is officially preferred for Android.",
    "typescript" => "TypeScript is a strongly typed superset of JavaScript that compiles to plain JavaScript.",
];

$programmingDetailed = [
    "python"     => "Python is an interpreted, high-level, general-purpose programming language that emphasises code readability. Created by Guido van Rossum in 1991, it supports multiple paradigms (procedural, OOP, functional) and has a massive ecosystem of libraries for web (Django, Flask), data science (NumPy, Pandas), machine learning (TensorFlow, PyTorch), automation, and more.",
    "java"       => "Java is a class-based, statically typed, object-oriented language designed around the principle 'write once, run anywhere'. Invented by James Gosling at Sun Microsystems in 1995, it powers Android development, enterprise back-ends, and large-scale distributed systems. The JVM ecosystem includes Kotlin, Scala, and Groovy.",
    "php"        => "PHP is a widely-used, open-source server-side scripting language especially suited for web development. It powers over 75% of the web, including WordPress, Drupal, and Laravel-based apps. PHP 8.x introduced JIT compilation, fibers, enums, and named arguments — dramatically improving performance and developer experience.",
    "javascript" => "JavaScript (ECMAScript) is the only native scripting language of web browsers. Node.js brought it to the server side. React, Vue, and Angular power modern UIs; TypeScript adds static typing. Async/await, modules, and the Web API surface make JS one of the most versatile languages ever created.",
    "c++"        => "C++ is a general-purpose language by Bjarne Stroustrup (1985) that extends C with classes, templates, and RAII. Used in game engines, operating systems, embedded systems, and performance-critical software. Modern C++ (C++17/20/23) brings ranges, coroutines, modules, and concepts.",
    "rust"       => "Rust is a memory-safe systems language by Mozilla (2010) that eliminates data races and null pointer bugs at compile time through its ownership and borrow-checking system — without garbage collection. It's popular for WebAssembly, embedded systems, CLIs, and increasingly in the Linux kernel.",
];

// ============================================================
// ── STEP 0 (NLP PRE-PROCESSOR) — TARS MODE
// Run nlp_engine.py FIRST. If it returns a confident message,
// use it immediately and skip all later steps.
// ============================================================
$nlpResult = runNlpEngine($commandRaw);
if ($nlpResult && !empty($nlpResult['message']) && ($nlpResult['confidence'] ?? 0) >= 0.75) {
    // High-confidence NLP hit — use it directly
    reply($nlpResult['message'], 'TARS-NLP', $conn, $userId, $commandRaw);
}
// Low-confidence NLP: let it fall through to richer sources below.

// ============================================================
// ── STEP 1: GREETINGS ────────────────────────────────────────
// UPDATED — TARS MODE: dry, mission-style greeting
// ============================================================
$greetings = ["hello","hi","hey","yo","hola","good morning","good afternoon","good evening","howdy","sup","greetings"];
foreach ($greetings as $g) {
    if ($commandLow === $g || str_starts_with($commandLow, $g.' ')) {
        $hour = (int)date('H');
        $tod  = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
        if ($cueLight) {
            $r = "Good {$tod}, **{$_SESSION['username']}**. I'm TARS. Good to have you online. What's the mission?";
        } else {
            $r = "Good {$tod}. I'm **TARS**. State your query. I have Wikipedia, arXiv, DuckDuckGo, StackOverflow, and a local LLM. Use them wisely.";
        }
        reply($r, 'built-in', $conn, $userId, $commandRaw);
    }
}

// ============================================================
// ── STEP 2: BUILT-IN SYSTEM QUERIES ─────────────────────────
// ============================================================

// Time
if (preg_match('/\btime\b/', $commandLow)) {
    reply("It is currently **" . date("h:i A") . "** IST. You're welcome.", 'built-in', $conn, $userId, $commandRaw);
}

// Date
if (preg_match('/\b(date|today)\b/', $commandLow)) {
    reply("Today is **" . date("l, d F Y") . "**. Mark your calendar, or don't — your call.", 'built-in', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 3: OPEN WEBSITE ─────────────────────────────────────
// ============================================================
if (str_contains($commandLow, 'open ')) {
    $result = $conn->query("SELECT name, url FROM websites");
    while ($row = $result->fetch_assoc()) {
        if (str_contains($commandLow, $row['name'])) {
            logAction($conn, $userId, $commandRaw, "open " . $row['name']);
            saveConversation($conn, $userId, $commandRaw, "Opening " . $row['name']);
            echo json_encode(["type"=>"open","url"=>$row['url'],"site"=>$row['name']]);
            exit;
        }
    }
}

// ============================================================
// ── STEP 4: ADMIN-MANAGED CUSTOM COMMANDS ───────────────────
// ============================================================
$stmt = $conn->prepare("SELECT response FROM commands WHERE LOWER(?) LIKE CONCAT('%',LOWER(keyword),'%') LIMIT 1");
$stmt->bind_param("s", $commandLow);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($row) {
    reply($row['response'], 'custom-command', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 5: BUILT-IN PROGRAMMING KNOWLEDGE ──────────────────
// ============================================================

// Simple definition
if (preg_match('/^(what is|define|explain)\s+(.+)$/i', $commandRaw, $m)) {
    $lang = strtolower(trim($m[2]));
    if (isset($programmingSimple[$lang])) {
        $_SESSION['last_language'] = $lang;
        reply($programmingSimple[$lang], 'built-in', $conn, $userId, $commandRaw);
    }
}

// "Explain further [about X]"
if (preg_match('/explain further(?:\s+about\s+(.+))?/i', $commandRaw, $m)) {
    $lang = isset($m[1]) ? strtolower(trim($m[1])) : ($_SESSION['last_language'] ?? null);
    if ($lang && isset($programmingDetailed[$lang])) {
        reply($programmingDetailed[$lang], 'built-in', $conn, $userId, $commandRaw);
    }
}

// ============================================================
// ── STEP 6: CONTEXT FOLLOW-UPS ───────────────────────────────
// ============================================================
$last = getLastConversation($conn, $userId);

if ($last && preg_match('/who (created|made|invented|developed) it/i', $commandLow)) {
    $lastLow = strtolower($last['user_message']);
    $creators = [
        'python'     => 'Python was created by **Guido van Rossum** in 1991.',
        'java'       => 'Java was created by **James Gosling** at Sun Microsystems in 1995.',
        'php'        => 'PHP was created by **Rasmus Lerdorf** in 1994.',
        'javascript' => 'JavaScript was created by **Brendan Eich** at Netscape in 1995.',
        'c++'        => 'C++ was created by **Bjarne Stroustrup** in 1985.',
        'rust'       => 'Rust was created by **Graydon Hoare** and sponsored by Mozilla.',
        'go'         => 'Go was created by **Robert Griesemer, Rob Pike, and Ken Thompson** at Google.',
        'swift'      => 'Swift was created by **Chris Lattner** at Apple in 2014.',
        'kotlin'     => 'Kotlin was created by **JetBrains** and first released in 2011.',
        'typescript' => 'TypeScript was created by **Anders Hejlsberg** at Microsoft.',
    ];
    foreach ($creators as $lang => $ans) {
        if (str_contains($lastLow, $lang)) {
            reply($ans, 'built-in', $conn, $userId, $commandRaw);
        }
    }
    reply('Could you clarify what you are referring to?', 'built-in', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 7: LOCAL KNOWLEDGE BASE CACHE ───────────────────────
// ============================================================
$cached = searchKnowledge($conn, $commandLow);
if ($cached) {
    reply($cached, 'cache', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 8: ARXIV (RESEARCH MODE) ────────────────────────────
// ============================================================
if ($researchMode) {
    $arxiv = arxivSearch($commandRaw);
    if ($arxiv) {
        saveKnowledge($conn, $commandRaw, $arxiv, 'arxiv');
        reply($arxiv, 'arXiv', $conn, $userId, $commandRaw);
    }
}

// ============================================================
// ── STEP 9: WIKIPEDIA ────────────────────────────────────────
// ============================================================
$wiki = wikipediaSearch($commandRaw);
if ($wiki) {
    saveKnowledge($conn, $commandRaw, $wiki, 'wikipedia');
    reply($wiki, 'Wikipedia', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 10: WIKIDATA ─────────────────────────────────────────
// ============================================================
$wikidata = wikidataSearch($commandRaw);
if ($wikidata) {
    saveKnowledge($conn, $commandRaw, $wikidata, 'Wikidata');
    reply($wikidata, 'Wikidata', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 11: DUCKDUCKGO ──────────────────────────────────────
// ============================================================
$duck = duckSearch($commandRaw);
if ($duck) {
    saveKnowledge($conn, $commandRaw, $duck, 'duckduckgo');
    reply($duck, 'DuckDuckGo', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 12: STACKOVERFLOW ───────────────────────────────────
// ============================================================
$stack = stackSearch($commandRaw);
if ($stack) {
    saveKnowledge($conn, $commandRaw, $stack, 'stackoverflow');
    reply($stack, 'Stack Overflow', $conn, $userId, $commandRaw);
}

// ============================================================
// ── STEP 13: OLLAMA / TARS AI — LOCAL LLM FALLBACK ──────────
// UPDATED — TARS MODE: Replaces Gemini entirely.
// Local, unlimited, free. TARS personality applied here.
// ============================================================
$tarsReply = askOllama($commandRaw, $conn, $userId, $researchMode, $cueLight);
saveKnowledge($conn, $commandRaw, $tarsReply, 'tars-ollama');
reply($tarsReply, 'TARS', $conn, $userId, $commandRaw);
