'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { DatabaseSync } = require('node:sqlite');

const databasePath = path.resolve(
    process.env.DATABASE_PATH || path.join(__dirname, 'data', 'fittronic.sqlite')
);

fs.mkdirSync(path.dirname(databasePath), { recursive: true });

const database = new DatabaseSync(databasePath);

database.exec(`
    PRAGMA foreign_keys = ON;
    PRAGMA journal_mode = WAL;

    CREATE TABLE IF NOT EXISTS approved_students (
        student_id TEXT PRIMARY KEY,
        registered_at INTEGER
    ) STRICT;

    CREATE TABLE IF NOT EXISTS accounts (
        id INTEGER PRIMARY KEY,
        role TEXT NOT NULL CHECK (role IN ('student', 'instructor')),
        student_id TEXT UNIQUE REFERENCES approved_students(student_id),
        email TEXT UNIQUE COLLATE NOCASE,
        password_salt TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        CHECK (
            (role = 'student' AND student_id IS NOT NULL AND email IS NULL) OR
            (role = 'instructor' AND student_id IS NULL AND email IS NOT NULL)
        )
    ) STRICT;

    CREATE TABLE IF NOT EXISTS student_verifications (
        token_hash TEXT PRIMARY KEY,
        student_id TEXT NOT NULL REFERENCES approved_students(student_id) ON DELETE CASCADE,
        expires_at INTEGER NOT NULL
    ) STRICT;

    CREATE TABLE IF NOT EXISTS sessions (
        id INTEGER PRIMARY KEY,
        account_id INTEGER NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
        token_hash TEXT NOT NULL UNIQUE,
        created_at INTEGER NOT NULL,
        expires_at INTEGER NOT NULL
    ) STRICT;

    CREATE INDEX IF NOT EXISTS student_verifications_expiry
        ON student_verifications(expires_at);

    CREATE INDEX IF NOT EXISTS sessions_expiry
        ON sessions(expires_at);
`);

module.exports = database;
