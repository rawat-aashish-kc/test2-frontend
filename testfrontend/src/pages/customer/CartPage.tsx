import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ApiError } from '../../api/client'
import type { Cart, OrderDetail } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { QuantityStepper } from '../../components/QuantityStepper'
import { money } from '../../lib/format'

function quantitiesFromCart(cart: Cart): Record<number, number> {
  return Object.fromEntries(cart.items.map((item) => [item.product_id, item.quantity]))
}

export function CartPage() {
  const [cart, setCart] = useState<Cart | null>(null)
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const [error, setError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  const [savingId, setSavingId] = useState<number | null>(null)
  const [choosingDiscount, setChoosingDiscount] = useState(false)
  const navigate = useNavigate()

  function load() {
    api
      .get<Cart>('/cart')
      .then((data) => {
        setCart(data)
        setQuantities(quantitiesFromCart(data))
      })
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  useEffect(load, [])

  async function saveQuantity(productId: number) {
    setError(null)
    setSavingId(productId)
    try {
      const updated = await api.put<Cart>(`/cart/items/${productId}`, { quantity: quantities[productId] })
      setCart(updated)
      setQuantities(quantitiesFromCart(updated))
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setSavingId(null)
    }
  }

  async function removeItem(productId: number) {
    setError(null)
    try {
      const updated = await api.delete<Cart>(`/cart/items/${productId}`)
      setCart(updated)
      setQuantities(quantitiesFromCart(updated))
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  async function chooseDiscount(discountType: 'product' | 'platform') {
    setError(null)
    setChoosingDiscount(true)
    try {
      const updated = await api.put<Cart>('/cart/discount-choice', { discount_type: discountType })
      setCart(updated)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setChoosingDiscount(false)
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
                {cart.items.map((item) => {
                  const pendingQuantity = quantities[item.product_id] ?? item.quantity
                  const dirty = pendingQuantity !== item.quantity
                  return (
                    <tr key={item.product_id}>
                      <td>{item.product_name}</td>
                      <td>{money(item.unit_price)}</td>
                      <td>
                        <QuantityStepper
                          value={pendingQuantity}
                          min={1}
                          onChange={(quantity) =>
                            setQuantities((prev) => ({ ...prev, [item.product_id]: quantity }))
                          }
                        />
                      </td>
                      <td>{money(item.line_subtotal)}</td>
                      <td>
                        <button
                          type="button"
                          className="secondary small"
                          disabled={!dirty || savingId === item.product_id}
                          onClick={() => saveQuantity(item.product_id)}
                        >
                          {savingId === item.product_id ? 'Saving…' : 'Save'}
                        </button>{' '}
                        <button type="button" className="secondary small" onClick={() => removeItem(item.product_id)}>
                          Remove
                        </button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>

            {cart.discount_options.product.available && cart.discount_options.platform.available && (
              <div className="discount-picker">
                <span className="discount-picker-label">Product and platform discounts can't combine — choose one:</span>
                <label>
                  <input
                    type="radio"
                    name="discount-choice"
                    checked={cart.discount_type === 'product'}
                    disabled={choosingDiscount}
                    onChange={() => chooseDiscount('product')}
                  />
                  Product discount (-{money(cart.discount_options.product.amount)})
                </label>
                <label>
                  <input
                    type="radio"
                    name="discount-choice"
                    checked={cart.discount_type === 'platform'}
                    disabled={choosingDiscount}
                    onChange={() => chooseDiscount('platform')}
                  />
                  Platform discount (-{money(cart.discount_options.platform.amount)})
                </label>
              </div>
            )}

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
