You are assisting with project documentation.

Create (or overwrite if it exists) the file: docs/AI_CHAT_BOOTSTRAP.md

Purpose: This single file consolidates the core project bootstrap documents required to align future LLM chat sessions with the repository's current authoritative context.

Do not generate summaries.
Do not paraphrase.
Do not omit lines.
Do not alter formatting.
Do not strip code blocks, tables, comments, use statements, routes, YAML, or markdown structure.

The generated file must preserve source documents verbatim.

Content structure:

1. Start with this exact header (verbatim):

# AI_CHAT_BOOTSTRAP.md — Core Project Context for LLM Sessions

This file is the single source to paste at the beginning of new LLM chats for full project alignment.

Paste the entire content (or as much as context allows) when starting a session.

2. After that header, include the required source files in this order.

Core authority/bootstrap order:

1. docs/AI_CHAT_CODEX.md
2. docs/PR2_ROADMAP.md
3. docs/CONVENTIONS.md
4. docs/ARCHITECTURE_INVENTORY.md
5. docs/PERMISSIONS_MATRIX.md
6. docs/ENUMS.md
7. docs/DB_SCHEMA.md
8. docs/UI_DESIGN.md
9. routes/web.php
10. docs/PR3_ROADMAP.md
11. docs/BACKLOG.md

Important authority note:

- Preserve the repository's documented authority order.
- Do not claim that docs/architecture/\*_/_.yaml outrank higher-authority files unless the repository explicitly says so.
- docs/PR3_ROADMAP.md is required context and must not be treated as optional or secondary.

3. For each included source file, add a level-2 header exactly like this:

## docs/AI_CHAT_CODEX.md

Follow that header immediately with the full original file contents.

Do not add commentary between the header and the file contents.

4. Verbatim-copy rules:

- Copy each included source file exactly as it exists in the repository.
- Preserve all Markdown formatting, tables, code fences, comments, spacing, and blank lines.
- Preserve all PHP use statements, route definitions, middleware groups, closures, and comments in routes/web.php exactly as written.
- Preserve all YAML formatting exactly as written.
- Do not normalize whitespace.
- Do not reorder file contents internally.

5. Missing-file behavior:

If any expected file does not exist, still include its level-2 header and then place this exact placeholder comment immediately below it:

<!-- File not found in repository at generation time. -->

Do not fail the whole consolidation because one expected file is missing.

6. Architecture glob behavior:

7. Output shape requirement:

The generated docs/AI_CHAT_BOOTSTRAP.md file must be a pure concatenation of:

- the required bootstrap header block
- then repeated sections of:
    - level-2 header with exact path
    - full verbatim file contents or the missing-file placeholder comment

8. Regeneration discipline:

Regenerate docs/AI_CHAT_BOOTSTRAP.md whenever any included source document changes, including:

- docs/AI_CHAT_CODEX.md
- docs/PR2_ROADMAP.md
- docs/CONVENTIONS.md
- docs/ARCHITECTURE_INVENTORY.md
- docs/PERMISSIONS_MATRIX.md
- docs/ENUMS.md
- docs/DB_SCHEMA.md
- docs/UI_DESIGN.md
- routes/web.php
- docs/PR3_ROADMAP.md
- docs/BACKLOG.md

9. Do not summarize the repository.

The goal is not to create a shortened overview.
The goal is to create a consolidated bootstrap file containing the exact source texts future LLM sessions need for accurate intake.
