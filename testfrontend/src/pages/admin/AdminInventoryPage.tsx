import { useEffect, useState } from 'react'
import { api, ApiError } from '../../api/client'
import type { InventoryRow, Product, Store } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'

export function AdminInventoryPage() {
  const [stores, setStores] = useState<Store[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [storeId, setStoreId] = useState<number | null>(null)
  const [inventory, setInventory] = useState<InventoryRow[]>([])
  const [quantities, setQuantities] = useState<Record<number, string>>({})
  const [error, setError] = useState<string | null>(null)
  const [savingId, setSavingId] = useState<number | null>(null)

  useEffect(() => {
    Promise.all([api.get<Store[]>('/admin/stores'), api.get<Product[]>('/admin/products')])
      .then(([storesData, productsData]) => {
        setStores(storesData)
        setProducts(productsData)
        if (storesData.length > 0) {
          setStoreId(storesData[0].id)
        }
      })
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }, [])

  useEffect(() => {
    if (storeId === null) {
      return
    }
    api
      .get<InventoryRow[]>(`/admin/stores/${storeId}/inventory`)
      .then((rows) => {
        setInventory(rows)
        setQuantities(Object.fromEntries(rows.map((row) => [row.product_id, String(row.quantity)])))
      })
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }, [storeId])

  async function save(productId: number) {
    if (storeId === null) {
      return
    }
    setError(null)
    setSavingId(productId)
    const quantity = Math.max(0, Number(quantities[productId] ?? 0))
    try {
      await api.put(`/admin/stores/${storeId}/inventory/${productId}`, { quantity })
      setInventory((prev) => {
        const existing = prev.find((row) => row.product_id === productId)
        if (existing) {
          return prev.map((row) => (row.product_id === productId ? { ...row, quantity } : row))
        }
        const product = products.find((p) => p.id === productId)
        return [...prev, { product_id: productId, product_name: product?.name ?? '', quantity }]
      })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setSavingId(null)
    }
  }

  const quantityByProduct = Object.fromEntries(inventory.map((row) => [row.product_id, row.quantity]))

  return (
    <div>
      <h1>Inventory</h1>
      <ErrorBanner message={error} />

      <div className="card">
        <div className="form-row" style={{ maxWidth: 320 }}>
          <label htmlFor="store">Store</label>
          <select id="store" value={storeId ?? ''} onChange={(e) => setStoreId(Number(e.target.value))}>
            {stores.map((store) => (
              <option key={store.id} value={store.id}>
                {store.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="card">
        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Quantity at this store</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {products.map((product) => (
              <tr key={product.id}>
                <td>
                  {product.name} {quantityByProduct[product.id] === undefined && <span className="badge">not stocked</span>}
                </td>
                <td>
                  <input
                    type="number"
                    min={0}
                    style={{ width: '5rem' }}
                    value={quantities[product.id] ?? '0'}
                    onChange={(e) => setQuantities((prev) => ({ ...prev, [product.id]: e.target.value }))}
                  />
                </td>
                <td>
                  <button type="button" className="secondary small" disabled={savingId === product.id} onClick={() => save(product.id)}>
                    {savingId === product.id ? 'Saving…' : 'Save'}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
