# Query Keyword Processing Strategy

## Purpose

Use Google Search Console query data as market-language input, not only SEO input.

Search queries show how buyers, operators, and evaluators describe the problems FooMake may solve. A query should be classified by intent before deciding whether it belongs as a feature page, solution page, learn article, help page, video, comparison page, or homepage section.

## Core Principle

A keyword is not automatically a blog post.

Each query should answer two questions:

- What is the searcher trying to accomplish?
- What page or asset would best satisfy that intent and move the visitor toward activation?

## Page-Type Decision Rules

### Feature Pages

Use a feature page when the query maps directly to a product capability.

Examples:

- `recipe management software` -> `/features/recipe-management`
- `ingredient management software` -> `/features/ingredient-management`

Feature pages should be conversion-oriented. They should show what FooMake does, include screenshots or videos where possible, describe the workflow, and offer a clear signup CTA.

### Solution Pages

Use a solution page when the query maps to an industry, buyer type, or operational problem.

Examples:

- `mrp software for food manufacturing` -> `/solutions/food-manufacturing-mrp`
- `mrp small business` -> `/solutions/small-manufacturer-mrp`
- `batch management software for food` -> `/solutions/batch-production`

Solution pages should explain the operating context, who the page is for, the practical workflow, and why FooMake is a good fit.

### Learn Pages

Use a learn page when the query is informational, exploratory, or definition-oriented.

Examples:

- `automated recipe control` -> `/learn/what-is-automated-recipe-control`
- `recipe maintenance` -> `/learn/recipe-maintenance-for-food-manufacturers`
- `spreadsheets vs recipe management software` -> `/learn/recipe-spreadsheets-vs-software`

Learn pages should teach clearly, then link to relevant feature or solution pages.

### Help Pages

Use a help page when the query is task-specific and likely comes from someone trying to perform an action.

Examples:

- `how to create a recipe`
- `how to receive a purchase order`
- `how to count inventory`

Help pages should be practical and step-based. They may include screenshots, short videos, and direct links to the relevant app workflow.

### Videos

Use video when the query is workflow-oriented, visual, or demo-friendly.

Examples:

- Create a material and supplier pack
- Build a recipe
- Create a make order
- Receive a purchase order
- Run an inventory count

Videos should live primarily on YouTube for discovery and embedding. Relevant videos should be embedded on feature, solution, learn, or help pages with short written summaries so the page remains indexable.

### Comparison Pages

Use a comparison page when the query references alternatives, tradeoffs, or replacement decisions.

Examples:

- `Katana alternative for food manufacturers`
- `Fishbowl alternative for small manufacturers`
- `NetSuite alternative for small food manufacturers`
- `spreadsheets vs MRP for food manufacturing`

Comparison pages should be useful and honest. They should state who FooMake is for, who it is not for, and what tradeoffs matter to small food manufacturers.

## Query Classification

Classify every meaningful Search Console query into one primary intent:

- Feature intent: asks for software that performs a named capability.
- Solution intent: asks for software for an industry, business size, or operational situation.
- Educational intent: asks what something is or how a concept works.
- Comparison intent: asks about alternatives, competitors, or replacement decisions.
- Operational intent: asks how to perform a specific workflow.
- Brand/category discovery intent: broad terms that suggest early awareness.

If one query can support more than one page type, prefer a small cluster:

- Feature page for conversion.
- Learn page for education.
- Video for workflow proof.
- Internal links tying them together.

## Current Search Console Query Mapping

Recent queries:

| Query | Initial Read | Recommended Asset |
| --- | --- | --- |
| `recipe management software` | Strong feature and commercial intent | Recipe management feature page plus supporting learn page |
| `recipe management system` | Feature or solution intent | Strengthen recipe feature page; consider solution support copy |
| `recipe management` | Broad category intent | Existing recipe learn page plus feature page links |
| `automated recipe control` | Educational or adjacent industry language | Learn article explaining fit and limits |
| `recipe maintenance` | Operational/educational intent | Learn or help page about maintaining recipe versions |
| `formula management software` | Adjacent-market feature intent | Future page only if FooMake intentionally targets formula/batch manufacturers beyond food |
| `ingredient management software` | Feature intent | Ingredient/material management feature page |
| `batch management software for food` | Solution intent | Batch production solution page |
| `mrp software for food manufacturing` | Solution intent | Food manufacturing MRP solution page |
| `mrp small business` | Solution/category intent | Small manufacturer MRP solution page |

## First Priority Cluster

Prioritize the recipe-management cluster because Search Console already shows impressions there.

Recommended first assets:

1. Recipe management feature page.
2. Stronger internal links from the homepage and existing learn pages.
3. A short workflow video showing recipe creation and make-order creation.
4. A learn article on recipe spreadsheets versus recipe management software.
5. A help article or video on maintaining recipe versions.

## Internal Linking Pattern

Use links to move visitors from education to conversion:

- Homepage -> feature and solution pages.
- Learn pages -> matching feature or solution pages.
- Feature pages -> related workflows and signup CTA.
- Solution pages -> feature pages that prove the workflow.
- Help pages -> relevant feature pages and app workflows.
- Videos -> embedded on relevant pages, with links back to product pages.

## Review Cadence

Review Search Console weekly.

For each query with impressions:

1. Record the query, clicks, impressions, CTR, and average position.
2. Classify intent.
3. Map it to an existing page or proposed asset.
4. Decide whether to improve title/meta, add content depth, create a new page, embed a video, or add internal links.
5. Re-check after two to four weeks.

## Decision Heuristics

Use these rules of thumb:

- If impressions exist but average position is poor, improve content depth and internal linking.
- If average position is good but CTR is poor, improve title and meta description.
- If one broad page receives many related query variants, strengthen that page before creating near-duplicates.
- If a query names a capability FooMake already supports, consider a feature page.
- If a query names a buyer, industry, or operating context, consider a solution page.
- If a query starts with how, what, why, or versus, consider a learn/help/comparison page.

## Guardrails

- Do not create thin pages for every keyword variant.
- Do not broaden positioning away from small food manufacturers without an explicit strategy decision.
- Do not claim support for formula, compliance, costing, or automation capabilities beyond what the product actually provides.
- Keep pages practical, specific, and grounded in real workflows.
- Every new strategic content page should have a clear next step toward signup, demo, or product understanding.
