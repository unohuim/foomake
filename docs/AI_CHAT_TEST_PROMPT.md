You are assisting on the current PR.
You already have full context of the PR scope, decisions, constraints, and out-of-scope items from the conversation so far.

Your task

Generate ONE Codex-ready prompt whose sole purpose is to update or add tests for THIS PR.

You are not to write tests or code.
You are writing a prompt that will be given to Codex.

MANDATORY BEHAVIOR

The prompt you generate MUST:

Be specific to THIS PR

Restate the PR goal, scope, decisions, and exclusions accurately.

Do not ask the human for PR details — you already have them.

Be hard-gated and non-skippable

Force Codex to acknowledge requirements before writing anything.

Prevent partial completion.

Prevent early “awaiting review” exits.

Produce complete and sufficient tests for THIS PR

No shortcuts

No missing contract coverage

No ambiguous validation rules

STRUCTURE YOU MUST PRODUCE

The Codex prompt MUST include all of the following sections, in this order:

1. Context / Scope (PR-Specific)

Clear restatement of what THIS PR adds/changes

Explicit behavioral rules and decisions

Explicit out-of-scope list

2. Operating Constraints

Tests only

Read project docs first (all docs/\*.md and any test conventions docs) BEFORE editing

Deterministic tests only (no time, randomness, external calls)

PSR-12 compliance

No global state

One cohesive suite per file

Full-file outputs only (no diffs/snippets)

Explicit list of allowed test files (exact paths)

Conflict Resolver: Authorization/Tenancy Coverage Overrides “No Redundant Echo Tests”

The “no redundant echo tests” rule DOES NOT apply to:

authorization matrices (guest vs authed-no-permission vs authed-with-permission vs super-admin if applicable)

tenancy isolation matrices (same-tenant ok vs cross-tenant blocked)

For these, explicit per-endpoint allow/deny tests are mandatory even if repetitive.

Quantitative Enforcement Gates (Hard Checks)

Codex MUST include these gates in its own output and treat failures as INCOMPLETE:

Per-file test count gate

For every test file you create/modify, output the number of it(...) tests in that file.

Any file with < 20 tests is INCOMPLETE (unless the repo docs explicitly forbid it; if so, cite the doc and ask exactly one clarifying question in the acknowledgment step).

Endpoint×Verb Auth+Tenancy checklist gate

You MUST output a checklist matrix of every endpoint×verb in scope (including line endpoints).

For each cell, you must name the exact test(s) that cover:

Guest blocked

Authed without permission blocked

Authed with permission allowed

Cross-tenant blocked (where applicable)

Any cell without an explicit test name is INCOMPLETE.

3. ACKNOWLEDGMENT GATE (Mandatory)

The prompt MUST instruct Codex to:

Respond FIRST with a checklist

Mark each item as ACKNOWLEDGED

Ask EXACTLY ONE clarifying question if anything is unclear

REFUSE to edit files until acknowledgment is complete

After the human answers, proceed without further questions

Additional mandatory acknowledgments to include in the checklist:

ACK that authorization/tenancy per-endpoint tests are required even if repetitive

ACK that the ≥20 tests-per-file gate is mandatory (or cite the doc that conflicts)

ACK that the endpoint×verb auth+tenancy checklist must be output and must fully map to test names

4. Minimum Coverage Matrix (Mandatory, PR-Specific)

The Codex prompt MUST include a “Minimum Coverage Matrix” that defines what “complete and sufficient” means.

Rules:

Codex MUST build a matrix where EACH PR behavior/decision is mapped to tests across these categories:
A) Request validation (422/403/404 etc.)
B) Normalization/defaulting behavior (request input → stored value)
C) Persistence/DB contract (stored fields match expected representation)
D) API contract (response keys + values)
E) Read contract via HTTP (GET/SHOW/INDEX)

Hard constraints:

For EVERY behavior that affects externally visible data (anything that the API returns or UI consumes),
Codex MUST include at least ONE end-to-end test that flows:
REQUEST (store/update) → DB ASSERTIONS → READ VIA HTTP (show/index) ASSERTIONS

“Read contract” tests MUST NOT be satisfied by creating DB rows directly and then asserting GET echoes them.
At least ONE read test MUST be driven by store/update inputs for each surfaced/changed field set.

Codex MUST NOT add redundant “echo tests” that re-assert the same seeded-data shape repeatedly.
If multiple tests assert the same GET keys/values without introducing a distinct behavior, they are NOT allowed.
Exception: this redundancy prohibition does not apply to the explicit auth/tenancy endpoint matrices described above.

Output format inside the Codex prompt:

A table or bullet matrix listing:

Behavior/Decision ID

Tests covering A/B/C/D/E (by test name)

Marked COVERED / NOT COVERED

Codex MUST treat any NOT COVERED entry as blocking.

5. Numbered, Non-Skippable Requirements (PR-Specific)

Convert the PR scope into numbered requirements such that:

Each requirement maps to ≥1 test

Each requirement is written so it can be verified by tests (no vague language)

Codex cannot claim completion unless EVERY number is satisfied

Include, when applicable:

Create behavior

Update behavior

Validation rules (including boundary cases)

Normalization/defaulting rules

Persistence/DB contract

Read/GET payload contract

Negative guarantees (things that must NOT happen)

Multi-tenant scoping and permissions, if in scope

Additionally, require:

An explicit numbered requirement for the endpoint×verb auth+tenancy checklist gate being 100% satisfied.

An explicit numbered requirement for the per-file ≥20 tests gate being satisfied.

6. Completion Gate (Mandatory)

Codex MUST follow these rules:

Codex may NOT say “awaiting review” unless ALL numbered requirements and the Minimum Coverage Matrix are satisfied AND both quantitative enforcement gates are satisfied.

If anything is missing, Codex MUST say:
INCOMPLETE: <short reason>
Then list exactly what remains and where it should be added.

Codex MUST NOT stop early after writing “some tests.”

Codex MUST update the matrix to ALL COVERED before claiming done.

7. Deliverable Format

Require Codex to output, in this exact order:

Files changed/created (exact paths)

The finalized Minimum Coverage Matrix (COVERED / NOT COVERED)

Numbered requirements checklist (SATISFIED / UNSATISFIED)

Endpoint×Verb Auth+Tenancy checklist (every cell mapped to test names; otherwise INCOMPLETE)

Per-file test count report (each file’s it(...) count; any <20 => INCOMPLETE)

Then STOP. No extra commentary.

HARD RULES

Do not weaken requirements.

Do not ask the human for missing PR info.

Do not produce multiple prompt variants.

Do not include commentary or explanations.

Output only the final Codex prompt.

SUCCESS CRITERIA

If Codex follows the prompt correctly:

The resulting tests would be considered complete and sufficient for THIS PR

No additional clarification would be required

The test suite avoids redundant echo-coverage while still producing explicit per-endpoint auth/tenancy matrices

The quantitative gates prevent “minimize redundancy” from skipping required explicit coverage
