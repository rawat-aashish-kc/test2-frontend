export type Role = 'admin' | 'customer'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  address: string | null
  lat: number | null
  lng: number | null
}

export interface Store {
  id: number
  name: string
  address: string
  lat: number
  lng: number
  is_active: boolean
}

export interface Product {
  id: number
  name: string
  description: string | null
  price: number
  is_active: boolean
}

export interface DiscountTier {
  min_quantity: number
  discount_percent: number
}

export interface CustomerProduct {
  id: number
  name: string
  description: string | null
  price: number
  available_quantity: number
  discount_tiers: DiscountTier[]
}

export interface InventoryRow {
  product_id: number
  product_name: string
  quantity: number
}

export interface InventorySummaryStore {
  store_id: number
  store_name: string
  quantity: number
}

export interface InventorySummaryRow {
  product_id: number
  product_name: string
  total_quantity: number
  stores: InventorySummaryStore[]
}

export interface ProductDiscount {
  id: number
  product_id: number
  min_quantity: number
  discount_percent: number
  is_active: boolean
}

export interface PlatformDiscount {
  id: number
  min_order_amount: number
  discount_percent: number
  is_active: boolean
}

export type DiscountType = 'none' | 'product' | 'platform'

export interface CartItem {
  product_id: number
  product_name: string
  unit_price: number
  quantity: number
  line_subtotal: number
}

export interface DiscountOption {
  available: boolean
  amount: number
}

export interface Cart {
  items: CartItem[]
  subtotal: number
  discount_type: DiscountType
  discount_amount: number
  total: number
  discount_options: {
    product: DiscountOption
    platform: DiscountOption
  }
}

export interface OrderAllocation {
  store_name: string
  quantity: number
  returned_quantity: number
  distance_km: number
}

export interface OrderItem {
  id: number
  product_id: number
  product_name: string
  unit_price: number
  quantity: number
  returned_quantity: number
  line_subtotal: number
  line_discount_amount: number
  allocations: OrderAllocation[]
}

export interface OrderSummary {
  id: number
  customer_name?: string
  subtotal: number
  discount_type: DiscountType
  discount_amount: number
  total: number
  original_total: number
  refund_amount: number
  status: string
  created_at: string
}

export interface OrderDetail extends OrderSummary {
  items: OrderItem[]
}
