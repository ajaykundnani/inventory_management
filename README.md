# Order & Inventory Management System

A small Laravel 12 + Vue 3 application. Admins manage products and order statuses. Staff browse products and create customer orders. Stock stays consistent even when several orders compete for the same product.

**Features**

- Session-based login (Laravel Sanctum SPA auth) with **admin** and **staff** roles, enforced on the backend.
- Dashboard: total products, total orders, pending orders, low-stock (and out-of-stock) products, all from the API.
- Products: list with name/SKU search, stock-status filter (in / low / out of stock), pagination, detail page, admin-only create form that shows API validation errors inline.
- Orders: product picker, quantities, live estimated total, submit, list with status filter and search, detail page with items and totals, admin-only status changes.
- Inventory safety: server-side pricing, atomic stock reservation in a transaction, full rollback on failure, overselling prevented under concurrency, cancellation returns stock exactly once.
- Consistent JSON envelope and HTTP status codes for every API response.
- Responsive UI built with **Bootstrap 5** and **Bootstrap Icons** (collapsible navbar, cards, tables, inline form validation states, red `*` on required fields, placeholders on every input, show/hide password toggle, first field auto-focused on every form, green success toasts in the top-right corner) and a custom stock-boxes favicon.

**Tech stack:** Laravel 12 · Sanctum · MySQL 8 · Vue 3 (Composition API) · Vue Router 4 · Axios · Bootstrap 5.3 · Bootstrap Icons · Vite 7

Detailed API reference: **[docs/API.md](docs/API.md)**

---

## Requirements

| Software | Version used / required |
|---|---|
| PHP | 8.2+ with `pdo_mysql`. `pcntl` is needed for the concurrency test (it is skipped otherwise) |
| Composer | 2.x |
| MySQL | 8.0+ (developed and tested against 8.0.46). MySQL is the only supported database |
| Node.js | 20.19+ or 22.12+ (Vite 7). Developed on Node 24 |
| npm | 10+ |

## Setup

```bash
git clone <repo-url> inventory_management
cd inventory_management

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your local MySQL credentials:

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventory_management
DB_USERNAME=root
DB_PASSWORD=
```

Create the application database **and** a separate test database (the test suite wipes its database, so it must never point at your app data). You can use the `mysql` CLI as below, or Adminer/phpMyAdmin with collation `utf8mb4_unicode_ci`:

```bash
mysql -u root -p -e "CREATE DATABASE inventory_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                     CREATE DATABASE inventory_management_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php artisan migrate --seed
# start over at any time:
php artisan migrate:fresh --seed
```

## Running

Two terminals:

```bash
php artisan serve            # http://127.0.0.1:8000
npm run dev                  # Vite dev server with HMR
```

Open **http://127.0.0.1:8000**. Use the same host as `APP_URL`; the session cookie is bound to it.

For a production-style build without the Vite dev server: `npm run build` (writes `public/build/`), then delete `public/hot` if a previous `npm run dev` left it behind.

### Demo accounts (local only)

Created by `UserSeeder`. These are throwaway credentials for local development; never seed them in a shared or production environment.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@example.com` | `password` |
| Staff | `staff@example.com` | `password` |

`DatabaseSeeder` runs one seeder per table group, in dependency order:

| Seeder | Creates |
|---|---|
| `UserSeeder` | Demo admin and staff accounts |
| `ProductSeeder` | 8 products covering in-stock, low-stock and out-of-stock |
| `OrderSeeder` | 2 pending orders with their items, created through `OrderService` so totals and stock decrements follow the real rules |

Seeders are safe to re-run (`php artisan db:seed`): existing users, products and orders are left as they are, and stock is never reset. A single seeder can be run with `php artisan db:seed --class=ProductSeeder`.

## Tests

```bash
php artisan test             # or: composer test
npm run build                # frontend compile check
vendor/bin/pint --test       # code style
```

Tests run against the **MySQL test database `inventory_management_test`**. `phpunit.xml` overrides only `DB_CONNECTION` and `DB_DATABASE`, and the host, username and password come from your `.env`, so no credentials are committed. Create that database first (see [Setup](#setup)). The first test of a run rebuilds the schema (`RefreshDatabase`), and every test after that runs inside a transaction that is rolled back. On a MySQL server with slow disk sync, that first schema rebuild can take about a minute.

Coverage of business rules:

| Area | Test |
|---|---|
| Product validation, duplicate SKU (case-insensitive), negative price/stock, 2-decimal price | `tests/Feature/ProductTest.php` |
| Search and stock-status filters, 404 envelope | `ProductTest` |
| Valid order with server-calculated total (client prices ignored) | `tests/Feature/OrderTest.php` |
| Insufficient stock, nonexistent / inactive / duplicate products, empty order, zero quantity | `OrderTest` |
| Rollback when a failure happens mid-transaction (after stock was already decremented) | `OrderTest::test_failure_mid_transaction_rolls_back_order_items_and_stock` |
| Status transitions, cancellation restores stock exactly once | `OrderTest` |
| Authorization: staff cannot create products or change status; staff see only their own orders | `ProductTest`, `OrderTest` |
| Login, logout, 401 on protected routes, dashboard counts | `tests/Feature/AuthTest.php` |
| **Concurrency:** 12 forked processes buy 1 unit each of a product with stock 5 | `tests/Feature/ConcurrentOrderTest.php` |

The concurrency test forks 12 real OS processes that hit the MySQL test database at the same moment, so it exercises real InnoDB row locks. Child processes can only see committed data, so this test commits its own fixtures and clears the test tables afterwards instead of using a rollback. It asserts that exactly 5 orders succeed, 7 get a clean insufficient-stock error, none fail unexpectedly, final stock is 0, and `stock + sold == initial`. It passed on 4 consecutive runs against MySQL 8.0.46.

---

## Architecture

Single Laravel project. The Vue SPA lives in `resources/js` and is served by a catch-all web route. The API is under `/api`.

```
app/
  Enums/                 OrderStatus (transition rules), ProductStatus, StockStatus, UserRole
  Exceptions/            BusinessRuleException → InsufficientStockException, InvalidStatusTransitionException
  Http/Controllers/Api/  Thin controllers: validate (FormRequest) → call service/model → ApiResponse
  Http/Requests/         Validation + authorization per endpoint
  Http/Resources/        JSON shapes (Product, Order, OrderItem, User)
  Models/                Product (search / stock scopes), Order (visibleTo scope), OrderItem, User
  Policies/              ProductPolicy, OrderPolicy (auto-discovered)
  Services/              OrderService (create, status change, stock), DashboardService
  Support/               ApiResponse (envelope), Money (integer-cent math)
bootstrap/app.php        statefulApi() + exception → JSON envelope mapping
database/migrations/     users.role, products, orders, order_items
database/seeders/        DatabaseSeeder → UserSeeder, ProductSeeder, OrderSeeder
resources/css/app.css    Imports Bootstrap + Bootstrap Icons, plus a few custom styles
resources/views/welcome.blade.php  SPA shell (title, favicon links, Vite assets)
public/favicon.svg, favicon.ico, apple-touch-icon.png  App icon (stacked stock boxes)
resources/js/
  api/                   Axios instance (CSRF, 419 retry, 401 hook, ApiError) + one module per resource
  stores/auth.js         Reactive auth state (user, isAdmin), no Pinia needed
  stores/toast.js        App-wide toast queue (success / error, auto-dismiss after 4s)
  directives/autofocus.js  v-autofocus: focuses a form's first field whenever the page is opened (also on in-app navigation)
  composables/           useAsync, usePaginatedList (filters ↔ URL query, debounce), useOrderDraft
  components/            AppLayout, PageHeader, RequestState, AlertMessage, ToastContainer, FormField, PasswordInput, StatusBadge,
                         StatCard, PaginationBar, ProductPicker, OrderItemsTable, OrderStatusControl
  views/                 Login, Dashboard, ProductList/Create/Detail, OrderList/Create/Detail, NotFound
  router/                Routes + guard (auth, guest, admin-only)
tests/Feature/           Auth, Product, Order, ConcurrentOrder
docs/API.md              Full API reference
```

### Database

| Table | Key columns |
|---|---|
| `users` | `role` (`admin` / `staff`, indexed) |
| `products` | `sku` unique, `price` DECIMAL(10,2), `stock_quantity` UNSIGNED INT, `low_stock_threshold`, `status` |
| `orders` | `order_number` unique, `user_id` FK (restrict), `customer_name`, `status`, `total` DECIMAL(12,2), `cancelled_at` |
| `order_items` | `order_id` FK (cascade), `product_id` FK (restrict), snapshot `product_name`/`product_sku`/`unit_price`, `quantity`, `line_total`; unique (`order_id`, `product_id`) |

### Authorization matrix

| Action | Admin | Staff |
|---|:-:|:-:|
| View dashboard, list/view products | ✅ | ✅ |
| Create product | ✅ | ❌ 403 |
| Create order | ✅ | ✅ |
| List orders | all | own only |
| View order detail | all | own only (403 otherwise) |
| Change order status | ✅ | ❌ 403 |

Enforced by policies called from FormRequest `authorize()` / `Gate::authorize()` and the `Order::visibleTo()` scope. The Vue router and navigation hide admin screens, but that is convenience only.

### Key design decisions

- **Server is the only source of truth for money and stock.** The order request accepts only `product_id` + `quantity`. Prices are read from locked product rows, and totals are computed in integer cents (`App\Support\Money`). The frontend total is labelled "Estimated".
- **Write-first stock reservation.** Each line is reserved with `UPDATE … SET stock = stock - q WHERE id = ? AND status = 'active' AND stock >= q`, in ascending id order, before anything is read. Check-and-decrement happens in one atomic statement, so stock can never go negative. On InnoDB it takes the same exclusive row lock as `SELECT … FOR UPDATE`, held until commit. A consistent lock order avoids deadlocks, and leftover deadlocks are retried (3 attempts). See [docs/API.md → Inventory behaviour](docs/API.md#inventory-behaviour).
- **Status rules live in one place** (`OrderStatus::allowedTransitions()`). The API returns `allowed_transitions` with each order, so the UI never duplicates them.
- **Cancellation restores stock once.** The order row is locked and re-read before the transition is validated, and `cancelled` is terminal. `completed` orders cannot be cancelled.
- **Staff only see their own orders.** This seemed the safer default for customer data. Changing it is a one-line change in `OrderPolicy::view` and `Order::scopeVisibleTo`.
- **Order items snapshot product name, SKU and price**, so order history stays correct if a product changes later.
- **Cookie-based Sanctum auth** instead of tokens: no token in `localStorage`, CSRF protection built in, and it fits a same-origin SPA.
- **Bootstrap 5 for the UI** (npm packages `bootstrap` and `bootstrap-icons`, imported in `resources/css/app.css` and bundled by Vite, no CDN). Only Bootstrap's CSS is used. Interactive parts such as the mobile navbar toggle are driven by Vue state rather than Bootstrap's JavaScript, so Vue stays in control of the DOM and Popper isn't needed. The original skeleton's Tailwind was removed: its Vite plugin had never been configured, so its classes were not being generated, and running both frameworks would make their base styles conflict.
- **Feedback pattern:** successful actions (sign in, sign out, product created, order placed, order status changed) show a green toast in the top-right corner that dismisses itself after 4 seconds. Errors stay inline next to the form or field they belong to, so they don't disappear before the user can act on them.
- **No Pinia.** Only the auth state is shared, and a module-level `reactive()` store covers that.

---

## Known limitations

- **No product update/delete or stock adjustment endpoints**, since the brief only asked for list/create/show. Stock only changes through orders and cancellations.
- **MySQL only.** The SQLite connection was removed from `config/database.php`. The concurrency guarantees rely on InnoDB row locks and were only tested on MySQL 8.0.
- The concurrency test needs the `pcntl` extension (Linux/macOS CLI). It is skipped on Windows, and it clears the tables of the test database it runs against.
- The order list and dashboard are not cached. Product search uses `LIKE '%term%'`, which will not use an index on large catalogues (a full-text index would help).
- There are no automated browser/component tests. The UI was checked with headless-Chrome screenshots of every main screen at desktop (1280px) and phone (390px) widths, with no console errors and no horizontal overflow, but not by a test suite.
- Order numbers are random (`ORD-YYYYMMDD-XXXXXXXX`, unique-indexed) rather than sequential.

### With more time

- Product edit/archive and audited manual stock adjustments (a stock-movements ledger table).
- CI job with a MySQL service container running the full suite, plus Vitest component tests and a Playwright happy-path test.
- Customer entity instead of a free-text name; per-status timestamps / order history log.
- Idempotency key on `POST /api/orders` so network retries cannot create duplicate orders.
- Rate limiting on write endpoints, and OpenAPI spec generation.

---

## AI Tools Used

### 1. Which AI coding tool(s) did you use?

**Claude Code** (Anthropic's CLI coding agent, model Claude Opus 5.5), used inside VS Code with direct access to this repository, a terminal, the local MySQL server and a headless Chrome browser.

### 2. Which tasks did you use them for?

- Inspecting the starter Laravel 12 + Vue 3 project and planning the implementation.
- Backend: schema and migrations, models, the `OrderService` stock/transaction logic, policies, form requests, API resources, controllers, the JSON error envelope, and the per-table seeders.
- Frontend: the API layer, auth and toast stores, composables, reusable components, views, router guards and the Bootstrap 5 styling.
- Writing and running the feature tests, including the multi-process concurrency test.
- Verification runs: test suite, build, code style, curl API checks and headless-Chrome screenshots.
- Drafting this README and `docs/API.md`.

### 3. Representative prompts

1. **Core brief (abridged):** *"Implement the complete Order & Inventory Management System described in the task PDF, using the existing Laravel 12 + Vue 3 project … Use database transactions and row-level locking (lockForUpdate where supported) to prevent overselling under concurrent requests. Lock products in a consistent order to reduce deadlocks … Decide and implement safe cancellation/stock-restoration behavior; prevent duplicate restoration … Do not claim concurrency has been proven unless a real suitable test verifies it."*
2. **Database follow-up:** *"No, I want MySQL only. I don't want SQLite."* This led to `.env` and `phpunit.xml` pointing at MySQL, the SQLite connection being removed, and the whole suite, including the concurrency test, being re-run on MySQL.
3. **UI follow-up (condensed):** *"The UI is too plain. Add the Bootstrap library to the project, restyle every screen with it, and document it in the README."*

### 4. What code, design or suggestions did the AI generate?

- All the code listed in [Architecture](#architecture), the 24 feature tests, this README and `docs/API.md`.
- Key design proposals that were kept:
  - **Write-first stock reservation:** an atomic conditional `UPDATE … WHERE stock_quantity >= ?`, run in product-id order inside one transaction.
  - **Status rules in one place:** order status transitions live in `OrderStatus`, and the API returns `allowed_transitions` so the UI never repeats the rules.
  - **Price snapshots:** order items store name, SKU and unit price at purchase time.
  - **Staff order visibility:** staff only see orders they created.
  - **Cookie login:** Sanctum cookie/session authentication instead of API tokens.
  - **Integer-cent money:** all totals are calculated in whole cents.
  - **One response shape:** a single JSON envelope for every API response.

### 5. What did you modify, reject or improve, and why?

**Changes the developer requested after reviewing the AI's output:**

- **Rejected the SQLite setup and required MySQL only.** The brief specifies MySQL, and the concurrency guarantees should be proven on the real database engine. The SQLite connection and file were removed, and tests now use a separate `inventory_management_test` MySQL database.
- **Rejected the first UI as too plain and asked for Bootstrap 5.** The investigation found that the skeleton's Tailwind had never been wired into Vite, so its classes weren't being generated. Tailwind was removed and every screen was restyled with Bootstrap 5 and Bootstrap Icons.
- **Rejected one large seeder.** The seeding was split into `UserSeeder`, `ProductSeeder` and `OrderSeeder`, called from `DatabaseSeeder`, so each table's demo data is easy to find and run on its own. Doing this exposed a bug that was also fixed: re-running the seeder used to reset live stock.
- **Overrode the AI's autofocus choice on the order form.** The AI focused the product search box; the developer changed it to Customer name.
- **Usability improvements:**
  - red `*` on required fields;
  - placeholders on every input;
  - a show/hide eye toggle on the password field;
  - green success toasts in the top-right corner for every successful action;
  - auto-focus on each form's first field;
  - a custom stock-boxes favicon instead of the empty default.

**Corrections made during the AI's own verification:**

- **Locking strategy rewritten after a failing test.** The first `OrderService::create` locked rows with `SELECT … FOR UPDATE`, checked stock in PHP, then decremented. The multi-process test, run on the SQLite setup used at the time, showed 11 of 12 concurrent buyers failing with `database is locked`, although stock never went negative. It was rewritten to reserve stock write-first. The test then passed with exactly 5 of 12 buyers succeeding, and later passed on MySQL as well.
- **Mutation check:** the stock guard was temporarily removed to prove the concurrency test catches overselling (stock went to −7), then restored. This was done during the SQLite phase and not repeated on MySQL.
- **Defensive choices:**
  - SKUs are upper-cased before validation, so uniqueness never depends on the column collation;
  - unexpected errors return a generic message unless `APP_DEBUG=true`;
  - a blank low-stock threshold is left out of the request so the server default applies, rather than being silently saved as 0.

### 6. How did you verify the generated code was correct and secure?

**Checks run by the AI session** (commands were actually executed and their results read):

- **Automated tests:**
  - `php artisan test` against MySQL 8.0.46: 24 tests passed.
  - The concurrency test (12 forked processes competing for 5 units of stock) passed on 4 consecutive runs.
  - Business rules covered: validation, duplicate SKU, negative values, server-side totals, insufficient stock, rollback mid-transaction, single stock restoration on cancel, and role and ownership authorization.
- **API over real HTTP (curl against `php artisan serve`):**
  - login with the Sanctum cookie and CSRF token;
  - 403 when staff try to create a product;
  - client-sent prices and totals ignored;
  - insufficient stock rejected;
  - an admin cancel restoring stock, and a repeat cancel rejected;
  - 419 without a CSRF token;
  - the 404 error envelope.
- **UI in headless Chrome:**
  - screenshots of every main screen at 1280px and 390px;
  - no console errors and no sideways scrolling;
  - checked by script: the password eye toggle, required-field errors, the threshold default, every success toast, and which field is auto-focused on each form.
  - Test records created during these checks were deleted from the database afterwards.
- **Security review points:**
  - every protected route requires authentication;
  - admin-only actions are enforced by backend policies, not just hidden in the UI;
  - staff cannot read other users' orders (tested);
  - login is rate-limited to 5 attempts per minute;
  - CSRF protection is active on cookie-authenticated requests;
  - prices and totals always come from the database;
  - internal error details are hidden unless debug mode is on;
  - the repository was scanned to confirm the MySQL password exists only in the git-ignored `.env`, and `.env.example` contains no credentials.
- **Build and style:** `npm run build` and `vendor/bin/pint --test` both passed.

### My own review and decisions

I used Claude Code to write the code, while I set the requirements, reviewed the output in the running app and in the editor, and decided what to change. What I personally did:

- **Defined the brief and constraints** before any code was written: follow the PDF as the source of truth, keep the single-project Laravel + Vue structure, use Sanctum, enforce inventory correctness with transactions and locking, no comments in frontend files, and no git commits or pushes by the tool.
- **Rejected SQLite and insisted on MySQL.** When I asked which database had been used, the answer was SQLite. The brief specifies MySQL, so I provided local MySQL access, required the project to run on MySQL only, and had the full test suite, including the concurrency test, re-run against it.
- **Reviewed the seeders and had them restructured.** Reading `DatabaseSeeder.php`, I didn't want all demo data in one file and asked for one seeder per table (`UserSeeder`, `ProductSeeder`, `OrderSeeder`) called from `DatabaseSeeder`.
- **Tested the app manually in the browser with both roles.**
  - As admin, I created a product (`LC-909`, "Sample Pro", $12.00).
  - As staff, I placed a 3-item order for "Ajay Kundnani" (2 × Mechanical Keyboard, 3 × USB-C Hub, 4 × Ethernet Cable). The server-calculated total of $324.81 was correct, and the order used up the last 3 USB-C Hubs, which then showed as out of stock.
- **Found UX problems while using it and had them fixed:**
  - the UI was too plain (which exposed that Tailwind was never wired into Vite), so I chose Bootstrap 5;
  - there was no indication of required fields, so I asked for red asterisks;
  - inputs were missing placeholders;
  - the password field had no show/hide toggle;
  - successful actions gave no feedback, so I asked for green top-right toasts;
  - forms didn't focus their first field;
  - the browser tab showed a default icon, so I asked for a stock icon.
- **Rejected an AI decision:** the AI put the order form's autofocus on the product search box. I changed it to Customer name, because that's the first thing staff fill in for an order.
- **Checked this AI Tools section** against the six questions in the PDF and had it restructured to answer them in order.

<!-- Add anything else you personally did before submitting, e.g. specific files you read line by line or extra security checks. Only add what you actually did. -->
