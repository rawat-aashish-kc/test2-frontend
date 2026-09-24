import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, ApiError } from '../../api/client'
import type { OrderSummary } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { money } from '../../lib/format'

export function AdminOrdersPage() {
  const [orders, setOrders] = useState<OrderSummary[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    api
      .get<OrderSummary[]>('/admin/orders')
      .then(setOrders)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }, [])

  return (
    <div>
      <h1>Customer orders</h1>
      <ErrorBanner message={error} />
      {orders && orders.length === 0 && <p className="empty-state">No orders yet.</p>}
      {orders && orders.length > 0 && (
        <div className="card">
          <table>
            <thead>
              <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Placed</th>
                <th>Discount</th>
                <th>Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {orders.map((order) => (
                <tr key={order.id}>
                  <td>#{order.id}</td>
                  <td>{order.customer_name}</td>
                  <td>{new Date(order.created_at).toLocaleString()}</td>
                  <td>{order.discount_type === 'none' ? '—' : `${order.discount_type} -${money(order.discount_amount)}`}</td>
                  <td>{money(order.total)}</td>
                  <td>
                    <Link to={`/admin/orders/${order.id}`}>View</Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
