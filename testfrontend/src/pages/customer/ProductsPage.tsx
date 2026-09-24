import { useEffect, useState } from 'react'
import { api, ApiError } from '../../api/client'
import type { Cart, CustomerProduct } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { money } from '../../lib/format'

export function ProductsPage() {
  const [products, setProducts] = useState<CustomerProduct[] | null>(null)
  const [cartQuantities, setCartQuantities] = useState<Record<number, number>>({})
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [addingId, setAddingId] = useState<number | null>(null)

  function loadCartQuantities() {
    return api
      .get<Cart>('/cart')
      .then((cart) => {
        setCartQuantities(Object.fromEntries(cart.items.map((item) => [item.product_id, item.quantity])))
      })
      .catch(() => {
        // Cart preview is a nice-to-have here; the add-to-cart call still enforces the real limit server-side.
      })
  }

  useEffect(() => {
    api
      .get<CustomerProduct[]>('/products')
      .then(setProducts)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
    loadCartQuantities()
  }, [])

  function inCart(id: number): number {
    return cartQuantities[id] ?? 0
  }

  function remainingFor(product: CustomerProduct): number {
    return Math.max(0, product.available_quantity - inCart(product.id))
  }

  function quantityFor(product: CustomerProduct): number {
    const remaining = remainingFor(product)
    return Math.min(quantities[product.id] ?? 1, Math.max(remaining, 1))
  }

  async function addToCart(product: CustomerProduct) {
    setError(null)
    setNotice(null)
    setAddingId(product.id)
    const quantity = quantityFor(product)
    try {
      await api.post('/cart/items', { product_id: product.id, quantity })
      setNotice(`Added ${quantity} × ${product.name} to cart`)
      await loadCartQuantities()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setAddingId(null)
    }
  }

  if (!products && !error) {
    return <p className="page-loading">Loading…</p>
  }

  return (
    <div>
      <h1>Products</h1>
      <ErrorBanner message={error} />
      {notice && <div className="badge success" style={{ marginBottom: '1rem' }}>{notice}</div>}
      <div className="grid">
        {products?.map((product) => {
          const remaining = remainingFor(product)
          const alreadyInCart = inCart(product.id)
          const cannotAddMore = remaining <= 0
          return (
            <div className="card product-card" key={product.id}>
              <strong>{product.name}</strong>
              {product.description && <span>{product.description}</span>}
              <span className="price">{money(product.price)}</span>
              <span className="stock">
                {product.available_quantity <= 0 ? 'Out of stock' : `${product.available_quantity} in stock`}
              </span>
              {alreadyInCart > 0 && <span className="stock">{alreadyInCart} already in your cart</span>}
              {product.discount_tiers.length > 0 && (
                <span className="discount-tiers">
                  {product.discount_tiers.map((tier) => `${tier.min_quantity}+ → ${tier.discount_percent}% off`).join(', ')}
                </span>
              )}
              <div className="form-inline">
                <input
                  type="number"
                  min={1}
                  max={remaining || 1}
                  value={quantityFor(product)}
                  disabled={cannotAddMore}
                  onChange={(e) =>
                    setQuantities((prev) => ({
                      ...prev,
                      [product.id]: Math.min(Math.max(1, Number(e.target.value)), Math.max(remaining, 1)),
                    }))
                  }
                  style={{ width: '4.5rem' }}
                />
                <button type="button" disabled={cannotAddMore || addingId === product.id} onClick={() => addToCart(product)}>
                  {addingId === product.id ? 'Adding…' : cannotAddMore ? 'All in cart' : 'Add to cart'}
                </button>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
