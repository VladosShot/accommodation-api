# Accommodation API

REST API for asynchronous accommodation offer imports, property search and offer reservations.

## Requirements

- PHP 8.2+
- Composer
- Docker
- Docker Compose

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/VladosShot/accommodation-api.git
cd accommodation-api
```

### 2. Prepare Laravel directories

Some runtime directories are intentionally excluded from Git.

On Linux/macOS:

```bash
mkdir -p bootstrap/cache
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p tests/Unit
```

On Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force -Path `
    bootstrap/cache, `
    storage/framework/cache, `
    storage/framework/sessions, `
    storage/framework/views, `
    storage/logs, `
    tests/Unit
```

### 3. Install PHP dependencies

```bash
composer install
```

### 4. Configure environment

Copy the example environment file:

```bash
cp .env.example .env
```

The project is configured to use the MySQL and Redis services provided by Docker Compose.

Default database configuration:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=accommodation
DB_USERNAME=laravel
DB_PASSWORD=laravel
```

Redis configuration:

```env
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 5. Generate application key

```bash
php artisan key:generate
```

### 6. Start Docker services

```bash
docker compose up -d
```

This starts:

- MySQL 8.0
- Redis

Check service status:

```bash
docker compose ps
```

### 7. Run migrations and seeders

```bash
php artisan migrate --seed
```

The database seeder creates two suppliers:

- `supplier-a`
- `supplier-b`

It also creates the following sample properties:

- `BCN-0001` — Hotel Barcelona Center, Barcelona
- `BCN-0002` — Barcelona Apartments, Barcelona
- `MAD-0001` — Madrid Grand Hotel, Madrid

## Queue Worker

Imports are processed asynchronously using Laravel queues with Redis.

Start a queue worker:

```bash
php artisan queue:work
```

Keep the queue worker running while testing imports.

## Running Tests

Run the full test suite:

```bash
php artisan test
```

The test suite covers:

- import creation and validation;
- import idempotency;
- asynchronous import processing;
- updating existing offers;
- property search;
- selecting the cheapest available offer;
- excluding unavailable and expired offers;
- successful reservations;
- reservations when no units are available;
- expired offers.

## API

### Create Import

```http
POST /api/imports
Content-Type: application/json
```

Example request:

```json
{
    "supplier": "supplier-a",
    "external_import_id": "import-001",
    "sent_at": "2026-09-10T10:00:00Z",
    "offers": [
        {
            "external_id": "offer-001",
            "property": {
                "code": "BCN-0001",
                "name": "Hotel Barcelona Center",
                "city": "Barcelona"
            },
            "check_in": "2026-10-01",
            "check_out": "2026-10-05",
            "max_guests": 2,
            "price": 72500,
            "currency": "EUR",
            "available_units": 3,
            "expires_at": "2026-09-15T12:00:00Z"
        }
    ]
}
```

The endpoint immediately returns `202 Accepted`:

```json
{
    "data": {
        "id": 1,
        "status": "pending",
        "total_offers": 1
    }
}
```

The actual offer processing is performed asynchronously by `ProcessImportJob`.

### Get Import Status

```http
GET /api/imports/{import}
```

Returns the current import state, including:

- supplier;
- external import ID;
- status;
- total offers;
- processed offers;
- error;
- creation and completion timestamps.

Possible import statuses:

- `pending`
- `processing`
- `completed`
- `failed`

### Search Properties

```http
GET /api/properties
```

Required parameters:

```text
check_in
check_out
guests
```

Optional parameter:

```text
city
```

Example:

```text
GET /api/properties?city=Barcelona&check_in=2026-10-01&check_out=2026-10-05&guests=2
```

The search returns only offers that:

- match the requested dates;
- support the requested number of guests;
- have available units;
- have not expired;
- match the requested city, when provided.

For each property, only the cheapest valid offer is returned.

The cheapest offer is selected at the database level, and the results are paginated.

### Reserve Offer

```http
POST /api/offers/{offer}/reservations
Content-Type: application/json
```

Example:

```json
{
    "client_reference": "booking-001",
    "customer_name": "John Doe",
    "customer_email": "john@example.com"
}
```

Successful reservation returns `201 Created`.

If the offer has no available units or has expired, the API returns `409 Conflict`.

## Import Idempotency

Imports are identified by the combination of:

```text
supplier + external_import_id
```

This combination is unique in the database.

If the same import is submitted more than once:

- a new import is not created;
- the job is not dispatched again;
- the existing import ID and current status are returned.

Offers are similarly identified by:

```text
supplier + external_id
```

If an offer with the same external ID already exists for the supplier, it is updated instead of duplicated.

Properties are identified by their external property code.

## Reservation Concurrency

Reservations are performed inside a database transaction.

Before decreasing the number of available units, the offer row is locked using `SELECT ... FOR UPDATE` (`lockForUpdate()`).

This ensures that concurrent transactions cannot reserve the same last available unit.

The availability is checked again after acquiring the row lock. If no units remain, the reservation is rejected with `409 Conflict`.

## Price Representation

Offer prices are stored as integers in the smallest currency unit.

For example:

```text
72500 EUR = €725.00
```

Using integers avoids floating-point precision problems when working with monetary values.

## Project Structure

The project follows a conventional Laravel structure:

- `app/Http/Controllers` — API controllers
- `app/Http/Requests` — request validation
- `app/Http/Resources` — API response resources
- `app/Jobs` — asynchronous import processing
- `app/Models` — Eloquent models
- `app/Enums` — import status enum
- `database/migrations` — database schema
- `database/seeders` — initial test data
- `tests/Feature` — API and business-flow tests

The implementation intentionally follows standard Laravel patterns without introducing unnecessary architectural layers.
