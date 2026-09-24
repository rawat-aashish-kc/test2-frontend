# CR-TASKS.md — customer picks the discount, not auto-applied

## What changes
- When a cart qualifies for BOTH a product discount and a platform discount, the
  customer picks which one applies (was: system silently picked the larger).
- When only one applies, it's still applied automatically (nothing to choose).
- Choice persists on the cart until changed or the cart is emptied by placing an order.
- Order placement uses whatever was chosen at that moment (same as before, just
  resolved from the stored choice instead of "always pick larger").

## What must keep working
- Product-and-platform-never-combine rule (still true — the choice just says *which
  one*, not both).
- Multi-tier "highest qualifying tier wins" logic, unchanged.
- All existing DiscountCalculator/StoreAllocator unit test *scenarios* (single-tier,
  multi-tier, insufficient stock, single/multi-store split) — math must be identical,
  only the "both qualify" case becomes user-controlled instead of auto-max.
- Cart/order response shape stays additive (new fields only), so nothing existing
  breaks: `discount_type`/`discount_amount`/`total`/`line_discount_amount` mean the
  same thing, just reflect the resolved choice instead of an auto-max.

## Backend
- [x] Migration: `carts.discount_choice` nullable enum('product','platform').
- [x] `DiscountCalculator::calculate()` now returns raw `product_discount_total` /
      `platform_discount_total` (no longer picks a winner itself).
- [x] `DiscountCalculator::resolve(productTotal, platformTotal, ?preference)` — pure,
      unit-tested: none/product-only/platform-only/both-with-valid-preference/
      both-with-no-or-invalid-preference (defaults to the larger, old behavior as
      fallback).
- [x] `CartPricer` calls calculate() + resolve(), builds the final priced shape
      (`discount_options.product/.platform.{available,amount}` + resolved
      discount_type/amount/lines), used by both cart preview and order placement.
- [x] `PUT /cart/discount-choice {discount_type}` — validates the choice is currently
      available, stores it, returns the repriced cart. 422 if not available.
- [x] Order placement clears `discount_choice` (along with cart items) after placing,
      so the next cart starts fresh.

## Frontend
- [x] `Cart` type: `discount_options`.
- [x] `api/client`: `PUT /cart/discount-choice`.
- [x] Cart page: when both options are available, show a picker (radio) with each
      option's amount; hidden when only one or neither applies (unchanged UI then).

## Docs
- [x] CONTRACT.md discount rule rewritten; new endpoint documented.
- [x] ASSUMPTIONS.md #7 updated (was: auto-pick larger; now: user picks, larger is
      just the default when neither has been chosen yet).
