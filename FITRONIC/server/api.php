<?php
declare(strict_types=1);

require_once __DIR__ . '/mysql.php';

const VERIFICATION_LIFETIME = 600;
const SESSION_LIFETIME = 3600;
const RATE_LIMIT_WINDOW = 900;

final class ApiError extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly ?string $codeName = null
    ) {
        parent::__construct($message);
    }
}

function respond(int $status, array $body, array $headers = []): never
{
    if (($_GET['form'] ?? '') === '1') {
        $route = (string)($_GET['route'] ?? '');
        $mode = $route === 'signin' ? 'signin' : 'signup';
        $message = $body['message'] ?? $body['error'] ?? 'The request could not be completed.';
        header(
            'Location: ../index1.html?authMode=' . $mode .
            '&authMessage=' . rawurlencode((string)$message) . '#initiate',
            true,
            303
        );
        exit;
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    foreach ($headers as $name => $value) {
        header($name . ': ' . $value);
    }
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function requestBody(): array
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_starts_with($contentType, 'application/json')) {
        $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > 16384) {
            throw new ApiError(413, 'The signup request is too large.');
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 16384) {
            throw new ApiError(413, 'The signup request is too large.');
        }
        try {
            $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiError(400, 'The signup request contains invalid JSON.');
        }
    } elseif (str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
            throw new ApiError(413, 'The signup request is too large.');
        }
        $body = $_POST;
    } else {
        throw new ApiError(415, 'Send signup data as JSON or form data.');
    }
    if (!is_array($body) || array_is_list($body)) {
        throw new ApiError(400, 'The signup request must contain form fields or a JSON object.');
    }
    return $body;
}

function studentId(mixed $value): string
{
    if (!is_string($value)) {
        throw new ApiError(400, 'Enter a valid student ID.');
    }
    $id = strtoupper(trim($value));
    if (strlen($id) > 64 || !preg_match('/^[0-9]{4}FIT[0-9]+$/', $id)) {
        throw new ApiError(400, 'Enter a valid student ID in the format 2026FIT1.');
    }
    return $id;
}

function emailAddress(mixed $value): string
{
    if (!is_string($value)) {
        throw new ApiError(400, 'Enter a valid email address.');
    }
    $email = strtolower(trim($value));
    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new ApiError(400, 'Enter a valid email address.');
    }
    return $email;
}

function passwordValue(mixed $value): string
{
    if (!is_string($value) || strlen($value) > 128) {
        throw new ApiError(400, 'Choose a password between 12 and 128 characters.');
    }
    $characterCount = preg_match_all('/./us', $value);
    if ($characterCount === false || $characterCount < 12) {
        throw new ApiError(400, 'Choose a password between 12 and 128 characters.');
    }
    if (!defined('PASSWORD_ARGON2ID') && strlen($value) > 72) {
        throw new ApiError(400, 'This server accepts passwords up to 72 bytes. Use a shorter password.');
    }
    return $value;
}

function applyRateLimit(PDO $db, string $action, int $limit): void
{
    $now = time();
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash('sha256', $action . ':' . $ip);
    $statement = $db->prepare(
        'INSERT INTO rate_limits (rate_key, window_start, attempts)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE
             attempts = IF(window_start <= ?, 1, attempts + 1),
             window_start = IF(window_start <= ?, ?, window_start)'
    );
    $statement->execute([$key, $now, $now - RATE_LIMIT_WINDOW, $now - RATE_LIMIT_WINDOW, $now]);
    $statement = $db->prepare('SELECT attempts, window_start FROM rate_limits WHERE rate_key = ?');
    $statement->execute([$key]);
    $entry = $statement->fetch();
    if (!$entry || (int)$entry['attempts'] > $limit) {
        $retryAfter = $entry
            ? max(1, (int)$entry['window_start'] + RATE_LIMIT_WINDOW - $now)
            : RATE_LIMIT_WINDOW;
        throw new ApiError(429, 'Too many attempts. Wait a little and try again.');
    }
}

function sessionToken(): string
{
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
        return trim($matches[1]);
    }
    return (string)($_COOKIE['fitronic_session'] ?? '');
}

function setSessionCookie(string $token): void
{
    setcookie('fitronic_session', $token, [
        'expires' => time() + SESSION_LIFETIME,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function tokenHash(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Hardware Hub bridge.
 * The forum, marketplace and projects pages identify people through the `users`
 * table. Every sign-up account gets exactly one matching `users` row, created the
 * first time that account signs in.
 */
function hubUserId(PDO $db, array $account): int
{
    $find = $db->prepare('SELECT id FROM users WHERE account_id = ?');
    $find->execute([(int)$account['id']]);
    $existing = $find->fetchColumn();
    if ($existing !== false) {
        return (int)$existing;
    }

    $name = $account['role'] === 'student'
        ? (string)$account['student_id']
        : (string)($account['instructor_username'] ?: 'instructor-' . $account['id']);

    try {
        $db->prepare('INSERT INTO users (full_name, email, role, account_id) VALUES (?, NULL, ?, ?)')
            ->execute([$name, $account['role'], (int)$account['id']]);
        return (int)$db->lastInsertId();
    } catch (PDOException $error) {
        // Two sign-ins at the same instant: use the row the other request just created.
        if ($error->getCode() === '23000') {
            $find->execute([(int)$account['id']]);
            $existing = $find->fetchColumn();
            if ($existing !== false) {
                return (int)$existing;
            }
        }
        throw $error;
    }
}

/** Remember the signed-in person in the PHP session that db.php reads. */
function startHubSession(PDO $db, array $account): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = hubUserId($db, $account);
    $_SESSION['account_id'] = (int)$account['id'];
    $_SESSION['role'] = $account['role'];
    $_SESSION['display_name'] = $account['role'] === 'student'
        ? (string)$account['student_id']
        : (string)($account['instructor_username'] ?: $account['email']);
}

function userDetails(array $account): array
{
    $user = ['id' => (int)$account['id'], 'role' => $account['role']];
    if ($account['student_id'] !== null) {
        $user['studentId'] = $account['student_id'];
    }
    if ($account['email'] !== null) {
        $user['email'] = $account['email'];
    }
    if (($account['instructor_username'] ?? null) !== null) {
        $user['instructorUsername'] = $account['instructor_username'];
    }
    return $user;
}

function verifyStudent(PDO $db): never
{
    applyRateLimit($db, 'student-verification', 10);
    $id = studentId(requestBody()['studentId'] ?? null);
    $statement = $db->prepare(
        'SELECT registered_at FROM approved_students WHERE student_id = ?'
    );
    $statement->execute([$id]);
    $student = $statement->fetch();
    if (!$student || $student['registered_at'] !== null) {
        throw new ApiError(400, 'Student ID could not be approved. Check it or contact your school administrator.', 'student_id_not_eligible');
    }

    $now = time();
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $db->prepare('DELETE FROM student_verifications WHERE expires_at <= ? OR student_id = ?')
        ->execute([$now, $id]);
    $db->prepare(
        'INSERT INTO student_verifications (token_hash, student_id, expires_at) VALUES (?, ?, ?)'
    )->execute([tokenHash($token), $id, $now + VERIFICATION_LIFETIME]);

    respond(200, [
        'verificationToken' => $token,
        'message' => 'Student ID verified. Set a password to continue.',
        'expiresInSeconds' => VERIFICATION_LIFETIME,
    ]);
}

function createStudentAccount(PDO $db): never
{
    applyRateLimit($db, 'student-signup', 10);
    $body = requestBody();
    $id = studentId($body['studentId'] ?? null);
    $password = passwordValue($body['password'] ?? null);
    $token = $body['verificationToken'] ?? null;
    if (!is_string($token) || strlen($token) > 128) {
        throw new ApiError(400, 'Verify your student ID before continuing.', 'student_verification_required');
    }

    $now = time();
    $db->beginTransaction();
    try {
        $statement = $db->prepare(
            'SELECT student_id, expires_at FROM student_verifications
             WHERE token_hash = ? FOR UPDATE'
        );
        $statement->execute([tokenHash($token)]);
        $verification = $statement->fetch();
        $statement = $db->prepare(
            'SELECT registered_at FROM approved_students WHERE student_id = ? FOR UPDATE'
        );
        $statement->execute([$id]);
        $approved = $statement->fetch();

        if (!$verification || $verification['student_id'] !== $id ||
            (int)$verification['expires_at'] <= $now || !$approved ||
            $approved['registered_at'] !== null) {
            throw new ApiError(400, 'Verify your student ID again before continuing.', 'student_verification_required');
        }

        $statement = $db->prepare(
            "INSERT INTO accounts (role, student_id, email, password_hash, created_at)
             VALUES ('student', ?, NULL, ?, ?)"
        );
        $statement->execute([$id, password_hash($password, PASSWORD_DEFAULT), $now]);
        $db->prepare('UPDATE approved_students SET registered_at = ? WHERE student_id = ?')
            ->execute([$now, $id]);
        $db->prepare('DELETE FROM student_verifications WHERE token_hash = ?')
            ->execute([tokenHash($token)]);
        $db->commit();
    } catch (PDOException $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($error->getCode() === '23000') {
            throw new ApiError(409, 'An account already exists for this student ID.', 'student_already_registered');
        }
        throw $error;
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $error;
    }

    respond(201, ['message' => 'Student account created.']);
}

function createInstructorAccount(PDO $db): never
{
    applyRateLimit($db, 'instructor-signup', 10);
    $body = requestBody();
    $email = emailAddress($body['email'] ?? null);
    $password = passwordValue($body['password'] ?? null);
    $permissionCode = $body['permissionCode'] ?? null;
    $expectedCode = getenv('FITRONIC_INSTRUCTOR_CODE') ?: (string)(appConfig()['instructor_code'] ?? '');
    if ($expectedCode === '' || !is_string($permissionCode) || !hash_equals($expectedCode, $permissionCode)) {
        throw new ApiError(403, 'The instructor permission code is invalid.');
    }

    $statement = $db->prepare(
        "INSERT INTO accounts
            (role, student_id, email, instructor_username, password_hash, created_at)
         VALUES ('instructor', NULL, ?, ?, ?, ?)"
    );
    for ($attempt = 0; ; $attempt++) {
        $username = 'instructor-' . strtoupper(bin2hex(random_bytes(6)));
        try {
            $statement->execute([
                $email,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                time(),
            ]);
            break;
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            $existingEmail = $db->prepare('SELECT 1 FROM accounts WHERE email = ?');
            $existingEmail->execute([$email]);
            if ($existingEmail->fetchColumn()) {
                throw new ApiError(409, 'An account already exists for this email.');
            }
            if ($attempt >= 4) {
                throw new RuntimeException('Could not generate a unique instructor username.', 0, $error);
            }
        }
    }
    respond(201, ['message' => 'Instructor account created.']);
}

function signIn(PDO $db): never
{
    applyRateLimit($db, 'signin', 20);
    $body = requestBody();
    $role = $body['role'] ?? '';
    if (!in_array($role, ['student', 'instructor'], true)) {
        throw new ApiError(400, 'Select a valid sign-in role.');
    }
    $password = $body['password'] ?? null;
    if (!is_string($password) || $password === '' || strlen($password) > 128) {
        throw new ApiError(400, 'Enter your password to sign in.');
    }

    if ($role === 'student') {
        $statement = $db->prepare(
            "SELECT * FROM accounts WHERE role = 'student' AND student_id = ?"
        );
        $statement->execute([studentId($body['studentId'] ?? $body['identity'] ?? null)]);
    } else {
        $statement = $db->prepare(
            "SELECT * FROM accounts WHERE role = 'instructor' AND email = ?"
        );
        $statement->execute([emailAddress($body['email'] ?? $body['identity'] ?? null)]);
    }
    $account = $statement->fetch();
    if (!$account || !password_verify($password, $account['password_hash'])) {
        throw new ApiError(401, 'Invalid email or password.');
    }

    $now = time();
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $db->prepare('DELETE FROM sessions WHERE expires_at <= ?')->execute([$now]);
    $db->prepare(
        'INSERT INTO sessions (account_id, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?)'
    )->execute([(int)$account['id'], tokenHash($token), $now, $now + SESSION_LIFETIME]);
    setSessionCookie($token);
    startHubSession($db, $account);
    if (($_GET['form'] ?? '') === '1') {
        header('Location: ../index1.html#hero', true, 303);
        exit;
    }
    respond(200, [
        'message' => 'Signed in successfully.',
        'expiresInSeconds' => SESSION_LIFETIME,
        'user' => userDetails($account),
    ]);
}

function getSession(PDO $db): never
{
    $token = sessionToken();
    if ($token === '') {
        throw new ApiError(401, 'Not signed in.');
    }
    $statement = $db->prepare(
        'SELECT a.id, a.role, a.student_id, a.email, a.instructor_username, s.expires_at
         FROM sessions s JOIN accounts a ON a.id = s.account_id
         WHERE s.token_hash = ?'
    );
    $statement->execute([tokenHash($token)]);
    $account = $statement->fetch();
    if (!$account || (int)$account['expires_at'] <= time()) {
        throw new ApiError(401, 'Session expired or invalid.');
    }
    respond(200, [
        'user' => userDetails($account),
        'expiresInSeconds' => max(0, (int)$account['expires_at'] - time()),
    ]);
}

function signOut(PDO $db): never
{
    $token = sessionToken();
    if ($token !== '') {
        $db->prepare('DELETE FROM sessions WHERE token_hash = ?')->execute([tokenHash($token)]);
    }
    setcookie('fitronic_session', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
    }
    $_SESSION = [];
    session_destroy();
    respond(200, ['message' => $token === '' ? 'No active session.' : 'Signed out.']);
}

try {
    $db = database();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (isset($_GET['route']) && is_string($_GET['route'])) {
        $path = '/api/' . trim($_GET['route'], '/');
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST' && $path === '/api/students/verify') {
        verifyStudent($db);
    } elseif ($method === 'POST' && $path === '/api/signup') {
        $body = requestBody();
        if (($body['role'] ?? null) === 'student') {
            createStudentAccount($db);
        } elseif (($body['role'] ?? null) === 'instructor') {
            createInstructorAccount($db);
        }
        throw new ApiError(400, 'Select a valid signup role.');
    } elseif ($method === 'POST' && $path === '/api/signup/student') {
        createStudentAccount($db);
    } elseif ($method === 'POST' && $path === '/api/signup/instructor') {
        createInstructorAccount($db);
    } elseif ($method === 'POST' && $path === '/api/signin') {
        signIn($db);
    } elseif ($method === 'POST' && $path === '/api/signout') {
        signOut($db);
    } elseif ($method === 'GET' && $path === '/api/session') {
        getSession($db);
    } elseif (str_starts_with((string)$path, '/api/')) {
        throw new ApiError(404, 'API route not found.');
    } else {
        throw new ApiError(404, 'Page not found.');
    }
} catch (ApiError $error) {
    $headers = $error->status === 429
        ? ['Retry-After' => (string)RATE_LIMIT_WINDOW]
        : [];
    $body = ['error' => $error->getMessage()];
    if ($error->codeName !== null) {
        $body['code'] = $error->codeName;
    }
    respond($error->status, $body, $headers);
} catch (Throwable $error) {
    error_log('FITRONIC API request failed: ' . $error->getMessage());
    respond(500, ['error' => 'The signup service encountered an error. Try again later.']);
}
