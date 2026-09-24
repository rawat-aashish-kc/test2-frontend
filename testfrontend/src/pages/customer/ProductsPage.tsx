import { useEffect, useState } from 'react'
import { api, ApiError } from '../../api/client'
import type { CustomerProduct } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { money } from '../../lib/format'

export function ProductsPage() {
  const [products, setProducts] = useState<CustomerProduct[] | null>(null)
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const [error, setError] = useState<string | null>(null)
  const [notice, setNotice] = useState<string | null>(null)
  const [addingId, setAddingId] = useState<number | null>(null)

  useEffect(() => {
    api
      .get<CustomerProduct[]>('/products')
      .then(setProducts)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }, [])

  function quantityFor(id: number): number {
    return quantities[id] ?? 1
  }

  async function addToCart(product: CustomerProduct) {
    setError(null)
    setNotice(null)
    setAddingId(product.id)
    try {
      await api.post('/cart/items', { product_id: product.id, quantity: quantityFor(product.id) })
      setNotice(`Added ${quantityFor(product.id)} × ${product.name} to cart`)
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
          const outOfStock = product.available_quantity <= 0
          return (
            <div className="card product-card" key={product.id}>
              <strong>{product.name}</strong>
              {product.description && <span>{product.description}</span>}
              <span className="price">{money(product.price)}</span>
              <span className="stock">
                {outOfStock ? 'Out of stock' : `${product.available_quantity} in stock`}
              </span>
              {product.discount_tiers.length > 0 && (
                <span className="discount-tiers">
                  {product.discount_tiers.map((tier) => `${tier.min_quantity}+ → ${tier.discount_percent}% off`).join(', ')}
                </span>
              )}
              <div className="form-inline">
                <input
                  type="number"
                  min={1}
                  max={product.available_quantity || 1}
                  value={quantityFor(product.id)}
                  disabled={outOfStock}
                  onChange={(e) =>
                    setQuantities((prev) => ({ ...prev, [product.id]: Math.max(1, Number(e.target.value)) }))
                  }
                  style={{ width: '4.5rem' }}
                />
                <button type="button" disabled={outOfStock || addingId === product.id} onClick={() => addToCart(product)}>
                  {addingId === product.id ? 'Adding…' : 'Add to cart'}
                </button>
              </div>
            </div>
          )
        })}
      </div>
    </div>
  )
}
