'use client'

import React, { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/ui/Input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/Table'
import { formatRole, getRoleBadgeColor } from '@/lib/utils'
import { User } from '@/types'
import { Search, User as UserIcon } from 'lucide-react'

// Mock data
const mockEmployees: User[] = [
  {
    id: '1',
    employeeId: 'emp001',
    name: 'John Doe',
    email: 'john@company.com',
    role: 'employee_internal',
    department: 'Engineering',
    designation: 'Software Engineer',
    timezone: 'Asia/Kolkata',
    companyTimezone: 'Asia/Kolkata',
  },
  {
    id: '2',
    employeeId: 'emp002',
    name: 'Jane Smith',
    email: 'jane@company.com',
    role: 'employee_remote',
    department: 'Engineering',
    designation: 'Software Engineer',
    timezone: 'America/New_York',
    companyTimezone: 'Asia/Kolkata',
  },
  {
    id: '3',
    employeeId: 'emp003',
    name: 'Bob Johnson',
    email: 'bob@company.com',
    role: 'employee_internal',
    department: 'Sales',
    designation: 'Sales Executive',
    timezone: 'Asia/Kolkata',
    companyTimezone: 'Asia/Kolkata',
  },
]

export default function EmployeesContent() {
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedDepartment, setSelectedDepartment] = useState<string>('all')

  const departments = ['all', ...Array.from(new Set(mockEmployees.map(e => e.department).filter(Boolean)))]

  const filteredEmployees = mockEmployees.filter(employee => {
    const matchesSearch = 
      employee.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      employee.employeeId.toLowerCase().includes(searchQuery.toLowerCase()) ||
      employee.email.toLowerCase().includes(searchQuery.toLowerCase())
    
    const matchesDepartment = selectedDepartment === 'all' || employee.department === selectedDepartment
    
    return matchesSearch && matchesDepartment
  })

  return (
    <div className="space-y-6">
      <div className="mt-0">
        <h1 className="text-2xl font-bold text-gray-900 mt-0">Employees</h1>
        <p className="mt-1 text-sm text-gray-600">Manage and view employee information</p>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="p-4">
          <div className="flex flex-col sm:flex-row gap-4">
            <div className="flex-1">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
                <input
                  type="text"
                  placeholder="Search employees..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                />
              </div>
            </div>
            <div className="sm:w-48">
              <select
                value={selectedDepartment}
                onChange={(e) => setSelectedDepartment(e.target.value)}
                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
              >
                {departments.map(dept => (
                  <option key={dept} value={dept}>
                    {dept === 'all' ? 'All Departments' : dept}
                  </option>
                ))}
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Employees Table */}
      <Card>
        <CardHeader>
          <CardTitle>Employee List ({filteredEmployees.length})</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Employee</TableHead>
                  <TableHead>Employee ID</TableHead>
                  <TableHead>Department</TableHead>
                  <TableHead>Designation</TableHead>
                  <TableHead>Role</TableHead>
                  <TableHead>Timezone</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredEmployees.length > 0 ? (
                  filteredEmployees.map((employee) => (
                    <TableRow key={employee.id}>
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="h-10 w-10 rounded-full bg-primary-600 flex items-center justify-center text-white font-medium">
                            {employee.name.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="font-medium text-gray-900">{employee.name}</p>
                            <p className="text-sm text-gray-500">{employee.email}</p>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell className="font-mono text-sm">{employee.employeeId}</TableCell>
                      <TableCell>{employee.department || '-'}</TableCell>
                      <TableCell>{employee.designation || '-'}</TableCell>
                      <TableCell>
                        <Badge className={getRoleBadgeColor(employee.role)}>
                          {formatRole(employee.role)}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col gap-1">
                          <span className="text-xs text-gray-600">{employee.timezone}</span>
                          {employee.timezone !== employee.companyTimezone && (
                            <span className="text-xs text-gray-400">Company: {employee.companyTimezone}</span>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                ) : (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center py-8 text-gray-500">
                      No employees found
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

