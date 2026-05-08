#!/usr/bin/env python3
"""
nlp_engine.py — Enhanced NLP preprocessor for TARS
────────────────────────────────────────────────────
Called by PHP via shell_exec() before hitting external APIs.
Returns a JSON blob with: intent, confidence, topic, sentiment, message.

Usage:
    python3 nlp_engine.py "What is machine learning?"
"""

import sys
import json
import re
import difflib
from datetime import datetime

# ──────────────────────────────────────────────────────────────
# INTENT CATALOGUE
# Each intent maps to a list of trigger phrases (lowercase).
# ──────────────────────────────────────────────────────────────
INTENTS = {
    "greeting":     ["hi", "hello", "hey", "good morning", "good evening",
                     "good afternoon", "howdy", "sup", "greetings", "hola"],
    "farewell":     ["bye", "goodbye", "see you", "later", "take care", "cya"],
    "thanks":       ["thank you", "thanks", "thank", "cheers", "appreciate"],
    "time":         ["time", "current time", "what time"],
    "date":         ["date", "today", "what day", "what date"],
    "weather":      ["weather", "temperature", "forecast", "rain", "sunny", "climate"],
    "define":       ["define", "what is", "who is", "explain", "describe",
                     "tell me about", "what are", "how does", "meaning of"],
    "calculate":    ["calculate", "compute", "solve", "what is", "how much is",
                     "plus", "minus", "times", "divided by"],
    "open":         ["open", "launch", "go to", "navigate to", "visit"],
    "research":     ["research", "paper", "study", "arxiv", "journal", "academic"],
    "joke":         ["joke", "funny", "make me laugh", "tell me a joke"],
    "unknown":      [],
}

# Sentiment word banks (simple lexicon approach)
POSITIVE_WORDS = {"great","good","awesome","excellent","amazing","love","best",
                  "wonderful","fantastic","nice","brilliant","superb","perfect"}
NEGATIVE_WORDS = {"bad","terrible","awful","hate","worst","horrible","poor",
                  "ugly","wrong","broken","fail","error","problem","issue"}

# ──────────────────────────────────────────────────────────────
# TEXT CLEANING
# ──────────────────────────────────────────────────────────────
def clean_text(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[^a-z0-9\s']", " ", text)
    text = re.sub(r"\s+", " ", text)
    return text.strip()

# ──────────────────────────────────────────────────────────────
# FUZZY TYPO CORRECTION
# Replaces individual words with close-matching intent keywords.
# ──────────────────────────────────────────────────────────────
def correct_typos(text: str) -> str:
    all_kw: list[str] = []
    for phrases in INTENTS.values():
        for p in phrases:
            all_kw.extend(p.split())

    corrected = []
    for word in text.split():
        if len(word) <= 2:
            corrected.append(word)
            continue
        matches = difflib.get_close_matches(word, all_kw, n=1, cutoff=0.78)
        corrected.append(matches[0] if matches else word)
    return " ".join(corrected)

# ──────────────────────────────────────────────────────────────
# INTENT DETECTION — returns (intent, confidence 0..1)
# ──────────────────────────────────────────────────────────────
def detect_intent(text: str) -> tuple[str, float]:
    scores: dict[str, float] = {}

    for intent, phrases in INTENTS.items():
        if not phrases:
            continue
        for phrase in phrases:
            if phrase in text:
                # Longer phrase → higher confidence
                weight = 0.5 + 0.05 * len(phrase.split())
                scores[intent] = max(scores.get(intent, 0), min(weight, 1.0))

    if not scores:
        return "unknown", 0.0

    best = max(scores, key=lambda k: scores[k])
    return best, round(scores[best], 2)

# ──────────────────────────────────────────────────────────────
# TOPIC EXTRACTION
# Strips the intent trigger phrase to isolate the subject.
# ──────────────────────────────────────────────────────────────
def extract_topic(text: str, intent: str) -> str | None:
    for phrase in INTENTS.get(intent, []):
        if phrase in text:
            topic = text.replace(phrase, "", 1).strip()
            if topic:
                return topic
    return None

# ──────────────────────────────────────────────────────────────
# SENTIMENT ANALYSIS (lexicon-based, 3-class)
# ──────────────────────────────────────────────────────────────
def detect_sentiment(text: str) -> str:
    words = set(text.lower().split())
    pos   = len(words & POSITIVE_WORDS)
    neg   = len(words & NEGATIVE_WORDS)
    if pos > neg:
        return "positive"
    if neg > pos:
        return "negative"
    return "neutral"

# ──────────────────────────────────────────────────────────────
# BUILT-IN RESPONSE GENERATION (for fast intents)
# Returns a string or None (meaning PHP should handle it).
# ──────────────────────────────────────────────────────────────
def generate_response(intent: str) -> str | None:
    now = datetime.now()
    if intent == "greeting":
        hour = now.hour
        tod  = "morning" if hour < 12 else ("afternoon" if hour < 17 else "evening")
        return f"Good {tod}! I'm NOVA — your AI assistant. How can I help?"
    if intent == "farewell":
        return "Goodbye! Have a great day! 👋"
    if intent == "thanks":
        return "You're welcome! Is there anything else I can help with?"
    if intent == "time":
        return "The current time is " + now.strftime("%I:%M %p") + " (IST)."
    if intent == "date":
        return "Today is " + now.strftime("%A, %d %B %Y") + "."
    if intent == "joke":
        jokes = [
            "Why do programmers prefer dark mode? Because light attracts bugs! 🐛",
            "Why did the developer go broke? Because he used up all his cache! 💸",
            "I told my computer I needed a break. Now it won't stop sending me Kit Kat ads.",
        ]
        import random
        return random.choice(jokes)
    return None  # Let PHP handle via external APIs

# ──────────────────────────────────────────────────────────────
# MAIN
# ──────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({
            "intent": "unknown", "confidence": 0,
            "topic": None, "sentiment": "neutral", "message": None,
            "error": "No input provided."
        }))
        sys.exit(1)

    raw_input  = " ".join(sys.argv[1:])          # join in case shell split it
    cleaned    = clean_text(raw_input)
    corrected  = correct_typos(cleaned)

    intent, confidence = detect_intent(corrected)
    topic      = extract_topic(corrected, intent)
    sentiment  = detect_sentiment(corrected)
    message    = generate_response(intent)

    output = {
        "intent":     intent,
        "confidence": confidence,
        "topic":      topic,
        "sentiment":  sentiment,
        "message":    message,     # None means PHP should keep processing
    }

    print(json.dumps(output, ensure_ascii=False))
