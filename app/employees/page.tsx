'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { AppLayout } from '@/components/layout/AppLayout'
import EmployeesContent from '@/components/pages/EmployeesContent'

export default function EmployeesPage() {
  const { isAuthenticated, user } = useAuth()
  const router = useRouter()

  useEffect(() => {
    if (!isAuthenticated) {
      router.push('/')
    } else if (user?.role !== 'admin' && user?.role !== 'manager') {
      router.push('/dashboard')
    }
  }, [isAuthenticated, user, router])

  if (!isAuthenticated || (user?.role !== 'admin' && user?.role !== 'manager')) {
    return null
  }

  return (
    <AppLayout>
      <EmployeesContent />
    </AppLayout>
  )
}

