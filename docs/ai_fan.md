# CODEX PROMPT — PR2-PUR-005: Purchase Order Lifecycle & Receiving

You are Codex CLI with full repository access.

Follow docs authority order and read these FIRST (do not modify docs unless explicitly instructed):

- docs/AI_CHAT_CODEX.md
- docs/PR2_ROADMAP.md
- docs/CONVENTIONS.md
- docs/ARCHITECTURE_INVENTORY.md
- docs/PERMISSIONS_MATRIX.md
- docs/ENUMS.md
- docs/DB_SCHEMA.md
- docs/UI_DESIGN.md
- docs/architecture/ui/PageModuleContract.yaml
- docs/architecture/ui/AjaxCrudControllerPattern.yaml
- routes/web.php

Operating rules:

- Do NOT write application code until tests are written and complete (min 20 tests per test file).
- Do NOT run tests or CI.
- Pest only. No PHPUnit-style new test classes.
- No global helper functions in tests.
- PSR-12 + PHPDoc requirements.
- Follow tenancy rules: tenant-owned models use HasTenantScope.
- All quantities are DECIMAL stored but ALL calculations must be BCMath with scale=6 and treat quantities as strings.
- UI must follow page-module contract: no executable JS in Blade; payload JSON only; JS in resources/js/pages/\*\*; no global JS state.

PR goal:
Implement purchase order lifecycle beyond DRAFT and implement receiving + short-close event history with inventory impact via stock moves. Update PO index/show UI accordingly.

================================================================================
SCOPE — DOMAIN RULES (CANONICAL)
================================================================================

Purchase order status values (exact):

- DRAFT
- OPEN
- PARTIALLY-RECEIVED
- RECEIVED
- BACK-ORDERED
- SHORT-CLOSED
- CANCELLED

Terminal states:

- RECEIVED
- SHORT-CLOSED
- CANCELLED

Allowed manual transitions:

- DRAFT → OPEN
- OPEN ↔ BACK-ORDERED
- OPEN → CANCELLED ONLY if no receipts exist

Auto-derived transitions (server-set after events):

- OPEN/BACK-ORDERED → PARTIALLY-RECEIVED after first receipt
- PARTIALLY-RECEIVED → RECEIVED when fully received and no short-close exists
- PARTIALLY-RECEIVED → SHORT-CLOSED when fully closed and any short-close exists (even if receipts exist)

Receiving allowed only when PO status is:

- OPEN
- BACK-ORDERED
- PARTIALLY-RECEIVED

Cancellation rule:

- Allow cancel only if NO receipt events exist for that PO.

Quantity unit:

- All receipt and short-close quantities are in PACK COUNTS (same unit as purchase_order_lines.pack_count).
- Storage: decimal(18,6). Canonical scale = 6.
- Validation: quantity > 0, and per line: received_sum + short_closed_sum <= pack_count.

Derived status logic (exact precedence):

1. For each PO line: balance = pack_count - received_sum - short_closed_sum
2. If ALL balances = 0 and ANY short-close exists => PO status SHORT-CLOSED
3. Else if ALL balances = 0 and total received_sum > 0 => PO status RECEIVED
4. Else if ANY balance > 0 and ANY receipt exists => PO status PARTIALLY-RECEIVED
5. Else keep manual status (OPEN or BACK-ORDERED, or DRAFT)

Short-close ledger:

- Short-close creates NO stock_moves. It only affects balance/status.

Stock moves on receipt:

- Create stock_moves rows for each receipt line.
- stock_moves.type = RECEIPT
- stock_moves.status = POSTED
- stock_moves.source_type = "purchase_order_receipt_line"
- stock_moves.source_id = receipt line id

================================================================================
SCHEMA CHANGES — MIGRATIONS (EXPLICIT, REVERSIBLE, SEPARATE)
================================================================================

Create receipt event tables:

1. purchase_order_receipts (tenant-owned)
   Required columns:

- id (PK)
- tenant_id (FK tenants.id)
- purchase_order_id (FK to purchase_orders; use composite FK (purchase_order_id, tenant_id) -> purchase_orders.(id, tenant_id) where pattern exists)
- received_at (datetime, required)
- received_by_user_id (FK users.id, required)
  Optional:
- reference (string nullable)
- notes (text nullable)
- timestamps

2. purchase_order_receipt_lines (tenant-owned)
   Required columns:

- id (PK)
- tenant_id (FK tenants.id)
- purchase_order_receipt_id (FK purchase_order_receipts.id)
- purchase_order_line_id (FK purchase_order_lines.id) (standard FK) + validate tenant consistency in app logic
- received_quantity decimal(18,6) required
- timestamps
  Indexes: tenant_id on both; also receipt_id and purchase_order_line_id indexes.

Create short-close event tables:

3. purchase_order_short_closures (tenant-owned)

- id (PK)
- tenant_id (FK tenants.id)
- purchase_order_id (FK; composite where applicable)
- short_closed_at datetime required
- short_closed_by_user_id (FK users.id) required
- reference string nullable
- notes text nullable
- timestamps

4. purchase_order_short_closure_lines (tenant-owned)

- id (PK)
- tenant_id (FK tenants.id)
- purchase_order_short_closure_id (FK purchase_order_short_closures.id)
- purchase_order_line_id (FK purchase_order_lines.id)
- short_closed_quantity decimal(18,6) required
- timestamps

Stock moves changes:

- Add stock_moves.status string NOT NULL DEFAULT "POSTED" and backfill existing rows to POSTED in the migration.
- Add stock_moves.source_type string nullable and stock_moves.source_id bigint nullable (if not already present). Keep this migration separate from status migration. Add indexes on (source_type, source_id) if appropriate.

Enums/doc alignment:

- Update docs/ENUMS.md to include:
    - Purchase Order Status (values above)
    - Stock Move Status: DRAFT, SUBMITTED, POSTED
- Update docs/DB_SCHEMA.md to add new tables and new stock_moves columns.
- Update docs/ARCHITECTURE_INVENTORY.md and create/update any needed docs/architecture YAML ONLY if explicitly instructed AFTER CI; for now do not change docs unless the PR requires it and the human requests doc updates post-CI.
- Create PR-specific testing YAML under docs/testing/ for PR2-PUR-005 (per testing-standards.yaml guidance). (This is allowed as documentation ONLY if human has requested it; the human HAS requested it.)

Permissions:

- Add permission slug: purchasing-purchase-orders-receive
- Ensure gate enforcement:
    - purchasing-purchase-orders-create continues to control index/show + draft CRUD
    - purchasing-purchase-orders-receive controls: receive, short-close, status-change endpoints
- Index/show must NOT require both.

================================================================================
ROUTES / ENDPOINTS (JSON ONLY, AJAX PATTERN)
================================================================================

All are under /purchasing/orders/{purchaseOrder}:

Receiving (create receipt event):

- POST /purchasing/orders/{purchaseOrder}/receipts
  Payload supports:
  A) single-line (line button):
- received_at? (defaults now if omitted)
- reference? notes?
- purchase_order_line_id
- received_quantity
  B) multi-line (PO-level receive):
- received_at? (defaults now)
- reference? notes?
- lines: [{ purchase_order_line_id, received_quantity }]

Short-close (create short-close event):

- POST /purchasing/orders/{purchaseOrder}/short-closures
  Payload supports:
  A) single-line:
- short_closed_at? (defaults now)
- reference? notes?
- purchase_order_line_id
- short_closed_quantity
  B) multi-line optional (if you implement): lines array, same pattern

Status change (manual only):

- PATCH /purchasing/orders/{purchaseOrder}/status
  Payload:
- status
  Rules:
- allow DRAFT->OPEN, OPEN<->BACK-ORDERED
- allow OPEN->CANCELLED only if no receipts exist
- deny any attempt to set derived statuses directly (PARTIALLY-RECEIVED/RECEIVED/SHORT-CLOSED) (server controls these)
- return 422 with stable error shape

All 422 responses:

- top-level message
- errors keyed by field (including nested lines.0.received_quantity etc)

================================================================================
ELOQUENT MODELS / RELATIONSHIPS
================================================================================

Create models (tenant-owned, use HasTenantScope):

- PurchaseOrderReceipt
- PurchaseOrderReceiptLine
- PurchaseOrderShortClosure
- PurchaseOrderShortClosureLine

Add relationships:

- PurchaseOrder hasMany receipts, hasMany shortClosures
- Receipt hasMany lines
- ShortClosure hasMany lines
- Each line belongsTo purchase order line
- Ensure tenant consistency checks in service layer or domain logic

Ensure any new models are mass-assignment safe (fillable/guarded per project style).

================================================================================
SERVICE / DOMAIN LOGIC (NO FLOATS, BCMATH ONLY)
================================================================================

Implement a dedicated domain service (or follow existing patterns) for:

- Creating receipt events transactionally
- Creating stock_moves from receipt lines
- Computing per-line received_sum and short_closed_sum using BCMath scale=6
- Updating derived PO status after receipt/short-close events
- Enforcing all lifecycle rules above

Rules:

- Receive/short-close must be blocked unless PO is eligible state.
- Validate per-line remaining balance.
- Receiving creates stock_moves with quantity = received_quantity (packs) and item_id from purchase_order_lines.item_id.
- stock_moves.quantity stored decimal(18,6) but all computations use strings + BCMath.
- stock_moves.status always POSTED for receipts.
- stock_moves.source_type/source_id set as specified.

================================================================================
UI REQUIREMENTS (BLADE + PAGE MODULES, MATCH EXISTING STYLE)
================================================================================

Update Purchase Orders index:

- Add Status column.
- Row actions dropdown includes Receive and Status actions (contextual).
- Receive from index opens a slide-over (no dedicated /receive route required).
- Labels (exact): Receive, Open, Back-Order, Cancel, Short-Close
- Follow existing Purchase Orders page patterns verbatim (Breeze/Tailwind). No new colors/components.

Update Purchase Order show:

- Status control UI (also available here).
- PO-level Receive button (opens multi-line receive slide-over).
- Per-line Receive button (opens receive slide-over with that single line only).
- Per-line Short-Close button (opens separate short-close slide-over with that single line only).
- Separate slide-overs: Receive and Short-Close.
- Inputs allow decimal scale 6 and prefill with remaining open balance for that line.
- Show Receipt History table and Short-Close History table.

History table columns/order:

Receipt History:

1. Received At
2. Received By
3. Reference
4. Notes
5. Lines summary ("N lines, X total packs")

Short-Close History:

1. Short-Closed At
2. Short-Closed By
3. Reference
4. Notes
5. Lines summary ("N lines, X total packs")

AJAX behavior:

- No page reload.
- Update UI immediately after create/status change.
- Errors show inline and toast pattern per existing page patterns.

JS:

- Implement/update page modules under resources/js/pages/\*\* per contract.
- No dynamic string imports; use existing import.meta.glob loader.
- No optional-chaining assignment.
- Stable error object shapes (arrays always present).

================================================================================
TESTING (Pest Feature Tests — MIN 20 TESTS PER FILE)
================================================================================

Create Pest feature tests covering:

- Permission allow/deny for:
    - create gate: index/show + draft CRUD stays as-is
    - receive gate: receipt creation, short-close creation, status-change endpoint
- Status-change transitions allowed/denied (each transition positive + negative cases)
- Cancel blocked if any receipts exist
- Receiving allowed only in OPEN/BACK-ORDERED/PARTIALLY-RECEIVED; denied in DRAFT and terminal states
- Receipt creation creates:
    - receipt header + receipt lines
    - stock_moves rows with type=RECEIPT, status=POSTED, correct item_id, quantity, source_type/source_id
- Multiple receipts over time accumulate correctly
- Short-close allowed after partial receipt and closes remaining balance
- Derived status precedence:
    - becomes PARTIALLY-RECEIVED after first receipt when any balance remains
    - becomes RECEIVED when all balances zero and no short-close exists
    - becomes SHORT-CLOSED when all balances zero and at least one short-close exists (even with receipts)
- Validation errors:
    - quantity > 0
    - received + short_closed <= pack_count
    - nested lines validation keys (lines.0.received_quantity, etc)

Use factories/seed helpers as per repo patterns. Do not create global test helpers.

================================================================================
DELIVERABLES
================================================================================

Implement:

- All migrations (separate, reversible)
- Models + relationships
- Controllers/endpoints (JSON only)
- Domain/service logic with transactions
- Gates/permissions wiring + seeding updates (permissions + role mapping if required by existing patterns)
- UI changes (index/show) with page modules and slide-overs
- Receipt + short-close history display
- Tests (>=20 per file) and PR-specific testing YAML under docs/testing/

Output requirement:

- Provide full file contents for each new/modified file.
- Enumerate files changed at the top of your response.
- Do not include any instructions to run tests or CI.
