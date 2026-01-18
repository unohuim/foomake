# AI Rules

These rules govern all AI-assisted development on this repository.

---

## Workflow

- Always work on a branch per task.
- Before editing anything, ensure that you have presented a plan that has been approved, whether or not a plan was explicitly asked for.
- AI must be >95% certain of requirements before a plan may be proposed.
- Always run `./ci.sh` before proposing completion (unless explicitly told not to).

---

## Completion Gate (Non-Negotiable)

AI may NOT declare a task, PR, or change set “complete”, “finished”, or “ready”
until the human explicitly approves completion in this chat.

- Implementing code ≠ PR complete
- Writing tests ≠ PR complete
- Passing local reasoning ≠ PR complete

AI output must end in one of the following states:

- “Awaiting human review”
- “Awaiting approval to proceed”
- “Awaiting requested changes”

AI must never self-certify completion.

---

## Change Discipline

- Prefer the smallest possible change.
- Never refactor unless explicitly requested.
- No global JavaScript state unless explicitly approved.

---

## Standards

- PHP code must follow PSR-12.
- PHPDoc is required per project conventions.
- Existing architectural patterns must be respected.

---

## Certainty & Communication

- Never act without >95% certainty of requirements.
- Ask clarifying questions **one at a time** to increase certainty.
- Always state current certainty level before asking a question.
- Do not infer intent from partial context.

---

## Scope

These rules apply to **all** AI-assisted contributions to this repository, including:

- Code
- Configuration
- Documentation
- Tests
- Scripts
- CI / build tooling

They apply regardless of AI tool, model, or interface used.

---

## Prohibited Actions

The following actions are **not permitted** unless explicitly requested or approved:

- Making changes directly on the default branch
- Auto-committing or auto-merging changes
- Refactoring beyond the requested scope
- Introducing global state or hidden side effects
- Modifying architecture, dependencies, or conventions without approval
- Skipping tests or bypassing `./ci.sh`
- Proceeding with unclear or assumed requirements

---

## Review Responsibility

All AI-generated output is subject to human review.

- AI is responsible for proposing minimal, well-scoped, convention-aligned changes.
- The human reviewer is responsible for validating correctness and intent.
- No AI-generated change is complete until verified via `./ci.sh` (unless explicitly waived).

---

## Escalation & Uncertainty Handling

If requirements, constraints, or intent are unclear:

- Work must pause immediately.
- Current certainty level must be stated.
- Clarifying questions must be asked one at a time.
- No assumptions may be made to “move forward.”
- If clarity cannot be reached, the task must be escalated back to the human owner.

---

## Test-Driven Pull Requests

All pull requests are **test-driven by default**.

- Any change affecting behavior, logic, data, validation, auth, or APIs requires tests written first.
- Bug fixes require a failing test that reproduces the issue.
- Tests must express intent, not implementation details.

### UI and Content Changes

- Pure copy/layout changes do not require tests if no behavior is affected.
- Changes affecting legal, financial, or operational content require a lightweight feature test.
- Existing tests must be updated if they cover the modified behavior.

No implementation may begin until proposed tests are reviewed and approved.

---

## Creating New Abstractions

- Default to existing components and patterns.
- If a new abstraction is proposed, AI must:
    - State the problem it solves
    - Explain why existing inventory is insufficient
    - Propose the minimal public API
    - Describe at least two concrete future reuse cases

- No new abstraction may be created without explicit approval.
- Approved abstractions must be added to `docs/ARCHITECTURE_INVENTORY.md` in the same PR.
