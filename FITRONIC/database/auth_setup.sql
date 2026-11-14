

USE hardware_hub;

CREATE TABLE IF NOT EXISTS approved_students (
    student_id    VARCHAR(64) NOT NULL PRIMARY KEY,
    registered_at BIGINT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS accounts (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role                ENUM('student', 'instructor') NOT NULL,
    student_id          VARCHAR(64)  NULL UNIQUE,
    email               VARCHAR(254) NULL UNIQUE,
    instructor_username VARCHAR(32)  NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    created_at          BIGINT UNSIGNED NOT NULL,
    CONSTRAINT fk_accounts_approved_student
        FOREIGN KEY (student_id) REFERENCES approved_students(student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS student_verifications (
    token_hash CHAR(64)    NOT NULL PRIMARY KEY,
    student_id VARCHAR(64) NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX student_verifications_expiry (expires_at),
    CONSTRAINT fk_verifications_approved_student
        FOREIGN KEY (student_id) REFERENCES approved_students(student_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signed-in sessions (1 hour)
CREATE TABLE IF NOT EXISTS sessions (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    account_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at BIGINT UNSIGNED NOT NULL,
    expires_at BIGINT UNSIGNED NOT NULL,
    INDEX sessions_expiry (expires_at),
    CONSTRAINT fk_sessions_account
        FOREIGN KEY (account_id) REFERENCES accounts(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    rate_key     CHAR(64) NOT NULL PRIMARY KEY,
    window_start BIGINT UNSIGNED NOT NULL,
    attempts     INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


ALTER TABLE users MODIFY email VARCHAR(254) NULL;

SET @has_account_id := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'users'
      AND COLUMN_NAME  = 'account_id'
);
SET @ddl := IF(@has_account_id = 0,
    'ALTER TABLE users ADD COLUMN account_id BIGINT UNSIGNED NULL UNIQUE',
    'SELECT 1');
PREPARE add_account_id FROM @ddl;
EXECUTE add_account_id;
DEALLOCATE PREPARE add_account_id;

-- DEMO DATA ONLY: these synthetic student IDs are placeholders for local testing.
-- Replace this list with the real approved roster through a private, non-public process before deployment.
INSERT IGNORE INTO approved_students (student_id) VALUES
    ('2026FIT1'), ('2026FIT2'), ('2026FIT3'), ('2026FIT4'), ('2026FIT5'),
    ('2026FIT6'), ('2026FIT7'), ('2026FIT8'), ('2026FIT9'), ('2026FIT10');

-- Quick check (should list the 5 new tables):
SHOW TABLES;
