-- ============================================================
-- NOVA Voice Assistant — Enhanced Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS voice_assistant
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE voice_assistant;

-- ──────────────────────────────────────────────
-- USERS
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id          INT(11)      AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    is_admin    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
-- COMMANDS (admin-managed keyword → reply)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS commands (
    id          INT(11)      AUTO_INCREMENT PRIMARY KEY,
    keyword     VARCHAR(100) NOT NULL UNIQUE,
    response    TEXT         NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
-- WEBSITES (admin-managed "open X" shortcuts)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS websites (
    id          INT(11)      AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(50)  NOT NULL UNIQUE,
    url         VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
-- INTERACTIONS (full chat log)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS interactions (
    id          INT(11)      AUTO_INCREMENT PRIMARY KEY,
    user_id     INT(11)      NOT NULL,
    command     TEXT         NOT NULL,
    response    TEXT         NOT NULL,
    source      VARCHAR(50)  DEFAULT 'unknown',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
-- KNOWLEDGE BASE (cached answers to avoid re-fetching)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS knowledge_base (
    id          INT(11)      AUTO_INCREMENT PRIMARY KEY,
    question    TEXT         NOT NULL,
    answer      TEXT         NOT NULL,
    source      VARCHAR(50)  DEFAULT 'unknown',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
-- CONVERSATION MEMORY (per-user short-term context)
-- ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS conversation_memory (
    id              INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id         INT(11) NOT NULL,
    user_message    TEXT    NOT NULL,
    assistant_reply TEXT    NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ──────────────────────────────────────────────
-- DEFAULT WEBSITE SHORTCUTS
-- ──────────────────────────────────────────────
INSERT IGNORE INTO websites (name, url) VALUES
  ('youtube',   'https://youtube.com'),
  ('google',    'https://google.com'),
  ('github',    'https://github.com'),
  ('gmail',     'https://mail.google.com'),
  ('wikipedia', 'https://wikipedia.org'),
  ('twitter',   'https://twitter.com'),
  ('reddit',    'https://reddit.com'),
  ('linkedin',  'https://linkedin.com');
