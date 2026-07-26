# Daher Phone — How the money numbers work

Written for the shop owner, not for accountants. Every dashboard card and every
report gets its numbers from **one** place in the code
([app/Models/Finance.php](../app/Models/Finance.php)), so the dashboard and the
reports can never disagree. The "Financial summary" report shows all of these
metrics for any date range.

---

## The story of one example month

We'll use this small month everywhere below:

| What happened | Numbers |
|---|---|
| Sold 2 phones for cash | $100 each, they cost the shop $60 each |
| Sold 1 laptop on credit | $450, it cost the shop $270 |
| Customer paid part of the laptop debt in cash | $200 |
| One cash phone came back (returned) and its money was refunded | return $100 + refund $100 |
| Delivered one repair | charged $80; the screen used cost the shop $30 |
| Paid the electricity bill | $50 |

---

## The metrics, one by one

### 1. Gross sales — "everything we invoiced"

Every **completed** sale invoice, at the actual prices charged.

- Tables: `sales` (column `total`, status `completed`)
- Increases: completing a sale in the POS
- Decreases: nothing (cancelled invoices never count at all)

> Example: 100 + 100 + 450 = **$650**

### 2. Refunds — "money we handed back"

- Tables: `refunds` (column `amount`), always linked to the original invoice
- A refund can never exceed the money actually received on that invoice.

> Example: **$100** (the returned phone)

### 3. Return credits — "sale value cancelled by returned goods"

When goods come back against an invoice that was **not fully paid** (a credit
sale), no money moves — instead the customer's debt shrinks. That part of the
sale is cancelled: it will never be collected, so it must leave revenue too.

- Tables: `customer_payments` with `method = 'return_credit'`
- Created automatically when a return is processed on an unpaid invoice.

> Example: $0 this month (the laptop wasn't returned)

### 4. Net sales / Total revenue — "what we really sold"

```
Net sales     = Gross sales − Refunds − Return credits
Total revenue = Net sales + Repair income
```

- Repair income: `repairs.total_cost` where status = `delivered`

> Example: 650 − 100 − 0 = **$550** net sales; + 80 repair = **$630 total revenue**

### 5. Cost of goods sold (COGS) — "what the sold items cost us"

Each sale line froze the item's cost **at the moment of sale**
(`sale_items.unit_cost`), so changing a product's cost later never rewrites
history. Returned items go **back on the shelf**, so their cost comes back too:

```
COGS (net) = cost of items sold − cost of items returned
```

- Tables: `sales.total_cost` (sum of frozen line costs), `return_items`
  joined back to the original sale line for the frozen cost

> Example: (60 + 60 + 270) − 60 returned = **$330**

### 6. Gross profit — "profit before the bills"

```
Gross profit = Net sales − COGS (net) + Repair profit
Repair profit = repair income − actual parts cost
```

> Example: (550 − 330) + (80 − 30) = 220 + 50 = **$270**

Check it by hand: kept phone $40 profit + laptop $180 profit (still profit even
though not fully paid yet — the customer owes it) + repair $50 = $270 ✓.
The returned phone contributes exactly **zero** — its sale, refund, and cost
all cancel out. That is the test of a correct model.

### 7. Expenses — "the bills"

- Tables: `expenses` (column `amount`, on `expense_date`)

> Example: **$50**

### 8. NET PROFIT — "what the shop actually earned"

```
Net profit = Gross profit − Expenses
```

> Example: 270 − 50 = **$220**

---

## Money owed vs. money earned (two different questions)

**Outstanding credit** (the Credit page) answers *"who owes us money?"*:

```
outstanding = invoice total − paid_amount        (per completed invoice)
```

`paid_amount` grows with real payments **and** with return credits — both
genuinely reduce what the customer owes. But on the customer profile,
**Total paid** shows only real money received; return credits are excluded,
and **Total purchases** is shown net of returns, so that
*purchases − paid = outstanding* always balances.

**Refundable** answers *"how much cash could we give back?"*:

```
refundable = money actually received − refunds already given
```

Return credits are NOT money received — this rule prevents refunding cash that
was never collected on a credit sale.

---

## What each action does, at a glance

| Action | Stock | Revenue | COGS | Debt | Cash refundable |
|---|---|---|---|---|---|
| Complete a cash/card sale | − items | + total | + frozen cost | — | + total |
| Complete a credit sale | − items | + total | + frozen cost | + total | — |
| Record a credit payment | — | — | — | − amount | + amount |
| Return goods (paid invoice) | + items | — | − returned cost | — | — |
| Return goods (unpaid invoice) | + items | − return credit | − returned cost | − return credit | — |
| Refund money | — | − amount | — | — | − amount |
| Cancel a sale (only if untouched) | + items | removed entirely | removed entirely | — | — |
| Record an expense | — | — | — | — | — (reduces net profit) |

*A return on a fully-paid invoice does not reduce revenue by itself — the money
is only truly given back when you record the matching **refund**. Process both
when the customer gets their cash back.*
