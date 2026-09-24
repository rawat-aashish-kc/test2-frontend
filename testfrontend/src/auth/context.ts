import { createContext } from 'react'
import type { User } from '../api/types'

export interface RegisterInput {
  name: string
  email: string
  password: string
  password_confirmation: string
  address: string
  lat: number
  lng: number
}

export interface AuthContextValue {
  user: User | null
  loading: boolean
  login: (email: string, password: string) => Promise<User>
  register: (input: RegisterInput) => Promise<User>
  logout: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)
