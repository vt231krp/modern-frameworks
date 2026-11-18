# Gym API — Sports Club Management (Symfony)

REST API for a sports club built with **Symfony 7.4**, **Doctrine ORM**, **JWT authentication** and role-based access control. It covers trainers, members, membership plans, subscriptions, payments, rooms, equipment, group classes, registrations and attendance records.

This is a feature-for-feature port of the Laravel API in `../laravel`: same endpoints, request validation, response shape, business rules, roles, demo data and test scenarios.

## Tech stack

- PHP 8.2+ / Symfony 7.4
- Doctrine ORM 3 + Doctrine Migrations
- SQLite by default (MySQL 8 is supported)
- JWT via `lexik/jwt-authentication-bundle`
- PHPUnit 11 (file-backed SQLite test database)
- Symfony Security (Lexik JWT authenticator, voter-based authorization)

## Requirements

- PHP 8.2+ with `pdo_sqlite` (or `pdo_mysql`), `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`
- Composer 2
- MySQL 8 / MariaDB (optional)

## Installation

```bash
cd symfony
composer install
php bin/console doctrine:migrations:migrate
php bin/console app:seed
```

The default `.env` is ready for local development:

- `DATABASE_URL` points to `var/data_dev.db` (SQLite).
- `JWT_SECRET_KEY` is a development-only HS256 secret. Replace it with a strong random value in production.

### Database

**SQLite (default):**

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/data_dev.db"
```

To recreate the database from scratch:

```bash
rm -f var/data_dev.db
php bin/console doctrine:migrations:migrate
php bin/console app:seed
```

**MySQL (optional):**

```sql
CREATE DATABASE symfony_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'symfony_user'@'127.0.0.1' IDENTIFIED BY 'password123';
GRANT ALL PRIVILEGES ON symfony_db.* TO 'symfony_user'@'127.0.0.1';
FLUSH PRIVILEGES;
```

```env
DATABASE_URL="mysql://symfony_user:password123@127.0.0.1:3306/symfony_db?serverVersion=8.0&charset=utf8mb4"
```

The bundled migration is generated for SQLite. With MySQL, create the schema directly from the Doctrine mappings (or regenerate the migration for your platform) before seeding:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:schema:create
php bin/console app:seed
```

The seeder is idempotent and populates every domain table with demo data.

## Running

```bash
php -S 127.0.0.1:8001 -t public
# or, with the Symfony CLI:
symfony serve --port=8001
```

The API is available at `http://127.0.0.1:8001/api`.

### Demo accounts

| Email              | Role    | Password   |
| ------------------ | ------- | ---------- |
| `admin@gym.test`   | admin   | `password` |
| `manager@gym.test` | manager | `password` |
| `client@gym.test`  | client  | `password` |

### Authenticate and call the API

```bash
TOKEN=$(curl -s -X POST http://127.0.0.1:8001/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@gym.test","password":"password"}' \
  | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["access_token"];')

curl http://127.0.0.1:8001/api/coaches \
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
| `revoked_tokens`   | JWT blacklist used by refresh/logout              |

See [PROJECT.md](PROJECT.md) for the full domain reference and business rules.

## Roles and permissions

| Role      | Permissions                                                                                             |
| --------- | ------------------------------------------------------------------------------------------------------- |
| `client`  | Own profile, own registrations and attendance; can view public schedules and plans                       |
| `manager` | Manage coaches, rooms, equipment, plans, subscriptions, payments, classes, registrations and attendance |
| `admin`   | Everything above plus users, role changes and deletions                                                 |

Permissions are enforced by `App\Security\GymVoter` (the Symfony equivalent of the Laravel policies) and `#[IsGranted('ROLE_ADMIN')]` on the users controller. Admins pass every voter check, replicating `Gate::before`.

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

Paginated responses contain `data`, `current_page`, `per_page`, `total`, `last_page` and navigation URLs, matching Laravel's `LengthAwarePaginator` payload.

## Business rules

- A subscription created with `plan_id` takes its `price` and `end_date` from the plan (`start_date + duration_days`) unless explicit values are provided.
- Inactive membership plans cannot be used for new subscriptions.
- A class capacity cannot exceed the capacity of its room; a room cannot be shrunk below its largest scheduled class.
- Attendance can only be recorded for a member who is registered for the class.
- A registration cannot be duplicated, and it respects class capacity.
- A client can only register for, view or update their own member profile and registrations.
- Pending payments with a past `payment_date` are flipped to `overdue` by the daily cron.

## Authentication

- `POST /api/auth/register` creates a client account plus its member profile and returns a JWT.
- `POST /api/auth/login` authenticates by email/password and returns a JWT.
- Send `Authorization: Bearer <token>` with every protected request.
- `POST /api/auth/refresh` rotates the token and blacklists the old one.
- `POST /api/auth/logout` invalidates the current token.
- Tokens live for 3600 seconds (`jwt_ttl` in `config/services.yaml`).
- Revoked token identifiers are stored in `revoked_tokens`; `JwtDecodedListener` rejects them.

The development configuration uses HS256 with a shared secret so no keypair generation is required. For production, override `JWT_SECRET_KEY`/`JWT_PUBLIC_KEY` with strong values or switch the encoder to RS256 and generate keys with `php bin/console lexik:jwt:generate-keypair`.

## Scheduled tasks

| Command | Schedule | Purpose |
|---|---|---|
| `payments:mark-overdue` | Daily at 00:10 | Flips past-due `pending` payments to `overdue` |

Run it manually:

```bash
php bin/console payments:mark-overdue
```

Production crontab:

```cron
10 0 * * * cd /path/to/symfony && php bin/console payments:mark-overdue >> /dev/null 2>&1
```

## Tests

```bash
vendor/bin/phpunit
```

The suite uses a file-backed SQLite database (`var/data_test.db`) that is recreated for every test, so no external database is required. Current coverage: **20 tests, 147 assertions** across auth, roles, CRUD, filtering, pagination, domain rules and the cron command.

## Postman collection

Import `Gym_API.postman_collection.json` into Postman.

1. Start the API with `php -S 127.0.0.1:8001 -t public`.
2. Run **Auth → Login as Admin**; the test script saves the JWT to `{{token}}`.
3. Create resources in dependency order: rooms → coaches → plans → members → classes → subscriptions → payments → registrations → attendances.
4. Create requests store the new ID in collection variables, so follow-up requests need no manual edits.

The collection includes all CRUD endpoints, filter examples, pagination examples and test scripts.

## Project structure

```
src/
├── Command/                 # app:seed, payments:mark-overdue
├── Controller/Api/          # thin controllers (validation + authorization)
├── Entity/                  # Doctrine entities and their JSON representation
├── Enum/                    # Role, payment/attendance/equipment enums
├── EventSubscriber/         # JSON exception rendering
├── Repository/              # Doctrine repositories
├── Security/                # JWT listeners, voter, entry point, blacklist store
├── Service/                 # business logic, query filtering, pagination
├── Util/                    # date and decimal formatting
└── Validation/              # Laravel-style request validator
config/
├── packages/                # doctrine, security, lexik_jwt_authentication, ...
└── routes.yaml
migrations/                  # schema migration
tests/                       # API feature tests
```

## Differences from the Laravel implementation

- Laravel policies are implemented as a single Symfony voter (`GymVoter`) instead of one class per model.
- Laravel service providers/middleware are replaced by Symfony DI, firewall configuration and the `jwt` authenticator.
- JWT-blacklist state is stored in the `revoked_tokens` table instead of the cache.
- The Laravel scheduler is replaced by a plain console command plus a cron entry.
- Validation rules are evaluated by `App\Validation\RequestValidator`, which implements the subset of Laravel rules used by this API and returns the same `422 {message, errors}` shape.
