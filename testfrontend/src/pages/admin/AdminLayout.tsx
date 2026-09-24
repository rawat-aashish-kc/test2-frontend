import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../../auth/useAuth'

export function AdminLayout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  async function onLogout() {
    await logout()
    navigate('/login')
  }

  return (
    <div>
      <header className="app-header">
        <span className="brand">Ordering System — Admin</span>
        <nav>
          <NavLink to="/admin" end>
            Stores
          </NavLink>
          <NavLink to="/admin/products">Products</NavLink>
          <NavLink to="/admin/inventory">Inventory</NavLink>
          <NavLink to="/admin/discounts">Discounts</NavLink>
          <NavLink to="/admin/orders">Orders</NavLink>
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
