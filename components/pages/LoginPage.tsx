'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { useToast } from '@/components/ui/Toaster'

export default function LoginPage() {
  const [employeeId, setEmployeeId] = useState('')
  const [password, setPassword] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const { login } = useAuth()
  const router = useRouter()
  const { showToast } = useToast()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setIsLoading(true)

    try {
      await login(employeeId, password)
      showToast('Login successful!', 'success')
      router.push('/dashboard')
    } catch (error) {
      showToast('Invalid credentials. Please try again.', 'error')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-primary-100 px-4 py-12">
      <Card className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-3xl font-bold text-gray-900 mb-2">HRMS</h1>
          <p className="text-gray-600">Human Resource Management System</p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <Input
            label="Employee ID / Email"
            type="text"
            value={employeeId}
            onChange={(e) => setEmployeeId(e.target.value)}
            placeholder="Enter your employee ID or email"
            required
            autoFocus
          />

          <Input
            label="Password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Enter your password"
            required
          />

          <Button
            type="submit"
            className="w-full"
            isLoading={isLoading}
            disabled={isLoading}
          >
            Sign In
          </Button>
        </form>

        <div className="mt-6 text-center text-sm text-gray-600">
          <p className="mb-2">Demo Credentials:</p>
          <div className="space-y-1 text-xs">
            <p>Admin: <code className="bg-gray-100 px-1 rounded">admin001</code> / password</p>
            <p>Manager: <code className="bg-gray-100 px-1 rounded">manager001</code> / password</p>
            <p>Employee: <code className="bg-gray-100 px-1 rounded">emp001</code> / password</p>
          </div>
        </div>
      </Card>
    </div>
  )
}

