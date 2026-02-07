'use client'

import React, { useState } from 'react'
import { Sidebar } from './Sidebar'
import { TopBar } from './TopBar'
import { BottomNav } from './BottomNav'
import { useAuth } from '@/contexts/AuthContext'

interface AppLayoutProps {
  children: React.ReactNode
}

export function AppLayout({ children }: AppLayoutProps) {
  const { user } = useAuth()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  if (!user) {
    return null
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Sidebar userRole={user.role} />
      <div className="lg:pl-64 flex flex-col flex-1 min-w-0">
        <TopBar onMenuClick={() => setMobileMenuOpen(!mobileMenuOpen)} />
        <main className="flex-1 pb-16 lg:pb-0 min-h-0 mt-0 pt-0">
          <div className="px-4 sm:px-6 lg:px-8 pt-0 pb-6">
            {children}
          </div>
        </main>
      </div>
      <BottomNav />
    </div>
  )
}

