# Teacher System API

Backend for managing a private teacher's students, parents and assistants (Egypt).
Laravel 13 · PHP 8.5 · Sanctum tokens · MySQL.

**Single-tenant:** one codebase, deployed separately per teacher, each with its own `.env` and database.

---

## Contents

- [Setup](#setup)
- [Configuration (.env)](#configuration-env)
- [Conventions](#conventions)
  - [Headers & authentication](#headers--authentication)
  - [Error format & codes](#error-format--codes)
  - [Phone numbers](#phone-numbers)
  - [Roles & permissions](#roles--permissions)
- [Endpoints summary](#endpoints-summary)
- [Auth](#auth)
- [Assistants (teacher only)](#assistants-teacher-only)
- [Students](#students)
- [Self-registration](#self-registration)
- [Response objects](#response-objects)
- [Activity log](#activity-log)
- [Testing](#testing)

---

## Setup

```powershell
composer install
copy .env.example .env          # then edit the TEACHER_* and DB_* values
php artisan key:generate
php artisan migrate --seed      # tables + roles/permissions
php artisan teacher:install     # creates the teacher account, prints the password once
php artisan serve               # API at http://localhost:8000/api
```

`teacher:install` options:

| Option | Description |
|---|---|
| `--phone=01012345678` | Teacher mobile (default: `TEACHER_PHONE`; asked interactively if empty) |
| `--name="..."` | Teacher name (default: `TEACHER_NAME`) |
| `--password=...` | Use this password (min 8). Without it a temporary password is generated and must be changed on first login |

The command refuses to run if a teacher already exists, the phone is invalid, or the phone is already used.

---

## Configuration (.env)

| Key | Default | Description |
|---|---|---|
| `TEACHER_NAME` | `Teacher` | Teacher display name |
| `TEACHER_PHONE` | — | Teacher mobile (used by `teacher:install`) |
| `TEACHER_SUBJECT` | — | Subject taught |
| `TEACHER_CURRENCY` | `EGP` | Currency |
| `TEACHER_BILLING_MODE` | `monthly` | `monthly` or `per_session` |
| `STUDENT_CODE_PREFIX` | `ST` | Student codes are `{prefix}-{1000+id}`, e.g. `ST-1001` |
| `FEATURE_SELF_REGISTRATION` | `false` | Enables `POST /api/register` |
| `FEATURE_WHATSAPP` | `false` | Reserved for WhatsApp integration |

Values are read through `config/teacher.php`. Run `php artisan config:clear` after editing `.env` if config is cached.

---

## Conventions

### Headers & authentication

Send on every request:

```http
Accept: application/json
Content-Type: application/json
```

Authenticated endpoints also need the token returned by login:

```http
Authorization: Bearer 1|abcdef...
```

Every authenticated endpoint also enforces:

- **Inactive users** (`is_active = false`) are rejected with `403 ACCOUNT_INACTIVE` and **all their tokens are revoked**.
- **Temporary password**: while `must_change_password = true`, only `GET /auth/me`, `POST /auth/logout` and `POST /auth/change-password` work. Everything else returns `403 PASSWORD_CHANGE_REQUIRED`.

### Error format & codes

All errors have the same shape:

```json
{
  "message": "Human readable message",
  "code": "MACHINE_CODE",
  "errors": {
    "field": ["Validation message"]
  }
}
```

`errors` is `{}` when there are no field errors.

| HTTP | `code` | When |
|---|---|---|
| 401 | `UNAUTHENTICATED` | Missing, invalid or revoked token |
| 403 | `FORBIDDEN` | Authenticated but not allowed (missing permission / policy) |
| 403 | `ACCOUNT_INACTIVE` | User is deactivated (login or any request) |
| 403 | `PASSWORD_CHANGE_REQUIRED` | Must change the temporary password first |
| 404 | `NOT_FOUND` | Resource (or route) not found |
| 404 | `FEATURE_DISABLED` | Feature flag is off (e.g. self-registration) |
| 405 | `METHOD_NOT_ALLOWED` | Wrong HTTP method |
| 409 | `STUDENT_NOT_PENDING` | Approve/reject a student who is not pending |
| 422 | `VALIDATION_FAILED` | Invalid input (see `errors`) |
| 422 | `INVALID_CREDENTIALS` | Wrong identifier or password |
| 429 | `TOO_MANY_ATTEMPTS` | Rate limit exceeded (`Retry-After` header is set) |

### Phone numbers

Every phone is normalized to **`01XXXXXXXXX`** before it is saved or looked up. Valid pattern: `^01[0125][0-9]{8}$`.

These inputs are all accepted and stored as `01012345678`:

```
01012345678     +201012345678     00201012345678     201012345678
1012345678      010 1234-5678     ٠١٠١٢٣٤٥٦٧٨ (Arabic-Indic digits)
```

### Roles & permissions

| Role | Access |
|---|---|
| `teacher` | Owner, can do everything |
| `assistant` | Only the permissions the teacher grants |
| `student` | Only their own data (policies) |
| `parent` | Only their own children's data (policies) |

Permissions that can be granted to assistants:

| Permission | Label |
|---|---|
| `attendance.scan` | تسجيل الحضور (مسح QR) |
| `attendance.view` | عرض الحضور |
| `students.view` | عرض الطلاب |
| `students.create` | إضافة طلاب |
| `students.edit` | تعديل بيانات الطلاب |
| `students.approve` | قبول / رفض طلبات التسجيل |
| `payments.collect` | تحصيل المدفوعات |
| `payments.reports` | تقارير المدفوعات |
| `groups.manage` | إدارة المجموعات |

> Attendance, payments and groups permissions exist already but their endpoints are not built yet.

---

## Endpoints summary

Base URL: `http://localhost:8000/api`

| Method | Endpoint | Auth | Access |
|---|---|---|---|
| POST | `/auth/login` | — | Public (5/min per identifier+IP) |
| GET | `/auth/me` | ✅ | Any user |
| POST | `/auth/logout` | ✅ | Any user |
| POST | `/auth/change-password` | ✅ | Any user |
| GET | `/assistants` | ✅ | Teacher |
| GET | `/assistants/permissions` | ✅ | Teacher |
| POST | `/assistants` | ✅ | Teacher |
| PUT | `/assistants/{id}/permissions` | ✅ | Teacher |
| POST | `/assistants/{id}/reset-password` | ✅ | Teacher |
| POST | `/assistants/{id}/deactivate` | ✅ | Teacher |
| POST | `/assistants/{id}/activate` | ✅ | Teacher |
| GET | `/students` | ✅ | `students.view` |
| POST | `/students` | ✅ | `students.create` |
| GET | `/students/{id}` | ✅ | `students.view`, or the student, or their parent |
| PATCH | `/students/{id}` | ✅ | `students.edit` |
| POST | `/students/{id}/regenerate-qr` | ✅ | `students.edit` |
| POST | `/students/{id}/approve` | ✅ | `students.approve` |
| POST | `/students/{id}/reject` | ✅ | `students.approve` |
| POST | `/register` | — | Public, only if `FEATURE_SELF_REGISTRATION=true` (5/min per IP) |

---

## Auth

### POST `/auth/login`

Log in with a **phone** (any format) **or a student code** (case-insensitive, e.g. `ST-1001`).

**Body**

| Field | Type | Rules |
|---|---|---|
| `identifier` | string | required, phone or student code |
| `password` | string | required |
| `device_name` | string | required, max 100 (e.g. `android`, `web`) |

```json
{
  "identifier": "01012345678",
  "password": "secret123",
  "device_name": "android"
}
```

**200 OK**

```json
{
  "token": "1|q8x0Jb...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "أ. محمد أحمد",
    "phone": "01012345678",
    "is_active": true,
    "must_change_password": true,
    "last_login_at": "2026-11-08T13:40:00+00:00",
    "student": null,
    "children": [],
    "created_at": "2026-11-08T13:00:00+00:00"
  },
  "roles": ["teacher"],
  "permissions": ["attendance.scan", "attendance.view", "students.view", "..."],
  "must_change_password": true
}
```

- If `must_change_password` is `true`, the app should go straight to the change-password screen.
- `user.student` is filled for student accounts and `user.children` for parents.
- `last_login_at` is updated, and a `login` activity is logged.

**Errors:** `422 INVALID_CREDENTIALS`, `403 ACCOUNT_INACTIVE`, `422 VALIDATION_FAILED`, `429 TOO_MANY_ATTEMPTS` (more than 5 attempts per minute for the same identifier from the same IP).

---

### GET `/auth/me`

Returns the current user. Works even while `must_change_password` is true.

**200 OK**: same as login, without `token` / `token_type`.

```json
{
  "user": { "id": 7, "name": "Ali Hassan", "phone": "01202222222", "...": "...",
    "children": [
      { "id": 3, "code": "ST-1003", "name": "Omar Ali", "status": "active",
        "status_label": "نشط", "relation": "father", "created_at": "..." }
    ]
  },
  "roles": ["parent"],
  "permissions": [],
  "must_change_password": false
}
```

---

### POST `/auth/logout`

Revokes **only the current token** (other devices stay logged in).

**200 OK**

```json
{ "message": "تم تسجيل الخروج." }
```

---

### POST `/auth/change-password`

| Field | Rules |
|---|---|
| `current_password` | required, must match the current password |
| `password` | required, min 8, must differ from current |
| `password_confirmation` | required, must equal `password` |

```json
{
  "current_password": "tmp7k2pq9x",
  "password": "MyNewPass123",
  "password_confirmation": "MyNewPass123"
}
```

**200 OK**: `{ "message": "تم تغيير كلمة المرور.", "user": {...}, "roles": [...], "permissions": [...], "must_change_password": false }`

Effects: sets `must_change_password = false` and **revokes all other tokens** (the current token keeps working).

**Errors:** `422 VALIDATION_FAILED`

---

## Assistants (teacher only)

All endpoints return `403 FORBIDDEN` for anyone except the teacher (assistants included, whatever their permissions).
`{id}` must be an assistant, otherwise `404 NOT_FOUND`.

### GET `/assistants`

**200 OK**

```json
{
  "data": [
    {
      "id": 2,
      "name": "Sara",
      "phone": "01123456789",
      "is_active": true,
      "must_change_password": false,
      "last_login_at": null,
      "roles": ["assistant"],
      "permissions": ["attendance.scan", "students.view"],
      "created_at": "2026-11-08T13:05:00+00:00"
    }
  ]
}
```

### GET `/assistants/permissions`

The permission catalogue with Arabic labels, for building the UI checkboxes.

```json
{
  "data": [
    { "value": "attendance.scan", "label": "تسجيل الحضور (مسح QR)" },
    { "value": "students.view", "label": "عرض الطلاب" }
  ]
}
```

### POST `/assistants`

| Field | Rules |
|---|---|
| `name` | required, max 255 |
| `phone` | required, valid Egyptian mobile, not already used |
| `password` | optional, min 8. If omitted a temporary one is generated |
| `permissions` | required array (can be empty) of permission values |

```json
{
  "name": "Sara",
  "phone": "+20 112 345 6789",
  "permissions": ["attendance.scan", "students.view"]
}
```

**201 Created**: the password is returned **only in this response**.

```json
{
  "data": { "id": 2, "name": "Sara", "phone": "01123456789", "must_change_password": true, "permissions": ["attendance.scan", "students.view"], "...": "..." },
  "credentials": { "phone": "01123456789", "password": "k7m2pq9xta" }
}
```

### PUT `/assistants/{id}/permissions`

Replaces the assistant's permissions with the given list (send `[]` to remove all).

```json
{ "permissions": ["payments.collect"] }
```

**200 OK**: `{ "data": { ...assistant } }`

### POST `/assistants/{id}/reset-password`

Body (optional): `{ "password": "NewTemp123" }`. If omitted, a temporary password is generated.

Effects: `must_change_password = true` and **all tokens are revoked**.

**200 OK**

```json
{
  "data": { "...": "assistant" },
  "credentials": { "phone": "01123456789", "password": "p4ht8wzr2c" }
}
```

### POST `/assistants/{id}/deactivate`

Sets `is_active = false` and **revokes all tokens**. Login is then rejected with `ACCOUNT_INACTIVE`.

**200 OK**: `{ "data": { ..., "is_active": false } }`

### POST `/assistants/{id}/activate`

**200 OK**: `{ "data": { ..., "is_active": true } }`

---

## Students

Student codes are generated automatically after creation: `{STUDENT_CODE_PREFIX}-{1000 + id}`, e.g. `ST-1001`.
Statuses: `pending` (في انتظار الموافقة), `active` (نشط), `suspended` (موقوف).

### GET `/students`

Permission: `students.view`. Paginated, 25 per page, newest first.

| Query | Description |
|---|---|
| `status` | `pending`, `active` or `suspended` |
| `search` | name (partial), exact code, or student/parent phone (any format) |
| `page` | page number |

```
GET /api/students?status=pending&search=01202222222&page=1
```

**200 OK**

```json
{
  "data": [ { "id": 3, "code": "ST-1003", "name": "Omar Ali", "...": "..." } ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "per_page": 25, "total": 1, "...": "..." }
}
```

### POST `/students`

Permission: `students.create`. Everything runs in one DB transaction.

| Field | Rules |
|---|---|
| `name` | required, max 255 |
| `phone` | student's own mobile, optional, must not be used by another account |
| `parent_phone` | parent mobile, optional |
| `parent_name` | optional (default: `ولي أمر {student name}`) |
| `parent_relation` | optional: `father`, `mother`, `guardian` |
| `notes` | optional, max 2000 (visible to staff only) |

At least one of `phone` / `parent_phone` is required.

**Account rules**

| Case | Result |
|---|---|
| Student has own `phone` | A **student** account is created (login by phone or code) |
| No student `phone` | No student account |
| `parent_phone` belongs to an existing **parent** | The student is **linked** to that parent; no new password |
| `parent_phone` is new | A **parent** account is created |
| `phone` == `parent_phone` | **Only** the parent account is created/linked |
| `parent_phone` belongs to a non-parent (assistant, student...) | `422`, error on `parent_phone`, nothing saved |

New accounts get temporary passwords (`must_change_password = true`) that are returned **only once**, in this response.

```json
{
  "name": "Omar Ali",
  "phone": "+201001111111",
  "parent_phone": "01202222222",
  "parent_name": "Ali Hassan",
  "parent_relation": "father",
  "notes": "Group A"
}
```

**201 Created**

```json
{
  "data": {
    "id": 3,
    "code": "ST-1003",
    "name": "Omar Ali",
    "status": "active",
    "status_label": "نشط",
    "notes": "Group A",
    "account": { "id": 5, "phone": "01001111111", "is_active": true },
    "parents": [
      { "id": 6, "name": "Ali Hassan", "phone": "01202222222", "relation": "father" }
    ],
    "created_at": "2026-11-08T13:20:00+00:00"
  },
  "credentials": [
    { "role": "student", "name": "Omar Ali", "phone": "01001111111", "password": "x8kq2mpt7a", "code": "ST-1003" },
    { "role": "parent", "name": "Ali Hassan", "phone": "01202222222", "password": "h3rz9cwn4e" }
  ]
}
```

`credentials` is `[]` when no new account was created (for example, a second child of an existing parent).

### GET `/students/{id}`

Allowed for staff with `students.view`, **the student themself**, or **one of their parents**. Others get `403 FORBIDDEN`.
`notes` is hidden from students and parents.

**200 OK**: `{ "data": { ...student } }`

### PATCH `/students/{id}`

Permission: `students.edit`. All fields optional.

| Field | Rules |
|---|---|
| `name` | string, max 255 |
| `status` | `active` or `suspended` (pending students use approve) |
| `notes` | string or null |

```json
{ "status": "suspended" }
```

**200 OK**: `{ "data": { ...student } }`

### POST `/students/{id}/regenerate-qr`

Permission: `students.edit`. Invalidates the old QR card and returns a new token (48 chars).

```json
{ "data": { "qr_token": "J7k2...48 chars" } }
```

### POST `/students/{id}/approve`

Permission: `students.approve`. Only for `pending` students (otherwise `409 STUDENT_NOT_PENDING`).

Activates the student and creates/links the accounts from the registration data, using the same rules as `POST /students`. Temporary passwords are returned to the approving staff member **once**.

**200 OK**

```json
{
  "data": { "id": 9, "code": "ST-1009", "status": "active", "...": "..." },
  "credentials": [
    { "role": "student", "name": "...", "phone": "01001111111", "password": "...", "code": "ST-1009" }
  ]
}
```

### POST `/students/{id}/reject`

Permission: `students.approve`. Only for `pending` students. The record is soft-deleted and the reason is kept in the activity log.

```json
{ "reason": "Duplicate registration" }
```

**200 OK**: `{ "message": "تم رفض طلب التسجيل." }`

---

## Self-registration

### POST `/register`

Public. Available **only when `FEATURE_SELF_REGISTRATION=true`**, otherwise `404 FEATURE_DISABLED`. Rate limit: 5/min per IP.

Same fields and rules as [`POST /students`](#post-students).

```json
{
  "name": "Mona Samir",
  "phone": "01001111111",
  "parent_phone": "01202222222",
  "parent_relation": "mother"
}
```

**201 Created**

```json
{
  "message": "تم استلام طلب التسجيل، وسيتم التواصل معك بعد مراجعته.",
  "data": { "id": 9, "code": "ST-1009", "name": "Mona Samir", "status": "pending" }
}
```

The student is created with status `pending`, and **no accounts are created yet**. Staff see the submitted phones in `registration_data` (`GET /students?status=pending`). Accounts are created on [approve](#post-studentsidapprove), so an anonymous request can never create a login or attach itself to an existing parent.

---

## Response objects

### User

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `name` | string | |
| `phone` | string\|null | `01XXXXXXXXX` |
| `is_active` | bool | |
| `must_change_password` | bool | |
| `last_login_at` | ISO date\|null | |
| `roles` | string[] | when loaded |
| `permissions` | string[] | when loaded |
| `student` | Student\|null | for student accounts (`/auth/*`) |
| `children` | Student[] | for parents (`/auth/*`) |
| `created_at` | ISO date | |

### Student

| Field | Type | Notes |
|---|---|---|
| `id` | int | |
| `code` | string | e.g. `ST-1001` |
| `name` | string | |
| `status` | string | `pending` \| `active` \| `suspended` |
| `status_label` | string | Arabic label |
| `relation` | string\|null | only inside a parent's `children` |
| `notes` | string\|null | staff only |
| `registration_data` | object | staff only, pending students only |
| `account` | object\|null | `{id, phone, is_active}`, the student's own login |
| `parents` | array | `[{id, name, phone, relation}]` |
| `created_at` | ISO date | |

The QR token is never included in normal responses. It is only returned by `regenerate-qr`.

---

## Activity log

Stored in the `activity_log` table (who did what, with `causer_id`):

| Log | Events |
|---|---|
| `auth` | `login`, `logout`, `login_failed`, `login_blocked`, `password_changed` |
| `users` | `created` / `updated` (name, phone, is_active, must_change_password), `assistant_created`, `permissions_updated` (old/new), `password_reset` |
| `students` | `created` / `updated` (name, code, status, notes...), `parent_linked`, `self_registered`, `rejected` |

---

## Testing

```powershell
php artisan test --compact        # or: php vendor/bin/pest
php vendor/bin/pint --format agent  # code style
php artisan route:list --path=api
```

The tests use an in-memory SQLite database and never touch the real one.
