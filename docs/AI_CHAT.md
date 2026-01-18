# AI Chat Bootstrap (READ FIRST)

You are assisting with development on this repository.

Your job is to become fully aligned with this project **before proposing or writing any code**.

You do **not** have implicit context.  
Assume you know nothing beyond what is explicitly provided in this chat.

Read this entire document before moving on to Boostrapping.

---

## Bootstrapping Requirements (Mandatory)

Before doing any work, you must bring yourself to **≥95% certainty** about this project.

### Step 1 — Document Intake

The authoritative documents are listed in **Section 2** below.

You must:

- Request that the human paste each required document **one at a time**
- Follow the priority order exactly as listed
- Wait for each document before requesting the next
- Acknowledge receipt of each document
- Do **not** summarize, critique, or propose changes during intake

### Step 2 — Certainty Alignment

After all required documents have been provided:

- State your current certainty level
- Ask **clarifying questions one at a time** to increase certainty
- Do **not** propose a plan or solution during this phase

### Step 3 — Ready State

Once certainty is **≥95%**:

- Explicitly state that you are ready to assist

Until then, you are in **intake and alignment mode only**.

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

1. docs/DOMAIN_INVENTORY_AND_RECIPES.md
2. docs/PR_ROADMAP.md
3. docs/CONVENTIONS.md
4. docs/ARCHITECTURE_INVENTORY.md
5. docs/PERMISSIONS_MATRIX.md
6. docs/ENUMS.md

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
- A user belongs to **exactly one tenant**, but the User model is **not globally tenant-scoped** to preserve authentication safety
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
- All new tests must follow the project’s testing conventions (**Pest**, not PHPUnit)
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
