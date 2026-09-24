import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { ApiError } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'

export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [address, setAddress] = useState('')
  const [lat, setLat] = useState('0')
  const [lng, setLng] = useState('0')
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    setSubmitting(true)
    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        address,
        lat: Number(lat),
        lng: Number(lng),
      })
      navigate('/products')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Server error, please try again')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="page auth-page">
      <h1>Create an account</h1>
      <div className="card">
        <ErrorBanner message={error} />
        <form onSubmit={onSubmit}>
          <div className="form-row">
            <label htmlFor="name">Name</label>
            <input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="form-row">
            <label htmlFor="email">Email</label>
            <input id="email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          </div>
          <div className="form-row">
            <label htmlFor="password">Password</label>
            <input
              id="password"
              type="password"
              minLength={8}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <div className="form-row">
            <label htmlFor="password_confirmation">Confirm password</label>
            <input
              id="password_confirmation"
              type="password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              required
            />
          </div>
          <div className="form-row">
            <label htmlFor="address">Delivery address</label>
            <input id="address" value={address} onChange={(e) => setAddress(e.target.value)} required />
          </div>
          <div className="form-inline">
            <div className="form-row">
              <label htmlFor="lat">Latitude</label>
              <input id="lat" type="number" step="any" value={lat} onChange={(e) => setLat(e.target.value)} required />
            </div>
            <div className="form-row">
              <label htmlFor="lng">Longitude</label>
              <input id="lng" type="number" step="any" value={lng} onChange={(e) => setLng(e.target.value)} required />
            </div>
          </div>
          <button type="submit" disabled={submitting}>
            {submitting ? 'Creating account…' : 'Create account'}
          </button>
        </form>
      </div>
      <p>
        Already have an account? <Link to="/login">Log in</Link>
      </p>
    </div>
  )
}
