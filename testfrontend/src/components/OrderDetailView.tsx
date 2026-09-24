import { useState } from 'react'
import type { OrderDetail, OrderItem } from '../api/types'
import { money } from '../lib/format'

interface OrderDetailViewProps {
  order: OrderDetail
  /** Only the customer's own order view passes this — admin stays read-only. */
  onReturn?: (orderItemId: number, quantity: number) => Promise<void>
}

export function OrderDetailView({ order, onReturn }: OrderDetailViewProps) {
  return (
    <div className="card">
      <p>
        <span className="badge">{order.status}</span>{' '}
        <span style={{ color: 'var(--color-muted)' }}>{new Date(order.created_at).toLocaleString()}</span>
      </p>
      {order.customer_name && (
        <p>
          <strong>Customer:</strong> {order.customer_name}
        </p>
      )}

      <table>
        <thead>
          <tr>
            <th>Product</th>
            <th>Unit price</th>
            <th>Qty</th>
            <th>Line subtotal</th>
            <th>Discount</th>
            {onReturn && <th></th>}
          </tr>
        </thead>
        <tbody>
          {order.items.map((item) => (
            <OrderItemRow key={item.id} item={item} onReturn={onReturn} />
          ))}
        </tbody>
      </table>

      <div className="totals">
        <div className="line">
          <span>Subtotal</span>
          <span>{money(order.subtotal)}</span>
        </div>
        {order.discount_type !== 'none' && (
          <div className="line discount">
            <span>Discount ({order.discount_type})</span>
            <span>-{money(order.discount_amount)}</span>
          </div>
        )}
        <div className="line total">
          <span>Total</span>
          <span>{money(order.total)}</span>
        </div>
        {order.refund_amount > 0 && (
          <div className="line discount">
            <span>Refunded</span>
            <span>-{money(order.refund_amount)}</span>
          </div>
        )}
      </div>
    </div>
  )
}

function OrderItemRow({ item, onReturn }: { item: OrderItem; onReturn?: (orderItemId: number, quantity: number) => Promise<void> }) {
  const remaining = item.quantity - item.returned_quantity
  const [quantity, setQuantity] = useState(1)
  const [submitting, setSubmitting] = useState(false)

  async function submit() {
    if (!onReturn) return
    setSubmitting(true)
    try {
      await onReturn(item.id, quantity)
      setQuantity(1)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <tr>
      <td>
        {item.product_name}
        <div className="allocations">
          {item.allocations
            .map((allocation) => `${allocation.quantity} from ${allocation.store_name} (${allocation.distance_km.toFixed(1)}km)`)
            .join(' + ')}
        </div>
        {item.returned_quantity > 0 && (
          <div className="allocations">
            {item.returned_quantity} of {item.quantity} returned
          </div>
        )}
      </td>
      <td>{money(item.unit_price)}</td>
      <td>{item.quantity}</td>
      <td>{money(item.line_subtotal)}</td>
      <td>{item.line_discount_amount > 0 ? `-${money(item.line_discount_amount)}` : '—'}</td>
      {onReturn && (
        <td>
          {remaining > 0 ? (
            <div className="form-inline">
              <input
                type="number"
                min={1}
                max={remaining}
                value={quantity}
                disabled={submitting}
                onChange={(e) => setQuantity(Math.min(remaining, Math.max(1, Number(e.target.value))))}
                style={{ width: '4rem' }}
              />
              <button type="button" className="secondary small" disabled={submitting} onClick={submit}>
                {submitting ? 'Returning…' : 'Return'}
              </button>
            </div>
          ) : (
            <span className="badge">fully returned</span>
          )}
        </td>
      )}
    </tr>
  )
}
