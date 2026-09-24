import { useEffect, useState, type FormEvent } from 'react'
import { api, ApiError } from '../../api/client'
import type { PlatformDiscount, Product, ProductDiscount } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'

export function AdminDiscountsPage() {
  const [products, setProducts] = useState<Product[]>([])
  const [productId, setProductId] = useState<number | null>(null)
  const [productDiscounts, setProductDiscounts] = useState<ProductDiscount[]>([])
  const [minQuantity, setMinQuantity] = useState('')
  const [productPercent, setProductPercent] = useState('')

  const [platformDiscounts, setPlatformDiscounts] = useState<PlatformDiscount[]>([])
  const [minOrderAmount, setMinOrderAmount] = useState('')
  const [platformPercent, setPlatformPercent] = useState('')

  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get<Product[]>('/admin/products')
      .then((data) => {
        setProducts(data)
        if (data.length > 0) {
          setProductId(data[0].id)
        }
      })
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
    loadPlatformDiscounts()
  }, [])

  useEffect(() => {
    if (productId === null) {
      return
    }
    loadProductDiscounts(productId)
  }, [productId])

  function loadProductDiscounts(id: number) {
    api
      .get<ProductDiscount[]>(`/admin/products/${id}/discounts`)
      .then(setProductDiscounts)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  function loadPlatformDiscounts() {
    api
      .get<PlatformDiscount[]>('/admin/platform-discounts')
      .then(setPlatformDiscounts)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  async function addProductDiscount(e: FormEvent) {
    e.preventDefault()
    if (productId === null) {
      return
    }
    setError(null)
    try {
      await api.post(`/admin/products/${productId}/discounts`, {
        min_quantity: Number(minQuantity),
        discount_percent: Number(productPercent),
      })
      setMinQuantity('')
      setProductPercent('')
      loadProductDiscounts(productId)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  async function deactivateProductDiscount(id: number) {
    setError(null)
    try {
      await api.delete(`/admin/product-discounts/${id}`)
      if (productId !== null) {
        loadProductDiscounts(productId)
      }
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  async function addPlatformDiscount(e: FormEvent) {
    e.preventDefault()
    setError(null)
    try {
      await api.post('/admin/platform-discounts', {
        min_order_amount: Number(minOrderAmount),
        discount_percent: Number(platformPercent),
      })
      setMinOrderAmount('')
      setPlatformPercent('')
      loadPlatformDiscounts()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  async function deactivatePlatformDiscount(id: number) {
    setError(null)
    try {
      await api.delete(`/admin/platform-discounts/${id}`)
      loadPlatformDiscounts()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  return (
    <div>
      <h1>Discounts</h1>
      <ErrorBanner message={error} />

      <div className="card">
        <h2>Product quantity discounts</h2>
        <div className="form-row" style={{ maxWidth: 320 }}>
          <label htmlFor="product">Product</label>
          <select id="product" value={productId ?? ''} onChange={(e) => setProductId(Number(e.target.value))}>
            {products.map((product) => (
              <option key={product.id} value={product.id}>
                {product.name}
              </option>
            ))}
          </select>
        </div>

        <table>
          <thead>
            <tr>
              <th>Min quantity</th>
              <th>Discount %</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {productDiscounts.map((tier) => (
              <tr key={tier.id}>
                <td>{tier.min_quantity}+</td>
                <td>{tier.discount_percent}%</td>
                <td>
                  <span className={`badge ${tier.is_active ? 'success' : ''}`}>{tier.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                  {tier.is_active && (
                    <button type="button" className="danger small" onClick={() => deactivateProductDiscount(tier.id)}>
                      Deactivate
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <form onSubmit={addProductDiscount} className="form-inline" style={{ marginTop: '1rem' }}>
          <div className="form-row">
            <label htmlFor="min_quantity">Min quantity</label>
            <input
              id="min_quantity"
              type="number"
              min={1}
              style={{ width: '5rem' }}
              value={minQuantity}
              onChange={(e) => setMinQuantity(e.target.value)}
              required
            />
          </div>
          <div className="form-row">
            <label htmlFor="product_percent">Discount %</label>
            <input
              id="product_percent"
              type="number"
              min={0}
              max={100}
              step="0.01"
              style={{ width: '5rem' }}
              value={productPercent}
              onChange={(e) => setProductPercent(e.target.value)}
              required
            />
          </div>
          <button type="submit">Add tier</button>
        </form>
      </div>

      <div className="card">
        <h2>Platform (order-level) discounts</h2>
        <table>
          <thead>
            <tr>
              <th>Min order amount</th>
              <th>Discount %</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {platformDiscounts.map((tier) => (
              <tr key={tier.id}>
                <td>${tier.min_order_amount}+</td>
                <td>{tier.discount_percent}%</td>
                <td>
                  <span className={`badge ${tier.is_active ? 'success' : ''}`}>{tier.is_active ? 'Active' : 'Inactive'}</span>
                </td>
                <td>
                  {tier.is_active && (
                    <button type="button" className="danger small" onClick={() => deactivatePlatformDiscount(tier.id)}>
                      Deactivate
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <form onSubmit={addPlatformDiscount} className="form-inline" style={{ marginTop: '1rem' }}>
          <div className="form-row">
            <label htmlFor="min_order_amount">Min order amount</label>
            <input
              id="min_order_amount"
              type="number"
              min={0}
              step="0.01"
              style={{ width: '6rem' }}
              value={minOrderAmount}
              onChange={(e) => setMinOrderAmount(e.target.value)}
              required
            />
          </div>
          <div className="form-row">
            <label htmlFor="platform_percent">Discount %</label>
            <input
              id="platform_percent"
              type="number"
              min={0}
              max={100}
              step="0.01"
              style={{ width: '5rem' }}
              value={platformPercent}
              onChange={(e) => setPlatformPercent(e.target.value)}
              required
            />
          </div>
          <button type="submit">Add tier</button>
        </form>
      </div>
    </div>
  )
}
