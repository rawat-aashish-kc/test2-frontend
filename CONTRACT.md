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
- GET `/admin/orders` → 200 `{data:[{id, customer_name, subtotal, discount_type, discount_amount, total, status, created_at}]}`
- GET `/admin/orders/{id}` → 200 `{data:{...order, items:[{product_name, unit_price, quantity, line_subtotal, line_discount_amount, allocations:[{store_name, quantity, distance_km}]}]}}` / 404

## Customer — Products (`/products`, auth + role:customer)
- GET `/products` → 200 `{data:[{id,name,description,price,available_quantity, discount_tiers:[{min_quantity,discount_percent}]}]}`
  (only `is_active=true` products; `available_quantity` = sum of inventory across active stores)
- GET `/products/{id}` → 200 `{data:product}` (same shape) / 404

## Customer — Cart (`/cart`, auth + role:customer)
- GET `/cart` → 200 `{data:{items:[{product_id, product_name, unit_price, quantity, line_subtotal}], subtotal, discount_type, discount_amount, total}}`
  (discount is always recomputed server-side, live, per current cart contents — see rule below)
- POST `/cart/items` Body `{product_id, quantity}` → adds, or increases quantity if line exists → 200 `{data: <cart, same shape as GET>}`
  - 422 `{message:"Only 5 in stock for Widget"}` if `quantity` exceeds `available_quantity`
  - 422 if `quantity < 1`
- PUT `/cart/items/{product_id}` Body `{quantity}` → sets exact quantity → 200 `{data:<cart>}`
  - same stock-limit 422 as above
- DELETE `/cart/items/{product_id}` → 200 `{data:<cart>}`

## Customer — Orders (`/orders`, auth + role:customer)
- POST `/orders` → places order from the customer's current cart, using the customer's
  saved `lat`/`lng`. Body: none (optionally `{lat, lng}` to override for this order).
  - 200/201 `{data:{id, subtotal, discount_type, discount_amount, total, status, items:[...with allocations...]}}`
  - 422 `{message:"Cart is empty"}` if no cart items
  - 422 `{message:"Only 3 of Widget available across all stores, 5 requested"}` if total stock
    (summed across all stores) can't cover a line's requested quantity — checked for every
    line before any inventory is touched (all-or-nothing)
  - on success: cart is cleared, inventory decremented per allocation, all inside one DB transaction
- GET `/orders` → 200 `{data:[{id, subtotal, discount_type, discount_amount, total, status, created_at}]}` (own orders only)
- GET `/orders/{id}` → 200 `{data:{...order, items:[{product_name, unit_price, quantity, line_subtotal, line_discount_amount, allocations:[{store_name, quantity, distance_km}]}]}}` (own order only) / 404 if not owner or doesn't exist

## Discount calculation rule (applies in GET/POST /cart and POST /orders — must match exactly)
1. `product_discount_total` = for each cart line, find the product's active discount tier
   with the highest `min_quantity` ≤ line quantity; if found, `line_discount = unit_price *
   quantity * discount_percent / 100`; sum across lines.
2. `platform_discount_total` = find the active platform-discount tier with the highest
   `min_order_amount` ≤ `subtotal` (pre-discount); if found, `= subtotal * discount_percent / 100`.
3. If both are 0 → `discount_type = "none"`, `discount_amount = 0`.
   Else if `product_discount_total >= platform_discount_total` → `discount_type = "product"`,
   `discount_amount = product_discount_total`, each line's `line_discount_amount` as computed
   in step 1, platform discount not applied.
   Else → `discount_type = "platform"`, `discount_amount = platform_discount_total`,
   all `line_discount_amount = 0`.
4. `total = subtotal - discount_amount`.
