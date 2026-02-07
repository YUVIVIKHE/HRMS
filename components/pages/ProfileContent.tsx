'use client'

import React from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { formatRole, getRoleBadgeColor } from '@/lib/utils'
import { User, Mail, Building, Briefcase, Clock, Globe } from 'lucide-react'

export default function ProfileContent() {
  const { user } = useAuth()

  if (!user) return null

  return (
    <div className="space-y-6">
      <div className="mt-0">
        <h1 className="text-2xl font-bold text-gray-900 mt-0">Profile</h1>
        <p className="mt-1 text-sm text-gray-600">Your profile information</p>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Profile Card */}
        <Card className="lg:col-span-1">
          <CardContent className="p-6">
            <div className="text-center">
              <div className="h-24 w-24 rounded-full bg-primary-600 flex items-center justify-center text-white text-3xl font-bold mx-auto mb-4">
                {user.name.charAt(0).toUpperCase()}
              </div>
              <h2 className="text-xl font-bold text-gray-900 mb-1">{user.name}</h2>
              <p className="text-sm text-gray-600 mb-3">{user.email}</p>
              <Badge className={getRoleBadgeColor(user.role)}>
                {formatRole(user.role)}
              </Badge>
            </div>
          </CardContent>
        </Card>

        {/* Details Card */}
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Employee Details</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="flex items-start gap-4">
                <div className="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                  <User className="h-5 w-5 text-primary-600" />
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-gray-600">Employee ID</p>
                  <p className="text-base font-semibold text-gray-900">{user.employeeId}</p>
                </div>
              </div>

              <div className="flex items-start gap-4">
                <div className="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                  <Mail className="h-5 w-5 text-primary-600" />
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-gray-600">Email</p>
                  <p className="text-base font-semibold text-gray-900">{user.email}</p>
                </div>
              </div>

              {user.department && (
                <div className="flex items-start gap-4">
                  <div className="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                    <Building className="h-5 w-5 text-primary-600" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-gray-600">Department</p>
                    <p className="text-base font-semibold text-gray-900">{user.department}</p>
                  </div>
                </div>
              )}

              {user.designation && (
                <div className="flex items-start gap-4">
                  <div className="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                    <Briefcase className="h-5 w-5 text-primary-600" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-medium text-gray-600">Designation</p>
                    <p className="text-base font-semibold text-gray-900">{user.designation}</p>
                  </div>
                </div>
              )}

              <div className="flex items-start gap-4">
                <div className="h-10 w-10 rounded-lg bg-primary-100 flex items-center justify-center flex-shrink-0">
                  <Globe className="h-5 w-5 text-primary-600" />
                </div>
                <div className="flex-1">
                  <p className="text-sm font-medium text-gray-600 mb-2">Timezone</p>
                  <div className="flex flex-wrap gap-2">
                    <Badge variant="info">Your: {user.timezone}</Badge>
                    <Badge variant="info">Company: {user.companyTimezone}</Badge>
                  </div>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      {/* Quick Stats */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">This Month</p>
                <p className="mt-2 text-2xl font-bold text-gray-900">160h</p>
                <p className="text-xs text-gray-500">Hours worked</p>
              </div>
              <Clock className="h-8 w-8 text-primary-400" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Leaves</p>
                <p className="mt-2 text-2xl font-bold text-gray-900">4</p>
                <p className="text-xs text-gray-500">Days remaining</p>
              </div>
              <Briefcase className="h-8 w-8 text-success-400" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Overtime</p>
                <p className="mt-2 text-2xl font-bold text-gray-900">8.5h</p>
                <p className="text-xs text-gray-500">This month</p>
              </div>
              <Clock className="h-8 w-8 text-warning-400" />
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="flex justify-end">
        <Button variant="outline">Edit Profile</Button>
      </div>
    </div>
  )
}

