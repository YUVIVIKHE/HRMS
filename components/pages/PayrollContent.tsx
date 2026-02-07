'use client'

import React, { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/Table'
import { formatDate } from '@/lib/utils'
import { PayrollRecord } from '@/types'
import { Download, FileText } from 'lucide-react'

// Mock data
const mockPayroll: PayrollRecord[] = [
  {
    id: '1',
    employeeId: 'emp001',
    employeeName: 'John Doe',
    month: 'November',
    year: 2024,
    baseSalary: 50000,
    allowances: 10000,
    deductions: 5000,
    overtime: 5000,
    netSalary: 60000,
    status: 'paid',
  },
  {
    id: '2',
    employeeId: 'emp002',
    employeeName: 'Jane Smith',
    month: 'November',
    year: 2024,
    baseSalary: 50000,
    allowances: 10000,
    deductions: 5000,
    overtime: 3000,
    netSalary: 58000,
    status: 'processed',
  },
]

export default function PayrollContent() {
  const [selectedPayroll, setSelectedPayroll] = useState<PayrollRecord | null>(null)
  const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth())
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear())

  const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ]

  const totalPayroll = mockPayroll.reduce((sum, p) => sum + p.netSalary, 0)
  const paidCount = mockPayroll.filter(p => p.status === 'paid').length

  return (
    <div className="space-y-6">
      <div className="mt-0">
        <h1 className="text-2xl font-bold text-gray-900 mt-0">Payroll Management</h1>
        <p className="mt-1 text-sm text-gray-600">Manage employee payroll and payslips</p>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Month
              </label>
              <select
                value={selectedMonth}
                onChange={(e) => setSelectedMonth(Number(e.target.value))}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                {months.map((month, index) => (
                  <option key={index} value={index}>
                    {month}
                  </option>
                ))}
              </select>
            </div>
            <div className="flex-1">
              <label className="block text-sm font-medium text-gray-700 mb-1.5">
                Year
              </label>
              <select
                value={selectedYear}
                onChange={(e) => setSelectedYear(Number(e.target.value))}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                {[2024, 2023, 2022].map(year => (
                  <option key={year} value={year}>{year}</option>
                ))}
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <Card>
          <CardContent className="p-6">
            <p className="text-sm font-medium text-gray-600 mb-2">Total Payroll</p>
            <p className="text-3xl font-bold text-gray-900">
              ₹{totalPayroll.toLocaleString()}
            </p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <p className="text-sm font-medium text-gray-600 mb-2">Employees Paid</p>
            <p className="text-3xl font-bold text-success-600">{paidCount}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-6">
            <p className="text-sm font-medium text-gray-600 mb-2">Pending</p>
            <p className="text-3xl font-bold text-warning-600">
              {mockPayroll.length - paidCount}
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Payroll Table */}
      <Card>
        <CardHeader>
          <CardTitle>Payroll Records</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Employee</TableHead>
                  <TableHead>Month</TableHead>
                  <TableHead>Base Salary</TableHead>
                  <TableHead>Allowances</TableHead>
                  <TableHead>Deductions</TableHead>
                  <TableHead>Overtime</TableHead>
                  <TableHead>Net Salary</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Action</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {mockPayroll.map((record) => (
                  <TableRow key={record.id}>
                    <TableCell className="font-medium">{record.employeeName}</TableCell>
                    <TableCell>{record.month} {record.year}</TableCell>
                    <TableCell>₹{record.baseSalary.toLocaleString()}</TableCell>
                    <TableCell>₹{record.allowances.toLocaleString()}</TableCell>
                    <TableCell>₹{record.deductions.toLocaleString()}</TableCell>
                    <TableCell>₹{record.overtime.toLocaleString()}</TableCell>
                    <TableCell className="font-bold">
                      ₹{record.netSalary.toLocaleString()}
                    </TableCell>
                    <TableCell>
                      <Badge
                        className={
                          record.status === 'paid'
                            ? 'bg-success-100 text-success-700 border-success-200'
                            : record.status === 'processed'
                            ? 'bg-warning-100 text-warning-700 border-warning-200'
                            : 'bg-gray-100 text-gray-700 border-gray-200'
                        }
                      >
                        {record.status}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setSelectedPayroll(record)}
                      >
                        <FileText className="h-4 w-4 mr-1" />
                        View
                      </Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>

      {/* Payslip Modal */}
      {selectedPayroll && (
        <Modal
          isOpen={!!selectedPayroll}
          onClose={() => setSelectedPayroll(null)}
          title="Payslip"
          size="lg"
        >
          <div className="space-y-6">
            <div className="border-b border-gray-200 pb-4">
              <h3 className="text-lg font-semibold text-gray-900">
                {selectedPayroll.employeeName}
              </h3>
              <p className="text-sm text-gray-600">
                {selectedPayroll.month} {selectedPayroll.year}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-sm text-gray-600 mb-1">Base Salary</p>
                <p className="text-lg font-semibold">
                  ₹{selectedPayroll.baseSalary.toLocaleString()}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600 mb-1">Allowances</p>
                <p className="text-lg font-semibold text-success-600">
                  +₹{selectedPayroll.allowances.toLocaleString()}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600 mb-1">Deductions</p>
                <p className="text-lg font-semibold text-danger-600">
                  -₹{selectedPayroll.deductions.toLocaleString()}
                </p>
              </div>
              <div>
                <p className="text-sm text-gray-600 mb-1">Overtime</p>
                <p className="text-lg font-semibold text-warning-600">
                  +₹{selectedPayroll.overtime.toLocaleString()}
                </p>
              </div>
            </div>

            <div className="pt-4 border-t border-gray-200">
              <div className="flex items-center justify-between">
                <p className="text-lg font-semibold text-gray-900">Net Salary</p>
                <p className="text-2xl font-bold text-primary-600">
                  ₹{selectedPayroll.netSalary.toLocaleString()}
                </p>
              </div>
            </div>

            <div className="flex items-center gap-3 pt-4">
              <Button
                variant="outline"
                onClick={() => setSelectedPayroll(null)}
                className="flex-1"
              >
                Close
              </Button>
              <Button className="flex-1">
                <Download className="h-4 w-4 mr-2" />
                Download Payslip
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}

