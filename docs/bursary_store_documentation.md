# OOU Bursary Store – System Documentation

![OOU Bursary Logo](../assets/images/OOU.png)

## 1. Overview

The Bursary Store application is a PHP + MySQL web system used to manage stationery and related inventory (e.g., paper, biro, printer toners) for Olabisi Onabanjo University.

- **Tech stack**: PHP (procedural), MySQL, HTML/Bootstrap, jQuery/vanilla JS.
- **Deployment style**: XAMPP-style on Windows (Apache + MySQL).
- **Base URL (local dev)**: `http://localhost/bursary/`.
- **Entry point**: `index.php` (login + role-based routing).

### 1.1 Roles

The system supports several user roles (stored in the `users` table):

- **Admin**
  - Manages users and inventory configuration.
  - Receives stock and adjusts stock levels.
  - Runs all reports (stock balance, grouped items, history, charts, export/import).
- **Storekeeper**
  - Receives and issues stock to units.
  - Views inventory and low-stock alerts.
- **VC / Director**
  - Read‑only access to dashboards and reports (overall inventory and grouped views).

Authentication and session control are implemented via:

- `index.php` – login form and `password_verify()` based authentication.
- `assets/inc/checklogins.php` – `check_login()` helper enforcing that a user is logged in.
- Session variables:
  - `$_SESSION['user_id']`
  - `$_SESSION['username']`
  - `$_SESSION['role']`
  - `$_SESSION['full_name']`


## 2. Directory Structure (high level)

Key folders/files:

- `/index.php` – login + role redirect to relevant dashboard.
- `/admin_dashboard.php`, `/vc_dashboard.php` – dashboards for Admin and VC.
- `/stationery_store.php` – stationery/toner catalogue view.
- `/stock_receive.php`, `/receive_stock.php`, `/receive_stationery.php` – receive stock flows.
- `/issue_items.php` – issue stock to units.
- `/inventory_report.php` – stock balance table.
- `/inventory_grouped.php` – grouped report by base item (paper sizes, toner variants).
- `/inventory_history.php` – issuance history.
- `/inventory_charts.php` – monthly usage chart.
- `/download_stock.php` – export stock to Excel‑compatible file.
- `/upload_stock.php` – import stock from CSV.

Shared includes:

- `assets/inc/config.php` – database connection (`$mysqli`) and environment settings.
- `assets/inc/functions.php` – helper functions (e.g., `log_action`).
- `assets/inc/stock_functions.php` – stock‑specific helpers such as `get_item_current_stock()`.
- `assets/inc/checklogins.php` – `check_login()` and related access helpers.
- `assets/inc/head.php`, `nav.php`, `sidebar_*.php`, `footer.php` – layout and navigation.


## 3. Database Model (core tables)

### 3.1 Users & Logs

- **`users`**
  - `user_id` (PK)
  - `username`
  - `password` (hashed)
  - `role` (e.g., `admin`, `storekeeper`, `vc`)
  - `full_name`

- **`logs`** (if enabled by `log_action()`)
  - Audits major actions (logins, stock operations, config changes).

### 3.2 Reference Tables

- **`units`**
  - Administrative or requesting units (e.g., `ADMIN`, `REG`, `FIN`).

- **`categories`**
  - `category_id` (PK)
  - `name` (e.g., `A4`, `A5`, `Legal`, `Toner`, `Biro`)
  - Uniquely identifies a logical category or size used for grouping.

### 3.3 Items & Grouping

The system has evolved through two item schemas. The documentation and code are written to support both.

- **Newer schema (from `003_create_items.sql`)**
  - `items` table:
    - `item_id` (PK)
    - `item_name` (e.g., `Paper`, `Paper (A4)`, `HP Toner 85A (Black)`)
    - `category_id` (FK → `categories.category_id`)
    - `unit` (e.g., `pack`, `ream`, `pcs`)

- **Legacy schema (backwards compatibility)**
  - `items` may also contain:
    - `name` (display name)
    - `category` (free text category description)
    - `unit_measure` (unit string)

**Variant naming convention** (used by the grouped report and per‑size stock):

- Base item name (group): `Paper`, `HP Toner 85A`, `Biro`.
- Variant per size/type: `Paper (A4)`, `Paper (A5)`, `Paper (Legal)`.
- For toners: `HP Toner 85A (Black)`, `HP Toner 85A (Blue)`.

The grouped report strips the trailing `" (… )"` to form the base group.

### 3.4 Stock Movement & Balances

- **`stock_receipts`**
  - Header for each receipt (supplier, received_by, received_at, note).

- **`receipt_items`**
  - Line items for receipts (receipt_id, item_id, quantity, unit_cost).

- **`stock_transactions`** (primary movement log)
  - `tx_id` (PK)
  - `item_id`
  - `qty_change` (positive for receives, negative for dispatches/adjustments)
  - `tx_type` (`receive`, `dispatch`, `adjustment`)
  - `reference_id` (links back to a receipt or request)
  - `user_id` (who performed the action)
  - `created_at`
  - `note`

- **`stock_entries`** (legacy movement log; still used by some pages)
  - `entry_id` (PK)
  - `item_id`
  - `qty_in`, `qty_out`
  - `reference`, `note`, `created_by`, `created_at`.

- **`stock_issues`** (legacy issuance records; used for history and charts where present)

- **`stock_balance`** (legacy/current snapshot table; new code prefers `stock_transactions` but can fall back to this table where it exists).

- **`inventory_thresholds`**
  - Per‑item threshold levels used for low‑stock alerts.


## 4. Main Functional Flows

### 4.1 Authentication and Role Routing

1. User opens `index.php`.
2. Submits username/password credentials.
3. PHP validates using `password_verify()` against `users.password`.
4. On success, sets session variables and redirects to the appropriate dashboard:
   - Admin → `admin_dashboard.php`
   - Storekeeper → `admin_dashboard.php` with storekeeper view
   - VC/Director → `vc_dashboard.php`

Access to all other pages is gated by `include('assets/inc/checklogins.php');` and `check_login()`.


### 4.2 Receiving Stock

There are two main entry points for receiving stock:

#### 4.2.1 Receive New Stock (generic) – `stock_receive.php`

- Audience: Admin/Storekeeper.
- Workflow:
  1. User selects **Item** (e.g., `Paper`).
  2. User selects **Category** (e.g., `A4`, `A5`, `Legal`).
  3. Form shows **Current Stock** based on the combination of selected item and category:
     - If a variant item exists (e.g., `Paper (A4)`), reads stock from `stock_transactions` for that item_id.
     - If no variant exists for the selected category, shows `0`.
  4. User enters **Quantity**, **Cost per Unit**, **Supplier**, and **Reference/PO Number**.
  5. On submission:
     - The backend ensures a variant item exists for (base item, category):
       - If `Paper (A4)` or `Paper (Legal)` etc. already exists, reuse its item_id.
       - Otherwise create a new item record with that variant name and link it to the category.
     - Inserts into:
       - `stock_receipts` (header).
       - `receipt_items` (line with quantity & cost).
       - `stock_transactions` (receive record with positive `qty_change`).

#### 4.2.2 Receive Stationery / Toner – `receive_stationery.php` (if used)

- Similar to `stock_receive.php` but pre‑filters selectable items to stationery/toner types and supports unit conversion (e.g., pack ↔ ream, migration away from litres).


### 4.3 Issuing Stock to Units – `issue_items.php`

1. User selects a unit (e.g., `ADMIN`, `REG`).
2. Chooses one or more items to issue and the quantities.
3. The system checks available stock (via `get_item_current_stock`).
4. Records the issue as:
   - Legacy: `stock_entries` (`qty_out`) and/or `stock_issues` row.
   - New: `stock_transactions` with negative `qty_change` and `tx_type = 'dispatch'`.

This data feeds `inventory_history.php` and `inventory_charts.php`.


### 4.4 Stationery Store Catalogue – `stationery_store.php`

- Shows stationery/toner items with their current stock.
- Items are fetched from `items` and filtered as stationery by:
  - Category being `Stationery` or `Toner`, or containing those terms.
  - Name containing stationery keywords (e.g., `paper`, `pen`, `biro`, `pencil`, `marker`, `toner`).
- For each item, `get_item_current_stock(item_id)` computes stock from `stock_transactions`.
- Actions:
  - **Receive** – navigates to `stock_receive.php` with the item preselected.
  - **Request** – navigates to item request page (where implemented).


### 4.5 Reports

#### 4.5.1 Stock Balance Report – `inventory_report.php`

- Lists every item with:
  - Item name
  - Category (from `categories.name` if available)
  - Unit
  - Current stock (sum of `qty_change` from `stock_transactions`, or 0 if none)
  - Total issued
  - Last updated timestamp

- This report is schema‑aware:
  - Detects `item_name` vs `name`, `unit_measure` vs `unit`.
  - Tries to use `stock_transactions`; falls back to `stock_balance` or zero stock if not present.

#### 4.5.2 Grouped Items Report – `inventory_grouped.php`

- New report to group related variants (paper sizes, toner variants, etc.).
- Logic:
  1. Load all items (`items`) plus stock from `stock_transactions` (if available).
  2. For each item, derive a *group name*:
     - If the name contains `" ("`, e.g., `Paper (A4)`, group name is the substring before the `" ("` → `Paper`.
     - Otherwise group name is the full item name.
  3. Build groups keyed by this base name and aggregate total stock per group.
  4. The UI shows:
     - A dropdown of all groups (`Paper`, `Biro`, `HP Toner 85A`, etc.) with the number of variants and total stock.
     - A table of all variants for the selected group with columns:
       - Item Variant (full name)
       - Category / Size
       - Unit
       - Current Stock
       - Last Updated

- This design automatically supports:
  - **Paper sizes**: A4, A5, Legal – via `Paper (A4)`, `Paper (A5)`, `Paper (Legal)`.
  - **Printer toners**: `HP 85A (Black)`, `HP 85A (Blue)`, `Canon 05A (Black)`, etc.
  - **Any future category** where you follow the `Base (Variant)` naming convention.

#### 4.5.3 Issuance History – `inventory_history.php`

- Shows stock issuance records chronologically.
- Columns: Date, Item, Unit, Quantity, Issued By, Purpose.
- Supports optional date filters (`from`, `to`).
- Resolves item names across `items.name` and `items.item_name`.

#### 4.5.4 Usage Charts – `inventory_charts.php`

- Shows 12‑month line chart of items issued per month.
- Uses Chart.js in the frontend.
- Data comes from `stock_issues` (legacy) aggregated by month.

#### 4.5.5 Export / Import Stock

- **Export** – `download_stock.php`
  - Outputs a tab‑separated `.xls` with columns: Item, Category, Unit, Quantity, Last Updated.
  - Schema‑aware: uses `stock_transactions` if present; otherwise `stock_balance` or zeros.

- **Import** – `upload_stock.php`
  - Accepts CSV in two formats:
    1. Legacy: `item_name,quantity` (updates by name only).
    2. Preferred: `item_name,category,quantity` (updates by both name and category).
  - When a category is supplied, the script strictly matches `item_name` + category and does **not** fall back to name‑only, to prevent merging A4 with Legal or A5 stock.
  - For each matched item, inserts a `stock_entries` record and updates `stock_balance` (for legacy compatibility).


## 5. UI Layout & Navigation

- Layout pattern:
  - `assets/inc/head.php` – HTML `<head>`, CSS/JS, SweetAlert hooks.
  - `assets/inc/nav.php` – top navigation bar (brand, user dropdown).
  - `assets/inc/sidebar_admin.php`, `sidebar_vc.php`, etc. – role‑specific sidebars.
  - Content – page‑specific body.
  - `assets/inc/footer.php` – shared footer + scripts.

- Sidebars include quick links for:
  - **Admin**:
    - Store Management → Register Item Types, Stationery Store, Receive New Stock, Manage Stock Levels, Low Stock Alerts, Issue to Units.
    - Reports → Stock Balance, Grouped Items (Paper & Toners), Issuance History, Usage Charts, Export/Import.
  - **VC**:
    - Inventory Overview → Stock Review, Low Stock Alerts, History, Charts.
    - Reports → Stock Balance, Grouped Items.


## 6. Extending the System

### 6.1 Adding New Paper Sizes

1. **Create Category**
   - Use the Store Management page (or insert directly into `categories`) to add sizes: `A4`, `A5`, `Legal`, etc.

2. **Define Items**
   - Base item: `Paper`.
   - Variants (as needed): `Paper (A4)`, `Paper (A5)`, `Paper (Legal)`.
   - Ensure each variant is linked to the appropriate `category_id` or, in legacy schema, has the right text `category`.

3. **Receive Stock**
   - In `stock_receive.php` select `Paper` and the appropriate Category.
   - The system ensures the variant exists and receives stock into the correct variant.

4. **Reporting**
   - `inventory_grouped.php` → choose group `Paper` to see all sizes and their stocks.

### 6.2 Adding Printer Toners with Variants

1. Create categories for toner types (e.g., `Black`, `Color`, `Blue`, `Red` or printer model groups).
2. Create items with variant naming, e.g.:
   - `HP Toner 85A (Black)` → link to category `Black`.
   - `HP Toner 85A (Blue)` → link to category `Blue`.
3. Use `stock_receive.php` to receive stock by base name + category; the system will keep separate item_ids.
4. Use `inventory_grouped.php` and select `HP Toner 85A` to see all toner variants and stock levels.

### 6.3 Creating Other Category Families

To support any new product family (e.g., envelopes, markers):

- Choose a **base name**: `Envelope`, `Marker`.
- Define **variant names** following the `Base (Variant)` pattern:
  - `Envelope (A4)`, `Envelope (C5)`.
  - `Marker (Red)`, `Marker (Blue)`.
- Optionally add size/colour categories in `categories`.
- All grouping and stock calculations will automatically include these new families.


## 7. Environment Setup & Backup

### 7.1 Local Setup

1. Install XAMPP (Apache + MySQL) on Windows.
2. Place the project folder under `C:/xampp/htdocs/bursary/`.
3. Configure database in `assets/inc/config.php` (default DB name: `bursarystore`).
4. Import migrations in `sql/migrations/` into the MySQL database (run in order: 000, 001, 002, 003, 004, 005, etc.).
5. Optionally use scripts under `tools/` to create an admin user and seed test data.

### 7.2 Backups

- Regularly export the `bursarystore` database using phpMyAdmin or `mysqldump`.
- Backup the entire project directory, especially:
  - `/assets/inc/config.php` (without committing passwords to version control).
  - Custom scripts and documentation under `/docs/`.


## 8. Security & Best Practices

- Use strong passwords for all admin accounts.
- Limit access to `upload_stock.php` to trusted users, as it changes stock in bulk.
- Prefer prepared statements (`$mysqli->prepare`) for all new SQL queries.
- When adding features that modify data, call `log_action()` (if available) to record what changed and who performed it.


## 9. How to Export This Documentation as PDF

This document is saved as `docs/bursary_store_documentation.md` in the project.

To generate a PDF:

1. Open the file in VS Code or any Markdown editor.
2. Use the editors **Export/Print to PDF** feature, or:
   - Copy the Markdown into a tool like VS Code + Markdown PDF extension, or a web Markdown viewer that supports PDF export.
3. Save the output as `Bursary_Store_Documentation.pdf` and share as required.
