import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'

export function CustomerLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function onLogout() {
    await logout()
    navigate('/login')
  }

  return (
    <div>
      <header className="app-header">
        <span className="brand">Ordering System</span>
        <nav>
          <NavLink to="/products">Products</NavLink>
          <NavLink to="/cart">Cart</NavLink>
          <NavLink to="/orders">My Orders</NavLink>
        </nav>
        <div className="spacer" />
        <span className="user-info">{user?.name}</span>
        <button type="button" className="secondary small" onClick={onLogout}>
          Log out
        </button>
      </header>
      <main className="page">
        <Outlet />
      </main>
    </div>
  )
}
