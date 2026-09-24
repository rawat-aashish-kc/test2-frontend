import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, ApiError } from '../../api/client'
import type { OrderDetail } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { OrderDetailView } from '../../components/OrderDetailView'

export function CustomerOrderDetailPage() {
  const { id } = useParams()
  const [order, setOrder] = useState<OrderDetail | null>(null)
  const [error, setError] = useState<string | null>(null)

  function load() {
    api
      .get<OrderDetail>(`/orders/${id}`)
      .then(setOrder)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  useEffect(load, [id])

  async function handleReturn(orderItemId: number, quantity: number) {
    setError(null)
    try {
      const updated = await api.post<OrderDetail>(`/orders/${id}/returns`, {
        items: [{ order_item_id: orderItemId, quantity }],
      })
      setOrder(updated)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  return (
    <div>
      <p>
        <Link to="/orders">← Back to orders</Link>
      </p>
      <h1>Order #{id}</h1>
      <ErrorBanner message={error} />
      {order && <OrderDetailView order={order} onReturn={handleReturn} />}
    </div>
  )
}
