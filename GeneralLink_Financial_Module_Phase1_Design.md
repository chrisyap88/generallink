# GeneralLink Financial Module — Phase 1 Design (Field-Level)
## Module by Module, Program by Program, Field by Field

Revised 1 Sep 2026. The first version of this document listed program names only. That was a mistake — a program name without its fields is not a design, it's a table of contents. This version specifies every field, every status, every numbering rule, every approval point, the way a real accounting system (SQL Account, AutoCount, Xero) is actually specified. Several items previously marked "already built" are honestly reclassified below as **built but under-specified** — they exist, but not to a standard a financial controller would sign off as complete.

**Status key:** ✅ Built to standard · ⚠️ Built but under-specified, needs upgrade · 🆕 To build

---

### Module 1 — General Ledger (GL)

**1. Chart of Accounts** — ⚠️ upgrade needed
Fields: Account Code · Account Name (EN/MS) · Account Name (ZH) · Account Type (Asset/Liability/Equity/Income/Expense) · **Account Classification** (Current Asset / Fixed Asset / Current Liability / Long-term Liability / etc. — missing today, needed for a correctly-grouped Balance Sheet, not just a flat list) · **Parent Account** (for sub-account hierarchy — missing today, e.g. "Bank" → "Maybank", "Bank" → "CIMB") · Normal Balance (Dr/Cr, system-derived) · **Is Control Account** (Y/N — AR/AP control accounts should not accept direct postings, only via the sub-ledger) · Is Active · Is System Account (locked from deletion) · Opening Balance · Created By / Date.

**2. Journal Voucher** — ⚠️ upgrade needed
Fields: **JV Number** — sequential, human-readable, per fiscal year (e.g. `JV-2026-09-0001`; today it's an internal UUID only, not acceptable for an audit trail) · Entry Date · Description/Narration · Reference Document · Lines: [Account, Debit, Credit, Line Description, Cost Centre/Fund] · Total Debit · Total Credit · **Status** (Draft / Pending Approval / Posted / Reversed) · Prepared By · **Approved By** (maker-checker — missing today, anyone with access can post directly) · Approval Date · Attachment · Reversal Reference (if this JV reverses another).

**3. Opening Balances** — 🆕
Fields: Fiscal Year · Account · Opening Debit/Credit Balance · Entry Date · Prepared By · locked once any transaction posts against that account in that year.

**4. Month-End Close / Period Lock** — ✅
Fields: Node · Year · Month · Status (Open/Closed) · Closed By · Closed At.

**5–9. Trial Balance, Balance Sheet, Income & Expenditure, GL detail, Journal Export** — ✅ as reports.
Parameters: As-of Date / From–To Date · Node/Cost-Centre filter · Fund filter (once Module 5's fund tagging lands) · **Comparative Period toggle** (this year vs last year — currently missing, an AGM pack without a prior-year column looks amateur) · Output format.

---

### Module 2 — Cash & Bank Management

**1. Bank Accounts Master** — 🆕
Fields: Account Code · Account Name · Bank Name · Account Number · Account Type (Current/Savings/Fixed Deposit/Petty Cash) · Opening Balance & Date · Signatories (for cheque accounts) · **GL Account Link** (each bank account must map to its own Balance Sheet asset account — today there is only ONE implicit cash account per temple, which is a structural gap, not a cosmetic one) · Is Active.

**2. Daily Transactions / Cashbook** — ⚠️ upgrade needed
Current fields: date, category, description, amount, bank statement link.
Add: **Bank Account** (which account — currently implicit/single) · Reference/Cheque No. · Payee/Payer Name · Transaction Type (Receipt/Payment/Transfer) · Attachment (receipt image — currently only Bills support this) · **Reconciled flag** (set by Bank Reconciliation, not editable by the treasurer directly) · Approved By (for payments above a threshold).

**3. Petty Cash Float & Reimbursement** — 🆕
Fields: Float Custodian · Float Amount · Top-up Date/Amount · Expense Entries [date, category, amount, receipt attachment] · Running Balance · Cash Count (physical count vs book balance, with variance) · Replenishment Request → Approval.

**4. Bank Transfer** — 🆕
Fields: From Account · To Account · Transfer Date · Amount · Reference No. · Purpose · Prepared By · Approved By.

**5. Cash & Bank Position report** — 🆕
Parameters: As-of date. Output: every account, balance, total — not just one number.

---

### Module 3 — Bank Reconciliation — ⚠️ upgrade needed

Current fields: statement date, opening balance, ending balance only — no line-level detail, which means it currently just logs "I checked it," not an actual reconciliation.
Full redesign:
Bank Account · Statement Period · Statement Opening/Closing Balance · Book (Ledger) Balance · **Reconciling Items** [Date, Description, Amount, Type (Unpresented Cheque / Deposit in Transit / Bank Charge / Interest / Error), Matched Ledger Transaction, Status (Matched/Unmatched)] · **Adjustment Journal** (auto-generated once a bank charge/interest line is identified — treasurer shouldn't have to post it separately) · Reconciled By · Reviewed By · Status (Draft/Completed/Locked).

---

### Module 4 — Accounts Payable

**1. Suppliers** — ⚠️ upgrade needed
Add: Supplier Code · Payment Terms (e.g. Net 30) · Bank Account details (for payment) · Tax Registration No. · Category (Trade/Service/Utility) · Status (Active/Blacklisted).

**2. Purchase Bills** — ⚠️ major upgrade needed, this is the core of the complaint
Current: one description, one amount, one category — no line items, no tax, no approval. That is not how a real bill works.
Full redesign: **Bill Number** (internal, sequential, e.g. `BILL-2026-0001`) · Supplier · **Supplier's Own Invoice No.** (their reference — distinct from our internal number) · Bill Date · Due Date (or auto-calculated from Payment Terms) · **Lines**: [Description, Quantity, Unit Price, Amount, GL/Expense Account, Cost Centre/Fund, Tax Code, Tax Amount] · Subtotal · Total Tax · **Total Amount** · Attachment (mandatory — no bill without the source document) · Status (Draft/Pending Approval/Approved/Unpaid/Partially Paid/Paid/Cancelled) · Prepared By · **Approved By** · Approval Date.

**3. Bill Payments** — ⚠️ upgrade needed
Add: Payment Method detail (Cheque No. / Bank Transfer Ref / Online banking ref) · Paid From (which bank account — needs Module 2's multi-account) · **Approved By** (maker-checker on the actual release of money — the highest-risk single point in the whole system) · Remittance advice attachment.

**4. Debit Notes** — 🆕 (posting logic exists, screen doesn't)
Fields: Debit Note No. · Related Bill · Date · Reason · Amount · GL Account affected · Attachment · Approved By.

**5. AP Aging** — ✅ as a report.

**6. Purchase Request** — 🆕
Fields: Request No. · Requested By · Department/Cost Centre · Date · Items Requested [Description, Qty, Estimated Unit Price, Estimated Total] · Purpose/Justification · Approval Status · Approved By · **Converted-to-Bill link** (traceability from request through to payment).

**7. Expense Claim** — ⚠️ verify/upgrade
Fields: Claimant · Claim Date · Category · Amount · **Receipt Attachment (mandatory, not optional)** · Description/Purpose · Event/Project reference · Status · Approved By · Reimbursement Payment link.

---

### Module 5 — Accounts Receivable / Donation & Fund Management

**1. Customers** — ⚠️ upgrade needed
Add: Customer Code · Category (Member/Sponsor/Renter/General) · Credit Terms · Status.

**2. Invoices** — ⚠️ major upgrade needed, same issue as Bills
Full redesign: **Invoice No.** (sequential, e.g. `INV-2026-0001`) · Customer · Invoice Date · Due Date · **Lines**: [Description, Qty, Unit Price, Amount, GL/Income Account, Fund, Tax Code, Tax Amount] · Subtotal · Tax · Total · Status (Draft/Sent/Unpaid/Partially Paid/Paid/Overdue/Void) · Prepared By · Approved By (where required).

**3. Invoice Payments (Receipts)** — ⚠️ upgrade needed
Add: **Official Receipt No.** — sequential, gap-free, non-reusable (this is the number an auditor traces; a payment without a proper receipt sequence is a real audit finding) · Payment Method · Received By · Bank Account credited.

**4. AR Aging** — ✅ as a report.

**5. Donor Register** — ⚠️ upgrade needed
Add: Donor Code · Donor Type (Individual/Corporate/Anonymous) · Preferred Fund/Cause.

**6. Donation Entry with Fund tagging** — 🆕
Fields: Donation Date · Donor (or Anonymous) · Amount · **Fund** (General/Building/Charity/Welfare/etc., with Restricted/Unrestricted flag) · Purpose/Campaign · Payment Method · **Receipt No. (auto, sequential)** · GL Posting (Dr Cash/Bank, Cr Fund Income) · Pledge-vs-Received flag · Recorded By.

**7. Official Donation Receipt** — ⚠️ upgrade needed
Must carry: **Receipt No. (sequential, gap-free — cancelled receipts stay in the sequence as VOID, never deleted)** · Donor Name & ID · Date · Amount in words and figures · Fund/Purpose · Payment Method · Authorized Signature block · Cancelled Receipt tracking.

**8. Fund Balance Report** — 🆕
Per fund: Opening Balance, Income, Expenditure, Transfers In/Out, Closing Balance — this is the Statement of Changes in Fund Balance, not a single "fund total" number.

**9. Fund classification on Chart of Accounts** — 🆕
Fields: Fund Type (Unrestricted / Temporarily Restricted / Permanently Restricted — standard nonprofit fund accounting terms) · Fund Name.

---

### Module 6 — Fixed Assets Register

**1–2. Register / Add Asset** — ⚠️ upgrade needed
Add: **Asset Tag/Code** (physical tag number for verification, distinct from the system ID) · Location · Custodian/Department · **Funding Source** (which fund paid for it — links to Module 5) · Supplier · Warranty/Insurance info · Depreciation Method (explicit field, even while straight-line is the only option) · Status (Active/Fully Depreciated/Disposed/Under Repair).

**3. Run Monthly Depreciation** — 🆕
Fields: Run Period · Asset list with calculated depreciation this period · GL posting preview (Dr Depreciation Expense, Cr Accumulated Depreciation, per asset) · Confirm & Post · Posted By · Reversal option (only before period lock).

**4. Asset Disposal** — 🆕
Fields: Asset · Disposal Date · Method (Sold/Scrapped/Donated) · Disposal Proceeds · Net Book Value at disposal · **Gain/Loss on Disposal (auto-calculated)** · Approved By · GL posting (remove asset & accumulated depreciation, recognize gain/loss).

**5. Fixed Asset Schedule report** — 🆕
Standard audit schedule: opening cost, additions, disposals, closing cost, opening accumulated depreciation, current year charge, disposals, closing accumulated depreciation, net book value — by asset category.

---

### Module 7 — Financial Reporting

**Cash Flow Statement** — 🆕, must be a real one
Operating Activities (surplus/deficit adjusted for non-cash items like depreciation, plus changes in AR/AP) · Investing Activities (asset purchases/disposals) · Financing Activities (fund transfers, loans if any) · Net Change in Cash · Opening Cash · Closing Cash — reconciling to the Balance Sheet cash figure. Not "income minus expense."

**Monthly Financial Summary** — 🆕
One page: Income vs Expenditure vs Budget (where available) · Cash position · Fund balances · Major variances flagged.

**President/Committee Dashboard** — ✅ existing, extend with Fund Balance and Fixed Asset NBV once Modules 5/6 land.

**AGM Treasurer's Report pack** — 🆕
Treasurer's narrative summary · comparative figures (this year vs last year vs budget) · Balance Sheet, Income & Expenditure, Cash Flow, Fund Balance, Fixed Asset Summary · Notes to accounts (accounting policies, significant items) · Auditor's Report attachment slot.

---

### Module 8 — Year-End Closing

**Year-End Rollforward** — 🆕
Fields: Fiscal Year being closed · **Closing checklist gate** (won't run until all 12 months are locked, bank recon complete, depreciation posted, AR/AP reviewed) · Closing Journal (auto-generated: zero Income/Expense accounts, transfer net surplus/deficit to Fund Balance, per fund) · New Fiscal Year opening balances (auto-carried) · Locked By · Lock Date (irreversible without Admin override).

**Year-End Checklist** — 🆕
Item · Status (Done/Pending) · Completed By · Date · Notes — a literal, auditable checklist, not a mental note.

**Prior-Year Comparison** — 🆕, added as a column on existing reports.

---

### Module 9 — Audit Trail & Internal Control

**Maker-Checker Payment Approval** — 🆕
Fields: Transaction Reference · Prepared By · Amount · **Approval Threshold Rule** (e.g. above RM500 requires second approval — configurable, not hardcoded) · Approver · Approval Date · Status (Pending/Approved/Rejected) · Rejection Reason.

**Journal Approval** — 🆕, same pattern applied to Manual JVs.

**Transaction History / Edit Log** — 🆕
Fields: Table/Record affected · Field Changed · Old Value · New Value · Changed By · Changed At.

**Void/Reversal control** — 🆕
Fields: Original Transaction Reference · Reversal Reason · Reversal Journal (auto-generated offsetting entry, never a hard delete) · Approved By · Date.

---

### Module 10 — ROS / Regulatory Reporting

**Annual Report Pack export** — 🆕
Assembles: Financial Year-End Report · Income Summary · Expenditure Summary · Assets & Liabilities Statement · Cash & Bank Summary · Fund Balances · pulls Membership/Activity/Committee data from other modules into one compiled pack — not a single report.

**Office-Bearer / Committee List** — 🆕
Fields: Name · IC/Passport No. · Position (President/Secretary/Treasurer/Committee Member) · Term Start/End · Contact · Signature on file (Y/N).

**Submission Checklist** — 🆕
Item (AGM held / Accounts approved / Audit completed if required / Form 9 prepared / Submitted to ROS) · Status · Date · Responsible Person · Deadline (60 days post-AGM) · Reminder flag.

---

**Honest summary of where this leaves us:** of what I previously marked "✅ already built," six programs (Chart of Accounts, Journal Voucher, Bills, Invoices, Bank Reconciliation, Donor Register, Receipts) need real upgrades before they meet the standard above — not rebuilds from zero, but genuine additions (multi-line items, tax, sequential numbering, approval fields). That's on top of the 33 net-new programs. This is the actual scope of Phase 1 done properly.
