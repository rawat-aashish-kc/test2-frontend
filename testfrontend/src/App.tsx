import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './auth/AuthContext'
import { useAuth } from './auth/useAuth'
import { RequireRole } from './auth/RequireRole'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'
import { CustomerLayout } from './pages/customer/CustomerLayout'
import { ProductsPage } from './pages/customer/ProductsPage'
import { CartPage } from './pages/customer/CartPage'
import { CustomerOrdersPage } from './pages/customer/CustomerOrdersPage'
import { CustomerOrderDetailPage } from './pages/customer/CustomerOrderDetailPage'
import { AdminLayout } from './pages/admin/AdminLayout'
import { AdminStoresPage } from './pages/admin/AdminStoresPage'
import { AdminProductsPage } from './pages/admin/AdminProductsPage'
import { AdminInventoryPage } from './pages/admin/AdminInventoryPage'
import { AdminDiscountsPage } from './pages/admin/AdminDiscountsPage'
import { AdminOrdersPage } from './pages/admin/AdminOrdersPage'
import { AdminOrderDetailPage } from './pages/admin/AdminOrderDetailPage'

function HomeRedirect() {
  const { user, loading } = useAuth()
  if (loading) {
    return <p className="page-loading">Loading…</p>
  }
  if (!user) {
    return <Navigate to="/login" replace />
  }
  return <Navigate to={user.role === 'admin' ? '/admin' : '/products'} replace />
}

export default function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/" element={<HomeRedirect />} />
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />

          <Route
            element={
              <RequireRole role="customer">
                <CustomerLayout />
              </RequireRole>
            }
          >
            <Route path="/products" element={<ProductsPage />} />
            <Route path="/cart" element={<CartPage />} />
            <Route path="/orders" element={<CustomerOrdersPage />} />
            <Route path="/orders/:id" element={<CustomerOrderDetailPage />} />
          </Route>

          <Route
            element={
              <RequireRole role="admin">
                <AdminLayout />
              </RequireRole>
            }
          >
            <Route path="/admin" element={<AdminStoresPage />} />
            <Route path="/admin/products" element={<AdminProductsPage />} />
            <Route path="/admin/inventory" element={<AdminInventoryPage />} />
            <Route path="/admin/discounts" element={<AdminDiscountsPage />} />
            <Route path="/admin/orders" element={<AdminOrdersPage />} />
            <Route path="/admin/orders/:id" element={<AdminOrderDetailPage />} />
          </Route>

          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  )
}
