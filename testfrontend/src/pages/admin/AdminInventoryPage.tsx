import { Fragment, useEffect, useState } from 'react'
import { api, ApiError } from '../../api/client'
import type { InventoryRow, InventorySummaryRow, Product, Store } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { QuantityStepper } from '../../components/QuantityStepper'

export function AdminInventoryPage() {
  const [stores, setStores] = useState<Store[]>([])
  const [products, setProducts] = useState<Product[]>([])
  const [storeId, setStoreId] = useState<number | null>(null)
  const [inventory, setInventory] = useState<InventoryRow[]>([])
  const [quantities, setQuantities] = useState<Record<number, number>>({})
  const [error, setError] = useState<string | null>(null)
  const [savingId, setSavingId] = useState<number | null>(null)

  const [summary, setSummary] = useState<InventorySummaryRow[] | null>(null)
  const [summaryError, setSummaryError] = useState<string | null>(null)
  const [summarySearch, setSummarySearch] = useState('')
  const [expandedProductIds, setExpandedProductIds] = useState<Set<number>>(new Set())

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

    api
      .get<InventorySummaryRow[]>('/admin/inventory/summary')
      .then(setSummary)
      .catch((err) => setSummaryError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }, [])

  function toggleExpanded(productId: number) {
    setExpandedProductIds((prev) => {
      const next = new Set(prev)
      if (next.has(productId)) {
        next.delete(productId)
      } else {
        next.add(productId)
      }
      return next
    })
  }

  const filteredSummary = summary?.filter((row) => row.product_name.toLowerCase().includes(summarySearch.trim().toLowerCase()))

  useEffect(() => {
    if (storeId === null) {
      return
    }
    api
      .get<InventoryRow[]>(`/admin/stores/${storeId}/inventory`)
      .then((rows) => {
        setInventory(rows)
        setQuantities(Object.fromEntries(rows.map((row) => [row.product_id, row.quantity])))
      })
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }, [storeId])

  async function save(productId: number) {
    if (storeId === null) {
      return
    }
    setError(null)
    setSavingId(productId)
    const quantity = quantities[productId] ?? 0
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
                  <QuantityStepper
                    value={quantities[product.id] ?? 0}
                    min={0}
                    onChange={(quantity) => setQuantities((prev) => ({ ...prev, [product.id]: quantity }))}
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

      <div className="card">
        <h2>Total inventory across all stores</h2>
        <ErrorBanner message={summaryError} />
        <div className="form-row" style={{ maxWidth: 320 }}>
          <label htmlFor="summary-search">Search by product name</label>
          <input
            id="summary-search"
            value={summarySearch}
            onChange={(e) => setSummarySearch(e.target.value)}
            placeholder="e.g. keyboard"
          />
        </div>

        <table>
          <thead>
            <tr>
              <th>Product</th>
              <th>Total quantity</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {filteredSummary?.map((row) => {
              const expanded = expandedProductIds.has(row.product_id)
              return (
                <Fragment key={row.product_id}>
                  <tr>
                    <td>{row.product_name}</td>
                    <td>{row.total_quantity}</td>
                    <td>
                      {row.stores.length > 0 && (
                        <button type="button" className="secondary small" onClick={() => toggleExpanded(row.product_id)}>
                          {expanded ? 'Hide stores' : 'Show stores'}
                        </button>
                      )}
                    </td>
                  </tr>
                  {expanded && (
                    <tr>
                      <td colSpan={3}>
                        <table>
                          <thead>
                            <tr>
                              <th>Store</th>
                              <th>Quantity</th>
                            </tr>
                          </thead>
                          <tbody>
                            {row.stores.map((store) => (
                              <tr key={store.store_id}>
                                <td>{store.store_name}</td>
                                <td>{store.quantity}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </td>
                    </tr>
                  )}
                </Fragment>
              )
            })}
            {filteredSummary?.length === 0 && (
              <tr>
                <td colSpan={3} className="empty-state">
                  No products match "{summarySearch}".
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}
