# Make Orders (Manufacturing)

## Overview

Persisted Make Orders support planning and execution with a defined lifecycle:
DRAFT → SCHEDULED → MADE.

## Lifecycle

### DRAFT

- Create a Make Order record.
- No stock moves are created.

### SCHEDULED

- Set due_date on a draft order.
- Status changes to SCHEDULED.
- No stock moves are created.

### MADE

- ExecuteRecipeAction issues inputs and receipts output.
- Status changes to MADE.
- made_by_user_id and made_at are set.
- Order is locked from further actions.

## Rules

- Tenant-scoped access on all reads/writes.
- Active recipe required for create, schedule, and make.
- Make is idempotent; repeated make returns an error and creates no moves.
- Stock moves use source_type = make_order.
