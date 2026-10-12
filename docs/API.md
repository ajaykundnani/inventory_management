# API Reference

Base URL: `/api` (e.g. `http://127.0.0.1:8000/api`). All requests and responses are JSON. Send `Accept: application/json`.

- [Authentication](#authentication)
- [Response envelope](#response-envelope)
- [Errors and status codes](#errors-and-status-codes)
- [Pagination](#pagination)
- [Endpoints](#endpoints): [Auth](#auth) · [Dashboard](#dashboard) · [Products](#products) · [Orders](#orders)
- [Order status transitions](#order-status-transitions)
- [Inventory behaviour](#inventory-behaviour)

---

## Authentication

The SPA uses **Laravel Sanctum stateful (cookie/session) authentication**. No API tokens are issued.

1. `GET /sanctum/csrf-cookie`: sets the `XSRF-TOKEN` cookie (response `204`).
2. `POST /api/login` with the header `X-XSRF-TOKEN: <decoded XSRF-TOKEN cookie>` and credentials. The response sets the session cookie.
3. Every later request sends the cookies plus `X-XSRF-TOKEN` on state-changing methods. Axios does this automatically with `withCredentials` and `withXSRFToken`.

Requests must come from a stateful domain (`SANCTUM_STATEFUL_DOMAINS`, which defaults to `localhost`, `127.0.0.1`, `127.0.0.1:8000` and the `APP_URL` host). The browser sends `Referer`/`Origin` automatically. With curl, add `-H "Referer: http://127.0.0.1:8000/"`.

Login is throttled to **5 attempts per minute per email + IP** (`429`).

### curl session example

```bash
B=http://127.0.0.1:8000; JAR=cookies.txt
curl -s -c $JAR -H "Referer: $B/" $B/sanctum/csrf-cookie
XSRF=$(python3 -c "import urllib.parse;print([urllib.parse.unquote(l.split()[-1]) for l in open('$JAR') if 'XSRF-TOKEN' in l][-1])")
curl -s -b $JAR -c $JAR -X POST $B/api/login \
  -H "Referer: $B/" -H "X-XSRF-TOKEN: $XSRF" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}'
curl -s -b $JAR -H "Referer: $B/" -H 'Accept: application/json' $B/api/dashboard
```

---

## Response envelope

Success:

```json
{ "success": true, "message": "Optional human message", "data": { }, "meta": { } }
```

`message` and `meta` are omitted when empty. `meta` only appears on paginated lists.

Error:

```json
{ "success": false, "message": "Human-readable summary", "errors": { "field": ["Message"] } }
```

`errors` is only present when there are field-level problems (validation, stock, invalid transitions). Keys use Laravel dot notation, e.g. `items.1.quantity`. The frontend shows these next to the matching input.

## Errors and status codes

| Status | When | Example `message` |
|---|---|---|
| 200 | Successful read/update | |
| 201 | Product or order created | `Product created.` |
| 401 | Not logged in / session expired | `Unauthenticated.` |
| 403 | Logged in but role/ownership not allowed | `You are not authorized to perform this action.` |
| 404 | Unknown record or route | `Resource not found.` |
| 419 | Missing/expired CSRF token | `CSRF token mismatch.` |
| 422 | Validation error, insufficient stock, unavailable product, invalid status transition | `The given data was invalid.` |
| 429 | Login throttle hit | `Too Many Attempts.` |
| 500 | Unexpected error. The real message is only shown when `APP_DEBUG=true`; details go to the log | `An unexpected error occurred.` |

## Pagination

List endpoints accept `page` (≥1) and `per_page` (1–100, default 15) and return:

```json
"meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 37 }
```

---

## Endpoints

| Method | Path | Auth / role | Purpose |
|---|---|---|---|
| POST | `/api/login` | Guest (throttled) | Start a session |
| POST | `/api/logout` | Any user | End the session |
| GET | `/api/user` | Any user | Current user |
| GET | `/api/dashboard` | Any user | Headline counts |
| GET | `/api/products` | Any user | List/search/filter products |
| POST | `/api/products` | **Admin** | Create a product |
| GET | `/api/products/{id}` | Any user | Product details |
| GET | `/api/orders` | Any user (staff: own orders only) | List/filter orders |
| POST | `/api/orders` | Any user | Create an order |
| GET | `/api/orders/{id}` | Admin, or the staff member who created it | Order details |
| PATCH | `/api/orders/{id}/status` | **Admin** | Change order status |

### Auth

#### `POST /api/login`

```json
{ "email": "admin@example.com", "password": "password" }
```

`200`:

```json
{ "success": true, "message": "Logged in.", "data": { "id": 1, "name": "Demo Admin", "email": "admin@example.com", "role": "admin" } }
```

`422` on wrong credentials: `errors.email = ["These credentials do not match our records."]`.

#### `POST /api/logout`
`200` `{ "success": true, "message": "Logged out." }`. Invalidates the session and rotates the CSRF token.

#### `GET /api/user`
`200` with the same `data` shape as login. `401` when not logged in.

### Dashboard

#### `GET /api/dashboard`

```json
{
  "success": true,
  "data": {
    "total_products": 8,
    "total_orders": 2,
    "pending_orders": 2,
    "low_stock_products": 2,
    "out_of_stock_products": 1
  }
}
```

Product counts are global. Order counts follow order visibility: an admin sees all orders, staff see only their own.

### Products

Product object:

```json
{
  "id": 4,
  "name": "USB-C Hub",
  "sku": "HB-4004",
  "description": "Demo product: USB-C Hub.",
  "price": "39.95",
  "stock_quantity": 3,
  "low_stock_threshold": 10,
  "stock_status": "low_stock",
  "status": "active",
  "created_at": "2026-10-12T06:10:18+00:00",
  "updated_at": "2026-10-12T06:10:18+00:00"
}
```

Money is returned as a **string with two decimals** to avoid float rounding.

`stock_status` is derived by the server and the three values are mutually exclusive:

| Value | Rule |
|---|---|
| `out_of_stock` | `stock_quantity = 0` |
| `low_stock` | `0 < stock_quantity <= low_stock_threshold` |
| `in_stock` | `stock_quantity > low_stock_threshold` |

#### `GET /api/products`

| Query | Type | Notes |
|---|---|---|
| `search` | string ≤100 | Case-insensitive match on name **or** SKU |
| `stock_status` | `in_stock` \| `low_stock` \| `out_of_stock` | |
| `status` | `active` \| `inactive` | |
| `page`, `per_page` | int | See pagination |

Sorted by name. Example: `GET /api/products?search=hub&stock_status=low_stock&per_page=10`

```json
{ "success": true, "data": [ { "id": 4, "name": "USB-C Hub", "...": "..." } ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 10, "total": 1 } }
```

An invalid filter value (e.g. `stock_status=bogus`) returns `422`.

#### `POST /api/products` (admin)

| Field | Rules |
|---|---|
| `name` | required, string, ≤255 |
| `sku` | required, ≤64, letters/digits/`-_.`, **unique**. Upper-cased before validation, so uniqueness is case-insensitive |
| `price` | required, numeric, `>= 0`, at most 2 decimals, ≤ 99,999,999.99 |
| `stock_quantity` | required, integer, `>= 0`, ≤ 1,000,000 |
| `low_stock_threshold` | optional integer `>= 0` (default 10) |
| `status` | optional `active` \| `inactive` (default `active`) |
| `description` | optional string ≤2000 |

```json
{ "name": "Desk Lamp", "sku": "dl-100", "price": "19.99", "stock_quantity": 30 }
```

`201` → `{ "success": true, "message": "Product created.", "data": { "sku": "DL-100", "...": "..." } }`

`422` example:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "sku": ["The sku has already been taken."],
    "price": ["The price field must be at least 0."],
    "stock_quantity": ["The stock quantity field must be at least 0."]
  }
}
```

`403` for staff.

#### `GET /api/products/{id}`
`200` with a product object. `404` if it does not exist.

### Orders

Order object (detail):

```json
{
  "id": 3,
  "order_number": "ORD-20261012-6GV0YCTV",
  "customer_name": "Acme Corp",
  "notes": null,
  "status": "pending",
  "allowed_transitions": ["processing", "cancelled"],
  "total": "79.90",
  "items": [
    { "id": 4, "product_id": 4, "product_name": "USB-C Hub", "product_sku": "HB-4004",
      "quantity": 2, "unit_price": "39.95", "line_total": "79.90" }
  ],
  "created_by": { "id": 2, "name": "Demo Staff", "email": "staff@example.com", "role": "staff" },
  "cancelled_at": null,
  "created_at": "2026-10-12T06:20:00+00:00",
  "updated_at": "2026-10-12T06:20:00+00:00"
}
```

`allowed_transitions` lists the statuses the order can move to next, so clients never re-implement the rules. Order items store a **snapshot** of product name, SKU and unit price at purchase time. List responses include `items_count` instead of `items`.

#### `GET /api/orders`

| Query | Type | Notes |
|---|---|---|
| `status` | `pending` \| `processing` \| `completed` \| `cancelled` | |
| `search` | string ≤100 | Matches order number or customer name |
| `page`, `per_page` | int | |

Newest first. Staff only receive orders they created; admins receive all orders.

#### `POST /api/orders`

| Field | Rules |
|---|---|
| `customer_name` | required, string ≤255 |
| `notes` | optional, string ≤1000 |
| `items` | required array, 1–50 entries |
| `items.*.product_id` | required, integer, must exist, **distinct** within the order |
| `items.*.quantity` | required, integer 1–10,000 |

```json
{
  "customer_name": "Acme Corp",
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 8, "quantity": 5 }
  ]
}
```

Any `price`, `unit_price` or `total` sent by the client is **ignored**. Prices come from the database.

`201` → order object, `status: "pending"`.

Business-rule failures (`422`, nothing is saved, stock untouched):

```json
{ "success": false, "message": "Insufficient stock for one or more items.",
  "errors": { "items.1.quantity": ["Only 3 of USB-C Hub in stock."] } }
```

```json
{ "success": false, "message": "One or more products cannot be ordered.",
  "errors": { "items.0.product_id": ["Old Widget is not available for ordering."] } }
```

#### `GET /api/orders/{id}`
`200` order object. `403` if a staff member requests someone else's order. `404` if unknown.

#### `PATCH /api/orders/{id}/status` (admin)

```json
{ "status": "processing" }
```

`200` → `{ "success": true, "message": "Order status updated.", "data": { "...": "..." } }`

`422` for a disallowed transition:

```json
{ "success": false, "message": "Cannot change order status from completed to cancelled.",
  "errors": { "status": ["Transition from completed to cancelled is not allowed."] } }
```

`403` for staff.

---

## Order status transitions

```
pending ──► processing ──► completed
   │             │
   └──► cancelled ◄┘
```

| From | Allowed to |
|---|---|
| `pending` | `processing`, `cancelled` |
| `processing` | `completed`, `cancelled` |
| `completed` | (terminal) |
| `cancelled` | (terminal) |

Defined once in `App\Enums\OrderStatus::allowedTransitions()`.

## Inventory behaviour

**Order creation** (`App\Services\OrderService::create`) runs inside one database transaction:

1. For each line, in ascending `product_id` order, run an atomic conditional decrement:
   `UPDATE products SET stock_quantity = stock_quantity - :qty WHERE id = :id AND status = 'active' AND stock_quantity >= :qty`.
   If 0 rows are affected, the line is rejected. Checking and decrementing in one statement means stock can never go negative, even under concurrent requests.
2. If any line was rejected, the reason is worked out (missing / inactive / insufficient). A `422` with per-line errors is thrown, and the transaction rolls back every reservation already made.
3. Prices are read from the now-locked product rows. Line totals and the order total are computed in integer cents, and the order and items are inserted.
4. Any exception (including unexpected DB errors) rolls back the order, its items and all stock changes.

Locking: on MySQL/InnoDB each `UPDATE` holds an exclusive row lock until commit, the same lock `SELECT … FOR UPDATE` would take. Rows are always locked in ascending id order to avoid lock-order deadlocks. Deadlocks and lock timeouts that still happen are retried up to 3 times (`DB::transaction(..., 3)`). `stock_quantity` is also `UNSIGNED`, so MySQL in strict mode refuses a negative value.

**Cancellation** (`OrderService::updateStatus`): the order row is re-read with `lockForUpdate()` inside a transaction, and the transition is re-validated against the locked row. Stock for each item is then incremented. `cancelled` is terminal, so a second or concurrent cancel fails validation, and stock is restored exactly once. `completed` orders cannot be cancelled, so shipped stock is never returned.
