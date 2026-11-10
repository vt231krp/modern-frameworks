# Gym API — Sports Club Management

REST API for a sports club built with **Laravel 11**, **JWT authentication** and **role-based access control**. It covers trainers, members, membership plans, subscriptions, payments, rooms, equipment, group classes, registrations and attendance records.

## Tech stack

- PHP 8.2+ / Laravel 11
- MySQL 8 (or SQLite for quick local runs)
- JWT via `php-open-source-saver/jwt-auth`
- PHPUnit 10 (in-memory SQLite)
- Laravel Pint for code style

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2
- MySQL 8 / MariaDB (optional if using SQLite)

## Installation

```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

### Database

**MySQL (recommended):**

```sql
CREATE DATABASE laravel_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'laravel_user'@'127.0.0.1' IDENTIFIED BY 'password123';
GRANT ALL PRIVILEGES ON laravel_db.* TO 'laravel_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

`.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_db
DB_USERNAME=laravel_user
DB_PASSWORD=password123
```

**SQLite (quick local option):**

```env
DB_CONNECTION=sqlite
```

```bash
touch database/database.sqlite
```

### Migrations and seed data

```bash
php artisan migrate
php artisan db:seed
```

Or recreate the database from scratch:

```bash
php artisan migrate:fresh --seed
```

> `migrate:fresh` drops all tables before migrating. Use it only for local development.

The seeder is idempotent and populates every domain table with demo data.

## Running

```bash
php artisan serve
```

The API is available at `http://127.0.0.1:8000/api`.

### Demo accounts

| Email              | Role    | Password   |
| ------------------ | ------- | ---------- |
| `admin@gym.test`   | admin   | `password` |
| `manager@gym.test` | manager | `password` |
| `client@gym.test`  | client  | `password` |

### Authenticate and call the API

```bash
TOKEN=$(curl -s -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@gym.test","password":"password"}' \
  | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["access_token"];')

curl http://127.0.0.1:8000/api/coaches \
  -H "Authorization: Bearer $TOKEN"
```

Send the JWT as `Authorization: Bearer <token>` for every endpoint except `auth/register` and `auth/login`.

## Domain model

```
users ──1:1── members
                 │
                 ├──1:N── subscriptions ──N:1── membership_plans
                 │            │
                 │            └──1:N── payments
                 │
                 ├──1:N── registrations ──N:1── classes ──N:1── coaches
                 │                                  │
                 │                                  ├──N:1── rooms ──1:N── equipment
                 │                                  │
                 └──1:N── attendances ──────────────┘
```

| Table              | Purpose                                           |
| ------------------ | ------------------------------------------------- |
| `users`            | Accounts and roles (`admin`, `manager`, `client`) |
| `coaches`          | Trainers                                          |
| `rooms`            | Club rooms with capacity                          |
| `equipment`        | Equipment assigned to a room                      |
| `members`          | Club members (linked to a client user)            |
| `membership_plans` | Subscription plans: price and duration            |
| `subscriptions`    | Member subscriptions; optional plan reference     |
| `payments`         | Payments for subscriptions                        |
| `classes`          | Group classes with coach, room, time and capacity |
| `registrations`    | Member registrations for classes                  |
| `attendances`      | Actual class visits of registered members         |

See [PROJECT.md](PROJECT.md) for the full domain reference and business rules.

## Roles and permissions

| Role      | Permissions                                                                                             |
| --------- | ------------------------------------------------------------------------------------------------------- |
| `client`  | Own profile, own registrations and attendance; can view public schedules                                |
| `manager` | Manage coaches, rooms, equipment, plans, subscriptions, payments, classes, registrations and attendance |
| `admin`   | Everything above plus users, role changes and deletions                                                 |

Permissions are enforced by Laravel policies (`app/Policies`) and the `role:` middleware. Admins bypass all policy checks through `Gate::before`.

## API overview

| Resource         | Base endpoint        | Notes                                              |
| ---------------- | -------------------- | -------------------------------------------------- |
| Auth             | `/api/auth`          | `register`, `login`, `me`, `refresh`, `logout`     |
| Users            | `/api/users`         | Admin only; `PATCH /api/users/{id}/role`           |
| Coaches          | `/api/coaches`       | Full CRUD                                          |
| Members          | `/api/members`       | Full CRUD; clients see their own profile           |
| Membership plans | `/api/plans`         | Full CRUD                                          |
| Subscriptions    | `/api/subscriptions` | Full CRUD; derives price/end date from plan        |
| Payments         | `/api/payments`      | Full CRUD; daily cron marks overdue                |
| Rooms            | `/api/rooms`         | Full CRUD                                          |
| Equipment        | `/api/equipment`     | Full CRUD                                          |
| Classes          | `/api/classes`       | Full CRUD; capacity bounded by room capacity       |
| Registrations    | `/api/registrations` | `GET/POST`, `GET/PUT/DELETE /{memberId}/{classId}` |
| Attendances      | `/api/attendances`   | Full CRUD; requires an existing registration       |

### Filtering, sorting and pagination

Every `index` endpoint supports:

- `page`, `per_page` (1–100, default 15)
- `sort`, `direction` (`asc` / `desc`)
- Resource-specific filters (see `description` of each Postman request, or the controller validation rules)

Example:

```http
GET /api/classes?room_id=1&available=1&sort=class_time&direction=asc&per_page=10&page=1
```

Paginated responses contain `data`, `current_page`, `per_page`, `total`, `last_page` and navigation URLs.

## Business rules

- A subscription created with `plan_id` takes its `price` and `end_date` from the plan (`start_date + duration_days`) unless explicit values are provided.
- Inactive membership plans cannot be used for new subscriptions.
- A class capacity cannot exceed the capacity of its room; a room cannot be shrunk below its largest scheduled class.
- Attendance can only be recorded for a member who is registered for the class.
- A registration cannot be duplicated, and it respects class capacity.
- A client can only register for, view or update their own member profile and registrations.
- Pending payments with a past `payment_date` are flipped to `overdue` by the daily cron.

## Scheduled tasks

The only scheduled operation is `payments:mark-overdue` (daily at 00:10):

```bash
php artisan payments:mark-overdue      # run once manually
php artisan schedule:list              # inspect the schedule
php artisan schedule:work              # local scheduler loop
```

Production crontab:

```cron
* * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
vendor/bin/phpunit
# or
php artisan test
```

The suite uses in-memory SQLite (`phpunit.xml`) and does not require a running MySQL server. Current coverage: **20 tests, 143 assertions** across auth, roles, CRUD, filtering, pagination, domain rules and the cron command.

## Postman collection

Import `Gym_API.postman_collection.json` into Postman.

1. Start the API with `php artisan serve`.
2. Run **Auth → Login as Admin**; the test script saves the JWT to `{{token}}`.
3. Create resources in dependency order: rooms → coaches → plans → members → classes → subscriptions → payments → registrations → attendances.
4. Create requests store the new ID in collection variables, so follow-up requests need no manual edits.

The collection includes all CRUD endpoints, filter examples, pagination examples and test scripts.

## Project structure

```
app/
├── Console/Commands/        # payments:mark-overdue
├── Enums/Role.php           # admin / manager / client
├── Http/Controllers/        # thin controllers (validation + authorization)
├── Http/Middleware/         # role middleware
├── Models/                  # Eloquent models
├── Policies/                # per-model authorization
├── Services/                # business logic and query filtering
routes/
├── api.php                  # REST API
└── console.php              # schedule definition
database/
├── migrations/              # 14 schema migrations
├── seeders/                 # idempotent demo data
└── factories/
tests/
├── Feature/                 # API, auth, domain and cron tests
└── Unit/
```
