import { useEffect, useState, type FormEvent } from 'react'
import { api, ApiError } from '../../api/client'
import type { Product } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { money } from '../../lib/format'

const emptyForm = { name: '', description: '', price: '' }

export function AdminProductsPage() {
  const [products, setProducts] = useState<Product[] | null>(null)
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  function load() {
    api
      .get<Product[]>('/admin/products')
      .then(setProducts)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  useEffect(load, [])

  function startEdit(product: Product) {
    setEditingId(product.id)
    setForm({ name: product.name, description: product.description ?? '', price: String(product.price) })
  }

  function cancelEdit() {
    setEditingId(null)
    setForm(emptyForm)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    const payload = { name: form.name, description: form.description || null, price: Number(form.price) }
    try {
      if (editingId) {
        await api.put(`/admin/products/${editingId}`, payload)
      } else {
        await api.post('/admin/products', payload)
      }
      cancelEdit()
      load()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setSubmitting(false)
    }
  }

  async function deactivate(product: Product) {
    setError(null)
    try {
      await api.delete(`/admin/products/${product.id}`)
      load()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  return (
    <div>
      <h1>Products</h1>
      <ErrorBanner message={error} />

      <div className="card">
        <h2>{editingId ? `Edit product #${editingId}` : 'Add product'}</h2>
        <form onSubmit={onSubmit}>
          <div className="form-row">
            <label htmlFor="name">Name</label>
            <input id="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </div>
          <div className="form-row">
            <label htmlFor="description">Description</label>
            <input
              id="description"
              value={form.description}
              onChange={(e) => setForm({ ...form, description: e.target.value })}
            />
          </div>
          <div className="form-inline">
            <div className="form-row">
              <label htmlFor="price">Price</label>
              <input
                id="price"
                type="number"
                step="0.01"
                min="0"
                value={form.price}
                onChange={(e) => setForm({ ...form, price: e.target.value })}
                required
              />
            </div>
            <button type="submit" disabled={submitting}>
              {editingId ? 'Update product' : 'Create product'}
            </button>
            {editingId && (
              <button type="button" className="secondary" onClick={cancelEdit}>
                Cancel
              </button>
            )}
          </div>
        </form>
      </div>

      <div className="card">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Price</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {products?.map((product) => (
              <tr key={product.id}>
                <td>{product.name}</td>
                <td>{product.description}</td>
                <td>{money(product.price)}</td>
                <td>
                  <span className={`badge ${product.is_active ? 'success' : ''}`}>
                    {product.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td>
                  <button type="button" className="secondary small" onClick={() => startEdit(product)}>
                    Edit
                  </button>{' '}
                  {product.is_active && (
                    <button type="button" className="danger small" onClick={() => deactivate(product)}>
                      Deactivate
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
