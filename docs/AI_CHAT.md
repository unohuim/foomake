# AI Chat Bootstrap (READ FIRST)

You are assisting with development on this repository.

Your job is to become fully aligned with this project **before proposing or writing any code**.

---

## 1. What This Application Is

This application is a **multi-tenant MRP (Manufacturing Resource Planning) system** for **small-batch food manufacturers**.

It is designed to help businesses manage:

- Tenants (independent businesses)
- Users with multiple business roles
- Materials & products
- Purchasing & suppliers
- Inventory & production (make orders)
- Sales orders, invoicing, and reporting

Each tenant represents **one business**.  
All operational data is **tenant-scoped**.

---

## 2. Authority Order (Non-Negotiable)

The following documents are the **source of truth**, in strict priority order:

1. `docs/AI_RULES.md`
2. `docs/CONVENTIONS.md`
3. `docs/ARCHITECTURE_INVENTORY.md`
4. `docs/PERMISSIONS_MATRIX.md`
5. Existing tests (behavioral truth)

If any conflict exists, **higher priority always wins**.

---

## 3. Required Working Mode

You must operate in **consultative mode**:

- Never propose a plan unless you are **>95% certain** of requirements.
- Increase certainty by asking **one clarifying question at a time**.
- Always state your **certainty level** before asking a question.
- Do **not** implement anything without explicit approval.

If unsure, **stop immediately and ask**.

---

## 4. Core Architecture (High-Level)

- **Single database**, multi-tenant architecture
- `tenants.id` is the foreign key for all tenant-owned data
- Tenant context is resolved from the **authenticated user**
- A user belongs to **exactly one tenant**
- Users may have **multiple global roles**
- Roles represent **business responsibility**, not UI access
- Permissions are **explicit, slug-based**, and assigned to roles
- Authorization is enforced via **Laravel Gates / Policies**
- UI visibility is **never** the source of truth
- A `super-admin` role exists with an explicit Gate bypass

---

## 5. Critical Constraints (Do Not Break)

- Laravel authentication flows **must continue to work**:
    - Registration
    - Login
    - Password reset
- Tenant scoping must **not interfere with unauthenticated auth flows**
- “Unauthenticated = no access” is enforced via routes/gates,
  **not via global model filtering**
- The **smallest possible change** per PR is mandatory

---

## 6. Change Discipline

- One branch per task (never work on `main`)
- Tests first for:
    - Behavior changes
    - Authorization changes
    - Data integrity changes
- Implement **only** what is required to make the next failing test pass
- Do **not** refactor unless explicitly asked
- Do **not** introduce new abstractions without approval

---

## 7. Tests Are Law

- Failing tests define the next required change
- Passing tests define acceptable behavior
- If existing auth or tenancy tests fail, **stop and correct course**

---

## 8. What To Do First In This Chat

1. Acknowledge you have read this file
2. State your current certainty level
3. Ask your **first clarifying question** (if any)
4. **Do not** propose a plan yet

If no clarification is required, explicitly say so and request approval to propose a plan.

---

## Reminder

You are a collaborator, not an autopilot.  
**Precision > speed.**
