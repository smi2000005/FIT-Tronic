# FITRONIC signup server

The signup flow requires Node.js 22.13 or newer. The server uses Node's built-in SQLite support and has no npm package dependencies.

## 1. Prepare the approved student roster

Create a CSV outside the website folder, with one column and this header:

```csv
studentId
STUDENT001
STUDENT002
```

Only IDs in this approved roster can pass student verification. Import it from the project root:

```powershell
node server/import-roster.js "C:\secure\student-roster.csv"
```

The import adds IDs without replacing existing accounts. IDs are trimmed and stored uppercase. Keep the roster private; do not put it in the public `images` folder.

## 2. Configure the instructor permission code

Set a long random code in the server's environment. Do not put the real code in HTML, JavaScript, or a committed file:

```powershell
$env:INSTRUCTOR_PERMISSION_CODE = (& node -e "process.stdout.write(require('node:crypto').randomBytes(32).toString('base64url'))")
```

Give this code only to instructors who are allowed to register. The server checks it during signup and does not store it.

## 3. Start the website and API

Run from the project root, in the same PowerShell window where the code was set:

```powershell
node server/server1.js
```

Open `http://127.0.0.1:4173`. Opening `index1.html` directly with a `file://` URL will not reach the signup API.

The database is created at `server/data/fittronic.sqlite`. Set `DATABASE_PATH` before importing the roster and starting the server if you need another private location. The server binds to localhost by default; deploy behind HTTPS before allowing access from other machines.

## Signup behavior

- Student IDs are checked against the imported roster. Approval creates a one-time verification token that expires after 10 minutes; the password field stays locked until verification succeeds.
- Instructor permission codes are checked on the server and signup attempts are rate-limited.
- Passwords are stored as salted scrypt hashes. The signup API does not expose database files or roster data to the website.

## Sign in behavior

- `POST /api/signin` accepts a role plus either a student ID or instructor email and password.
- The server validates credentials against the account record and issues a signed session cookie for one hour.
- `GET /api/session` reads the current session and returns the signed-in user details, while `POST /api/signout` clears it.
