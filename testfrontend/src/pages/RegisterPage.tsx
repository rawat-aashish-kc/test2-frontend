import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { ApiError } from '../api/client'
import { ErrorBanner } from '../components/ErrorBanner'
import { LocationPicker } from '../components/LocationPicker'

export function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [address, setAddress] = useState('')
  const [lat, setLat] = useState<number | null>(null)
  const [lng, setLng] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setError(null)
    if (lat === null || lng === null) {
      setError('Pick your delivery location on the map')
      return
    }
    setSubmitting(true)
    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
        address,
        lat,
        lng,
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

          <LocationPicker
            address={address}
            lat={lat}
            lng={lng}
            onChange={(location) => {
              setAddress(location.address)
              setLat(location.lat)
              setLng(location.lng)
            }}
          />

          <button type="submit" disabled={submitting} style={{ marginTop: '1rem' }}>
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
