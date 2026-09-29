# GeneralLink Financial Module — Solution Architect Analysis
## Using Akaunting as a Design Reference

Compiled 1 Sep 2026.

**Important framing note before the analysis.** You asked me to classify Akaunting components as: (1) reuse as-is, (2) reuse with modification, (3) must be rewritten, (4) should be replaced. I researched Akaunting's actual license (Business Source License 1.1, read from their own GitHub repo) before doing this analysis, because it changes what "reuse" can legally mean here.

Akaunting's license blocks two things outright, with no workaround short of a bespoke commercial agreement that isn't publicly listed: white-labeling/rebranding their code for distribution in any product, and using it as an "Accounting Service" where multiple outside organisations benefit from its accounting functionality. GLADE is exactly that second thing — one system serving many independent temples. So literal code reuse (copying their PHP/Vue files into GeneralLink) is off the table regardless of effort savings, and you already confirmed this by picking "design reference only" when I raised it.

That means every item below is really answering: **does Akaunting's screen design, database shape, and workflow sequence give us a proven pattern to copy — building it fresh in your own native code — or not?** "Category 1: reuse as-is" doesn't exist in this analysis for that reason. I've relabeled the four categories to match what's actually possible:

- **A — Pattern proven, already built natively.** GeneralLink already has a working native screen that follows the same shape Akaunting uses. Zero further work.
- **B — Pattern proven, build natively.** Akaunting's screen design is a good template. We build it ourselves, matching the pattern, adapted for CBE (multi-temple, RM currency, your field set).
- **C — No usable Akaunting pattern.** This is CBE/nonprofit-specific (donor-restricted funds, temple hierarchy) — Akaunting doesn't have an equivalent, so it must be designed from scratch based on nonprofit accounting standards, not Akaunting.
- **D — Akaunting's pattern doesn't fit — use a different reference.** The Akaunting screen exists but is built for a different business model, so copying it would be wrong for a temple/nonprofit context.

Effort estimates assume native Laravel/Blade build, one developer (me), working within your existing `cbe_*` schema conventions.

---

## Module 1 — Company / Multi-Entity Setup & Permissions

*Akaunting reference: multi-company switcher, fine-grained role-level permissions, multilingual panel.*

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Multi-entity (per-temple) data scoping | A | Already built — `cbe_node_id` scoping. Matches Akaunting's per-company data isolation pattern. | Done |
| Role-based permissions | B | Akaunting has fine-grained per-module permission toggles per role. GeneralLink currently only has Admin-gate. Worth copying the *pattern* (permission matrix per role, not just admin/non-admin). | 3-4 days |
| Multilingual panel | A | Already built — EN/MS/ZH throughout, matches Akaunting's approach of full UI translation via lang files. | Done |
| Fiscal year / period setup with lock | C | Akaunting doesn't strongly enforce period locking either — this is a general accounting-software gap in both systems. Needs original design. | 2-3 days |

## Module 2 — Sales / Accounts Receivable

*Akaunting reference: Customers, Invoices (single + multi-line), Recurring Invoices, Payments Received, Client Portal.*

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Customer master | A | Built this session, mirrors Akaunting's customer record shape (name, contact, email, address, notes). | Done |
| Single-line invoice + payment | A | Built this session on native ledger, mirrors Akaunting's invoice → payment → status flow (draft/sent/partial/paid/overdue). | Done |
| Multi-line invoices (multiple items per invoice) | B | Akaunting's invoice line-item table (item, qty, price, tax) is a clean, proven pattern worth copying directly into a native `cbe_invoice_lines` table. | 4-5 days |
| Recurring invoices | B | Akaunting's recurring-invoice scheduler (frequency, start/end, auto-generate) is a good template to adapt natively. | 3-4 days |
| Credit notes | B | Akaunting's credit-note-against-invoice pattern maps directly onto your existing debit-note pattern (already partially built for AP) — same design, AR side. | 2 days |
| Customer statements | B | Akaunting generates a PDF/print statement per customer of open items — straightforward to replicate with your existing Excel-export pattern. | 2 days |
| Client Portal (customer-facing self-service) | D | Akaunting's client portal is built for businesses invoicing external paying customers online. CBE temples' "customers" are mostly internal — a public self-service portal isn't the right pattern here. Skip or replace with an internal notice/reminder instead. | N/A — not recommended |

## Module 3 — Purchases / Accounts Payable

*Akaunting reference: Vendors, Bills (single + multi-line), Payments Made.*

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Supplier master, bill entry, bill payment | A | Already built, mirrors Akaunting's vendor → bill → payment flow. | Done |
| Multi-line bills | B | Same pattern as multi-line invoices above — copy the line-item table shape. | 3-4 days (shares work with AR multi-line) |
| Recurring bills | B | Same scheduler pattern as recurring invoices. | 2 days (shares work with AR recurring) |
| Debit notes screen | B | Backend already exists (posting logic, table) — just needs the data-entry screen, same pattern as Akaunting's vendor-side credit adjustment. | 1-2 days |
| Purchase Order → 3-way match | D | Akaunting doesn't actually have PO/3-way-match either (it's a Sales/Purchases invoicing tool, not full procurement). No proven pattern from Akaunting here — if you want this, it needs original design regardless. | Not from Akaunting — original design, 5+ days if wanted |

## Module 4 — Banking

*Akaunting reference: Bank/Cash Accounts (multiple per company), Transactions, Deposits & Transfers, Reconciliation.*

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Bank reconciliation (statement-level) | A | Built this session — matches Akaunting's "confirm statement balance vs ledger" pattern at MVP level. | Done |
| Multiple bank accounts per temple | B | Currently GeneralLink assumes one implicit cash account per temple. Akaunting's multi-account model (each account is its own ledger node) is the right pattern to copy. | 3 days |
| Line-by-line statement matching/auto-match | B | Akaunting matches imported statement lines to ledger transactions one-by-one. Good pattern, meaningful build. | 5-6 days |
| Bank feed import (live bank connection) | D | Akaunting's bank feeds rely on third-party aggregators (Plaid-style, for Western banks) — doesn't apply to Malaysian bank connectivity. Would need a different, Malaysia-specific approach (e.g., manual CSV/OFX import) rather than Akaunting's pattern. | Original design if wanted, 4-5 days for CSV import only |
| Inter-fund/inter-account transfers | B | Akaunting's "Transfer" screen (move money between two of the company's own accounts, one journal entry) is directly applicable. | 2 days |
| Petty cash | D | Not a distinct Akaunting concept — same as any cash account there. For temples, worth a lightweight variant (small-float, frequent top-up) — original design. | 2-3 days |

## Module 5 — Double-Entry Core (Chart of Accounts, GL, Journals, Statements)

*Akaunting reference: this is the separately-sold "Double-Entry" app — Chart of Accounts, Manual Journals, Trial Balance, General Ledger, Balance Sheet, P&L, basic depreciation tracking.*

This is the most important module to call out clearly: **Akaunting's own core product does not include double-entry accounting at all.** Chart of Accounts, journals, GL, trial balance and balance sheet are a separate paid add-on ("Double-Entry app," from $6/month, sold by Akaunting Inc, not included even in paid on-premise tiers). GeneralLink already built its own native double-entry engine independently — it is not behind Akaunting's core, it's ahead of it, and it's the one area where Akaunting's *design* (not code) is worth studying closely since Akaunting Inc clearly thought carefully about this exact feature set.

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Chart of Accounts (Asset/Liability/Equity/Income/Expense, parent/sub-accounts) | A | Already built. Matches the Double-Entry app's account-type structure. Sub-account hierarchy (parent/child) is not yet built — see below. | Done (flat); sub-accounts pending |
| Parent/sub-account hierarchy | B | Double-Entry app supports nested accounts (e.g. "Bank" → "Maybank", "Bank" → "CIMB"). Worth copying — currently GeneralLink's CoA is flat. | 2-3 days |
| Manual Journal Vouchers | A | Built this session. Matches the pattern (multi-line, debit=credit enforced before posting, attachment support planned). | Done |
| Journal attachments | B | Double-Entry app allows attaching a receipt/document to a manual journal. Bills already support this — extend the same pattern to JV. | 1 day |
| Trial Balance, General Ledger, Balance Sheet, P&L | A | Already built, matches the Double-Entry app's report set closely. | Done |
| Depreciation tracking | A (partial) | Already calculated for display; auto-posting to GL monthly is the remaining gap (flagged in the feature checklist). | 2-3 days remaining |
| Statement of Cash Flows | B | Double-Entry app includes this. Worth building using the same indirect-method pattern. | 3-4 days |
| Recurring/reversing journal entries | B | Reasonable pattern to copy from the recurring-invoice scheduler once that's built (shared scheduler logic). | 2 days (shares work with recurring invoices) |
| Period-end close / lock | C | Not a strong feature in Akaunting either — needs original design for GeneralLink's multi-temple context. | 3 days |

## Module 6 — Reports & Reporting Engine

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Excel export on all reports | A | Already built and arguably ahead of Akaunting's default (which is largely on-screen/PDF, not necessarily Excel by default). | Done |
| Dashboards & widgets | B | Akaunting's dashboard (income/expense summary cards, recent activity) is a reasonable pattern to bring into GeneralLink's finance hub page, kept within the no-scroll/fixed-screen rule. | 2-3 days |
| Custom report builder | D | Akaunting doesn't have a true ad-hoc report builder either — this would be original work regardless of Akaunting. Low priority. | 5+ days if wanted, not recommended near-term |

## Module 7 — Fund / Donor-Restricted Accounting

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Restricted vs unrestricted fund tracking, donor-designated funds | C | **Akaunting has no equivalent at all** — it's built for ordinary businesses, not nonprofits, so there's no fund-accounting pattern to borrow. This must be designed from nonprofit fund-accounting standards (which I researched separately). This is likely your single most important gap given CBE temples hold donor-restricted donations. | 5-6 days (fund tagging on CoA + donation entry + fund-based reporting) |

## Module 8 — Tax, Multi-Currency

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Tax rate setup, SST/GST tracking | B | Akaunting's tax-rate-per-transaction-line pattern is directly usable once multi-line invoices/bills exist (tax applies per line). | 2-3 days (after multi-line items are built) |
| Multi-currency | D | Akaunting supports it, but all GeneralLink temples currently transact in MYR only — not worth building against a pattern you don't need yet. Low priority, revisit only if a temple needs foreign-currency transactions. | Defer |

## Module 9 — Fixed Assets

| Screen/Feature | Category | Notes | Effort |
|---|---|---|---|
| Asset register, straight-line depreciation | A | Already built. | Done |
| Auto-depreciation posting, disposal, transfer | B | Double-Entry app's asset lifecycle (capitalise → depreciate → dispose) is a clean pattern to finish copying. | 3-4 days combined |

## Module 10 — App-Store-Only Add-ons (Inventory, Projects, Payroll, CRM, Expense Claims)

These are separate paid Akaunting apps, not core. Relevance to GeneralLink varies:

- **Expense Claims** — A. GeneralLink already has staff expense claims built as AP payables; matches the pattern.
- **Projects** — D. Akaunting's Projects app is for billable client project tracking; not relevant to a temple's operations.
- **Payroll, CRM, Inventory** — D. Outside GeneralLink's current scope; GeneralLink already has its own membership/network system separate from accounting. Not recommended to pursue.

---

## Proposed Development Sequence — Reuse First

Per your instruction to lead with what's reusable, not what's hard:

**Phase 1 — Finish what's already 80% proven (1-2 weeks).** These are Category A items with small gaps: sub-account hierarchy in Chart of Accounts, auto-depreciation posting to GL, journal attachments, debit notes screen. All follow patterns you've already implemented elsewhere in the system.

**Phase 2 — High-value Category B items with a clear Akaunting template (3-4 weeks).** Multi-line invoices and bills (shared build), recurring invoices/bills (shared scheduler), multiple bank accounts per temple, inter-account transfers, credit notes, customer statements, dashboard widgets.

**Phase 3 — Category C items with no Akaunting equivalent, needs original design (2-3 weeks).** Fund/donor-restricted accounting — this is the highest-priority original-design item because it's a real gap for a nonprofit and nothing in Akaunting helps here. Period-end close/lock. Fiscal year setup.

**Phase 4 — Lower priority / defer.** Multi-currency, custom report builder, PO/3-way-match, bank feed live import, client portal — none of these have a strong Akaunting pattern that fits CBE's actual needs, and none are urgent given current usage.

---

**Bottom line on your original question:** almost nothing here is a literal "take Akaunting's code" situation — that path is closed by their license regardless of effort. But a large share of what's left to build (everything marked B above) has a proven, well-thought-out design in Akaunting that we can copy faithfully without touching their code, which meaningfully speeds up the "what fields, what workflow, what statuses" decisions. The genuinely original work is concentrated in one place: fund/donor-restricted accounting, which is nonprofit-specific and wouldn't have been solved by Akaunting reuse even if the license allowed it.
