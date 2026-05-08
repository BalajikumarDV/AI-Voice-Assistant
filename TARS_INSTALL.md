# TARS — Tactical AI Assistant
### Upgraded from NOVA | Powered by Ollama (Local LLM) | 100% Free & Self-Hosted

---

## Summary of Changes

| File | What Changed |
|------|-------------|
| `config.php` | Removed `GEMINI_API_KEY`. Added `OLLAMA_HOST`, `OLLAMA_MODEL`, `OLLAMA_CONTEXT_TURNS`, `OLLAMA_TIMEOUT`, `CUE_LIGHT_DEFAULT` |
| `process_command.php` | Replaced `askGemini()` with `askOllama()`. Added `runNlpEngine()` (NLP pre-processor). Added cue_light support. STEP 12 → Ollama. STEP 0 → NLP engine. Full 7-turn conversation history. |
| `index.php` | All "NOVA" → "TARS". Amber/gold military color theme. Cue Light toggle added. Research mode labelled tactical. TARS personality welcome message. |
| `login.php` | NOVA → TARS branding. "Neural Omnilingual Voice Assistant" → "Tactical Autonomous Robotic System". |
| `register.php` | NOVA → TARS branding. Same color + text updates. |
| `admin_*.php` | All titles and logos updated to TARS. |
| `nlp_engine.py` | Docstring updated to TARS. No logic changes. |

---

## Step 1 — Install Ollama

### Linux / WSL / Ubuntu Server
```bash
curl -fsSL https://ollama.com/install.sh | sh
```

### macOS
```bash
brew install ollama
```

### Windows
Download the installer from: https://ollama.com/download/windows

---

## Step 2 — Pull the LLM Model

```bash
# Default model (recommended — fast, capable, ~2GB)
ollama pull llama3.2

# Alternatives you can use (set OLLAMA_MODEL in config.php):
ollama pull mistral          # 7B — great all-rounder
ollama pull phi3             # Microsoft Phi3 — very fast on CPU
ollama pull gemma2           # Google Gemma2 — good for reasoning
ollama pull llama3.1         # Larger, smarter, slower
```

---

## Step 3 — Start Ollama

```bash
# Start the Ollama server (runs on http://localhost:11434 by default)
ollama serve

# To run it in background / as a service on Linux:
sudo systemctl enable ollama
sudo systemctl start ollama
```

Verify it's running:
```bash
curl http://localhost:11434/api/tags
# Should return a JSON list of your installed models
```

---

## Step 4 — Deploy the Updated Files

Replace these files in your `voice_assistant_enhanced/` folder:

```
config.php              ← replaces old config (Gemini key removed)
process_command.php     ← replaces old processor (Ollama + TARS)
index.php               ← TARS UI + Cue Light
login.php               ← TARS branding
register.php            ← TARS branding
admin_panel.php         ← TARS branding
admin_users.php         ← TARS branding
admin_commands.php      ← TARS branding
admin_websites.php      ← TARS branding
admin_interactions.php  ← TARS branding
nlp_engine.py           ← minor docstring update
```

The `voice_assistant.sql` schema is **unchanged** — no database migration needed.

---

## Step 5 — Configure (optional)

Edit `config.php` to adjust:

```php
define('OLLAMA_HOST',  'http://localhost:11434'); // Change if Ollama runs on another host
define('OLLAMA_MODEL', 'llama3.2');               // Or 'mistral', 'phi3', etc.
define('OLLAMA_CONTEXT_TURNS', 7);               // How many past turns TARS remembers
define('OLLAMA_TIMEOUT', 120);                   // Increase if your hardware is slow
```

Or use environment variables (recommended for production):
```bash
export OLLAMA_HOST=http://localhost:11434
export OLLAMA_MODEL=llama3.2
```

---

## Step 6 — PHP Requirements

- PHP 8.0+ (uses `string|false` union types, `str_contains`, `str_starts_with`)
- PHP `curl` extension enabled
- `shell_exec()` enabled (for nlp_engine.py — check `disable_functions` in php.ini)
- Python 3 available at `/usr/bin/python3` (for nlp_engine.py)

Verify:
```bash
php -m | grep curl
python3 --version
php -r "echo shell_exec('echo ok');"
```

---

## Troubleshooting

**TARS responds: "I'm unable to reach the local Ollama service"**
→ Run `ollama serve` in a terminal. Check `curl http://localhost:11434/api/tags`.

**Slow responses**
→ Normal on CPU-only hardware. Use a smaller model like `phi3` or `gemma2:2b`.
→ Increase `OLLAMA_TIMEOUT` in config.php if you keep getting timeouts.

**NLP engine not firing**
→ Check that `shell_exec()` is enabled: `php -r "echo shell_exec('echo ok');"`
→ Verify Python 3 is at `/usr/bin/python3`

**Ollama running on a different machine/IP**
→ Set `OLLAMA_HOST` to `http://192.168.x.x:11434` or your server's IP.

---

## Cue Light Feature

The **Cue Light** toggle in the UI activates TARS's "friendlier" mode.
When enabled, the system prompt instructs TARS to increase its humour setting
and be approximately 12% less abrasive. It is still TARS — just the version
that's been asked nicely.

> "I have a cue light I can turn on if you want me to be more friendly."
> — TARS, *Interstellar* (2014)

