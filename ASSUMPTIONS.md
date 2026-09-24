# ASSUMPTIONS.md

Defaults chosen where the requirements didn't specify. No question was blocking enough to
ask up front (all have a sensible default below).

1. **Store distance** = straight-line (Haversine) distance in km between the customer's
   delivery coordinates and each store's coordinates. No maps/geocoding API. Store gets
   `lat`/`lng` (admin-entered). Customer gets `lat`/`lng`/`address` captured at registration
   (simple number inputs), used for every order.
2. **Two portals, one backend, one frontend app.** Single Laravel API serves both. Single
   React app adds `react-router-dom` and splits into `/admin/*` (role=admin) and `/*`
   customer routes, gated by the logged-in user's role. No separate repo/build.
3. **Auth** = Laravel Sanctum personal access tokens (Bearer header), not cookie/SPA auth —
   simplest across two independent dev ports (Vite + `php artisan serve`).
4. **Admin accounts are seeded only**, no admin self-registration endpoint/UI. One demo
   admin created by the seeder, credentials printed.
5. **Product visibility**: customers see all active products regardless of stock; a product
   with 0 total stock across stores is shown but not addable to cart (disabled, "Out of
   stock"). "Available quantity" shown = sum of stock across stores.
6. **Quantity discount tiers**: a product can have multiple discount rows (e.g. 5+ → 10%,
   10+ → 20%), not just one. The highest `min_quantity` the cart quantity meets/exceeds wins.
   Same pattern for platform discounts (multiple `min_order_amount` tiers), highest
   qualifying tier wins.
7. **"Cannot combine" resolution** (revised by CR-TASKS.md, 2026-09-24 — was: always
   auto-pick the larger amount): compute both (a) total product-quantity discount across
   all cart lines, and (b) platform discount on the pre-discount subtotal. If only one is
   > 0, it applies automatically. If both are > 0, the **customer picks** which one via
   `PUT /cart/discount-choice` — the picker only shows when there's an actual choice to
   make. Until they pick, the larger amount is used as the default (old behavior becomes
   just the starting point); ties still favor product. The choice persists on the cart and
   resets when the cart is emptied by placing an order.
8. **Cart** is persisted server-side (`carts`/`cart_items`), one open cart per customer, so a
   page refresh doesn't lose it and discount preview is always server-computed (never
   trust client-side totals).
9. **Store allocation algorithm** (per order line, product qty Q): among stores with stock
   for that product, sorted by distance ascending — if one store alone covers Q, use it
   (nearest such store). Otherwise fulfill greedily from nearest stores first until Q is
   covered. If total stock across all stores for that product < Q, reject the whole order
   with 422 before any inventory is touched (all-or-nothing order placement).
10. **Inventory decrement** happens inside the order-placement DB transaction, with
    row-level locks (`lockForUpdate`) on the inventory rows touched, to avoid oversell under
    concurrent orders.
11. **Order status**: a single `placed` status is enough for the required flow, but the
    column is a string/enum so later statuses (e.g. `cancelled`) are cheap to add.
12. **Money**: `decimal(10,2)`, no currency field (single-currency system).
13. **Admin "view customer orders"** = read-only list + detail (no status change action
    required by the spec, so none is built).
