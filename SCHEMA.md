# SCHEMA.md

All money columns `decimal(10,2)`. All tables have `id` PK + timestamps unless noted.
Extensibility choices: discount tiers are rows (not single fields) so admin can add more
tiers later; `status` columns instead of deletes; distance is computed on the fly, never
stored on the order (except `order_item_allocations.distance_km` — a historical fact, kept
for audit/display, not recomputed). Same reasoning extends to `order_item_discount_tiers`/
`order_platform_discount_tiers`: historical facts (what discount rules applied at the
moment of purchase), snapshotted once and never recomputed from the live
`product_discounts`/`platform_discounts` tables, which can keep changing after the order
was placed.

## users
- `name` string
- `email` string, unique
- `password` string (hashed)
- `role` enum('admin','customer')
- `address` string, nullable (customer only)
- `lat` decimal(10,7), nullable (customer only)
- `lng` decimal(10,7), nullable (customer only)

## stores
- `name` string
- `address` string
- `lat` decimal(10,7)
- `lng` decimal(10,7)
- `is_active` boolean, default true

## products
- `name` string
- `description` text, nullable
- `price` decimal(10,2)
- `is_active` boolean, default true

## inventories
- `store_id` FK → stores, cascade delete
- `product_id` FK → products, cascade delete
- `quantity` unsigned integer, default 0
- unique(`store_id`, `product_id`)

## product_discounts
- `product_id` FK → products, cascade delete
- `min_quantity` unsigned integer (≥ 1)
- `discount_percent` decimal(5,2) (0–100)
- `is_active` boolean, default true
- index(`product_id`, `is_active`)

## platform_discounts
- `min_order_amount` decimal(10,2)
- `discount_percent` decimal(5,2) (0–100)
- `is_active` boolean, default true
- index(`is_active`)
(not tied to a product — order-level, global)

## carts
- `user_id` FK → users, cascade delete, unique (one cart per customer)
- `discount_choice` enum('product','platform'), nullable — customer's pick when both
  discounts qualify (CR-TASKS.md); cleared when the cart is emptied by an order

## cart_items
- `cart_id` FK → carts, cascade delete
- `product_id` FK → products, cascade delete
- `quantity` unsigned integer (≥ 1)
- unique(`cart_id`, `product_id`)

## orders
- `user_id` FK → users, restrict delete
- `subtotal` decimal(10,2) — sum of *current* line subtotals (see order_items note below;
  shrinks after a return)
- `discount_type` enum('none','product','platform') — current, recalculated on every return
- `discount_amount` decimal(10,2), default 0 — current, recalculated on every return
- `total` decimal(10,2) — subtotal − discount_amount, current
- `original_total` decimal(10,2) — `total` as it was the moment the order was placed,
  **never changed afterward**. Lets `refund_amount` always be derived as
  `original_total − total`, correct after any number of returns, with no extra column to
  keep in sync (ASSUMPTIONS.md #14 area, ties into the return feature)
- `status` string, default `placed`; also `returned` once every line's remaining quantity
  is 0 (ASSUMPTIONS.md #18 — no separate "partially returned" value)
- `customer_lat` decimal(10,7) — snapshot of customer location used for allocation
- `customer_lng` decimal(10,7)

## order_items
- `order_id` FK → orders, cascade delete
- `product_id` FK → products, restrict delete (keep history even if product later deleted... products aren't hard-deleted, see below)
- `product_name` string — snapshot (survives product edits)
- `unit_price` decimal(10,2) — snapshot, never changes
- `quantity` unsigned integer — **original ordered quantity, never changes** (historical
  record of what was actually ordered)
- `returned_quantity` unsigned integer, default 0 — cumulative across all returns on this
  line; `quantity − returned_quantity` = what's still kept. Application-enforced
  `returned_quantity ≤ quantity`
- `line_subtotal` decimal(10,2) — **current**: `unit_price × (quantity − returned_quantity)`,
  recomputed on every return (so `order.subtotal` = sum of these always stays correct)
- `line_discount_amount` decimal(10,2), default 0 — current, recomputed on every return;
  non-zero only when `order.discount_type = 'product'`

## order_item_allocations
(records the store split for one order line — may be 1..N rows per order_item)
- `order_item_id` FK → order_items, cascade delete
- `store_id` FK → stores, restrict delete
- `quantity` unsigned integer — original quantity fulfilled from this store, never changes
- `returned_quantity` unsigned integer, default 0 — cumulative quantity restocked back to
  this specific store; `quantity − returned_quantity` = still outstanding from this store.
  Application-enforced `returned_quantity ≤ quantity`
- `distance_km` decimal(8,3) — distance from customer to this store at order time

## order_item_discount_tiers
(snapshot of the product's active discount tiers *at order placement time* — one row set
per order_item, so a later return can recalculate against what the customer actually saw,
never against tiers an admin changes afterward — ASSUMPTIONS.md #14)
- `order_item_id` FK → order_items, cascade delete
- `min_quantity` unsigned integer
- `discount_percent` decimal(5,2)

## order_platform_discount_tiers
(snapshot of every active platform discount tier at order placement time, order-level —
same reasoning as above)
- `order_id` FK → orders, cascade delete
- `min_order_amount` decimal(10,2)
- `discount_percent` decimal(5,2)

## Notes
- Products/stores are soft-deactivated (`is_active = false`), never hard-deleted, so
  historical orders/inventory stay valid — hence `restrict` on delete FKs from
  order/inventory tables into products/stores.
- `product_discounts`/`platform_discounts` rows are also deactivated, not deleted, so past
  orders' discount reasoning is still traceable if ever needed (`is_active` filters "current"
  ones for calculation).
