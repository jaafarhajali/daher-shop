# Manual test checklist

Run through this once after installing (15–20 minutes). Every line should pass.

## Authentication
- [ ] Visiting any page while signed out redirects to the login screen.
- [ ] Wrong password shows "Invalid username or password."
- [ ] 5 wrong attempts locks login for ~5 minutes.
- [ ] `admin` / `admin123` signs in; the dashboard loads.
- [ ] Sign out returns to login; the back button does not reopen private pages with data.

## Categories
- [ ] Add category → appears in list with product count 0.
- [ ] Edit renames it; duplicate names are rejected.
- [ ] Delete works only when the category has no products, otherwise a warning appears.

## Products
- [ ] Add product with cost 250 / price 320 → form shows profit 70 live; list shows the same.
- [ ] Barcode must be unique (try saving the same barcode twice).
- [ ] Search finds by name and barcode; category & stock filters work; column sort toggles.
- [ ] Adjust stock +5 with a note → quantity rises and Stock history shows "+5 restock".
- [ ] Adjust stock below zero is rejected with a friendly error.
- [ ] Set min-stock above quantity → product appears in Low stock and in the sidebar badge.
- [ ] Deleting a never-sold product removes it; deleting a sold product archives it
      (it disappears from lists/POS but its invoices still show it).

## POS / Sales
- [ ] Searching shows only in-stock products; clicking adds to cart.
- [ ] Typing an exact barcode adds the product automatically.
- [ ] Qty +/- respects available stock; price is editable per line.
- [ ] Discount reduces the total; totals update live.
- [ ] Completing a sale opens the invoice; stock decreased; movement journal shows the invoice number.
- [ ] Invoice prints cleanly (Print invoice → only the invoice, no sidebar).
- [ ] Cancelling the sale restocks items and marks it CANCELLED.
- [ ] Selling more than stock in two tabs at once: the second checkout fails safely.

## Customers
- [ ] Add / edit / search customers.
- [ ] Profile shows purchase history, repair history, lifetime value.
- [ ] Deleting a customer with repairs is blocked; with only sales it works and
      old invoices show "Walk-in customer".

## Repairs
- [ ] New ticket → receipt prints with ticket number; deposit recorded.
- [ ] Status timeline highlights the current stage; each change appears in history with note.
- [ ] Adding a stock part deducts inventory; removing it returns inventory.
- [ ] External part with custom cost/charge updates totals.
- [ ] Payment cannot exceed the remaining balance; balance hits zero at full payment.
- [ ] Delivering stamps the delivered date; cancelling restocks stock parts.

## Expenses
- [ ] Add / edit / delete an expense; filtered total updates.
- [ ] Category filter and date range work.

## Reports
- [ ] Each of the 10 report types runs without error (with and without data).
- [ ] Date presets fill the from/to fields correctly.
- [ ] CSV opens in Excel with correct characters; Excel export opens; PDF/Print view prints.
- [ ] Sales-by-day totals match the sales list for the same range.

## Backup
- [ ] Create backup → file listed with size; download works.
- [ ] Restore the backup → data identical afterwards.
- [ ] Add a test category, restore again → the test category is gone (restore really replaces).

## Settings & theming
- [ ] Change shop name → sidebar, login screen, invoices update.
- [ ] Currency symbol/position changes all money displays.
- [ ] Accent colour changes buttons/links; dark mode persists after reload.
- [ ] Password change: wrong current password rejected; mismatch rejected; new one signs in.

## Security spot-checks
- [ ] `http://localhost/daher-store/config/config.php` → 403 Forbidden.
- [ ] `http://localhost/daher-store/storage/backups/` → 403 Forbidden.
- [ ] Submitting any form after deleting the `_token` field (browser dev tools) is rejected.
- [ ] `index.php?r=products/index&q='"><script>alert(1)</script>` renders as text, no popup.
- [ ] A `q` value like `%' OR 1=1 --` returns no products and no SQL error.
