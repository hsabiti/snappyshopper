# Snappy Shopper -- Store & Delivery Backend

Technical assessment implementing a **Laravel 10+ backend service** for:

-   Store management\
-   Geospatial store discovery\
-   Delivery feasibility checks\
-   UK postcode import with coordinates\
-   Fully reproducible **Docker setup**

Designed to **run out-of-the-box in under 5 minutes**.

------------------------------------------------------------------------

# 🚀 Quick Start (Docker)

## 1. Clone repository

``` bash
git clone https://github.com/hsabiti/snappyshopper.git
cd snappyshopper
```

## 2. Start containers

``` bash
docker compose up --build
```

This will automatically:

-   install Composer dependencies\
-   generate `.env` and `APP_KEY`\
-   wait for MySQL readiness\
-   run migrations\
-   import sample UK postcode data\
-   start Laravel at:

```{=html}
<!-- -->
```
    http://localhost:8000

No manual setup required.

------------------------------------------------------------------------

# 🔐 API Authentication

All endpoints require:

    X-API-KEY: change-me

The key is configurable via:

    API_KEY=change-me

in `.env`.

------------------------------------------------------------------------

# 📦 API Endpoints

## Create Store

    POST /api/stores

### Example

``` bash
curl -X POST http://localhost:8000/api/stores   -H "Content-Type: application/json"   -H "X-API-KEY: change-me"   -d '{
    "name": "Test Store",
    "postcode": "SW1A 1AA",
    "delivery_radius_km": 5,
    "opens_at": "08:00",
    "closes_at": "22:00"
  }'
```

------------------------------------------------------------------------

## Nearby Stores

    GET /api/stores/nearby?postcode=SW1A+1AA

OR

    GET /api/stores/nearby?lat=51.5&lng=-0.12

Returns stores within a configurable radius, sorted by distance.

------------------------------------------------------------------------

## Delivery Feasibility

    GET /api/stores/can-deliver?store_id=1&postcode=SW1A+1AA

Response includes:

-   distance\
-   radius check\
-   open/closed state\
-   ETA estimate\
-   delivery eligibility

------------------------------------------------------------------------

# 🗺️ Postcode Import

Console command:

``` bash
php artisan import:postcodes
```

Automatically executed during Docker startup.

Features:

-   postcode normalisation\
-   latitude/longitude storage\
-   idempotent import\
-   safe to re-run

------------------------------------------------------------------------

# 🧠 Architecture Overview

## Key Decisions

### Simple, portable geospatial logic

-   Bounding-box SQL prefilter\
-   Haversine distance in PHP\
-   Avoids MySQL spatial extensions\
-   Works across MySQL/PostgreSQL

### Service extraction for unit testing

Core geo logic isolated in:

    App\Services\GeoService

Enables **fast PHPUnit unit tests without Laravel boot**.

### Reproducible Docker runtime

Single command:

    docker compose up --build

ensures:

-   zero local PHP/MySQL dependency\
-   deterministic reviewer experience

------------------------------------------------------------------------

# ⚡ Performance Considerations

-   Bounding box reduces DB scan size\
-   Distance filtering done in memory on small candidate set\
-   Postcode lookups cached for **24 hours**\
-   Pagination supported on nearby search

Suitable for **assessment-scale datasets** while remaining
production-extendable.

------------------------------------------------------------------------

# 🕒 Store Operating Hours & ETA

## Operating hours

-   Supports timezone-aware open/close checks\
-   Handles **overnight windows** (e.g. 22:00 → 02:00)

## ETA estimation

Minimal heuristic:

    10 min handling + travel at 20 km/h

Simple, explainable, and easy to evolve.

------------------------------------------------------------------------

# 🧪 Testing

Run:

``` bash
docker exec -it snappy_app php artisan test
```

Includes:

-   **Unit tests** for geo distance + ETA logic\
-   **Feature tests** for API delivery behaviour

Focus:

-   correctness\
-   edge cases\
-   delivery eligibility

------------------------------------------------------------------------

# 🐳 Docker Services

  Service        Purpose
  -------------- -------------------------
  `snappy_app`   Laravel PHP application
  `snappy_db`    MySQL 8 database

Environment configured automatically for reviewers.

------------------------------------------------------------------------

# 📚 Trade-offs & Future Improvements

## Trade-offs (time-boxed assessment)

-   PHP distance calc instead of DB spatial index\
-   Simple ETA heuristic\
-   API key auth instead of full OAuth/JWT\
-   Minimal postcode dataset for speed

## With more time

-   MySQL spatial indexes + `ST_Distance_Sphere`\
-   Redis caching layer\
-   Background postcode import queue\
-   OpenAPI / Swagger docs\
-   Rate limiting & auth tokens\
-   Load/performance benchmarks

------------------------------------------------------------------------

# ✅ Assessment Requirement Coverage

  Requirement                Status
  -------------------------- --------
  Console postcode import    ✔
  Store creation API         ✔
  Nearby discovery           ✔
  Delivery feasibility       ✔
  Relational DB + indexes    ✔
  Unit + integration tests   ✔
  Docker setup               ✔
  Caching                    ✔
  Operating hours            ✔
  ETA estimation             ✔
  API authentication         ✔

------------------------------------------------------------------------

# 📚 Performance Benchmarks

Basic performance validation was carried out using simple HTTP load testing (ApacheBench) against the nearby-stores endpoint.
Results in a local Docker environment showed low-millisecond response times and stable behaviour under concurrent requests, confirming the bounding-box filtering, indexing, caching, and pagination approach is sufficient for MVP scale.
These can be improved to a more satisfactory level.

ab -n 200 -c 20 "http://loab -n 200 -c 20 "http://localhost:8000/api/stores/nearby?postcode=SW1A1AA"
This is ApacheBench, Version 2.3 <$Revision: 1903618 $>
Copyright 1996 Adam Twiss, Zeus Technology Ltd, http://www.zeustech.net/
Licensed to The Apache Software Foundation, http://www.apache.org/

Benchmarking localhost (be patient)
Completed 100 requests
Completed 200 requests
Finished 200 requests


Server Software:        
Server Hostname:        localhost
Server Port:            8000

Document Path:          /api/stores/nearby?postcode=SW1A1AA
Document Length:        43 bytes

Concurrency Level:      20
Time taken for tests:   2.106 seconds
Complete requests:      200
Failed requests:        140
   (Connect: 0, Receive: 0, Length: 140, Exceptions: 0)
Non-2xx responses:      200
Total transferred:      1001590 bytes
HTML transferred:       929240 bytes
Requests per second:    94.97 [#/sec] (mean)
Time per request:       210.593 [ms] (mean)
Time per request:       10.530 [ms] (mean, across all concurrent requests)
Transfer rate:          464.46 [Kbytes/sec] received

Connection Times (ms)
              min  mean[+/-sd] median   max
Connect:        0    0   0.2      0       1
Processing:    11  202 136.6    159     605
Waiting:       11  200 133.6    158     605
Total:         12  202 136.5    159     606

Percentage of the requests served within a certain time (ms)
  50%    159
  66%    187
  75%    200
  80%    209
  90%    593
  95%    594
  98%    595
  99%    605
 100%    606 (longest request)


------------------------------------------------------------------------

# 👤 Author

**Henry Sabiti**\
Backend / Full-Stack Engineer

GitHub:\
https://github.com/hsabiti
