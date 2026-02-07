'use client'

import React from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/Table'
import { Clock, Users, UserX, Calendar, TrendingUp } from 'lucide-react'
import { formatTime, formatDate, getStatusColor } from '@/lib/utils'
import { DashboardStats, AttendanceRecord, LeaveRequest } from '@/types'

// Mock data - Replace with API calls
const mockStats: DashboardStats = {
  totalEmployees: 150,
  presentToday: 120,
  absentToday: 15,
  onLeaveToday: 10,
  overtimeHours: 45.5,
}

const mockAttendance: AttendanceRecord[] = [
  {
    id: '1',
    date: new Date().toISOString(),
    clockIn: new Date().toISOString(),
    clockOut: new Date(Date.now() + 8 * 60 * 60 * 1000).toISOString(),
    status: 'present',
    hoursWorked: 8,
    overtime: 0,
    timezone: 'Asia/Kolkata',
  },
]

const mockPendingLeaves: LeaveRequest[] = [
  {
    id: '1',
    employeeId: 'emp002',
    employeeName: 'Jane Smith',
    type: 'PL',
    startDate: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toISOString(),
    endDate: new Date(Date.now() + 4 * 24 * 60 * 60 * 1000).toISOString(),
    days: 3,
    reason: 'Family emergency',
    status: 'pending',
    appliedDate: new Date().toISOString(),
  },
]

export default function DashboardContent() {
  const { user } = useAuth()

  if (!user) return null

  const isAdminOrManager = user.role === 'admin' || user.role === 'manager'
  const isEmployee = user.role === 'employee_internal' || user.role === 'employee_remote'

  return (
    <div className="space-y-6 mt-0">
      <div className="mt-0">
        <h1 className="text-2xl font-bold text-gray-900 mt-0">Dashboard</h1>
        <p className="mt-1 text-sm text-gray-600">
          Welcome back, {user.name}
        </p>
      </div>

      {isAdminOrManager && (
        <>
          {/* Stats Cards */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Total Employees</p>
                    <p className="mt-2 text-3xl font-bold text-gray-900">{mockStats.totalEmployees}</p>
                  </div>
                  <div className="h-12 w-12 rounded-full bg-primary-100 flex items-center justify-center">
                    <Users className="h-6 w-6 text-primary-600" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Present Today</p>
                    <p className="mt-2 text-3xl font-bold text-success-600">{mockStats.presentToday}</p>
                  </div>
                  <div className="h-12 w-12 rounded-full bg-success-100 flex items-center justify-center">
                    <Clock className="h-6 w-6 text-success-600" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Absent Today</p>
                    <p className="mt-2 text-3xl font-bold text-danger-600">{mockStats.absentToday}</p>
                  </div>
                  <div className="h-12 w-12 rounded-full bg-danger-100 flex items-center justify-center">
                    <UserX className="h-6 w-6 text-danger-600" />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardContent className="p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">Overtime Hours</p>
                    <p className="mt-2 text-3xl font-bold text-warning-600">{mockStats.overtimeHours}</p>
                  </div>
                  <div className="h-12 w-12 rounded-full bg-warning-100 flex items-center justify-center">
                    <TrendingUp className="h-6 w-6 text-warning-600" />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Attendance Table */}
          <Card>
            <CardHeader>
              <CardTitle>Today's Attendance</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Employee</TableHead>
                    <TableHead>Clock In</TableHead>
                    <TableHead>Clock Out</TableHead>
                    <TableHead>Hours</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockAttendance.length > 0 ? (
                    mockAttendance.map((record) => (
                      <TableRow key={record.id}>
                        <TableCell className="font-medium">{user.name}</TableCell>
                        <TableCell>{record.clockIn ? formatTime(record.clockIn, record.timezone) : '-'}</TableCell>
                        <TableCell>{record.clockOut ? formatTime(record.clockOut, record.timezone) : '-'}</TableCell>
                        <TableCell>{record.hoursWorked || 0}h</TableCell>
                        <TableCell>
                          <Badge className={getStatusColor(record.status)}>
                            {record.status}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))
                  ) : (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center py-8 text-gray-500">
                        No attendance records for today
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          {/* Pending Leave Approvals */}
          {user.role === 'manager' && (
            <Card>
              <CardHeader>
                <CardTitle>Pending Leave Approvals</CardTitle>
              </CardHeader>
              <CardContent>
                {mockPendingLeaves.length > 0 ? (
                  <div className="space-y-4">
                    {mockPendingLeaves.map((leave) => (
                      <div key={leave.id} className="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                        <div>
                          <p className="font-medium text-gray-900">{leave.employeeName}</p>
                          <p className="text-sm text-gray-600">
                            {formatDate(leave.startDate)} - {formatDate(leave.endDate)} ({leave.days} days)
                          </p>
                          <p className="text-sm text-gray-500 mt-1">{leave.reason}</p>
                        </div>
                        <div className="flex items-center gap-2">
                          <Badge className={getStatusColor(leave.status)}>
                            {leave.status}
                          </Badge>
                          <Button size="sm" variant="primary">Approve</Button>
                          <Button size="sm" variant="danger">Reject</Button>
                        </div>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-center py-8 text-gray-500">No pending leave approvals</p>
                )}
              </CardContent>
            </Card>
          )}
        </>
      )}

      {isEmployee && (
        <>
          {/* Employee Dashboard */}
          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {/* Today's Attendance Card */}
            <Card>
              <CardHeader>
                <CardTitle>Today's Attendance</CardTitle>
              </CardHeader>
              <CardContent>
                {mockAttendance.length > 0 ? (
                  <div className="space-y-4">
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-gray-600">Status</span>
                      <Badge className={getStatusColor(mockAttendance[0].status)}>
                        {mockAttendance[0].status}
                      </Badge>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-gray-600">Clock In</span>
                      <span className="font-medium">
                        {mockAttendance[0].clockIn ? formatTime(mockAttendance[0].clockIn, user.timezone) : 'Not clocked in'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-gray-600">Clock Out</span>
                      <span className="font-medium">
                        {mockAttendance[0].clockOut ? formatTime(mockAttendance[0].clockOut, user.timezone) : 'Not clocked out'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between pt-4 border-t border-gray-200">
                      <span className="text-sm font-medium text-gray-900">Hours Worked</span>
                      <span className="text-lg font-bold text-primary-600">
                        {mockAttendance[0].hoursWorked || 0}h
                      </span>
                    </div>
                    {!mockAttendance[0].clockOut && (
                      <Button className="w-full mt-4">Clock Out</Button>
                    )}
                  </div>
                ) : (
                  <div className="text-center py-8">
                    <Clock className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                    <p className="text-gray-600 mb-4">You haven't clocked in today</p>
                    <Button>Clock In</Button>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Monthly Summary */}
            <Card>
              <CardHeader>
                <CardTitle>Monthly Summary</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Total Hours</span>
                    <span className="text-xl font-bold text-gray-900">160h</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Leaves Taken</span>
                    <span className="text-xl font-bold text-gray-900">2 days</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Overtime</span>
                    <span className="text-xl font-bold text-warning-600">8.5h</span>
                  </div>
                  <div className="pt-4 border-t border-gray-200">
                    <p className="text-sm text-gray-600 mb-2">Timezone</p>
                    <div className="flex items-center gap-2">
                      <Badge variant="info">Your: {user.timezone}</Badge>
                      <Badge variant="info">Company: {user.companyTimezone}</Badge>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </>
      )}
    </div>
  )
}

