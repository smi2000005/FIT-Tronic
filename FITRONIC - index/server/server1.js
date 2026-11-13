'use strict';

const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const {
    createHash,
    randomBytes,
    scrypt: scryptCallback,
    timingSafeEqual
} = require('node:crypto');
const { promisify } = require('node:util');

const instructorCode = process.env.INSTRUCTOR_PERMISSION_CODE;
if (!instructorCode || Buffer.byteLength(instructorCode) < 16) {
    console.error('Set INSTRUCTOR_PERMISSION_CODE to a secret of at least 16 bytes before starting the server.');
    process.exit(1);
}

const database = require('./database1');
const scrypt = promisify(scryptCallback);
const root = path.resolve(__dirname, '..');
const host = process.env.HOST || '127.0.0.1';
const port = Number(process.env.PORT || 4173);
const requestLimitBytes = 16 * 1024;
const verificationLifetimeMs = 10 * 60 * 1000;
const rateLimitWindowMs = 15 * 60 * 1000;
const rateLimits = new Map();
const instructorCodeHash = createHash('sha256').update(instructorCode).digest();

if (!Number.isInteger(port) || port < 1 || port > 65535) {
    console.error('PORT must be an integer between 1 and 65535.');
    process.exit(1);
}

class HttpError extends Error {
    constructor(status, message, code) {
        super(message);
        this.status = status;
        this.code = code;
    }
}

function securityHeaders() {
    return {
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'DENY',
        'Referrer-Policy': 'no-referrer',
        'Cache-Control': 'no-store',
        'Content-Security-Policy': "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'"
    };
}

function sendJson(response, status, value, headers) {
    response.writeHead(status, Object.assign({}, securityHeaders(), {
        'Content-Type': 'application/json; charset=utf-8'
    }, headers || {}));
    response.end(JSON.stringify(value));
}

function sendError(response, error) {
    if (response.headersSent || response.destroyed) return;
    if (error instanceof HttpError) {
        const body = { error: error.message };
        if (error.code) body.code = error.code;
        sendJson(response, error.status, body);
        return;
    }

    console.error('Signup service request failed.', error);
    sendJson(response, 500, { error: 'The signup service encountered an error. Try again later.' });
}

function readJson(request) {
    const contentType = request.headers['content-type'] || '';
    if (!contentType.toLowerCase().startsWith('application/json')) {
        throw new HttpError(415, 'Send signup data as JSON.');
    }

    return new Promise((resolve, reject) => {
        const chunks = [];
        let totalBytes = 0;
        let rejected = false;

        request.on('data', (chunk) => {
            if (rejected) return;
            totalBytes += chunk.length;
            if (totalBytes > requestLimitBytes) {
                rejected = true;
                reject(new HttpError(413, 'The signup request is too large.'));
                request.resume();
                return;
            }
            chunks.push(chunk);
        });

        request.on('end', () => {
            if (rejected) return;
            try {
                const value = JSON.parse(Buffer.concat(chunks).toString('utf8'));
                if (!value || typeof value !== 'object' || Array.isArray(value)) {
                    throw new HttpError(400, 'The signup request must be a JSON object.');
                }
                resolve(value);
            } catch (error) {
                reject(error instanceof HttpError ? error : new HttpError(400, 'The signup request contains invalid JSON.'));
            }
        });

        request.on('error', (error) => {
            if (!rejected) reject(error);
        });
    });
}

function takeRateLimit(request, action, limit) {
    const now = Date.now();
    const address = request.socket.remoteAddress || 'unknown';
    const key = `${action}:${address}`;
    let entry = rateLimits.get(key);

    if (!entry || now - entry.startedAt >= rateLimitWindowMs) {
        entry = { startedAt: now, count: 0 };
        rateLimits.set(key, entry);
    }
    if (entry.count >= limit) return false;
    entry.count += 1;

    if (rateLimits.size > 5000) {
        rateLimits.forEach((value, mapKey) => {
            if (now - value.startedAt >= rateLimitWindowMs) rateLimits.delete(mapKey);
        });
    }
    return true;
}

function normalizeStudentId(value) {
    if (typeof value !== 'string') {
        throw new HttpError(400, 'Enter a valid student ID.');
    }
    const studentId = value.trim().toUpperCase();
    if (!/^[A-Z0-9][A-Z0-9._/-]{0,63}$/.test(studentId)) {
        throw new HttpError(400, 'Enter a valid student ID.');
    }
    return studentId;
}

function normalizeEmail(value) {
    if (typeof value !== 'string') {
        throw new HttpError(400, 'Enter a valid email address.');
    }
    const email = value.trim().toLowerCase();
    if (email.length > 254 || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        throw new HttpError(400, 'Enter a valid email address.');
    }
    return email;
}

function validatePassword(value) {
    if (typeof value !== 'string' || value.length < 12 || value.length > 128) {
        throw new HttpError(400, 'Choose a password between 12 and 128 characters.');
    }
    return value;
}

async function hashPassword(password) {
    const salt = randomBytes(16);
    const derivedKey = await scrypt(password, salt, 64, {
        N: 16384,
        r: 8,
        p: 1,
        maxmem: 64 * 1024 * 1024
    });
    return {
        salt: salt.toString('base64url'),
        hash: `scrypt$16384$8$1$${derivedKey.toString('base64url')}`
    };
}

function parseStoredPasswordHash(storedHash) {
    if (typeof storedHash !== 'string' || !storedHash.startsWith('scrypt$')) {
        throw new Error('Stored password hash is invalid.');
    }

    const [algorithm, N, r, p, derivedKeyBase64] = storedHash.split('$');
    if (!algorithm || !N || !r || !p || !derivedKeyBase64) {
        throw new Error('Stored password hash is malformed.');
    }

    return {
        N: Number(N),
        r: Number(r),
        p: Number(p),
        derivedKeyBase64
    };
}

async function verifyPassword(password, saltBase64, storedHash) {
    const { N, r, p, derivedKeyBase64 } = parseStoredPasswordHash(storedHash);
    const salt = Buffer.from(saltBase64, 'base64url');
    const derivedKey = await scrypt(password, salt, 64, {
        N,
        r,
        p,
        maxmem: 64 * 1024 * 1024
    });
    const expected = Buffer.from(derivedKeyBase64, 'base64url');

    if (derivedKey.length !== expected.length) return false;
    try {
        return timingSafeEqual(derivedKey, expected);
    } catch {
        return false;
    }
}

function tokenDigest(token) {
    return createHash('sha256').update(token).digest('hex');
}

function isSecureRequest(request) {
    return !!(request.socket && request.socket.encrypted) || request.headers['x-forwarded-proto'] === 'https';
}

function extractSessionToken(request) {
    const authorization = request.headers.authorization || '';
    if (authorization.toLowerCase().startsWith('bearer ')) {
        return authorization.slice(7).trim();
    }

    const cookies = request.headers.cookie || '';
    const match = /(?:^|;\s*)fittronic_session=([^;]+)/.exec(cookies);
    if (match) {
        return decodeURIComponent(match[1]);
    }

    return '';
}

function setSessionCookie(response, token, request) {
    const secureFlag = isSecureRequest(request) ? '; Secure' : '';
    response.setHeader('Set-Cookie', `fittronic_session=${encodeURIComponent(token)}; Path=/; HttpOnly; SameSite=Lax; Max-Age=3600${secureFlag}`);
}

function sendRateLimit(response) {
    sendJson(response, 429, { error: 'Too many attempts. Wait a little and try again.' }, {
        'Retry-After': String(Math.ceil(rateLimitWindowMs / 1000))
    });
}

async function verifyStudent(request, response) {
    if (!takeRateLimit(request, 'student-verification', 10)) {
        sendRateLimit(response);
        return;
    }

    const body = await readJson(request);
    const studentId = normalizeStudentId(body.studentId);
    const approvedStudent = database.prepare(
        'SELECT registered_at FROM approved_students WHERE student_id = ?'
    ).get(studentId);

    if (!approvedStudent || approvedStudent.registered_at !== null) {
        throw new HttpError(400, 'Student ID could not be approved. Check it or contact your school administrator.', 'student_id_not_eligible');
    }

    const now = Date.now();
    const token = randomBytes(32).toString('base64url');
    const tokenHash = tokenDigest(token);
    database.prepare('DELETE FROM student_verifications WHERE expires_at <= ?').run(now);
    database.prepare('DELETE FROM student_verifications WHERE student_id = ?').run(studentId);
    database.prepare(
        'INSERT INTO student_verifications (token_hash, student_id, expires_at) VALUES (?, ?, ?)'
    ).run(tokenHash, studentId, now + verificationLifetimeMs);

    sendJson(response, 200, {
        verificationToken: token,
        message: 'Student ID verified. Set a password to continue.',
        expiresInSeconds: verificationLifetimeMs / 1000
    });
}

async function createStudentAccount(request, response) {
    if (!takeRateLimit(request, 'student-signup', 10)) {
        sendRateLimit(response);
        return;
    }

    const body = await readJson(request);
    const studentId = normalizeStudentId(body.studentId);
    const password = validatePassword(body.password);
    if (typeof body.verificationToken !== 'string' || body.verificationToken.length > 128) {
        throw new HttpError(400, 'Verify your student ID before continuing.', 'student_verification_required');
    }
    const tokenHash = tokenDigest(body.verificationToken);
    const now = Date.now();
    const verification = database.prepare(
        'SELECT student_id, expires_at FROM student_verifications WHERE token_hash = ?'
    ).get(tokenHash);
    const approvedStudent = database.prepare(
        'SELECT registered_at FROM approved_students WHERE student_id = ?'
    ).get(studentId);

    if (!verification || verification.student_id !== studentId || verification.expires_at <= now ||
        !approvedStudent || approvedStudent.registered_at !== null) {
        throw new HttpError(400, 'Verify your student ID again before continuing.', 'student_verification_required');
    }

    const credentials = await hashPassword(password);
    database.exec('BEGIN IMMEDIATE');
    try {
        const currentVerification = database.prepare(
            'SELECT student_id, expires_at FROM student_verifications WHERE token_hash = ?'
        ).get(tokenHash);
        const currentStudent = database.prepare(
            'SELECT registered_at FROM approved_students WHERE student_id = ?'
        ).get(studentId);

        if (!currentVerification || currentVerification.student_id !== studentId ||
            currentVerification.expires_at <= Date.now() ||
            !currentStudent || currentStudent.registered_at !== null) {
            throw new HttpError(400, 'Verify your student ID again before continuing.', 'student_verification_required');
        }

        const accountResult = database.prepare(`
            INSERT INTO accounts (role, student_id, email, password_salt, password_hash, created_at)
            VALUES ('student', ?, NULL, ?, ?, ?)
            ON CONFLICT(student_id) DO NOTHING
        `).run(studentId, credentials.salt, credentials.hash, Date.now());
        if (Number(accountResult.changes) !== 1) {
            throw new HttpError(409, 'An account already exists for this student ID.', 'student_already_registered');
        }

        const rosterResult = database.prepare(
            'UPDATE approved_students SET registered_at = ? WHERE student_id = ? AND registered_at IS NULL'
        ).run(Date.now(), studentId);
        if (Number(rosterResult.changes) !== 1) {
            throw new HttpError(409, 'An account already exists for this student ID.', 'student_already_registered');
        }

        database.prepare('DELETE FROM student_verifications WHERE token_hash = ?').run(tokenHash);
        database.exec('COMMIT');
    } catch (error) {
        database.exec('ROLLBACK');
        throw error;
    }

    sendJson(response, 201, { message: 'Student account created.' });
}

async function createInstructorAccount(request, response) {
    if (!takeRateLimit(request, 'instructor-signup', 10)) {
        sendRateLimit(response);
        return;
    }

    const body = await readJson(request);
    const email = normalizeEmail(body.email);
    const password = validatePassword(body.password);
    if (typeof body.permissionCode !== 'string' || body.permissionCode.length > 256) {
        throw new HttpError(403, 'The instructor permission code is invalid.');
    }

    const suppliedCodeHash = createHash('sha256').update(body.permissionCode).digest();
    if (!timingSafeEqual(suppliedCodeHash, instructorCodeHash)) {
        throw new HttpError(403, 'The instructor permission code is invalid.');
    }

    const credentials = await hashPassword(password);
    const result = database.prepare(`
        INSERT INTO accounts (role, student_id, email, password_salt, password_hash, created_at)
        VALUES ('instructor', NULL, ?, ?, ?, ?)
        ON CONFLICT(email) DO NOTHING
    `).run(email, credentials.salt, credentials.hash, Date.now());

    if (Number(result.changes) !== 1) {
        throw new HttpError(409, 'An account already exists for this email.');
    }
    sendJson(response, 201, { message: 'Instructor account created.' });
}

async function signIn(request, response) {
    if (!takeRateLimit(request, 'signin', 20)) {
        sendRateLimit(response);
        return;
    }

    const body = await readJson(request);
    const role = body.role === 'student' || body.role === 'instructor' ? body.role : '';
    if (!role) {
        throw new HttpError(400, 'Select a valid sign-in role.');
    }

    if (typeof body.password !== 'string' || body.password.length < 1 || body.password.length > 128) {
        throw new HttpError(400, 'Enter your password to sign in.');
    }

    let account;
    if (role === 'student') {
        const studentId = normalizeStudentId(body.studentId);
        account = database.prepare(
            'SELECT * FROM accounts WHERE role = ? AND student_id = ?'
        ).get('student', studentId);
    } else {
        const email = normalizeEmail(body.email);
        account = database.prepare(
            'SELECT * FROM accounts WHERE role = ? AND email = ?'
        ).get('instructor', email);
    }

    if (!account) {
        throw new HttpError(401, 'Invalid email or password.');
    }

    const passwordIsValid = await verifyPassword(body.password, account.password_salt, account.password_hash);
    if (!passwordIsValid) {
        throw new HttpError(401, 'Invalid email or password.');
    }

    const now = Date.now();
    const token = randomBytes(32).toString('base64url');
    const tokenHash = tokenDigest(token);
    const expiresAt = now + 60 * 60 * 1000;

    database.prepare('DELETE FROM sessions WHERE expires_at <= ?').run(now);
    database.prepare(
        'INSERT INTO sessions (account_id, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?)'
    ).run(account.id, tokenHash, now, expiresAt);

    setSessionCookie(response, token, request);
    sendJson(response, 200, {
        message: 'Signed in successfully.',
        expiresInSeconds: 60 * 60,
        user: {
            id: account.id,
            role: account.role,
            ...(account.student_id ? { studentId: account.student_id } : {}),
            ...(account.email ? { email: account.email } : {})
        }
    });
}

async function getSession(request, response) {
    const token = extractSessionToken(request);
    if (!token) {
        throw new HttpError(401, 'Not signed in.');
    }

    const session = database.prepare(
        'SELECT account_id, expires_at FROM sessions WHERE token_hash = ?'
    ).get(tokenDigest(token));
    if (!session || session.expires_at <= Date.now()) {
        throw new HttpError(401, 'Session expired or invalid.');
    }

    const account = database.prepare(
        'SELECT id, role, student_id, email FROM accounts WHERE id = ?'
    ).get(session.account_id);
    if (!account) {
        throw new HttpError(401, 'Session expired or invalid.');
    }

    sendJson(response, 200, {
        user: {
            id: account.id,
            role: account.role,
            ...(account.student_id ? { studentId: account.student_id } : {}),
            ...(account.email ? { email: account.email } : {})
        },
        expiresInSeconds: Math.max(0, Math.ceil((session.expires_at - Date.now()) / 1000))
    });
}

async function signOut(request, response) {
    const token = extractSessionToken(request);
    if (!token) {
        sendJson(response, 200, { message: 'No active session.' });
        return;
    }

    database.prepare('DELETE FROM sessions WHERE token_hash = ?').run(tokenDigest(token));
    response.setHeader('Set-Cookie', 'fittronic_session=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
    sendJson(response, 200, { message: 'Signed out.' });
}

const staticFiles = new Map([
    ['/', 'index1.html'],
    ['/index1.html', 'index1.html'],
    ['/timer.html', 'timer.html'],
    ['/style1.css', 'style1.css'],
    ['/script1.js', 'script1.js']
]);

function staticPath(urlPath) {
    const file = staticFiles.get(urlPath);
    if (file) return path.join(root, file);

    const imageMatch = /^\/images\/(image-\d{2}|project-\d{2}|kinetic-1[01])\.jpg$/.exec(urlPath);
    if (imageMatch) return path.join(root, 'images', `${imageMatch[1]}.jpg`);
    return null;
}

async function serveStatic(urlPath, response) {
    const filePath = staticPath(urlPath);
    if (!filePath) {
        sendJson(response, 404, { error: 'Page not found.' });
        return;
    }

    const contentTypes = {
        '.css': 'text/css; charset=utf-8',
        '.html': 'text/html; charset=utf-8',
        '.jpg': 'image/jpeg',
        '.js': 'text/javascript; charset=utf-8'
    };
    const content = await fs.promises.readFile(filePath);
    response.writeHead(200, Object.assign({}, securityHeaders(), {
        'Content-Type': contentTypes[path.extname(filePath)] || 'application/octet-stream'
    }));
    response.end(content);
}

const server = http.createServer(async (request, response) => {
    try {
        const url = new URL(request.url, 'http://localhost');
        if (request.method === 'POST' && url.pathname === '/api/students/verify') {
            await verifyStudent(request, response);
        } else if (request.method === 'POST' && url.pathname === '/api/signup/student') {
            await createStudentAccount(request, response);
        } else if (request.method === 'POST' && url.pathname === '/api/signup/instructor') {
            await createInstructorAccount(request, response);
        } else if (request.method === 'POST' && url.pathname === '/api/signin') {
            await signIn(request, response);
        } else if (request.method === 'POST' && url.pathname === '/api/signout') {
            await signOut(request, response);
        } else if (request.method === 'GET' && url.pathname === '/api/session') {
            await getSession(request, response);
        } else if (request.method === 'GET') {
            await serveStatic(url.pathname, response);
        } else if (url.pathname.startsWith('/api/')) {
            sendJson(response, 404, { error: 'API route not found.' });
        } else {
            sendJson(response, 405, { error: 'Method not allowed.' }, { Allow: 'GET, POST' });
        }
    } catch (error) {
        sendError(response, error);
    }
});

server.listen(port, host, () => {
    console.log(`FITRONIC signup site listening at http://${host}:${port}`);
});

function closeServer() {
    server.close(() => {
        database.close();
        process.exit(0);
    });
}

process.on('SIGINT', closeServer);
process.on('SIGTERM', closeServer);
