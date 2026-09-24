# CONTRACT.md

Base: `/api`. Auth: `Authorization: Bearer <token>` (Sanctum). All responses:
success `{ "data": ..., "message": "..." }`, error `{ "message": "...", "errors"?: {...} }`.
422 = validation/business rule, 404 = not found, 401 = no/bad token, 403 = wrong role,
409 = conflict, 500 = unexpected (never an empty body).

## Auth

### POST /auth/register — public, customer only
Body: `{name, email, password, password_confirmation, address, lat, lng}`
201 `{data:{token, user}}`
422 `{message:"The email has already been taken.", errors:{email:[...]}}`

### POST /auth/login — public
Body: `{email, password}`
200 `{data:{token, user:{id,name,email,role}}}`
422 `{message:"These credentials do not match our records."}`

### POST /auth/logout — auth
204 no body OR 200 `{data:null, message:"Logged out"}`

### GET /auth/me — auth
200 `{data:{id,name,email,role,address,lat,lng}}`

## Admin — Stores (`/admin/stores`, auth + role:admin)
- GET `/admin/stores` → 200 `{data:[{id,name,address,lat,lng,is_active}]}`
- POST `/admin/stores` Body `{name,address,lat,lng}` → 201 `{data:store}`
- GET `/admin/stores/{id}` → 200 `{data:store}` / 404
- PUT `/admin/stores/{id}` Body any of `{name,address,lat,lng,is_active}` → 200 `{data:store}`
- DELETE `/admin/stores/{id}` → sets `is_active=false` (soft) → 200 `{data:null, message:"Store deactivated"}`

## Admin — Products (`/admin/products`, auth + role:admin)
- GET `/admin/products` → 200 `{data:[{id,name,description,price,is_active}]}`
- POST `/admin/products` Body `{name,description?,price}` → 201 `{data:product}`
- GET `/admin/products/{id}` → 200 `{data:product}` / 404
- PUT `/admin/products/{id}` Body any of `{name,description,price,is_active}` → 200 `{data:product}`
- DELETE `/admin/products/{id}` → sets `is_active=false` → 200 `{data:null, message:"Product deactivated"}`

## Admin — Inventory (`/admin/stores/{store}/inventory`, auth + role:admin)
- GET `/admin/stores/{store}/inventory` → 200 `{data:[{product_id, product_name, quantity}]}`
- PUT `/admin/stores/{store}/inventory/{product}` Body `{quantity}` (upsert row) → 200 `{data:{product_id, quantity}}`
  - 422 if `quantity < 0`

## Admin — Product discounts (`/admin/products/{product}/discounts`, auth + role:admin)
- GET `/admin/products/{product}/discounts` → 200 `{data:[{id,min_quantity,discount_percent,is_active}]}`
- POST `/admin/products/{product}/discounts` Body `{min_quantity, discount_percent}` → 201 `{data:discount}`
  - 422 if `min_quantity < 1` or `discount_percent` not in 0–100
- PUT `/admin/product-discounts/{id}` Body any of `{min_quantity,discount_percent,is_active}` → 200 `{data:discount}`
- DELETE `/admin/product-discounts/{id}` → sets `is_active=false` → 200 `{data:null}`

## Admin — Platform discounts (`/admin/platform-discounts`, auth + role:admin)
- GET `/admin/platform-discounts` → 200 `{data:[{id,min_order_amount,discount_percent,is_active}]}`
- POST `/admin/platform-discounts` Body `{min_order_amount, discount_percent}` → 201 `{data:discount}`
  - 422 if `min_order_amount < 0` or `discount_percent` not in 0–100
- PUT `/admin/platform-discounts/{id}` Body any of `{min_order_amount,discount_percent,is_active}` → 200 `{data:discount}`
- DELETE `/admin/platform-discounts/{id}` → sets `is_active=false` → 200 `{data:null}`

## Admin — Orders (`/admin/orders`, auth + role:admin)
- GET `/admin/orders` → 200 `{data:[{id, customer_name, subtotal, discount_type, discount_amount, total, original_total, refund_amount, status, created_at}]}`
- GET `/admin/orders/{id}` → 200 `{data:{...order, items:[{product_id, product_name, unit_price, quantity, returned_quantity, line_subtotal, line_discount_amount, allocations:[{store_name, quantity, returned_quantity, distance_km}]}]}}` / 404
  (`quantity`/allocation `quantity` are the *original* amounts and never change;
  `returned_quantity` is cumulative; `line_subtotal`/`line_discount_amount`/order totals
  are *current* — i.e. already reflect any returns. `refund_amount = original_total - total`.
  Read-only — admin cannot process a return, see Customer — Orders below)

## Customer — Products (`/products`, auth + role:customer)
- GET `/products` → 200 `{data:[{id,name,description,price,available_quantity, discount_tiers:[{min_quantity,discount_percent}]}]}`
  (only `is_active=true` products; `available_quantity` = sum of inventory across active stores)
- GET `/products/{id}` → 200 `{data:product}` (same shape) / 404

## Customer — Cart (`/cart`, auth + role:customer)
- GET `/cart` → 200 `{data:{items:[{product_id, product_name, unit_price, quantity, line_subtotal}], subtotal, discount_type, discount_amount, total, discount_options:{product:{available,amount}, platform:{available,amount}}}}`
  (discount is always recomputed server-side, live, per current cart contents — see rule below.
  `discount_options` tells the frontend whether a picker is needed: a picker only makes sense
  when both `.available` are true)
- POST `/cart/items` Body `{product_id, quantity}` → adds, or increases quantity if line exists → 200 `{data: <cart, same shape as GET>}`
  - 422 `{message:"Only 5 in stock for Widget"}` if `quantity` exceeds `available_quantity`
  - 422 if `quantity < 1`
- PUT `/cart/items/{product_id}` Body `{quantity}` → sets exact quantity → 200 `{data:<cart>}`
  - same stock-limit 422 as above
- DELETE `/cart/items/{product_id}` → 200 `{data:<cart>}`
- PUT `/cart/discount-choice` Body `{discount_type: "product"|"platform"}` → stores the customer's
  choice on the cart, only meaningful (and only offered by the UI) when both
  `discount_options.product.available` and `.platform.available` are true → 200 `{data:<cart>}`
  - 422 `{message:"The platform discount is not currently available for this cart"}` if the
    chosen type isn't actually available right now (cart changed since the options were fetched)

## Customer — Orders (`/orders`, auth + role:customer)
- POST `/orders` → places order from the customer's current cart, using the customer's
  saved `lat`/`lng`. Body: none (optionally `{lat, lng}` to override for this order).
  - 200/201 `{data:{id, subtotal, discount_type, discount_amount, total, original_total, status, items:[...with allocations...]}}`
  - 422 `{message:"Cart is empty"}` if no cart items
  - 422 `{message:"Only 3 of Widget available across all stores, 5 requested"}` if total stock
    (summed across all stores) can't cover a line's requested quantity — checked for every
    line before any inventory is touched (all-or-nothing)
  - on success: cart is cleared, inventory decremented per allocation, `original_total` set
    equal to `total` (immutable from here on), the currently-active product/platform
    discount tiers are snapshotted for later returns (see Order recalculation rule) — all
    inside one DB transaction
- GET `/orders` → 200 `{data:[{id, subtotal, discount_type, discount_amount, total, original_total, refund_amount, status, created_at}]}` (own orders only)
- GET `/orders/{id}` → 200 `{data:{...order, items:[{product_id, product_name, unit_price, quantity, returned_quantity, line_subtotal, line_discount_amount, allocations:[{store_name, quantity, returned_quantity, distance_km}]}]}}` (own order only) / 404 if not owner or doesn't exist
  (same "original vs current" field meanings as the admin endpoint above)

### POST /orders/{order}/returns — auth + role:customer, own order only (404 otherwise)
Body: `{items: [{order_item_id, quantity}, ...]}` — one or more lines in a single return.
- Validates every `order_item_id` belongs to this order and
  `quantity ≤ (that line's quantity − returned_quantity)` for **all** lines before writing
  anything (all-or-nothing, same discipline as order placement).
  - 422 `{message:"Nothing to return"}` if `items` is empty
  - 422 `{message:"Only 4 of Widget remaining to return, 6 requested"}` if any line's
    requested return exceeds what's still kept
  - 404 if the order isn't this customer's, or doesn't exist
- On success, inside one DB transaction:
  1. For each returned line, restore inventory to the store(s) that supplied it — walk its
     `order_item_allocations` in their original (creation) order, restocking up to each
     allocation's remaining (`quantity − returned_quantity`) until the line's return
     quantity is covered; increments each allocation's `returned_quantity` and the
     corresponding `inventories.quantity`, row-locked (same race-safety pattern as order
     placement's decrement — 409 `{message:"Inventory changed while processing this return, please try again."}` on a detected conflict).
  2. Increments each returned line's `order_items.returned_quantity`.
  3. Recalculates the **whole order** from its remaining quantities per the Order
     recalculation rule below, overwriting `order_items.line_subtotal`/
     `line_discount_amount` and `orders.subtotal`/`discount_type`/`discount_amount`/`total`.
     `original_total` is never touched.
  4. If every line's remaining quantity (`quantity − returned_quantity`) is now 0,
     `orders.status = "returned"`.
  - 200 `{data:{...order, items:[...], refund_amount}}` (same detail shape as `GET /orders/{id}`)

## Order recalculation rule (POST /orders/{order}/returns — the return-time counterpart of
the Discount calculation rule further below, using the order's own tier snapshots instead
of live discount tables, since discount tiers can change after an order is placed)
1. For each order item, `remaining_quantity = quantity − returned_quantity`. Lines with
   `remaining_quantity = 0` contribute 0 to everything below.
2. `product_discount_total` = for each line, find the highest `min_quantity` tier in that
   line's own `order_item_discount_tiers` (its order-time snapshot) that
   `remaining_quantity` still meets; if found, `line_discount = unit_price ×
   remaining_quantity × discount_percent / 100`; sum across lines. Exactly the tier-choice
   logic from step 1 of the Discount calculation rule, just re-run against the smaller
   quantity and the frozen tier set.
3. `platform_discount_total` = highest `min_order_amount` tier in this order's own
   `order_platform_discount_tiers` that the new subtotal (`Σ unit_price × remaining_quantity`)
   still meets; same math as step 2 of the Discount calculation rule.
4. Resolve exactly like step 3 of the Discount calculation rule, **except** the preference
   fed in is the order's *own current* `discount_type` (not a stored cart choice) — so if
   what the order already had still qualifies, it's kept; otherwise resolve() falls
   through the same way it does the first time a cart has no preference yet.
5. `refund_amount = original_total − total` (always current, never separately stored).

## Discount calculation rule (applies in GET/POST/PUT/DELETE /cart/* and POST /orders — must match exactly)
1. `product_discount_total` = for each cart line, find the product's active discount tier
   with the highest `min_quantity` ≤ line quantity; if found, `line_discount = unit_price *
   quantity * discount_percent / 100`; sum across lines.
2. `platform_discount_total` = find the active platform-discount tier with the highest
   `min_order_amount` ≤ `subtotal` (pre-discount); if found, `= subtotal * discount_percent / 100`.
3. Resolve which one applies (never both — product and platform discounts never combine):
   - Neither total is > 0 → `discount_type = "none"`, `discount_amount = 0`.
   - Only one total is > 0 → that one applies automatically (nothing for the customer to
     choose — there's only one option).
   - Both totals are > 0 → this is a customer choice. Use the cart's stored
     `discount_choice` (set via `PUT /cart/discount-choice`) if it's `"product"` or
     `"platform"`; otherwise default to whichever total is larger (ties favor `"product"`).
     This default is just a starting point — the customer can change it any time via
     `PUT /cart/discount-choice` and it sticks until they change it again or the cart is
     emptied by placing an order (which also clears the stored choice).
4. When `discount_type = "product"`, each line's `line_discount_amount` is its own tier
   discount from step 1; when `"platform"` or `"none"`, every line's `line_discount_amount`
   is 0.
5. `total = subtotal - discount_amount`.
6. `discount_options.product.available` / `.platform.available` = whether that total is > 0
   (i.e. whether picking it would do anything) — this is what the cart response uses to
   decide whether to show a picker at all.
