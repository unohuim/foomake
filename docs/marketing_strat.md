# FooMake 3-Month Marketing Strategy

## 1. Executive Summary

FooMake’s strongest go-to-market strategy is product-led SEO plus founder-led content, self-serve onboarding, and honest beta transparency.

The core positioning is:

> Simple MRP software for small food manufacturers who have outgrown spreadsheets.

FooMake should focus on practical operational control for small food manufacturers: materials, suppliers, purchase orders, recipes, make orders, inventory counts, sales orders, and workflow tasks where available. The product is in beta, ready for companies to use, and actively improved. The public message should be confident and transparent without positioning FooMake as unfinished or casual.

The long-term revenue model should be inbound-led and self-serve, with minimal sales calls. SEO, useful content, help docs, short workflow videos, clear pricing, and product-led onboarding should drive the path from visitor to activated customer.

## 2. Positioning

FooMake is simple MRP software for small food manufacturers.

The product should be described as:

- built from real manufacturing experience
- designed around practical workflows, not enterprise complexity
- ready for beta and early-access companies to use
- actively improved weekly
- built around real purchasing, recipe, inventory, production, and sales workflows

Avoid language such as:

- unfinished
- rough
- not polished

Use language such as:

- beta
- early access
- actively improved
- built around real manufacturing workflows
- improving weekly

Recommended positioning line:

> FooMake helps small food manufacturers replace disconnected spreadsheets with practical MRP workflows for materials, suppliers, recipes, purchasing, production, inventory counts, and sales orders.

## 3. ICP and Wedge

The primary ICP is small-batch food manufacturers with 5-50 employees.

They are usually:

- using spreadsheets or disconnected tools
- managing ingredients, suppliers, purchase orders, recipes, production, inventory counts, and sales orders
- not ready for enterprise ERP
- trying to get better operational control without a heavy implementation project
- frustrated by software that is either too basic or too enterprise-oriented

Food makers are not too niche. Food is specific enough to build trust, write useful SEO content, and avoid sounding generic. FooMake should start food-only in its messaging.

Later expansion categories may include:

- pet food
- cosmetics
- supplements
- soap
- candles
- other recipe and batch manufacturers

Do not broaden initial messaging too early. Broad messaging weakens trust and makes SEO harder.

## 4. Pricing Direction

Early beta pricing should be simple and easy to understand.

Recommended early beta options:

- $99/month includes 5 users
- $10/user/month for extra users

Or, if onboarding and product value feel strong enough:

- $149/month includes 5 users

Long-term pricing should aim higher as FooMake becomes more valuable:

- Starter: $149/month, includes 3 users
- Growth: $299/month, includes 8 users
- Ops: $499/month+, includes 20 users
- Extra users: $20-$30/user/month

Customer math:

- At $150/month average revenue per account, $1M ARR requires about 556 customers.
- At $150/month average revenue per account, $2M ARR requires about 1,112 customers.
- At $300/month average revenue per account, $1M ARR requires about 278 customers.

Therefore, long-term pricing and packaging should aim for roughly $250-$350/month ARPA if the product becomes valuable enough.

Do not aggressively punish small teams for inviting taskers or shop-floor users. Consider cheaper or bundled tasker users later. Charge mainly by company size and operational value, not every user.

## 5. Founder Credibility

Founder credibility should be used as a trust advantage.

Recommended framing:

> FooMake was built inside a real manufacturing business to solve the day-to-day problems small teams face with purchasing, recipes, inventory, production, and sales orders.

This should be a credibility badge, not the entire brand identity. FooMake should not sound like a side project or an internal tool casually repackaged. The message should be that the product is grounded in real operations and shaped by the problems small manufacturers actually face.

## 6. 3-Month Action Plan

### Month 1: Conversion Base

Ship the core marketing pages:

- `/learn/food-manufacturing-mrp`
- `/learn/inventory-management-for-food-manufacturers`
- `/learn/recipe-management-software`
- `/learn/purchase-order-software-for-food-manufacturers`
- `/learn/production-planning-for-small-food-manufacturers`
- `/learn/mrp-for-small-manufacturers`

Each page should include:

- the problem
- who it is for
- screenshots or product panels where available
- exact workflows
- pricing direction or beta CTA
- a Start beta CTA
- no demo required or self-serve messaging if true

Also add:

- public changelog
- beta note
- homepage links to the marketing pages when safe

### Month 2: Bottom-Funnel SEO

Create comparison and alternative pages:

- Best MRP software for small food manufacturers
- MRP software for small business
- NetSuite alternative for small food manufacturers
- Katana alternative for food manufacturers
- Fishbowl alternative for small manufacturers
- Spreadsheets vs MRP for food manufacturing

Tone should be:

- useful
- honest
- specific
- not fake-neutral
- clear about who FooMake is and is not for

### Month 3: Proof and Activation Loops

Add:

- 3 case-study style beta stories
- 10 short workflow videos
- help center articles
- onboarding checklist
- in-app sample company
- weekly product updates
- email drip after signup

Activation goal:

> Visitor -> trial -> creates materials -> adds supplier -> creates recipe -> creates make order

## 7. Content Cadence

Weekly for 12 weeks:

- 1 SEO article
- 1 product or workflow page improvement
- 2 short LinkedIn posts
- 1 short video demo
- 1 changelog update

Suggested content topics:

- How small food manufacturers track inventory
- How to manage recipe versions
- How to know what ingredients to buy
- How to create make orders from recipes
- How to stop production from running on spreadsheets
- How to manage supplier prices
- How to manage inventory counts
- How to connect recipes, purchasing, and production

## 8. Self-Serve Onboarding Requirements

The biggest blocker is not traffic. It is activation.

A stranger should be able to:

- sign up
- understand what FooMake does
- create core records
- see value in 15 minutes
- know what to do next

Self-serve onboarding needs to guide the first useful workflow, not just show an empty product. The first activation path should focus on materials, suppliers, recipe setup, and a first make order.

## 9. 3-Month Target Metrics

Practical early targets:

- 500-2,000 targeted monthly visitors
- 50-150 beta signups
- 10-30 activated companies
- 3-10 paying customers

The goal is learning and activation before aggressive scaling. Early marketing should answer:

- Which pages attract qualified visitors?
- Which signups activate?
- Which workflows create the first value moment?
- What pricing feels fair once companies understand the product?

## 10. Page Writing Formula

Recommended page structure:

1. Hero
2. Problem
3. FooMake solution
4. Feature and workflow sections
5. Why not spreadsheets
6. Why not enterprise ERP
7. Screenshots or product panels
8. Beta note
9. FAQ
10. CTA

Every page should be specific to the workflow and written for small food manufacturers. Avoid generic SaaS claims. Use concrete terms like materials, supplier packs, purchase orders, recipes, make orders, inventory counts, and sales orders.

## 11. Route and Implementation Notes

Marketing pages should stay under `/learn/{slug}`.

This is safer than a root-level `/{slug}` catch-all because FooMake has many first-level authenticated and domain routes, including auth, dashboard, materials, inventory, purchasing, manufacturing, sales, profile, and admin routes. The `/learn` namespace prevents public SEO pages from shadowing app routes.

The public sitemap route is `/sitemap.xml`. It should expose only intended public marketing pages loaded from `resources/content/marketing/`.

Homepage links should point to the `/learn` marketing pages where it is safe to update the public homepage.

Invitation acceptance should use guest token routes:

- `GET /invitations/{token}/register`
- `POST /invitations/{token}/register`

Admin invitation management routes must remain authenticated and must not be used in invite emails.

UOM conversion domain routes should be authenticated application routes. They operate on tenant and item conversion data and should live inside the `auth` and `verified` middleware group unless a future public use case is explicitly documented and protected.
