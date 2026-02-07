'use client'

import React, { useState } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Modal } from '@/components/ui/Modal'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/Table'
import { formatDate, getStatusColor } from '@/lib/utils'
import { LeaveBalance, LeaveRequest } from '@/types'
import { useToast } from '@/components/ui/Toaster'
import { Calendar, Plus } from 'lucide-react'

// Mock data
const mockLeaveBalances: LeaveBalance[] = [
  { type: 'PL', total: 12, used: 3, available: 9 },
  { type: 'CL', total: 6, used: 1, available: 5 },
  { type: 'EL', total: 5, used: 0, available: 5 },
  { type: 'ACL', total: 0, used: 0, available: 0 },
]

const mockLeaveRequests: LeaveRequest[] = [
  {
    id: '1',
    employeeId: 'emp001',
    employeeName: 'John Doe',
    type: 'PL',
    startDate: new Date(Date.now() + 5 * 24 * 60 * 60 * 1000).toISOString(),
    endDate: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(),
    days: 3,
    reason: 'Personal work',
    status: 'approved',
    appliedDate: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
  },
  {
    id: '2',
    employeeId: 'emp001',
    employeeName: 'John Doe',
    type: 'CL',
    startDate: new Date(Date.now() + 10 * 24 * 60 * 60 * 1000).toISOString(),
    endDate: new Date(Date.now() + 10 * 24 * 60 * 60 * 1000).toISOString(),
    days: 1,
    reason: 'Medical appointment',
    status: 'pending',
    appliedDate: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString(),
  },
]

export default function LeaveContent() {
  const { user } = useAuth()
  const { showToast } = useToast()
  const [isRequestModalOpen, setIsRequestModalOpen] = useState(false)
  const [formData, setFormData] = useState({
    type: 'PL' as 'PL' | 'CL' | 'EL' | 'ACL',
    startDate: '',
    endDate: '',
    reason: '',
  })

  if (!user) return null

  const isManager = user.role === 'manager' || user.role === 'admin'

  const handleSubmitLeaveRequest = () => {
    // Validate form
    if (!formData.startDate || !formData.endDate || !formData.reason) {
      showToast('Please fill all fields', 'error')
      return
    }

    // Calculate days
    const start = new Date(formData.startDate)
    const end = new Date(formData.endDate)
    const days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1

    // Check balance
    const balance = mockLeaveBalances.find(b => b.type === formData.type)
    if (balance && days > balance.available) {
      showToast(`Insufficient ${formData.type} balance`, 'error')
      return
    }

    showToast('Leave request submitted successfully', 'success')
    setIsRequestModalOpen(false)
    setFormData({ type: 'PL', startDate: '', endDate: '', reason: '' })
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-0">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 mt-0">Leave Management</h1>
          <p className="mt-1 text-sm text-gray-600">Manage your leave requests and balances</p>
        </div>
        {!isManager && (
          <Button onClick={() => setIsRequestModalOpen(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Request Leave
          </Button>
        )}
      </div>

      {/* Leave Balances */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {mockLeaveBalances.map((balance) => (
          <Card key={balance.type}>
            <CardContent className="p-6">
              <div className="text-center">
                <p className="text-sm font-medium text-gray-600 mb-2">{balance.type}</p>
                <p className="text-3xl font-bold text-gray-900 mb-1">{balance.available}</p>
                <p className="text-xs text-gray-500">
                  {balance.used} of {balance.total} used
                </p>
                <div className="mt-4 w-full bg-gray-200 rounded-full h-2">
                  <div
                    className="bg-primary-600 h-2 rounded-full transition-all"
                    style={{ width: `${(balance.used / balance.total) * 100}%` }}
                  />
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Leave Requests */}
      <Card>
        <CardHeader>
          <CardTitle>{isManager ? 'Leave Approvals' : 'My Leave Requests'}</CardTitle>
        </CardHeader>
        <CardContent>
          {mockLeaveRequests.length > 0 ? (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    {isManager && <TableHead>Employee</TableHead>}
                    <TableHead>Type</TableHead>
                    <TableHead>Start Date</TableHead>
                    <TableHead>End Date</TableHead>
                    <TableHead>Days</TableHead>
                    <TableHead>Reason</TableHead>
                    <TableHead>Status</TableHead>
                    {isManager && <TableHead>Action</TableHead>}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockLeaveRequests.map((request) => (
                    <TableRow key={request.id}>
                      {isManager && (
                        <TableCell className="font-medium">{request.employeeName}</TableCell>
                      )}
                      <TableCell>
                        <Badge variant="info">{request.type}</Badge>
                      </TableCell>
                      <TableCell>{formatDate(request.startDate)}</TableCell>
                      <TableCell>{formatDate(request.endDate)}</TableCell>
                      <TableCell>{request.days}</TableCell>
                      <TableCell className="max-w-xs truncate">{request.reason}</TableCell>
                      <TableCell>
                        <Badge className={getStatusColor(request.status)}>
                          {request.status}
                        </Badge>
                      </TableCell>
                      {isManager && request.status === 'pending' && (
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <Button size="sm" variant="primary">Approve</Button>
                            <Button size="sm" variant="danger">Reject</Button>
                          </div>
                        </TableCell>
                      )}
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          ) : (
            <p className="text-center py-8 text-gray-500">No leave requests</p>
          )}
        </CardContent>
      </Card>

      {/* Leave Request Modal */}
      <Modal
        isOpen={isRequestModalOpen}
        onClose={() => setIsRequestModalOpen(false)}
        title="Request Leave"
      >
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Leave Type
            </label>
            <select
              value={formData.type}
              onChange={(e) => setFormData({ ...formData, type: e.target.value as any })}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
              {mockLeaveBalances.map((balance) => (
                <option key={balance.type} value={balance.type}>
                  {balance.type} ({balance.available} available)
                </option>
              ))}
            </select>
          </div>

          <Input
            label="Start Date"
            type="date"
            value={formData.startDate}
            onChange={(e) => setFormData({ ...formData, startDate: e.target.value })}
            required
          />

          <Input
            label="End Date"
            type="date"
            value={formData.endDate}
            onChange={(e) => setFormData({ ...formData, endDate: e.target.value })}
            required
          />

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1.5">
              Reason
            </label>
            <textarea
              value={formData.reason}
              onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
              rows={4}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
              placeholder="Enter reason for leave..."
              required
            />
          </div>

          <div className="flex items-center gap-3 pt-4">
            <Button
              variant="outline"
              onClick={() => setIsRequestModalOpen(false)}
              className="flex-1"
            >
              Cancel
            </Button>
            <Button onClick={handleSubmitLeaveRequest} className="flex-1">
              Submit Request
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}

