'use client'

import React from 'react'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { 
  LayoutDashboard, 
  Clock, 
  Calendar, 
  DollarSign, 
  User, 
  Users,
  Settings,
  FolderKanban
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { UserRole } from '@/types'

interface NavItem {
  name: string
  href: string
  icon: React.ComponentType<{ className?: string }>
  roles: UserRole[]
}

const navigation: NavItem[] = [
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard, roles: ['admin', 'manager', 'employee_internal', 'employee_remote'] },
  { name: 'Attendance', href: '/attendance', icon: Clock, roles: ['admin', 'manager', 'employee_internal', 'employee_remote'] },
  { name: 'Leave', href: '/leave', icon: Calendar, roles: ['admin', 'manager', 'employee_internal', 'employee_remote'] },
  { name: 'Projects', href: '/projects', icon: FolderKanban, roles: ['admin', 'manager', 'employee_internal', 'employee_remote'] },
  { name: 'Payroll', href: '/payroll', icon: DollarSign, roles: ['admin'] },
  { name: 'Employees', href: '/employees', icon: Users, roles: ['admin', 'manager'] },
  { name: 'Profile', href: '/profile', icon: User, roles: ['admin', 'manager', 'employee_internal', 'employee_remote'] },
  { name: 'Settings', href: '/settings', icon: Settings, roles: ['admin', 'manager', 'employee_internal', 'employee_remote'] },
]

interface SidebarProps {
  userRole: UserRole
}

export function Sidebar({ userRole }: SidebarProps) {
  const pathname = usePathname()
  const filteredNav = navigation.filter(item => item.roles.includes(userRole))

  return (
    <div className="hidden lg:flex lg:flex-shrink-0 fixed inset-y-0 left-0 z-30">
      <div className="flex flex-col w-64">
        <div className="flex flex-col flex-grow bg-white border-r border-gray-200 pt-5 pb-4 overflow-y-auto">
          <div className="flex items-center flex-shrink-0 px-4 mb-8">
            <h1 className="text-xl font-bold text-primary-600">HRMS</h1>
          </div>
          <nav className="flex-1 px-2 space-y-1">
            {filteredNav.map((item) => {
              const isActive = pathname === item.href || pathname?.startsWith(item.href + '/')
              const Icon = item.icon
              return (
                <Link
                  key={item.name}
                  href={item.href}
                  className={cn(
                    'group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors',
                    isActive
                      ? 'bg-primary-50 text-primary-700'
                      : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900'
                  )}
                >
                  <Icon
                    className={cn(
                      'mr-3 h-5 w-5 flex-shrink-0',
                      isActive ? 'text-primary-600' : 'text-gray-400 group-hover:text-gray-500'
                    )}
                  />
                  {item.name}
                </Link>
              )
            })}
          </nav>
        </div>
      </div>
    </div>
  )
}

