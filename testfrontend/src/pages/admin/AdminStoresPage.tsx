import { useEffect, useState, type FormEvent } from 'react'
import { api, ApiError } from '../../api/client'
import type { Store } from '../../api/types'
import { ErrorBanner } from '../../components/ErrorBanner'
import { LocationPicker } from '../../components/LocationPicker'

interface StoreForm {
  name: string
  address: string
  lat: number | null
  lng: number | null
}

const emptyForm: StoreForm = { name: '', address: '', lat: null, lng: null }

export function AdminStoresPage() {
  const [stores, setStores] = useState<Store[] | null>(null)
  const [form, setForm] = useState<StoreForm>(emptyForm)
  const [editingId, setEditingId] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  function load() {
    api
      .get<Store[]>('/admin/stores')
      .then(setStores)
      .catch((err) => setError(err instanceof ApiError ? err.message : 'Server error, please try again'))
  }

  useEffect(load, [])

  function startEdit(store: Store) {
    setEditingId(store.id)
    setForm({ name: store.name, address: store.address, lat: store.lat, lng: store.lng })
  }

  function cancelEdit() {
    setEditingId(null)
    setForm(emptyForm)
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    if (form.lat === null || form.lng === null) {
      setError('Pick the store location on the map')
      return
    }
    setSubmitting(true)
    const payload = { name: form.name, address: form.address, lat: form.lat, lng: form.lng }
    try {
      if (editingId) {
        await api.put(`/admin/stores/${editingId}`, payload)
      } else {
        await api.post('/admin/stores', payload)
      }
      cancelEdit()
      load()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setSubmitting(false)
    }
  }

  async function deactivate(store: Store) {
    setError(null)
    try {
      await api.delete(`/admin/stores/${store.id}`)
      load()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    }
  }

  return (
    <div>
      <h1>Stores</h1>
      <ErrorBanner message={error} />

      <div className="card">
        <h2>{editingId ? `Edit store #${editingId}` : 'Add store'}</h2>
        <form onSubmit={onSubmit}>
          <div className="form-row">
            <label htmlFor="name">Name</label>
            <input id="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
          </div>

          <LocationPicker
            address={form.address}
            lat={form.lat}
            lng={form.lng}
            onChange={(location) => setForm({ ...form, ...location })}
          />

          <div className="form-inline" style={{ marginTop: '1rem' }}>
            <button type="submit" disabled={submitting}>
              {editingId ? 'Update store' : 'Create store'}
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
              <th>Address</th>
              <th>Lat</th>
              <th>Lng</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {stores?.map((store) => (
              <tr key={store.id}>
                <td>{store.name}</td>
                <td>{store.address}</td>
                <td>{store.lat}</td>
                <td>{store.lng}</td>
                <td>
                  <span className={`badge ${store.is_active ? 'success' : ''}`}>
                    {store.is_active ? 'Active' : 'Inactive'}
                  </span>
                </td>
                <td>
                  <button type="button" className="secondary small" onClick={() => startEdit(store)}>
                    Edit
                  </button>{' '}
                  {store.is_active && (
                    <button type="button" className="danger small" onClick={() => deactivate(store)}>
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
