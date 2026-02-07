'use client'

import React, { createContext, useContext, useState, useEffect } from 'react'
import { User, UserRole } from '@/types'

interface AuthContextType {
  user: User | null
  login: (employeeId: string, password: string) => Promise<void>
  logout: () => void
  isAuthenticated: boolean
}

const AuthContext = createContext<AuthContextType | undefined>(undefined)

// Mock user data - Replace with actual API calls
const MOCK_USERS: Record<string, User> = {
  'admin001': {
    id: '1',
    employeeId: 'admin001',
    name: 'Admin User',
    email: 'admin@company.com',
    role: 'admin',
    department: 'HR',
    designation: 'HR Manager',
    timezone: 'Asia/Kolkata',
    companyTimezone: 'Asia/Kolkata',
  },
  'manager001': {
    id: '2',
    employeeId: 'manager001',
    name: 'Manager User',
    email: 'manager@company.com',
    role: 'manager',
    department: 'Engineering',
    designation: 'Engineering Manager',
    timezone: 'Asia/Kolkata',
    companyTimezone: 'Asia/Kolkata',
  },
  'emp001': {
    id: '3',
    employeeId: 'emp001',
    name: 'John Doe',
    email: 'john@company.com',
    role: 'employee_internal',
    department: 'Engineering',
    designation: 'Software Engineer',
    timezone: 'Asia/Kolkata',
    companyTimezone: 'Asia/Kolkata',
  },
  'emp002': {
    id: '4',
    employeeId: 'emp002',
    name: 'Jane Smith',
    email: 'jane@company.com',
    role: 'employee_remote',
    department: 'Engineering',
    designation: 'Software Engineer',
    timezone: 'America/New_York',
    companyTimezone: 'Asia/Kolkata',
  },
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null)

  useEffect(() => {
    // Check for stored session
    const storedUser = localStorage.getItem('hrms_user')
    if (storedUser) {
      setUser(JSON.parse(storedUser))
    }
  }, [])

  const login = async (employeeId: string, password: string) => {
    // Mock login - Replace with actual API call
    await new Promise(resolve => setTimeout(resolve, 500))
    
    // Trim whitespace and convert to lowercase for lookup
    const trimmedEmployeeId = employeeId.trim().toLowerCase()
    const trimmedPassword = password.trim()
    
    // Try to find user by employeeId (case-insensitive)
    let foundUser: User | undefined
    for (const [key, user] of Object.entries(MOCK_USERS)) {
      if (key.toLowerCase() === trimmedEmployeeId || user.email.toLowerCase() === trimmedEmployeeId) {
        foundUser = user
        break
      }
    }
    
    if (foundUser && trimmedPassword === 'password') {
      setUser(foundUser)
      localStorage.setItem('hrms_user', JSON.stringify(foundUser))
    } else {
      throw new Error('Invalid credentials')
    }
  }

  const logout = () => {
    setUser(null)
    localStorage.removeItem('hrms_user')
  }

  return (
    <AuthContext.Provider value={{ user, login, logout, isAuthenticated: !!user }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider')
  }
  return context
}

