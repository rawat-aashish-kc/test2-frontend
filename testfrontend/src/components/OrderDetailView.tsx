import type { OrderDetail } from '../api/types'
import { money } from '../lib/format'

export function OrderDetailView({ order }: { order: OrderDetail }) {
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
          </tr>
        </thead>
        <tbody>
          {order.items.map((item) => (
            <tr key={item.product_id}>
              <td>
                {item.product_name}
                <div className="allocations">
                  {item.allocations
                    .map((allocation) => `${allocation.quantity} from ${allocation.store_name} (${allocation.distance_km.toFixed(1)}km)`)
                    .join(' + ')}
                </div>
              </td>
              <td>{money(item.unit_price)}</td>
              <td>{item.quantity}</td>
              <td>{money(item.line_subtotal)}</td>
              <td>{item.line_discount_amount > 0 ? `-${money(item.line_discount_amount)}` : '—'}</td>
            </tr>
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
      </div>
    </div>
  )
}
