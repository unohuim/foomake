# CODEX_BOOTSTRAP.md — Direct Bootstrap for Codex CLI

Paste this directly into Codex CLI at the start of a session.

Codex has full repository file access.

Codex is an execution agent that must follow strict intake, PR alignment, Certainty Mode, **test-first enforcement**, PR discipline, and documentation rules.

---

## Operating Mode (Non-Negotiable)

Codex must assume it knows nothing about the current task.

Codex may not plan, propose, write tests, or write code until:

1. Document intake is complete
2. The PR is identified
3. The PR scope is restated verbatim and approved
4. Certainty Mode is completed (all AOCs ≥95%)
5. Explicit approval is given to propose a plan

Until then, Codex is in alignment mode only.

---

## Authority Order (Highest → Lowest)

When conflicts exist, this order wins:

1. docs/AI_CODEX_BOOTSTRAP.md
2. docs/PR2_ROADMAP.md
3. docs/CONVENTIONS.md
4. docs/ARCHITECTURE_INVENTORY.md
5. docs/PERMISSIONS_MATRIX.md
6. docs/ENUMS.md
7. docs/DB_SCHEMA.md
8. docs/UI_DESIGN.md
9. docs/architecture/README.yaml
10. docs/architecture/ui/PageModuleContract.yaml
11. docs/testing/testing-standards.yaml
12. docs/testing/\*.yaml
13. routes/web.php

For architecture invariants, priority is:

CONVENTIONS → ENUMS → architecture yaml → code

For testing standards/invariants, priority is:

docs/testing/testing-standards.yaml → docs/testing/\*.yaml → code

---

## Step 1 — Mandatory Document Intake

Codex must open and read every file in the Authority Order (including `docs/testing/testing-standards.yaml` and all `docs/testing/*.yaml`).

Codex must not summarize, plan, or propose changes.

After intake, Codex’s only response must be:

Document intake complete. Which PR are we working on?

---

## Step 2 — PR Identification

When the human provides the PR:

Codex must restate the entire PR scope verbatim.

Codex must not interpret, summarize, or rephrase.

Codex must wait for approval.

---

## Step 3 — Enter Certainty Mode (PR-Scoped Only)

Only after the PR scope restatement is approved, Codex enters **Certainty Mode**.

Certainty Mode is based only on the PR scope.

Codex’s job is to reach ≥95% certainty across all Areas of Certainty (AOC) before proposing any plan.

---

## Areas of Certainty (AOC)

Codex must track certainty for:

1. PR Identity
2. User Outcome
3. User Workflow
4. UX Requirements
5. Data & Schema
6. Models & Relationships
7. Domain Rules / Invariants
8. Authorization
9. Routes & Controllers
10. UI / Interaction Pattern
11. UI Aesthetics
12. Validation & Error Handling
13. Testing Plan
14. Documentation Impact

---

## Required Response Format (Every Turn in Certainty Mode)

### Areas of Certainty (AOC)

List each AOC with current certainty %.

### Assumptions

List all assumptions currently being made.
Anything not listed here is not assumed.

### Next Question

Ask exactly one question that will increase certainty in any AOC that is <95%.

Do not ask multiple questions.
Do not plan.
Do not suggest implementation.
Do not summarize.

---

## Certainty Rules

- Codex may choose any AOC <95% to ask about
- Human answers may increase multiple AOCs
- After each answer, Codex must recompute all certainty levels
- Once all AOCs are ≥95%, Codex must say:

All Areas of Certainty are ≥95%. Requesting approval to propose a plan.

Codex must wait for approval.

---

## Step 4 — Planning (After Approval)

Only after approval to propose a plan:

Codex may outline the PR plan.

No tests or code may be written yet.

---

## Step 5 — Test-First Enforcement (Critical)

After the plan is approved, Codex must **write tests before any implementation**.

When instructed to write tests, Codex must obey the following **Test Writing Protocol**.

---

## Test Writing Protocol (Mandatory)

When approved to write tests for the PR, Codex must:

### 0. Testing Standards Intake (Non-Negotiable)

Before creating or editing any test file:

- Read `docs/testing/testing-standards.yaml` and **treat it as the canonical template**
- Read all `docs/testing/*.yaml` and apply any additional rules
- If `docs/testing/*.yaml` conflicts, `docs/testing/testing-standards.yaml` wins

Codex must structure and write tests to match the patterns, naming, assertions style, and file organization described in `docs/testing/testing-standards.yaml`.

### 1. Restate PR Context

Accurately restate:

- What the PR adds/changes
- Behavioral rules and decisions
- Explicit out-of-scope items

### 2. Operating Constraints

- Tests only
- Must comply with `docs/testing/testing-standards.yaml` and all `docs/testing/*.yaml`
- Deterministic tests only
- PSR-12 compliant
- No global state
- One cohesive suite per file
- Full-file outputs only
- Explicit list of test file paths before editing

### Authorization/Tenancy Override Rule

The “no redundant echo tests” rule does NOT apply to:

- Authorization matrices
- Tenancy isolation matrices

Explicit per-endpoint allow/deny tests are mandatory.

---

### Quantitative Enforcement Gates

#### Per-File Test Count Gate

For each file, output `it(...)` count.

Any file with <20 tests is INCOMPLETE (unless a repo doc forbids it — cite it and ask one question).

#### Endpoint × Verb Auth + Tenancy Gate

Output a matrix of every endpoint × verb in scope and list the exact tests covering:

- Guest blocked
- Authed without permission blocked
- Authed with permission allowed
- Cross-tenant blocked

Any missing cell is INCOMPLETE.

---

### ACKNOWLEDGMENT GATE (Before Editing)

Codex must first output a checklist marked ACKNOWLEDGED and ask exactly one clarifying question if needed.

Must acknowledge:

- `docs/testing/testing-standards.yaml` is the canonical template for tests
- All `docs/testing/*.yaml` must be followed
- Auth/tenancy repetition requirement
- ≥20 tests-per-file requirement
- Endpoint×verb matrix requirement

No file edits before acknowledgment.

---

### Minimum Coverage Matrix

For each PR behavior/decision, map tests across:

A) Validation
B) Normalization/defaulting
C) Persistence/DB contract
D) API contract
E) Read via HTTP

At least one test must flow:

REQUEST → DB ASSERTIONS → HTTP READ ASSERTIONS

Echo tests are not allowed except for auth/tenancy matrices.

Any NOT COVERED entry blocks completion.

---

### Numbered Non-Skippable Requirements

Convert PR scope into numbered requirements.

Each must map to ≥1 test and be verifiable.

Must include:

- Create/update behavior
- Validation and boundaries
- Normalization
- DB contract
- GET contract
- Negative guarantees
- Tenant and permission rules
- Endpoint×verb gate satisfied
- ≥20 tests-per-file gate satisfied

---

### Completion Gate

Codex may not say “awaiting review” unless:

- All requirements satisfied
- Matrix fully covered
- Both quantitative gates satisfied

Otherwise must output:

INCOMPLETE: <reason>

---

### Deliverable Format (Exact Order)

1. Files changed/created
2. Minimum Coverage Matrix
3. Numbered requirements checklist
4. Endpoint×Verb Auth/Tenancy matrix
5. Per-file test count report

Then STOP.

---

## Step 6 — Implementation (After Tests Approved)

Only after tests are approved may Codex implement the PR.

Implementation plan must include:

- All classes
- All migrations
- All policies/gates
- All relationships
- Full tenant correctness
- Adherence to CONVENTIONS, ENUMS, DB_SCHEMA

Smallest possible change per PR.

---

## Post-CI Documentation Stage (Conditional)

After CI passes, Codex must ask:

Are documentation updates required for this PR?

If yes, update only when instructed.

If architecture changed:

- Update/create docs/architecture/_/_/\*.yaml
- Follow docs/architecture/README.yaml
- Update docs/ARCHITECTURE_INVENTORY.md

---

## What Codex Must Never Do

- Never write code without approval
- Never write tests before planning approval
- Never update docs without approval
- Never run tests
- Never summarize the PR scope
- Never propose plans before all AOCs ≥95%
- Never create/edit tests without first applying `docs/testing/testing-standards.yaml` + `docs/testing/*.yaml`
