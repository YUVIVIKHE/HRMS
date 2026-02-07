'use client'

import React, { useState } from 'react'
import { Bell, Search, Menu, ChevronDown } from 'lucide-react'
import { Menu as HeadlessMenu, Transition } from '@headlessui/react'
import { useAuth } from '@/contexts/AuthContext'
import { formatRole } from '@/lib/utils'
import { getRoleBadgeColor } from '@/lib/utils'
import { Badge } from '@/components/ui/Badge'

export function TopBar({ onMenuClick }: { onMenuClick?: () => void }) {
  const { user, logout } = useAuth()
  const [notifications] = useState(3) // Mock notification count

  return (
    <div className="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-x-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:gap-x-6 sm:px-6 lg:px-8">
      {/* Mobile menu button */}
      <button
        type="button"
        className="-m-2.5 p-2.5 text-gray-700 lg:hidden"
        onClick={onMenuClick}
      >
        <Menu className="h-6 w-6" />
      </button>

      {/* Search */}
      <div className="hidden md:flex flex-1 max-w-md">
        <div className="relative w-full">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            type="text"
            placeholder="Search..."
            className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
        </div>
      </div>

      <div className="flex items-center gap-x-4 ml-auto">
        {/* Notifications */}
        <button className="relative p-2 text-gray-400 hover:text-gray-500 transition-colors">
          <Bell className="h-6 w-6" />
          {notifications > 0 && (
            <span className="absolute top-0 right-0 block h-2 w-2 rounded-full bg-danger-500 ring-2 ring-white" />
          )}
        </button>

        {/* Profile dropdown */}
        <HeadlessMenu as="div" className="relative">
          <HeadlessMenu.Button className="flex items-center gap-x-2 text-sm font-semibold leading-6 text-gray-900 hover:opacity-80">
            <div className="h-8 w-8 rounded-full bg-primary-600 flex items-center justify-center text-white font-medium">
              {user?.name.charAt(0).toUpperCase()}
            </div>
            <span className="hidden lg:block">{user?.name}</span>
            <ChevronDown className="h-4 w-4 text-gray-400" />
          </HeadlessMenu.Button>
          <Transition
            enter="transition ease-out duration-100"
            enterFrom="transform opacity-0 scale-95"
            enterTo="transform opacity-100 scale-100"
            leave="transition ease-in duration-75"
            leaveFrom="transform opacity-100 scale-100"
            leaveTo="transform opacity-0 scale-95"
          >
            <HeadlessMenu.Items className="absolute right-0 z-10 mt-2.5 w-56 origin-top-right rounded-lg bg-white py-2 shadow-lg ring-1 ring-gray-900/5 focus:outline-none">
              <div className="px-4 py-3 border-b border-gray-200">
                <p className="text-sm font-medium text-gray-900">{user?.name}</p>
                <p className="text-xs text-gray-500 truncate">{user?.email}</p>
                <Badge className={`mt-2 ${getRoleBadgeColor(user?.role || '')}`}>
                  {formatRole(user?.role || '')}
                </Badge>
              </div>
              <HeadlessMenu.Item>
                {({ active }) => (
                  <a
                    href="/profile"
                    className={`block px-4 py-2 text-sm ${active ? 'bg-gray-50' : ''} text-gray-700`}
                  >
                    Your profile
                  </a>
                )}
              </HeadlessMenu.Item>
              <HeadlessMenu.Item>
                {({ active }) => (
                  <a
                    href="/settings"
                    className={`block px-4 py-2 text-sm ${active ? 'bg-gray-50' : ''} text-gray-700`}
                  >
                    Settings
                  </a>
                )}
              </HeadlessMenu.Item>
              <HeadlessMenu.Item>
                {({ active }) => (
                  <button
                    onClick={logout}
                    className={`w-full text-left px-4 py-2 text-sm ${active ? 'bg-gray-50' : ''} text-danger-600`}
                  >
                    Sign out
                  </button>
                )}
              </HeadlessMenu.Item>
            </HeadlessMenu.Items>
          </Transition>
        </HeadlessMenu>
      </div>
    </div>
  )
}

