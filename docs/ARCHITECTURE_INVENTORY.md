# Architecture Inventory

This document tracks reusable abstractions, components, and patterns used throughout the project.

Its purpose is to prevent duplication, improve discoverability, and provide a shared mental model for both human and AI contributors.

---

## How to Use This Document

- Before creating a new abstraction, review this inventory to determine whether an existing solution already exists.
- When introducing a new reusable component, add an entry here as part of the same PR.
- Entries should be concise, factual, and descriptive—this is an index, not a tutorial.

---

## Entry Requirements

Each inventory entry must include:

- **Name**
- **Type** (e.g. Service, Action, DTO, Helper, Blade Component, JS Module)
- **Location** (file path)
- **Purpose**
- **When to Use**
- **When Not to Use**
- **Public API / Interface**
- **Example Usage**

---

## Inventory

> _No reusable abstractions have been formally registered yet._

New entries will appear here as the project evolves.

---

## Authorization Layer (Planned)

**Name:** Global Role + Domain Authorization Layer  
**Type:** Authorization Pattern (Gates / Policies)  
**Location:** `app/Providers/AuthServiceProvider.php`, `app/Policies/*`

**Purpose:**  
Provide a centralized, testable authorization mechanism based on global roles and business domains.

**When to Use:**

- Any access control decision
- Any domain-level permission check

**When Not to Use:**

- UI-only visibility decisions without backend enforcement

**Public Interface:**

- Gate abilities (e.g. `view-inventory`, `manage-sales`)
- Policy methods where appropriate

**Example Usage:**

```php
Gate::authorize('manage-inventory');
```
