# مصنع دهب للطوب — Phase 1 Summary

## Infrastructure

- Laravel 11 project at `D:\dahab` with SQLite database
- Timezone: `Africa/Cairo`, Locale: `ar`
- Filament v3 admin panel at `/admin` — Amber color scheme, Arabic brand name, RTL layout

---

## Database (5 Tables)

| Table | Purpose |
|---|---|
| `entry_types` | Seeded lookup — "تحصيل من عميل" |
| `clients` | Factory clients |
| `orders` | Orders per client |
| `cash_entries` | Every money movement |
| `payments` | Links an order to a cash entry |

---

## Models & Business Logic

- 5 models with full relationships: `Client`, `Order`, `CashEntry`, `EntryType`, `Payment`
- Computed attributes on `Order` (never stored): `amount_paid`, `remaining`, `status`
- `PaymentService` — sole place that creates, edits, and deletes payments (always atomic via DB transaction), with Arabic validation errors

---

## Filament UI

### Clients (`/admin/clients`)

- List, Create, View, Edit pages
- View page shows: Edit + Delete header buttons
- Edit page redirects to View after save
- Orders relation manager embedded in View page only (not Edit)

### Orders Relation Manager (inside Client View)

- Columns: date, quantity, total price, paid, remaining, status badge
- Clickable rows → order view page
- Per-row actions: تسديد · عرض · تعديل · حذف
- "طلب جديد" button appears on view page only
- Deposit field on create (optional)
- Filters: date range (default today) + status

### Orders (`/admin/orders`)

- List, Create, View, Edit pages
- Edit page redirects to View after save
- View page shows: Edit + Delete header buttons
- Same columns, filters, and row actions as the relation manager
- Deposit field on create
- Date range filter defaults to today

### Payments (inside Order View)

- Table showing: date, amount, description, deposit badge
- Per-row: تعديل (pre-filled modal) + حذف (confirmation dialog)

### Dashboard

- 3 stats widgets: total clients, pending orders, total debt (ج.م)

---

## Validations & Protections

- Cannot pay more than the remaining balance
- Cannot set a future payment date
- Cannot delete a client that has orders
- Cannot delete an order that has payments
- Edit payment validates new amount doesn't exceed remaining + current payment value
