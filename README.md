# Hidayah Backend — Deployment Guide

## Prerequisites

- PHP 8.0+ with cURL extension enabled
- MySQL 5.7+ or MariaDB
- Composer
- Apache with `mod_rewrite` (or equivalent URL rewriting on your web server)

---

## Folder Structure

```
hidaya_backend/
├── api/
│   └── v1/
│       └── hidayah/
│           ├── auth/
│           │   ├── send_otp.php          → POST /hidayah/auth/otp/send
│           │   ├── verify_otp.php        → POST /hidayah/auth/otp/verify
│           │   ├── unsubscribe.php       → POST /hidayah/auth/unsubscribe
│           │   └── refresh_token.php     → POST /auth/refresh
│           └── subscription_status.php   → GET  /hidayah/subscription
├── config/
│   ├── config.php                        ← YOUR credentials (git-ignored)
│   └── config.example.php                ← Template with placeholders
├── helpers/
│   ├── bdapps_client.php                 ← BDApps API wrapper
│   └── jwt_helper.php                    ← JWT encode/decode
├── logs/
│   └── .gitkeep                          ← Log directory (writable)
├── vendor/                               ← Composer dependencies (git-ignored)
├── schema.sql                            ← Database schema
├── composer.json
├── composer.lock
└── README.md
```

The URL path `/hidayah/auth/otp/send` maps to `api/v1/hidayah/auth/send_otp.php`, and so on. Your web server should be configured so that the project root serves these paths.

---

## Step-by-Step Deployment

### 1. Upload Files

Upload the entire `hidaya_backend/` folder to your web server.

### 2. Import Database Schema

Import `schema.sql` into MySQL via phpMyAdmin, MySQL Workbench, or CLI:

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < schema.sql
```

### 3. Install Composer Dependencies

From the project root:

```bash
composer install --no-dev
```

### 4. Create `config.php` from the Example

```bash
cp config/config.example.php config/config.php
```

Open `config/config.php` and fill in:

| Constant | Description |
|----------|-------------|
| `APP_ENV` | Set to `'production'` before going live |
| `BDAPPS_APP_ID` | Your BDApps application ID |
| `BDAPPS_APP_PASSWORD` | Your BDApps application password |
| `JWT_SECRET` | A random 64-character string (`openssl rand -hex 32`) |
| `DB_HOST` | MySQL host (usually `localhost`) |
| `DB_NAME` | Database name |
| `DB_USER` | MySQL username |
| `DB_PASS` | MySQL password |

### 5. Set Permissions

Ensure the `logs/` directory is writable by the web server:

```bash
chmod 755 logs/
```

### 6. Configure Web Server URL Rewriting

The API endpoints must map to the PHP files under `api/v1/hidayah/`. Below is an Apache `.htaccess` example placed at the project root:

```apache
RewriteEngine On

# Auth endpoints
RewriteRule ^hidayah/auth/otp/send$    api/v1/hidayah/auth/send_otp.php    [L]
RewriteRule ^hidayah/auth/otp/verify$  api/v1/hidayah/auth/verify_otp.php  [L]
RewriteRule ^hidayah/auth/unsubscribe$ api/v1/hidayah/auth/unsubscribe.php [L]
RewriteRule ^auth/refresh$             api/v1/hidayah/auth/refresh_token.php [L]

# Subscription status
RewriteRule ^hidayah/subscription$     api/v1/hidayah/subscription_status.php [L]
```

For Nginx, use `location` blocks with `try_files` or explicit rewrites pointing to the same PHP scripts.

### 7. Verify `config.php` Is Not Committed

`config.php` is listed in `.gitignore` and must never be committed to version control. The template `config.example.php` is safe to commit.

---

## API Endpoints

All endpoints return JSON with the following envelope:

```json
{
  "success": true,
  "statusCode": 200,
  "message": "...",
  "data": { ... },
  "timestamp": "2026-09-10T18:00:00+00:00"
}
```

### `POST /hidayah/auth/otp/send`

**Request**

```json
{ "phoneNumber": "01812345678" }
```

**Responses**

- `200` — OTP sent (new user) or tokens issued (existing active user):
  ```json
  {
    "alreadyRegistered": false,
    "referenceNo": "REF_...",
    "expiresInSeconds": 60
  }
  ```
- `400` — Invalid phone format or missing field
- `500` — BDApps error or internal failure

---

### `POST /hidayah/auth/otp/verify`

**Request**

```json
{ "phoneNumber": "01812345678", "referenceNo": "REF_...", "code": "123456" }
```

**Responses**

- `200` — OTP verified, tokens issued:
  ```json
  {
    "user": { "id": 1, "phoneNumber": "01812345678", "role": "user" },
    "accessToken": "eyJ...",
    "refreshToken": "abc123...",
    "isNewUser": true
  }
  ```
- `400` — OTP verification failed or missing fields

---

### `POST /hidayah/auth/unsubscribe`

**Auth required** — Header: `Authorization: Bearer <accessToken>`

**Request**

```json
{}
```

**Responses**

- `200` — Unsubscribed successfully:
  ```json
  { "alreadyUnsubscribed": false }
  ```
- `401` — Missing/invalid token or user not found
- `500` — BDApps error

---

### `GET /hidayah/subscription`

**Auth required** — Header: `Authorization: Bearer <accessToken>`

**Responses**

- `200`:
  ```json
  { "active": true }
  ```
- `401` — Missing/invalid token or user not found

---

### `POST /auth/refresh`

**Request**

```json
{ "refreshToken": "raw_token_string" }
```

**Responses**

- `200` — New tokens issued:
  ```json
  {
    "accessToken": "eyJ...",
    "refreshToken": "xyz789...",
    "user": { "id": 1, "phoneNumber": "01812345678" }
  }
  ```
- `401` — Invalid, expired, or revoked refresh token

---

## Production Checklist

- [ ] `config.php` created with real credentials
- [ ] `APP_ENV` set to `'production'` in `config.php`
- [ ] Database imported via `schema.sql`
- [ ] `composer install --no-dev` run successfully
- [ ] `logs/` directory writable
- [ ] CORS origin restricted to your frontend domain (edit `sendCorsHeaders()` in `config/config.php`)
- [ ] Web server URL rewriting configured

---

## Security Notes

- **CORS**: `sendCorsHeaders()` currently sets `Access-Control-Allow-Origin: *`. Restrict this to your actual frontend domain before going live.
- **SSL**: When `APP_ENV` is `'production'`, cURL verifies SSL peer and host. Keep it `'local'` only for development if your local environment has certificate issues.
- **Secrets**: `config.php` is git-ignored. Never commit it. Use `config.example.php` as the template.
- **Logging**: BDApps request/response logs in `logs/` do **not** contain plaintext passwords (they are redacted before writing).
