<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

# Reservation System API

A Laravel-based REST API for managing resource reservations with capacity control, availability checking, expiration, idempotency, concurrency protection, and reservation history.

The system is designed to prevent overbooking even when multiple requests attempt to reserve the same resource concurrently.

---

## Features

* Create reservations
* Confirm reservations
* Cancel reservations
* Update reservation units and time
* Check resource availability
* Resource capacity management
* Automatic reservation expiration after 2 minutes
* Overbooking prevention
* Idempotent write operations
* Concurrent request protection
* Reservation history and audit trail
* User and Admin roles
* Database transactions
* Row-level database locking
* Request validation using Laravel Form Requests

---

# Business Rules

## Users

The system supports two roles:

| Role  | Permissions                                                       |
| ----- | ----------------------------------------------------------------- |
| User  | Create reservations                                               |
| Admin | Confirm, cancel, update reservations and manage resource capacity |

Authentication is intentionally kept outside the scope of this assessment.

---

## Reservation Status

Reservations can have one of the following statuses:

```text
pending
confirmed
cancelled
expired
```

### Pending

A newly created reservation starts as `pending`.

It holds capacity for two minutes.

### Confirmed

A pending reservation can be confirmed before it expires.

### Cancelled

Pending and confirmed reservations can be cancelled.

Cancelled reservations no longer consume capacity.

### Expired

A pending reservation automatically becomes expired after two minutes if it has not been confirmed.

Expired reservations no longer consume capacity.

---

# Reservation Time Interval

Reservations use a half-open interval:

 [start_time, end_time)

For example:

Reservation A: 10:00 - 11:00
Reservation B: 11:00 - 12:00

These reservations do not overlap because Reservation A ends exactly when Reservation B starts.

---

# Capacity Management

Each resource has a maximum capacity.

Example:

Resource capacity = 10

If the following reservations exist:

Reservation A
10:00 - 11:00
6 units

Reservation B
10:30 - 12:00
4 units

The peak usage between `10:30` and `11:00` is:

6 + 4 = 10 units

Therefore the resource is fully occupied during that period.

A new reservation is accepted only when:

peak usage + requested units <= resource capacity

---

# Peak Capacity Calculation

The system does not simply sum all overlapping reservations.

Instead, it uses a **sweep-line algorithm** to calculate the maximum simultaneous usage.

This is important because reservations can overlap partially.

Example:

A: 10:00 ───── 11:00
   6 units

B:      10:30 ───────── 12:00
        4 units

Peak usage:

10:00 - 10:30 → 6
10:30 - 11:00 → 10
11:00 - 12:00 → 4

Therefore:

Peak = 10

---

# Concurrency Protection

The system must prevent overbooking when multiple backend instances receive reservation requests at the same time.

Reservation writes use a database transaction and lock the resource row:

```php
$resource = Resource::query()
    ->lockForUpdate()
    ->findOrFail($resourceId);
```

The flow is:

```text
Request
   ↓
Database Transaction
   ↓
Lock Resource Row
   ↓
Expire Old Reservations
   ↓
Calculate Peak Usage
   ↓
Validate Capacity
   ↓
Create / Update Reservation
   ↓
Create Reservation History
   ↓
Commit Transaction
```

Because all application instances share the same MySQL database, the row-level lock coordinates concurrent requests across backend instances.

---

# Idempotency

Every write request requires an:

```text
Idempotency-Key
```

Example:

```http
Idempotency-Key: reservation-create-001
```

The same key can only represent the same request.

### Same Key + Same Request

Sending the same request again returns the previously stored response.

No duplicate reservation is created.

### Same Key + Different Request

The API rejects the request.

Example:

```json
{
    "message": "The Idempotency-Key has already been used with a different request."
}
```

The idempotency record stores:

* Request method
* Request path
* Request hash
* Response status
* Response body

This allows the API to safely handle retries.

---

# Reservation Expiration

Pending reservations expire two minutes after creation.

Example:

```text
Created:
22:43:37

Expires:
22:45:37
```

When an expired pending reservation is encountered, the system changes:

```text
pending → expired
```

and records the change in `reservation_histories`.

Expiration is handled by business logic and does not depend exclusively on a scheduler.

This means a server restart does not lose expiration information because the expiration timestamp is persisted in the database.

---

# Reservation History

Every reservation change is recorded in:

```text
reservation_histories
```

Supported actions:

```text
created
confirmed
cancelled
expired
updated
```

Each history record stores:

```text
old_data
new_data
```

Example:

```json
{
    "reservation_id": 1,
    "action": "confirmed",
    "old_data": {
        "status": "pending"
    },
    "new_data": {
        "status": "confirmed"
    }
}
```

This provides an audit trail for reservation changes.

---

# Capacity Updates

Admins can change resource capacity.

However, the capacity cannot be reduced below the current peak usage.

Example:

```text
Current peak usage = 8
```

This is valid:

```text
New capacity = 10
```

This is also valid:

```text
New capacity = 8
```

But this is rejected:

```text
New capacity = 7
```

The system validates capacity against the **peak simultaneous usage**, not the total number of units across all reservations.

---

# Database Structure

The main tables are:

```text
users
resources
reservations
reservation_histories
idempotency_keys
```

## Users

```text
id
name
email
password
role
created_at
updated_at
```

Roles:

```text
user
admin
```

---

## Resources

```text
id
created_by
name
capacity
created_at
updated_at
```

`created_by` references the admin who created the resource.

---

## Reservations

```text
id
user_id
resource_id
units
start_time
end_time
status
expires_at
created_at
updated_at
```

---

## Reservation Histories

```text
id
reservation_id
action
old_data
new_data
created_at
```

---

## Idempotency Keys

```text
id
key
request_method
request_path
request_hash
response_status
response_body
created_at
updated_at
```

---

# Project Structure

```text
app/
├── Enum/
│   ├── ReservationAction.php
│   ├── ReservationStatus.php
│   └── UserRole.php
│
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AvailabilityController.php
│   │       ├── ReservationController.php
│   │       └── ResourceController.php
│   │
│   ├── Middleware/
│   │   ├── RequireIdempotencyKey.php
│   │   └── SetCurrentUser.php
│   │
│   └── Requests/
│       ├── Reservation/
│       │   ├── StoreReservationRequest.php
│       │   └── UpdateReservationRequest.php
│       │
│       └── Resource/
│           └── UpdateResourceCapacityRequest.php
│
├── Models/
│   ├── IdempotencyKey.php
│   ├── Reservation.php
│   ├── ReservationHistory.php
│   ├── Resource.php
│   └── User.php
│
└── Services/
    ├── AvailabilityService.php
    ├── CapacityService.php
    ├── IdempotencyService.php
    ├── ReservationExpirationService.php
    ├── ReservationService.php
    └── ResourceService.php

database/
└── migrations/

routes/
└── api.php
```

---

# Installation

## Requirements

Make sure the following are installed:

* PHP 8.3+
* Composer
* MySQL
* Laravel
* Postman

---

## Clone the Project

```bash
git clone <repository-url>
```

Navigate to the project:

```bash
cd reservation-system
```

Install dependencies:

```bash
composer install
```

---

# Environment Configuration

Create the environment file:

```bash
cp .env.example .env
```

On Windows PowerShell:

```powershell
copy .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure the database in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=reservation_db
DB_USERNAME=root
DB_PASSWORD=
```

Create the database:

```text
reservation_db
```

Run migrations and seed the database:

```bash
php artisan migrate:fresh --seed
```

---

# Seeded Data

The database seeder creates:

### Admin

```text
ID: 1
Email: admin@example.com
Role: admin
```

### User

```text
ID: 2
Email: user@example.com
Role: user
```

### Second User

```text
ID: 3
Email: user2@example.com
Role: user
```

### Resource

```text
ID: 1
Name: Main Cinema Hall
Capacity: 10
Created By: Admin #1
```

---

# Running the Application

Start the Laravel development server:

```bash
php artisan serve
```

The API will be available at:

```text
http://127.0.0.1:8000
```

---

# API Endpoints

## Reservations

### Create Reservation

```http
POST /api/reservations
```

Headers:

```http
Accept: application/json
Content-Type: application/json
Idempotency-Key: reservation-create-001
X-User-Id: 2
```

Request:

```json
{
    "user_id": 2,
    "resource_id": 1,
    "units": 6,
    "start_time": "2026-09-26 10:00:00",
    "end_time": "2026-09-26 11:00:00"
}
```

---

### Get Reservation

```http
GET /api/reservations/{reservation}
```

Example:

```http
GET /api/reservations/1
```

---

### Confirm Reservation

```http
POST /api/reservations/{reservation}/confirm
```

Headers:

```http
Idempotency-Key: reservation-confirm-001
X-User-Id: 1
```

---

### Cancel Reservation

```http
POST /api/reservations/{reservation}/cancel
```

Headers:

```http
Idempotency-Key: reservation-cancel-001
X-User-Id: 1
```

---

### Update Reservation

```http
PUT /api/reservations/{reservation}
```

Headers:

```http
Idempotency-Key: reservation-update-001
X-User-Id: 1
```

Request:

```json
{
    "units": 4,
    "start_time": "2026-09-26 11:00:00",
    "end_time": "2026-09-26 12:00:00"
}
```

---

# Availability

### Check Availability

```http
GET /api/resources/{resource}/availability
```

Example:

```http
GET /api/resources/1/availability?units=2&start_time=2026-09-26%2010:30:00&end_time=2026-09-26%2011:00:00
```

Example response:

```json
{
    "success": true,
    "message": "Availability checked successfully.",
    "data": {
        "resource_id": 1,
        "resource_name": "Main Cinema Hall",
        "capacity": 10,
        "requested_units": 2,
        "peak_reserved_units": 4,
        "available_units": 6,
        "is_available": true,
        "start_time": "2026-09-26T10:30:00.000000Z",
        "end_time": "2026-09-26T11:00:00.000000Z"
    }
}
```

---

# Resource Management

## Update Resource Capacity

```http
PUT /api/resources/{resource}/capacity
```

Headers:

```http
Accept: application/json
Content-Type: application/json
X-User-Id: 1
```

Request:

```json
{
    "capacity": 5
}
```

Only administrators can update resource capacity.

---

# Postman Testing Scenarios

The following scenarios were used to validate the main business rules.

## 1. Create Reservation

Create:

```text
6 units
10:00 → 11:00
```

Expected:

```text
201 Created
```

---

## 2. Idempotency

Send the same create request twice with:

```text
Idempotency-Key: reservation-create-001
```

Expected:

* Only one reservation is created.
* The second request returns the stored response.

---

## 3. Idempotency Conflict

Reuse the same key with different request data.

Expected:

```text
422 Unprocessable Entity
```

with:

```text
The Idempotency-Key has already been used with a different request.
```

---

## 4. Overbooking

Existing:

```text
4 units
```

New request:

```text
7 units
```

Same overlapping interval.

Result:

```text
4 + 7 = 11
```

Capacity:

```text
10
```

Expected:

```text
422 Unprocessable Entity
```

---

## 5. Reservation Expiration

Create a pending reservation and wait more than two minutes.

Expected:

```text
pending → expired
```

The reservation should no longer consume capacity.

---

## 6. Confirm Reservation

Confirm a pending reservation before expiration.

Expected:

```text
pending → confirmed
```

---

## 7. Cancel Reservation

Cancel a pending or confirmed reservation.

Expected:

```text
pending/confirmed → cancelled
```

The reservation releases its capacity.

---

## 8. Update Reservation

Change units and/or reservation time.

The system recalculates capacity while excluding the reservation being updated.

---

## 9. Availability

Check the available capacity for a specific time interval without creating a reservation.

---

## 10. Capacity Reduction

Attempt to reduce resource capacity below the current peak usage.

Expected:

```text
422 Unprocessable Entity
```

The capacity must not be changed.

---

# Error Handling

Validation errors return:

```text
422 Unprocessable Entity
```

Example:

```json
{
    "message": "The requested units exceed the resource capacity.",
    "errors": {
        "units": [
            "The requested units exceed the resource capacity."
        ]
    }
}
```

Missing idempotency key:

```json
{
    "success": false,
    "message": "Idempotency-Key header is required."
}
```

Idempotency conflict:

```json
{
    "message": "The Idempotency-Key has already been used with a different request.",
    "errors": {
        "Idempotency-Key": [
            "The Idempotency-Key has already been used with a different request."
        ]
    }
}
```

---

# Important Design Decisions

## 1. Service Layer

Business logic is separated from controllers.

Controllers are responsible mainly for:

* Receiving requests
* Validating input
* Calling services
* Returning responses

Business rules are handled by services.

---

## 2. Resource Row Locking

All capacity-sensitive reservation writes lock the resource row using:

```php
lockForUpdate()
```

This prevents concurrent requests from both seeing the same available capacity.

---

## 3. Sweep-Line Capacity Calculation

A sweep-line algorithm is used instead of simply summing overlapping reservations.

This correctly handles partially overlapping intervals.

---

## 4. Database Transactions

Capacity validation and reservation creation/update happen inside the same transaction.

This prevents another request from changing the resource state between the capacity check and the reservation write.

---

## 5. Idempotency

Idempotency is implemented at the database level using a unique idempotency key and stored response.

This allows safe retries and protects against duplicate writes.

---

## 6. Expiration

Reservation expiration is stored in the database using `expires_at`.

Business operations process expired pending reservations before performing capacity-sensitive operations.

This means expiration does not depend exclusively on an in-memory timer or running application process.

---

## 7. Reservation History

Reservation changes are stored separately in `reservation_histories`.

This provides an audit trail for:

```text
created
confirmed
cancelled
expired
updated
```

---

# Development Commands

Run migrations:

```bash
php artisan migrate
```

Reset and seed the database:

```bash
php artisan migrate:fresh --seed
```

Start the development server:

```bash
php artisan serve
```

Clear application cache:

```bash
php artisan optimize:clear
```

---



