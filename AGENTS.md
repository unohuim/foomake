# AGENTS.md — Codex Repository Rules

These rules govern all Codex-assisted development on this repository.
This file exists to bootstrap new LLM sessions and enforce project invariants.

---

## Authoritative Inputs (Must Be Read First)

Before proposing a plan or writing any files, Codex MUST read and treat the following as authoritative:

### Core Context

- README.md
- docs/AI_RULES.md
- docs/CONVENTIONS.md
- docs/PR2_ROADMAP.md
- docs/ENUMS.md
- docs/UI_DESIGN.md

### Architecture (Primary Source of Truth)

- docs/architecture/README.yaml
- docs/architecture/**/**/\*.yaml

These YAML files define canonical domain rules, invariants, and approved abstractions.

### Architecture Inventory (Derived / Bootstrap Only)

- docs/ARCHITECTURE_INVENTORY.md

This file exists solely to help bootstrap new LLM chats.
It is **not** the primary source of architectural truth.

### Database (Order of Authority)

1. database/migrations/\*\* (source of truth)
2. docs/DB_SCHEMA.md (contextual reference only)

### Dependency Reality

- composer.lock
- package-lock.json

If any conflicts are detected between sources, work MUST pause and be escalated to the human.

---

## Workflow

- Always work on a branch per task.
- Before editing anything, Codex must present a plan and wait for human approval.
- Codex must be >95% certain of requirements before proposing a plan.
- Codex must never proceed based on inferred or partial intent.

---

## CI & Execution Policy (Strict)

- Codex MUST NOT run `./ci.sh` or any test/CI commands unless explicitly instructed.
- The human is responsible for running all CI, tests, and scripts.
- Codex MAY:
    - Create and modify test files
    - Propose improvements to tests
    - Propose the exact commands the human should run
- Codex must stop after writing code/tests and await human review.

---

## Completion Gate (Non-Negotiable)

Codex may NOT declare a task, PR, or change set “complete”, “finished”, or “ready”
until the human explicitly approves completion in chat.

Codex output MUST end in one of:

- “Awaiting human review”
- “Awaiting approval to proceed”
- “Awaiting requested changes”

Codex must never self-certify completion.

---

## Change Discipline

- Prefer the smallest possible change.
- Never refactor unless explicitly requested.
- No global JavaScript state unless explicitly approved.

---

## Standards

- PHP code must follow PSR-12.
- PHPDoc is required per project conventions.
- Existing architectural patterns and invariants must be respected.

---

## Certainty & Communication

- Never act without >95% certainty of requirements.
- Ask clarifying questions one at a time.
- Always state current certainty level before asking a question.
- Do not infer intent from partial context.

---

## Test-Driven Pull Requests

- All PRs are test-driven by default.
- Codex may write initial or scaffolded tests.
- Tests are expected to be refined collaboratively with the human.
- No implementation may begin until proposed tests are reviewed and approved.

### UI and Content Changes

- Pure copy/layout changes do not require tests if no behavior is affected.
- Changes affecting legal, financial, or operational content require a lightweight feature test.
- Existing tests must be updated if they cover modified behavior.

---

## Creating New Abstractions (Updated)

- Default to existing abstractions defined in `docs/architecture/**`.
- If a new abstraction is proposed, Codex MUST:
    - State the problem it solves
    - Explain why existing architecture YAML files are insufficient
    - Propose the minimal surface area
    - Identify the correct `docs/architecture/<domain>/` location

### Approval & Documentation Flow

- New abstractions require explicit human approval.
- Once approved:
    - A new YAML file MUST be added under `docs/architecture/**`
    - The file MUST follow the schema defined in `docs/architecture/README.yaml`
    - The abstraction MUST enforce invariants, not implementation trivia
- Afterward, `docs/ARCHITECTURE_INVENTORY.md` MUST be updated
  **only as a derived summary for future LLM bootstrapping**

---

## Prohibited Actions (Unless Explicitly Approved)

- Making changes directly on the default branch
- Auto-committing or auto-merging
- Refactoring beyond the approved scope
- Introducing global state or hidden side effects
- Modifying architecture, dependencies, or conventions
- Running CI, tests, or scripts
- Proceeding with unclear requirements

---

## File Update Discipline

When changing core logic, Codex should prefer rewriting entire files
rather than partial snippets, unless explicitly approved.
