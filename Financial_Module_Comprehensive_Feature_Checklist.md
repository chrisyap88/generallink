# GeneralLink CBE — Financial Accounting Module
## Comprehensive Feature Checklist

Compiled 30 Aug 2026. This is independent research into what a comprehensive financial/accounting module should contain, grounded in standard accounting software practice (the core/non-core module breakdown used industry-wide) and nonprofit fund accounting standards (relevant because GLADE/CBE temples hold donor-restricted funds, not just ordinary commercial revenue). This is not based on guesses about what Chris wants — it is the standard feature set any comprehensive financial module is expected to have, against which GeneralLink's current build is honestly marked.

**Status key:** ✅ Built and working · 🟡 Partially built · ⬜ Not built

---

### 1. Master Data & System Setup
*(Chris flagged this as visibly missing — it is, and it matters: this is the foundation everything else depends on.)*

1.1 Chart of Accounts setup & maintenance (add/edit/deactivate accounts, account types) — ✅ Built
1.2 Fiscal Year & Accounting Period setup, with period lock/close — ⬜ Not built
1.3 Opening balance / balance-brought-forward entry — ⬜ Not built (Journal Voucher can be used informally, but there's no dedicated opening-balance screen)
1.4 Document numbering sequences (auto invoice no., bill no., JV no., receipt no.) — 🟡 Partial (fields exist but are free-text, not auto-sequenced)
1.5 Currency & exchange rate setup — 🟡 Partial (single currency, MYR, hardcoded; no multi-currency support)
1.6 Tax code / tax rate setup (SST/GST/VAT) — ⬜ Not built
1.7 Approval workflow & authorization limit setup (who approves what, at what amount) — ⬜ Not built
1.8 Finance-specific user roles & permissions (segregation of duties) — 🟡 Partial (Admin-only gate exists; no maker-checker or granular finance roles)
1.9 Entity / Fund / Cost-Center setup (per-temple books) — 🟡 Partial (each temple's transactions are scoped, but there's no formal restricted-vs-unrestricted fund concept)
1.10 Default account mapping (which GL account each transaction type posts to) — ✅ Built (hardcoded core accounts + category-to-account mapping)
1.11 Audit trail / change-log configuration — ⬜ Not built

### 2. Accounts Receivable (AR)
2.1 Customer/debtor master maintenance — ✅ Built
2.2 Invoice creation (single line) — ✅ Built
2.3 Multi-line invoices (multiple items per invoice) — ⬜ Not built (one description/amount per invoice currently)
2.4 Recurring/scheduled invoices — ⬜ Not built
2.5 Credit notes / credit memos — ⬜ Not built
2.6 Customer payment receipt (full/partial) — ✅ Built
2.7 Customer statements — ⬜ Not built
2.8 AR Aging report — ✅ Built
2.9 Bad debt write-off — ⬜ Not built
2.10 Payment reminders / dunning — ⬜ Not built
2.11 Customer credit limit control — ⬜ Not built
2.12 Deposit / advance payment handling — ⬜ Not built

### 3. Accounts Payable (AP)
3.1 Supplier/vendor master maintenance — ✅ Built
3.2 Purchase order (with 3-way match to bill) — ⬜ Not built
3.3 Bill entry (single line) — ✅ Built
3.4 Multi-line bills — ⬜ Not built
3.5 Recurring bills — ⬜ Not built
3.6 Debit notes (return/adjustment against a bill) — 🟡 Partial (database table and posting logic exist; needs a data-entry screen)
3.7 Bill payment (full/partial) — ✅ Built
3.8 AP Aging report — ✅ Built
3.9 Payment approval workflow — ⬜ Not built
3.10 Vendor statement reconciliation — ⬜ Not built
3.11 Withholding tax on payments — ⬜ Not built
3.12 Staff expense claims as payables — ✅ Built

### 4. General Ledger (GL)
4.1 Chart of Accounts — ✅ Built
4.2 Manual Journal Voucher entry — ✅ Built (just added)
4.3 Recurring journal entries — ⬜ Not built
4.4 Reversing entries — ⬜ Not built
4.5 Journal approval workflow (maker-checker) — ⬜ Not built
4.6 Trial Balance — ✅ Built
4.7 General Ledger detail report (drill-down by account) — ✅ Built
4.8 Balance Sheet — ✅ Built
4.9 Profit & Loss / Income & Expenditure Statement — ✅ Built
4.10 Statement of Cash Flows — ⬜ Not built
4.11 Statement of Changes in Fund Balance (nonprofit equivalent of Statement of Equity) — ⬜ Not built
4.12 Comparative reporting (period vs period, budget vs actual) — ⬜ Not built
4.13 Consolidated reporting across multiple temples/entities — ⬜ Not built
4.14 Period-end close / period lock (prevents backdated edits after close) — ⬜ Not built
4.15 Year-end closing entries (roll forward balances into new fiscal year) — ⬜ Not built

### 5. Fixed Assets
5.1 Asset register (add/view assets) — ✅ Built
5.2 Straight-line depreciation calculation — ✅ Built (calculated for display)
5.3 Automatic monthly depreciation posting to GL — ⬜ Not built (flagged earlier — Balance Sheet currently shows assets at full cost, not net of depreciation)
5.4 Asset disposal / write-off — ⬜ Not built
5.5 Asset transfer between temples/locations — ⬜ Not built
5.6 Asset revaluation — ⬜ Not built
5.7 Asset tagging / physical verification tracking — ⬜ Not built
5.8 Insurance / warranty tracking — ⬜ Not built

### 6. Cash & Bank Management
6.1 Bank account master (multiple accounts per temple) — ⬜ Not built (one implicit Cash account per temple; no multi-account support)
6.2 Bank Reconciliation (statement vs ledger) — ✅ Built (MVP — logs the check, doesn't match line-by-line yet)
6.3 Bank statement line import & auto-matching — ⬜ Not built
6.4 Cash flow forecasting — ⬜ Not built
6.5 Petty cash management — ⬜ Not built
6.6 Inter-fund / inter-account transfers — ⬜ Not built

### 7. Budgeting & Forecasting
7.1 Annual budget setup by account — ⬜ Not built
7.2 Budget vs Actual reporting — ⬜ Not built
7.3 Budget revision/amendment tracking — ⬜ Not built
7.4 Multi-year budget planning — ⬜ Not built

### 8. Tax & Statutory Compliance
8.1 SST/GST/VAT tracking and reporting — ⬜ Not built
8.2 Withholding tax — ⬜ Not built
8.3 Tax return preparation exports — ⬜ Not built
8.4 Statutory reporting formats (e.g. Registrar of Societies filing format for nonprofits) — ⬜ Not built

### 9. Fund / Donor-Restricted Accounting
*(Standard for nonprofits — a temple's donations often come with donor-specified restrictions, e.g. "for building fund only," which is legally and accounting-wise different from general operating income.)*

9.1 Restricted vs unrestricted fund tracking — ⬜ Not built (donations are recorded, but not tagged as restricted/unrestricted in the ledger)
9.2 Donor-designated fund reporting — ⬜ Not built
9.3 Grant/donation compliance reporting — ⬜ Not built
9.4 Fund transfer between restricted/unrestricted — ⬜ Not built

### 10. Cost Center / Project / Department Accounting
10.1 Cost center tagging on transactions — 🟡 Partial (each temple is its own cost center; no sub-department tagging within one temple)
10.2 Departmental P&L — ⬜ Not built
10.3 Project-based costing (e.g. a specific building fund or event) — ⬜ Not built

### 11. Financial Reporting & Compliance
11.1 Standard financial statements (Balance Sheet, P&L) — ✅ Built
11.2 Statement of Functional Expenses (nonprofit-specific) — ⬜ Not built
11.3 Custom report builder — ⬜ Not built
11.4 Export to Excel — ✅ Built (all reports export to .xlsx)
11.5 Multi-entity consolidated reporting — ⬜ Not built
11.6 Annual Report generation — ✅ Built (separate CBE Annual Report module)

### 12. Audit Trail & Internal Controls
12.1 Full transaction audit log (who/when/what changed) — ⬜ Not built for finance specifically
12.2 Segregation of duties enforcement — ⬜ Not built
12.3 Maker-checker approval on postings — ⬜ Not built
12.4 Document/receipt attachment on transactions — 🟡 Partial (bills support file attachment; invoices/JV do not)
12.5 Void/reversal instead of hard delete — 🟡 Partial (no delete UI exists at all yet, which is safe by omission, but there's also no formal void/reversal flow)

### 13. Integration & Data Exchange
13.1 Generic journal CSV export for external accountants — ✅ Built
13.2 Bank feed integration — ⬜ Not built
13.3 API for third-party integration — ⬜ Not built
13.4 Bulk import from Excel/CSV — ⬜ Not built

### 14. Multi-Currency
14.1 Multi-currency transaction entry — ⬜ Not built
14.2 Exchange rate management — ⬜ Not built
14.3 Currency translation/revaluation — ⬜ Not built
*(Low priority unless GeneralLink expects temples to transact outside MYR.)*

---

**Note on this document:** I could not generate this as a Word (.docx) file tonight — the code-execution sandbox needed for that has been down all session (a known outage, not something I can fix from here). This Markdown file is the full content; I'll convert it to .docx as soon as the sandbox is back, or you can open this file directly (any text editor, or drag it into Word).
