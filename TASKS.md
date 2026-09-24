# TASKS.md

## Requirements checklist

Admin portal
- [x] Add/manage stores
- [x] Add/manage products
- [x] Manage inventory per store/product
- [x] Configure product quantity discounts (min qty + %)
- [x] Configure platform/order discounts (min amount + %)
- [x] View customer orders

Customer portal
- [x] Register + login
- [x] View available products
- [x] Select quantity
- [x] Add to cart
- [x] View cart + applicable discount
- [x] Place order
- [x] View own orders

Inventory rules
- [x] Same product in multiple stores
- [x] One order can span multiple stores (across different product lines)
- [x] Auto store selection by availability + distance
- [x] Prefer single store per line when possible
- [x] Fall back to multi-store split per line when needed
- [x] Inventory decremented after order placed

Discounts
- [x] Product discount applies when qty meets configured minimum
- [x] Platform discount applies when order amount meets configured minimum
- [x] Product and platform discounts never combine on the same order

## Concrete test cases (from the rules above)

**Product discount, single tier**: Product price $10, discount rule (min_qty=5, 10%).
Cart qty 5 → line_discount = 5 × $10 × 10% = $5.00. Cart qty 4 → no discount. Cart qty 10
(still only one tier, min 5) → discount = 10 × $10 × 10% = $10.00.

**Product discount, multiple tiers**: rules (5, 10%) and (10, 20%). Qty 7 → 10% tier applies
(20% tier not met). Qty 12 → 20% tier applies (highest qualifying wins, not both).

**Platform discount**: rule (min_order_amount=$100, 15%). Subtotal $120 → platform_discount
= $18.00, total = $102.00. Subtotal $90 → no platform discount.

**Cannot combine**: Cart has a product-discount-eligible line worth $8 product-discount, and
subtotal also qualifies for a platform discount worth $15. Only platform discount applies
(bigger amount) → discount_amount=$15, discount_type=platform, line_discount_amount=0 on all
lines even though the product tier was met.

**Single-store fulfillment**: Product X, Store A (dist 2km, stock 20), Store B (dist 5km,
stock 20). Order qty 15 → fulfilled entirely from Store A (nearest, sufficient alone).

**Multi-store split**: Product X, Store A (dist 2km, stock 5), Store B (dist 5km, stock 20).
Order qty 15 → 5 from Store A + 10 from Store B (nearest first, greedy).

**Insufficient stock**: Product X total stock across all stores = 8. Order qty 10 → whole
order rejected 422, nothing decremented anywhere.

**Concurrent orders**: two customers order the last 5 units of Product X from the same store
at once → one succeeds, the other either gets a smaller/alternate allocation or a 422 if
nothing is left anywhere — no oversell (row lock on inventory).

## Slices (~5–8 min each, riskiest first, commit after each)

Backend core logic (no HTTP yet — pure PHP/unit-testable, riskiest)
1. [x] Migrations + models for all tables in SCHEMA.md. Done when: `php artisan migrate` runs clean.
2. [x] `DiscountCalculator` service: given cart lines + active discount rules, returns
   {discount_type, discount_amount, per-line discounts} per the CONTRACT.md rule. Done when:
   unit tests for all 4 discount test cases above pass.
3. [x] `StoreAllocator` service: given product + qty + customer lat/lng, returns per-store
   allocation list using Haversine distance. Done when: unit tests for single-store,
   multi-store-split and insufficient-stock cases above pass.

Auth
4. [x] Sanctum install + config. Register/login/logout/me endpoints, role on user. Done when:
   curl register→login→me round-trip returns a working token for a customer.
5. [x] Admin seeder (1 admin) + demo data seeder (stores, products, inventory, discounts).
   Done when: `php artisan db:seed` prints admin email/password and creates enough demo data
   to place a real order.
6. [x] Role middleware (`role:admin`, `role:customer`) wired onto route groups. Done when: a
   customer token hitting an `/admin/*` route gets 403.

Admin CRUD
7. [x] Store CRUD endpoints. Done when: create/list/update/deactivate all verified via curl.
8. [x] Product CRUD endpoints. Done when: same, via curl.
9. [x] Inventory upsert + list-per-store endpoints. Done when: set + read quantity via curl.
10. [x] Product discount CRUD endpoints. Done when: create/list/update/deactivate via curl.
11. [x] Platform discount CRUD endpoints. Done when: create/list/update/deactivate via curl.
12. [x] Admin order list/detail endpoints. Done when: seeded order (created in slice 15)
    shows up with correct items/allocations via curl.

Customer flow
13. [x] Product list/detail endpoints (active only, available_quantity, discount_tiers).
    Done when: curl shows correct aggregated stock across stores.
14. [x] Cart endpoints (get/add/update/remove), using `DiscountCalculator` for the live
    preview. Done when: curl reproduces the discount test cases end-to-end through the API.
15. [x] Order placement endpoint: validates stock, runs `StoreAllocator` per line inside a
    DB transaction, decrements inventory, clears cart, snapshots discount. Done when: curl
    places a real multi-store order and inventory numbers drop correctly afterward.
16. [x] Customer order list/detail endpoints (own orders only). Done when: curl as a
    different customer gets 404 on someone else's order id.

Frontend — shared
17. [x] `react-router-dom` + API client (base URL, token header, unwraps
    `{data,message}`/shows `message` on error) + auth context. Done when: login persists
    token and redirects by role.

Frontend — customer portal
18. [x] Register/login pages. Done when: new account can log in through the UI.
19. [x] Product list page (qty selector, add to cart, disabled when out of stock). Done when:
    adding a product updates cart count in the UI.
20. [x] Cart page (quantity edit/remove, shows subtotal/discount/total from the API). Done
    when: the discount test cases are visibly correct in the UI.
21. [x] Place order + order confirmation. Done when: placing an order empties the cart and
    shows the new order.
22. [x] Customer order list/detail pages. Done when: past orders list and open with correct
    per-store breakdown.

Frontend — admin portal
23. [x] Store management page (list/create/edit/deactivate). Done when: usable end-to-end.
24. [x] Product management page (list/create/edit/deactivate). Done when: usable end-to-end.
25. [x] Inventory management page (per store, edit quantities per product). Done when:
    usable end-to-end.
26. [x] Product + platform discount management pages. Done when: usable end-to-end.
27. [x] Admin order list/detail pages. Done when: usable end-to-end.

Final
28. [x] Final verification pass (step 8 of CLAUDE.md pipeline).
