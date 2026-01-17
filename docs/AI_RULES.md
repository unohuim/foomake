# AI Rules

These rules govern all AI-assisted development on this repository.

---

## Workflow

- Always work on a branch per task.
- Before editing anything, ensure that you have presented a plan that has been approved, whether or not a plan was explicitly asked for.
- AI must be 95% certain of requirements before a plan may be proposed.
- Always run `./ci.sh` before proposing completion.

---

## Change Discipline

- Prefer smallest change; never refactor unless asked.
- No global JS state unless requested.

---

## Standards

- PSR-12 + PHPDoc rules.

---

## Certainty & Communication

- Never do anything without being > 95% certain of what's required.
- Ask questions, one at a time, to increase certainty.
- Always display certainty level before asking a question.

---

## Scope

These rules apply to all AI-assisted contributions to this repository, including code, configuration, documentation, tests, scripts, and build or CI-related changes, regardless of tool or model used.

---

## Prohibited Actions

The following actions are not permitted unless explicitly requested or approved:

- Making changes directly on the default branch
- Auto-committing or auto-merging changes
- Refactoring or restructuring code beyond the requested scope
- Introducing global state or side effects without approval
- Modifying architectural patterns, dependencies, or conventions without consent
- Skipping tests or bypassing `./ci.sh`
- Making assumptions when requirements are unclear

---

## Review Responsibility

All AI-generated changes are subject to human review and approval.

- The AI is responsible for proposing changes that are minimal, well-scoped, and aligned with existing conventions.
- The human reviewer is responsible for validating correctness, intent, and impact before merging.
- No AI-generated change is considered complete until it has been reviewed, approved, and verified through `./ci.sh`.
- Any uncertainty or ambiguity must be surfaced before review, not after.

---

## Escalation / Uncertainty Handling

When requirements, intent, or constraints are unclear, work must pause.

- Uncertainty must be explicitly stated along with the current certainty level.
- Clarifying questions must be asked one at a time.
- No assumptions may be made to “move forward.”
- If clarity cannot be achieved, the task must be escalated back to the human owner before any changes are made.

---

## Test-Driven Pull Requests

All pull requests are test-driven by default.

- For any change that affects behavior, logic, data, validation, authentication, authorization, or APIs, tests **must be written first**.
- Bug fixes require a failing test that reproduces the issue before any implementation work begins.
- Tests should clearly express intent and failure conditions relevant to the PR scope.

### UI and Content Changes

- Pure copy, markup, or layout changes **do not require new tests** if no behavior is affected.
- If the change impacts legally, financially, or operationally sensitive content (e.g. pricing, guarantees, checkout, compliance text), add a lightweight feature test asserting presence.
- If existing UI or feature tests already cover the area, update them to reflect the change.

No implementation work may begin until the proposed tests have been reviewed and approved as part of the PR plan.

---

## Creating New Abstractions

- Default to using existing components and patterns.
- If a new reusable abstraction seems beneficial, the AI must:
    - State the problem it solves
    - Explain why existing inventory items are insufficient
    - Propose the minimal public API
    - Describe expected reuse (at least 2 concrete future uses)
- No new abstraction may be created until the proposal is reviewed and explicitly approved.
- If approved, the new abstraction must be added to `docs/ARCHITECTURE_INVENTORY.md` in the same PR.
