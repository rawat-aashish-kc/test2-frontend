# CR-TASKS.md — Product Return

## What changes
Customer can return part or all of a placed order. Returned quantity goes back to the
exact store(s) that fulfilled it. The order's discount is recalculated from scratch on
what's left, using the same product-vs-platform "never combine" rule as checkout — but
evaluated against the discount tiers **as they were when the order was placed** (decided:
"Order-time tiers", see ASSUMPTIONS.md #14), not whatever tiers are configured today.

## Requirement → contract map
1. "Customer can return products from an existing order" → `POST /orders/{order}/returns`
   (auth + role:customer, own order only — 404 otherwise, same pattern as GET /orders/{id}).
2. "Returned quantity is added back to the store inventory, specifically to the store it
   was fulfilled from" → walk each returned line's `order_item_allocations` (original
   fulfillment order), increment `inventories.quantity` at each store, cap at what that
   store actually supplied (`order_item_allocations.returned_quantity` tracks this,
   ASSUMPTIONS.md #16).
3. "Order recalculates its discount... if remaining quantity no longer meets a product
   quantity discount, it no longer applies... if remaining order amount no longer meets
   the platform discount condition, it no longer applies... stay mutually exclusive" →
   `OrderPricer` (new, mirrors `CartPricer`) re-runs `DiscountCalculator::calculate()` +
   `resolve()` — the same pure functions used at checkout — over the order's *remaining*
   quantities, fed from the order-time tier snapshots (new tables, see SCHEMA.md).
4. "Customer sees the updated order amount" → `GET /orders/{id}` (existing endpoint)
   response gains `original_total` (immutable, set at placement) and `refund_amount`
   (`original_total - total`, always current); each order item gains `returned_quantity`.
5. "Order and inventory state must be correct" → the whole return (inventory increments +
   order/order_item recalculation) runs in one DB transaction with row locks, mirroring
   how order placement already does it (decrement → increment, same discipline).

## Test cases → concrete scenarios (write as tests in TASKS.md once implementation starts)
- Multi-store order line (e.g. 3 from Store A + 7 from Store B): returning 5 restores 3 to
  A and 2 to B (original allocation order first, per-allocation cap — ASSUMPTIONS.md #16).
- Returning more than was ordered, or more than what's still remaining after an earlier
  partial return, is rejected 422 with a specific message ("Only 4 of Widget remaining to
  return, 6 requested"), nothing written.
- A line drops below its product-discount tier's `min_quantity` after a return → that
  line's discount is removed on recalculation (or drops to a lower qualifying tier if one
  still applies, evaluated fresh — not just "does the original tier still apply").
- Order subtotal drops below the platform discount's `min_order_amount` after a return →
  platform discount removed on recalculation.
- Both a product and a platform discount still qualify post-return → still mutually
  exclusive; the order's existing `discount_type` is kept if it still qualifies
  (preserves the customer's original cart choice), else falls back automatically
  (ASSUMPTIONS.md #15).
- Two separate partial returns on the same order, then a third that empties every line →
  cumulative `returned_quantity` tracked correctly at every step; order status becomes
  `returned` only once every line's remaining quantity is 0.
- A return admin/customer places right after another (two requests close together) does
  not let `returned_quantity` exceed `quantity` on any line or allocation (row-locked,
  transactional — same discipline as the order-placement race-safety, 409 on conflict).

## Backend work
- [x] Migrations: `orders.original_total`; `order_items.returned_quantity`;
      `order_item_allocations.returned_quantity`; new `order_item_discount_tiers`
      (order_item_id, min_quantity, discount_percent); new `order_platform_discount_tiers`
      (order_id, min_order_amount, discount_percent).
- [x] Order placement (`POST /orders`) additionally snapshots the currently-active
      product discount tiers (per product in the cart) and platform discount tiers into
      the new snapshot tables — this is what makes order-time recalculation possible.
- [x] `OrderPricer` service (mirrors `CartPricer`): builds calculator input from an
      order's remaining quantities + its own tier snapshots + its current `discount_type`
      as the resolve() preference; returns the same shape `CartPricer` does.
- [x] `POST /orders/{order}/returns` — validate, restore inventory per allocation
      (row-locked), update `returned_quantity` counters, recalculate via `OrderPricer`,
      update `order_items.line_subtotal`/`line_discount_amount` and the order's
      `subtotal`/`discount_type`/`discount_amount`/`total`, set `status = "returned"` when
      every line is fully returned. One transaction.
- [x] `GET /orders`, `GET /orders/{id}`, admin `GET /admin/orders/{id}` responses gain
      `original_total`, `refund_amount`, and `order_items[].returned_quantity`.
- [x] Seeder: place (or seed directly) a demo multi-store order on the demo customer so a
      return can be tested by hand without placing one manually first.

## Frontend work (after backend verified — new UI must match the current redesign, not the
old styling)
- [x] Customer order detail page: a "Return" control per line (quantity input capped at
      remaining, per line), shows `refund_amount`/`original_total` after a return.

## Docs
- [x] SCHEMA.md updated (this pass).
- [x] CONTRACT.md updated (this pass).
- [x] ASSUMPTIONS.md — defaults #14–#19 (allocation-order restock, order-time tiers,
      preference-carries-forward on recalculation, no partial-return status, no separate
      returns/audit table — cumulative counters are enough for every stated requirement).
