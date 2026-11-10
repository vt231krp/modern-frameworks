# Domain Reference — Gym API

This document describes the data model of the sports club, the meaning of every table and the business rules enforced by the API. For installation and run instructions see [README.md](README.md).

## Entity relationships

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

## Tables

### `users`
Authentication accounts and roles.

| Column | Notes |
|---|---|
| `id` | Primary key |
| `name`, `email`, `password` | Credentials |
| `role` | `admin`, `manager` or `client` (indexed) |

### `coaches`
Club trainers.

| Column | Notes |
|---|---|
| `coach_id` | Primary key |
| `name`, `phone` | Trainer details |

### `rooms`
Training rooms.

| Column | Notes |
|---|---|
| `room_id` | Primary key |
| `name` | Unique room name |
| `capacity` | Maximum number of people |
| `description` | Optional notes |

### `equipment`
Inventory assigned to a room.

| Column | Notes |
|---|---|
| `equipment_id` | Primary key |
| `name`, `quantity` | Item details |
| `condition` | `excellent`, `good`, `fair` or `broken` |
| `room_id` | Nullable FK → `rooms`, `nullOnDelete` |

### `members`
Club members. Client accounts get a linked member profile automatically.

| Column | Notes |
|---|---|
| `member_id` | Primary key |
| `user_id` | Nullable unique FK → `users`, `nullOnDelete` |
| `name`, `phone` | Member details |
| `join_date` | Defaults to the current date |

### `membership_plans`
Catalog of subscription plans.

| Column | Notes |
|---|---|
| `plan_id` | Primary key |
| `name` | Plan name |
| `duration_days` | Used to calculate the subscription end date |
| `price` | Used as the default subscription price |
| `description` | Optional text |
| `is_active` | Inactive plans cannot be used for new subscriptions |

### `subscriptions`
Member subscriptions.

| Column | Notes |
|---|---|
| `sub_id` | Primary key |
| `member_id` | FK → `members`, cascade delete |
| `plan_id` | Nullable FK → `membership_plans`, `nullOnDelete` |
| `start_date`, `end_date` | Validity period |
| `price` | Amount charged for the subscription |

### `payments`
Payments for subscriptions.

| Column | Notes |
|---|---|
| `payment_id` | Primary key |
| `sub_id` | FK → `subscriptions`, cascade delete |
| `amount`, `payment_date` | Payment details |
| `method` | `cash`, `card`, `online` or `transfer` |
| `status` | `pending`, `completed`, `failed` or `overdue` |

### `classes`
Group classes.

| Column | Notes |
|---|---|
| `class_id` | Primary key |
| `title` | Class name |
| `coach_id` | Nullable FK → `coaches` |
| `room_id` | Nullable FK → `rooms`, `nullOnDelete` |
| `class_time` | Date and time |
| `capacity` | Must not exceed the room capacity |

### `registrations`
Member registration for a class. Composite primary key `(member_id, class_id)`.

| Column | Notes |
|---|---|
| `member_id` | FK → `members`, cascade delete |
| `class_id` | FK → `classes`, cascade delete |

### `attendances`
Actual visits of registered members.

| Column | Notes |
|---|---|
| `attendance_id` | Primary key |
| `member_id` | FK → `members`, cascade delete |
| `class_id` | FK → `classes`, cascade delete |
| `status` | `present`, `absent` or `late` |
| `checked_in_at` | Set automatically for present/late |
| `notes` | Optional comment |

Unique constraint: `(member_id, class_id)` — one attendance record per registration.

## Business rules

1. **Plan-driven subscriptions.** When `plan_id` is provided, `price` defaults to the plan price and `end_date` defaults to `start_date + duration_days`. Explicit request values take precedence.
2. **Inactive plans.** A plan with `is_active = false` cannot be used for a new subscription. Existing subscriptions keep working.
3. **Room capacity.** A class capacity cannot exceed its room capacity. A room capacity cannot be reduced below the largest class already scheduled in it.
4. **Registration integrity.** A member cannot be registered for the same class twice, and registrations respect class capacity.
5. **Attendance integrity.** Attendance can only be recorded for a member registered for the class. Duplicate attendance is rejected.
6. **Payments.** New payments default to `pending`. The daily cron `payments:mark-overdue` changes pending payments with a past `payment_date` to `overdue`.
7. **Ownership.** A client can view/update only their own member profile and can register only their own member profile for classes.

## Roles

| Role | Access |
|---|---|
| `client` | Own profile, own subscriptions/registrations/attendance; read-only access to schedules and plans |
| `manager` | Manage coaches, members, rooms, equipment, plans, subscriptions, payments, classes, registrations and attendance |
| `admin` | All manager permissions plus users, role changes and deletions |

Policies live in `app/Policies`. Admins bypass all checks via `Gate::before` in `AppServiceProvider`.

## API map

| Resource | Endpoints |
|---|---|
| Auth | `POST /api/auth/register`, `POST /api/auth/login`, `GET /api/auth/me`, `POST /api/auth/refresh`, `POST /api/auth/logout` |
| Users | `GET/POST /api/users`, `GET/PUT/DELETE /api/users/{id}`, `PATCH /api/users/{id}/role` |
| Coaches | Full CRUD on `/api/coaches` |
| Members | Full CRUD on `/api/members` |
| Plans | Full CRUD on `/api/plans` |
| Subscriptions | Full CRUD on `/api/subscriptions` |
| Payments | Full CRUD on `/api/payments` |
| Rooms | Full CRUD on `/api/rooms` |
| Equipment | Full CRUD on `/api/equipment` |
| Classes | Full CRUD on `/api/classes` |
| Registrations | `GET/POST /api/registrations`, `GET/PUT/DELETE /api/registrations/{memberId}/{classId}` |
| Attendances | Full CRUD on `/api/attendances` |

## Query conventions

All `index` endpoints accept:

- `page` — page number
- `per_page` — page size (1–100, default 15)
- `sort` — allowed column (validated per resource)
- `direction` — `asc` or `desc`
- resource-specific filters (documented in the Postman collection and controller validation)

The response is a standard Laravel paginator: `data`, `current_page`, `per_page`, `total`, `last_page` and navigation URLs.

## Authentication

- JWT is issued by `POST /api/auth/login` and `POST /api/auth/register`.
- Send `Authorization: Bearer <token>` with every protected request.
- `POST /api/auth/refresh` rotates the token and blacklists the old one.
- `POST /api/auth/logout` invalidates the current token.

## Scheduled tasks

| Command | Schedule | Purpose |
|---|---|---|
| `payments:mark-overdue` | Daily at 00:10 | Flips past-due `pending` payments to `overdue` |

Defined in `routes/console.php`; run the scheduler with `php artisan schedule:work` locally or `php artisan schedule:run` from cron in production.

## Tests

`tests/Feature` covers authentication, role restrictions, CRUD, filters, pagination, domain invariants (plan/room/attendance) and the cron command. Run:

```bash
vendor/bin/phpunit
```
