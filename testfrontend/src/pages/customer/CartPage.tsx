import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ApiError } from '../../api/client'
import type { Cart, OrderDetail } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { money } from '../../lib/format'

export function CartPage() {
  const [cart, setCart] = useState<Cart | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const navigate = useNavigate()

  function load() {
    api
      .get<Cart>('/cart')
      .then(setCart)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  useEffect(load, [])

  async function updateQuantity(productId: number, quantity: number) {
    setError(null)
    try {
      const updated = await api.put<Cart>(`/cart/items/${productId}`, { quantity })
      setCart(updated)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  async function removeItem(productId: number) {
    setError(null)
    try {
      const updated = await api.delete<Cart>(`/cart/items/${productId}`)
      setCart(updated)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  async function placeOrder() {
    setError(null)
    setBusy(true)
    try {
      const order = await api.post<OrderDetail>('/orders')
      navigate(`/orders/${order.id}`)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setBusy(false)
    }
  }

  if (!cart && !error) {
    return <p className="page-loading">Loading…</p>
  }

  return (
    <div>
      <h1>Your cart</h1>
      <ErrorBanner message={error} />
      {cart && cart.items.length === 0 ? (
        <p className="empty-state">Your cart is empty.</p>
      ) : (
        cart && (
          <div className="card">
            <table>
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Unit price</th>
                  <th>Quantity</th>
                  <th>Line subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {cart.items.map((item) => (
                  <tr key={item.product_id}>
                    <td>{item.product_name}</td>
                    <td>{money(item.unit_price)}</td>
                    <td>
                      <input
                        type="number"
                        min={1}
                        defaultValue={item.quantity}
                        style={{ width: '4rem' }}
                        onBlur={(e) => {
                          const quantity = Math.max(1, Number(e.target.value))
                          if (quantity !== item.quantity) {
                            updateQuantity(item.product_id, quantity)
                          }
                        }}
                      />
                    </td>
                    <td>{money(item.line_subtotal)}</td>
                    <td>
                      <button type="button" className="secondary small" onClick={() => removeItem(item.product_id)}>
                        Remove
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            <div className="totals">
              <div className="line">
                <span>Subtotal</span>
                <span>{money(cart.subtotal)}</span>
              </div>
              {cart.discount_type !== 'none' && (
                <div className="line discount">
                  <span>Discount ({cart.discount_type})</span>
                  <span>-{money(cart.discount_amount)}</span>
                </div>
              )}
              <div className="line total">
                <span>Total</span>
                <span>{money(cart.total)}</span>
              </div>
            </div>

            <div style={{ marginTop: '1rem', textAlign: 'right' }}>
              <button type="button" disabled={busy} onClick={placeOrder}>
                {busy ? 'Placing order…' : 'Place order'}
              </button>
            </div>
          </div>
        )
      )}
    </div>
  )
}
