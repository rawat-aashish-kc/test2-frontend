import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, setToken } from '../api/client'
import type { User } from '../api/types'
import { AuthContext, type AuthContextValue, type RegisterInput } from './context'

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    const token = localStorage.getItem('token')
    const restore = token
      ? api
          .get<User>('/auth/me')
          .then(setUser)
          .catch(() => setToken(null))
      : Promise.resolve()

    restore.finally(() => setLoading(false))
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      loading,
      async login(email, password) {
        const { token, user } = await api.post<{ token: string; user: User }>('/auth/login', { email, password })
        setToken(token)
        setUser(user)
        return user
      },
      async register(input: RegisterInput) {
        const { token, user } = await api.post<{ token: string; user: User }>('/auth/register', input)
        setToken(token)
        setUser(user)
        return user
      },
      async logout() {
        try {
          await api.post('/auth/logout')
        } finally {
          setToken(null)
          setUser(null)
        }
      },
    }),
    [user, loading],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
