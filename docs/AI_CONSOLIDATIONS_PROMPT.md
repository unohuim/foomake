You are assisting with project documentation.

Create (or overwrite if it exists) the file: docs/AI_CHAT_BOOTSTRAP.md

Purpose: This single file consolidates **all core authoritative documents and the main routes file** required to bootstrap an LLM chat session (e.g. Grok, ChatGPT, Codex CLI, etc.) so the human doesn't have to paste multiple separate files every time.

Content structure:

1. Start with this exact header (verbatim):

# AI_CHAT_BOOTSTRAP.md — Core Project Context for LLM Sessions

This file is the single source to paste at the beginning of new LLM chats for full project alignment.

Paste the entire content (or as much as context allows) when starting a session.

Authority Order (highest to lowest — conflicts resolved by this order):

1. docs/AI_CHAT_CODEX.md
2. docs/PR2_ROADMAP.md
3. docs/CONVENTIONS.md
4. docs/ARCHITECTURE_INVENTORY.md
5. docs/PERMISSIONS_MATRIX.md
6. docs/ENUMS.md
7. docs/DB_SCHEMA.md
8. docs/UI_DESIGN.md
9. routes/web.php (main web routes — included here for complete bootstrap context)

10. Then, for each item in the above order, add a level-2 header exactly like this:

## docs/AI_CHAT_CODEX.md

(or ## routes/web.php for the last one)

Followed immediately by the **full, verbatim content** of that file/document.

Do NOT summarize, shorten, paraphrase, omit lines, or change formatting — copy the entire original text exactly as it exists in the repository.

- For Markdown files: preserve all headers, tables, lists, code blocks.
- For PHP files (routes/web.php): include the full code with all use statements, Route:: definitions, middleware groups, and comments — exactly as written.
- If any file does not yet exist (e.g., docs/AI_CHAT_CODEX.md), insert a placeholder comment under its header like:
