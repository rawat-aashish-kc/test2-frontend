# SCHEMA.md

All money columns `decimal(10,2)`. All tables have `id` PK + timestamps unless noted.
Extensibility choices: discount tiers are rows (not single fields) so admin can add more
tiers later; `status` columns instead of deletes; distance is computed on the fly, never
stored on the order (except `order_item_allocations.distance_km` — a historical fact, kept
for audit/display, not recomputed).

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

## cart_items
- `cart_id` FK → carts, cascade delete
- `product_id` FK → products, cascade delete
- `quantity` unsigned integer (≥ 1)
- unique(`cart_id`, `product_id`)

## orders
- `user_id` FK → users, restrict delete
- `subtotal` decimal(10,2) — sum of line subtotals before any discount
- `discount_type` enum('none','product','platform')
- `discount_amount` decimal(10,2), default 0
- `total` decimal(10,2) — subtotal − discount_amount
- `status` string, default `placed` (extensible; only `placed` is used now)
- `customer_lat` decimal(10,7) — snapshot of customer location used for allocation
- `customer_lng` decimal(10,7)

## order_items
- `order_id` FK → orders, cascade delete
- `product_id` FK → products, restrict delete (keep history even if product later deleted... products aren't hard-deleted, see below)
- `product_name` string — snapshot (survives product edits)
- `unit_price` decimal(10,2) — snapshot
- `quantity` unsigned integer
- `line_subtotal` decimal(10,2) — unit_price × quantity
- `line_discount_amount` decimal(10,2), default 0 — only non-zero when order.discount_type = 'product'

## order_item_allocations
(records the store split for one order line — may be 1..N rows per order_item)
- `order_item_id` FK → order_items, cascade delete
- `store_id` FK → stores, restrict delete
- `quantity` unsigned integer — quantity fulfilled from this store
- `distance_km` decimal(8,3) — distance from customer to this store at order time

## Notes
- Products/stores are soft-deactivated (`is_active = false`), never hard-deleted, so
  historical orders/inventory stay valid — hence `restrict` on delete FKs from
  order/inventory tables into products/stores.
- `product_discounts`/`platform_discounts` rows are also deactivated, not deleted, so past
  orders' discount reasoning is still traceable if ever needed (`is_active` filters "current"
  ones for calculation).
