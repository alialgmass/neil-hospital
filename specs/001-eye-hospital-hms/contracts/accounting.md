# Contract: Accounting Module

**Module**: `Modules/Accounting`
**Permission prefix**: `treasury.*`, `journal.*`

---

## Treasury Routes

### GET /treasury
**Controller**: `TreasuryController@index` | **Permission**: `treasury.view`
**Props**:
```json
{
  "entries": { "data": [...], "meta": {...} },
  "summary": { "total_in": 45000.00, "total_out": 12000.00, "balance": 33000.00 },
  "filters": { "date": "2026-04-14", "type": null }
}
```

### POST /treasury
**Controller**: `TreasuryController@store` | **Permission**: `treasury.write`
**Action**: `RecordTreasuryEntryAction`
**Request**:
```json
{
  "type": "required|in:in,out",
  "description": "required|string|max:300",
  "amount": "required|numeric|min:0.01",
  "date": "required|date",
  "reference_no": "nullable|string|max:50",
  "beneficiary": "nullable|string|max:150",
  "account_id": "nullable|exists:accounts,id"
}
```

---

## Journal Routes

### GET /journal
**Controller**: `JournalController@index` | **Permission**: `journal.view`
**Props**: `{ entries: [...paginated...], accounts: [...all active accounts...] }`

### POST /journal
**Controller**: `JournalController@store` | **Permission**: `journal.write`
**Action**: `PostJournalEntryAction`
**Request**:
```json
{
  "date": "required|date",
  "description": "required|string|max:300",
  "debit_account_id": "required|exists:accounts,id",
  "credit_account_id": "required|exists:accounts,id|different:debit_account_id",
  "amount": "required|numeric|min:0.01",
  "reference": "nullable|string|max:80"
}
```
**Business rule**: `debit_account_id` MUST differ from `credit_account_id` — enforced in both Request and Action.

---

## Chart of Accounts Routes

### GET /accounts
**Controller**: `ChartOfAccountsController@index` | **Permission**: `journal.view`
**Returns**: Tree-structured accounts grouped by `group` (assets/liabilities/equity/revenues/expenses)

### POST /accounts
**Controller**: `ChartOfAccountsController@store` | **Permission**: `journal.write`
**Request**:
```json
{
  "code": "required|string|unique:accounts,code",
  "name": "required|string|max:150",
  "group": "required|in:assets,liabilities,equity,revenues,expenses",
  "nature": "required|in:debit,credit",
  "parent_id": "nullable|exists:accounts,id"
}
```

---

## Reports Routes (Accounting)

### GET /trial-balance
**Controller**: `TrialBalanceController@index` | **Permission**: `reports.financial`
**Query**: `from_date`, `to_date`
**Props**:
```json
{
  "accounts": [
    {
      "code": "1000",
      "name": "الصندوق النقدي",
      "group": "assets",
      "total_debit": 50000.00,
      "total_credit": 12000.00,
      "balance": 38000.00
    }
  ],
  "totals": { "total_debit": 150000.00, "total_credit": 150000.00 }
}
```
**Business rule**: `total_debit` MUST equal `total_credit` (enforced by TrialBalanceService).

### GET /income-statement
**Controller**: `IncomeStatementController@index` | **Permission**: `reports.financial`
**Query**: `from_date`, `to_date`
**Props**:
```json
{
  "revenues": [
    { "name": "إيرادات العيادة", "amount": 25000.00 }
  ],
  "expenses": [
    { "name": "مستحقات الأطباء", "amount": 8000.00 }
  ],
  "total_revenues": 70000.00,
  "total_expenses": 20000.00,
  "net_income": 50000.00
}
```

---

## Auto-posting Rules

The chart of accounts and the full trigger map (which action posts which entry
for every business event) are documented authoritatively in
[`docs/accounts-map.md`](../../../docs/accounts-map.md). Account numbering follows
الدليل المحاسبي v2.0 as of migration `2026_09_07_120000_restructure_chart_of_accounts_v2`
(inventory 1051, cash 1010, revenue by dept 4010–4060, doctor share 5110/5120,
insurance receivable 1030 → revenue 4110–4150, net-salary payable 2030).

When a booking payment is confirmed, `AutoPostBookingPaymentAction` fires:
1. Treasury entry: `type=in, source=booking`, for the posted amount.
2. Journal entry: `debit=Cash(1010) or Bank(1020)`, `credit=` the service's
   `revenue_account_id` or the dept default revenue account, `amount=` the payment.
3. `AutoPostDoctorDuesAction`: `debit=5110/5120`, `credit=2010`, `amount=` the
   doctor's share of that payment.
