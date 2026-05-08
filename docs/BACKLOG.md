# BACKLOG

This backlog captures outstanding product capabilities identified from competitive feature review and QuickBooks Online integration planning.

These items are not committed PR scope unless explicitly selected and approved.

---

## Priority Themes

1. Traceability and compliance
2. Warehouse execution
3. Barcode and label workflows
4. Forecasting and replenishment
5. Production planning depth
6. Costing and accounting depth
7. Document generation
8. Integrations, starting with QuickBooks Online

---

## 1. Traceability: Lot, Batch, Serial, and Expiry Tracking

### Problem

Food manufacturers need to trace which raw materials went into which finished goods, and where those finished goods were sold or shipped.

### Why It Matters

Traceability supports recalls, expiry control, customer trust, retail readiness, and compliance expectations.

### Desired Capability

- Lot/batch tracking for purchased materials
- Lot/batch tracking for manufactured finished goods
- Expiry dates
- Supplier lot references
- Customer shipment traceability
- Recall lookup: input lot → finished batches → customers/orders
- Finished batch genealogy

### Candidate PRs

- TRACE-001 — Lot Model + Tenant-Scoped Lot Records
- TRACE-002 — Receiving Creates/Assigns Lots
- TRACE-003 — Make Orders Consume Input Lots and Produce Output Lots
- TRACE-004 — Sales Orders Ship Specific Lots
- TRACE-005 — Recall Traceability Report

### Out of Scope Until Approved

- Regulatory certification workflows
- Automated recall notifications
- GS1 compliance

---

## 2. Warehouse Management: Locations, Bins, and Freezers

### Problem

A single on-hand quantity is not enough once inventory exists across multiple freezers, shelves, rooms, warehouses, or production areas.

### Why It Matters

Location-aware inventory prevents stock confusion, supports picking accuracy, and makes physical operations easier to manage.

### Desired Capability

- Warehouse/location records
- Bin/freezer/shelf records
- Inventory by item + location
- Receiving into a location
- Moving stock between locations
- Picking stock from a location
- Location-aware inventory counts

### Candidate PRs

- WH-001 — Locations and Bins Foundation
- WH-002 — Receive Purchase Order Lines Into Location
- WH-003 — Internal Stock Transfers
- WH-004 — Location-Aware Inventory Counts
- WH-005 — Sales Picking From Location

### Out of Scope Until Approved

- Mobile warehouse app
- Wave picking
- Advanced fulfillment routing

---

## 3. Barcode Scanning and Label Printing

### Problem

Manual entry becomes slow and error-prone as inventory, receiving, picking, and production volume increases.

### Why It Matters

Barcode workflows reduce mistakes, speed up receiving and picking, and make warehouse work easier for non-technical operators.

### Desired Capability

- Barcode value storage on items, lots, locations, and orders
- Scan-to-receive
- Scan-to-pick
- Scan-to-count
- Printable item labels
- Printable lot labels
- Printable location/bin labels

### Candidate PRs

- BAR-001 — Barcode Fields and Lookup Service
- BAR-002 — Label Template Foundation
- BAR-003 — Print Item and Lot Labels
- BAR-004 — Scan-to-Receive Workflow
- BAR-005 — Scan-to-Count Workflow

### Out of Scope Until Approved

- Dedicated mobile app
- Hardware-specific scanner integrations
- GS1 barcode generation

---

## 4. Forecasting, Replenishment, and Shortage Planning

### Problem

The system tracks operational truth, but users also need to know what to buy or make next.

### Why It Matters

Forecasting and replenishment prevent stockouts, reduce overproduction, and create the core planning value expected from an MRP system.

### Desired Capability

- Current availability report
- Shortage report
- Reorder points
- Preferred supplier per material
- Suggested purchase quantities
- Suggested make orders
- Demand from sales orders
- Demand from forecasted sales
- Time-phased material requirements

### Candidate PRs

- PLAN-001 — Availability and Shortage Report
- PLAN-002 — Reorder Points and Preferred Suppliers
- PLAN-003 — Suggested Purchase Orders
- PLAN-004 — Suggested Make Orders
- PLAN-005 — Forecast Demand Inputs
- PLAN-006 — Time-Phased MRP Planning

### Out of Scope Until Approved

- Machine-learning forecasting
- Auto-generated purchase orders without user approval
- Capacity-aware planning

---

## 5. Production Planning: Routing, Scheduling, and Capacity

### Problem

Recipes define what materials are required, but not how production flows through equipment, labour, time, or constrained work centers.

### Why It Matters

Scheduling helps answer what should be made today, where bottlenecks exist, and whether production capacity can satisfy demand.

### Desired Capability

- Production steps/routing
- Work centers
- Standard run time per step
- Setup time
- Labour requirements
- Production calendar
- Scheduled make orders
- Capacity visibility
- Simple production queue

### Candidate PRs

- PROD-001 — Work Centers Foundation
- PROD-002 — Recipe Routing Steps
- PROD-003 — Make Order Scheduling Fields
- PROD-004 — Production Calendar View
- PROD-005 — Capacity Conflict Warnings

### Out of Scope Until Approved

- Full finite-capacity scheduling engine
- Drag-and-drop Gantt chart
- Maintenance planning
- Subcontracting

---

## 6. Costing and Accounting Depth

### Problem

The system tracks inventory movement and pricing snapshots, but deeper financial views are needed for product margin, valuation, and accounting confidence.

### Why It Matters

Manufacturers need to understand true product cost, gross margin, inventory value, and financial impact before scaling.

### Desired Capability

- Product cost rollups from recipes
- Actual cost from purchase order receipts
- Inventory valuation
- COGS calculation
- Gross margin reporting
- WIP visibility
- FIFO or weighted-average costing decision
- Labour and overhead cost layers

### Candidate PRs

- COST-001 — Costing Method Decision Record
- COST-002 — Recipe Standard Cost Rollup
- COST-003 — Receipt-Based Actual Material Cost
- COST-004 — Inventory Valuation Report
- COST-005 — Sales Margin Report
- COST-006 — WIP Cost Tracking

### Out of Scope Until Approved

- Full general ledger
- Payroll costing
- Tax filing

---

## 7. Document Generation: PDFs, Templates, and Labels

### Problem

Manufacturing businesses still rely on documents for suppliers, customers, warehouse work, and compliance records.

### Why It Matters

Printable documents are often required for purchase orders, invoices, packing slips, receiving records, production sheets, and labels.

### Desired Capability

- Purchase order PDFs
- Sales order PDFs
- Invoice PDFs
- Packing slips
- Receiving documents
- Production batch sheets
- Inventory count sheets
- Label templates
- Email-ready document attachments

### Candidate PRs

- DOC-001 — PDF Rendering Foundation
- DOC-002 — Purchase Order PDF
- DOC-003 — Sales Order and Invoice PDF
- DOC-004 — Packing Slip PDF
- DOC-005 — Production Batch Sheet PDF
- DOC-006 — Label Template System

### Out of Scope Until Approved

- Full visual template editor
- E-signatures
- Customer portal document sharing

---

## 8. QuickBooks Online Integration

### Problem

The MRP system manages operational truth, but accounting systems need clean financial records without duplicate manual entry.

### Why It Matters

QuickBooks Online integration reduces admin work, improves bookkeeping accuracy, and makes the product easier to adopt for small manufacturers.

### Desired Capability

- OAuth connection to QuickBooks Online
- Tenant-level QBO connection settings
- Customer sync
- Supplier/vendor sync
- Item/product sync
- Invoice push
- Bill or purchase transaction push
- Payment status pull or webhook sync
- Chart of accounts mapping
- Sync logs and failure visibility
- Idempotent retry behavior

### Candidate PRs

- QBO-001 — QBO App Connection and OAuth
- QBO-002 — QBO Mapping Tables and Sync Log
- QBO-003 — Customer and Supplier Sync
- QBO-004 — Item/Product Sync
- QBO-005 — Invoice Push to QBO
- QBO-006 — Purchase Order / Bill Push to QBO
- QBO-007 — Payment Status Sync
- QBO-008 — Sync Error Dashboard

### Core Decisions Required

- Is this system the operational source of truth?
- Is QBO the financial source of truth?
- Should inventory be tracked in QBO or only in this system?
- Should received purchase orders create QBO bills?
- Should sales orders create QBO invoices immediately or only after shipment/completion?
- Should QBO edits be pulled back, blocked, or treated as accounting-only changes?

### Out of Scope Until Approved

- Payroll
- Tax filing
- Full accounting ledger replacement
- Multi-currency accounting automation beyond explicit mapped transactions

---

## Suggested Sequencing

### Near-Term Commercial Gaps

1. QBO-001 — QBO App Connection and OAuth
2. DOC-001 — PDF Rendering Foundation
3. TRACE-001 — Lot Model + Tenant-Scoped Lot Records
4. WH-001 — Locations and Bins Foundation

### Mid-Term Operational Depth

1. BAR-001 — Barcode Fields and Lookup Service
2. PLAN-001 — Availability and Shortage Report
3. COST-001 — Costing Method Decision Record
4. PROD-001 — Work Centers Foundation

### Later Competitive Parity

1. Time-phased MRP planning
2. Full traceability reporting
3. Capacity planning
4. Inventory valuation
5. QBO invoice/bill/payment automation

---

## Notes

- These backlog items should be converted into approved PR scopes before implementation.
- Each PR should remain small, test-first, and tenant-safe.
- Documentation updates should only happen when explicitly required and approved.
- Any reusable abstraction introduced by these PRs must be recorded in the architecture inventory when applicable.
