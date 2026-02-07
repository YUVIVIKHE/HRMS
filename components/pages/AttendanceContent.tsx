'use client'

import React, { useState } from 'react'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/Table'
import { Modal } from '@/components/ui/Modal'
import { formatDate, formatTime, getStatusColor } from '@/lib/utils'
import { AttendanceRecord } from '@/types'
import { Calendar } from 'lucide-react'

// Mock data
const mockAttendance: AttendanceRecord[] = [
  {
    id: '1',
    date: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(),
    clockIn: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000 + 9 * 60 * 60 * 1000).toISOString(),
    clockOut: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000 + 17 * 60 * 60 * 1000).toISOString(),
    status: 'present',
    hoursWorked: 8,
    overtime: 0,
    timezone: 'Asia/Kolkata',
  },
  {
    id: '2',
    date: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000).toISOString(),
    clockIn: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000 + 9 * 60 * 60 * 1000).toISOString(),
    clockOut: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000 + 19 * 60 * 60 * 1000).toISOString(),
    status: 'present',
    hoursWorked: 10,
    overtime: 2,
    timezone: 'Asia/Kolkata',
  },
  {
    id: '3',
    date: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'wfh',
    hoursWorked: 8,
    timezone: 'Asia/Kolkata',
  },
  {
    id: '4',
    date: new Date(Date.now() - 2 * 24 * 60 * 60 * 1000).toISOString(),
    status: 'leave',
    timezone: 'Asia/Kolkata',
  },
  {
    id: '5',
    date: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString(),
    clockIn: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000 + 9 * 60 * 60 * 1000).toISOString(),
    clockOut: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000 + 17 * 60 * 60 * 1000).toISOString(),
    status: 'present',
    hoursWorked: 8,
    overtime: 0,
    timezone: 'Asia/Kolkata',
  },
]

export default function AttendanceContent() {
  const { user } = useAuth()
  const [selectedRecord, setSelectedRecord] = useState<AttendanceRecord | null>(null)
  const [viewMode, setViewMode] = useState<'table' | 'calendar'>('table')

  if (!user) return null

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mt-0">
        <div>
          <h1 className="text-2xl font-bold text-gray-900 mt-0">Attendance</h1>
          <p className="mt-1 text-sm text-gray-600">View your attendance records</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            onClick={() => setViewMode('table')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              viewMode === 'table'
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            Table
          </button>
          <button
            onClick={() => setViewMode('calendar')}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              viewMode === 'calendar'
                ? 'bg-primary-600 text-white'
                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
            }`}
          >
            <Calendar className="h-4 w-4 inline mr-1" />
            Calendar
          </button>
        </div>
      </div>

      {/* Desktop Table View */}
      {viewMode === 'table' && (
        <Card>
          <CardContent padding="none">
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Date</TableHead>
                    <TableHead>Clock In</TableHead>
                    <TableHead>Clock Out</TableHead>
                    <TableHead>Hours</TableHead>
                    <TableHead>Overtime</TableHead>
                    <TableHead>Status</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {mockAttendance.map((record) => (
                    <TableRow
                      key={record.id}
                      className="cursor-pointer"
                      onClick={() => setSelectedRecord(record)}
                    >
                      <TableCell className="font-medium">
                        {formatDate(record.date)}
                      </TableCell>
                      <TableCell>
                        {record.clockIn ? formatTime(record.clockIn, record.timezone) : '-'}
                      </TableCell>
                      <TableCell>
                        {record.clockOut ? formatTime(record.clockOut, record.timezone) : '-'}
                      </TableCell>
                      <TableCell>{record.hoursWorked || 0}h</TableCell>
                      <TableCell>
                        {record.overtime ? (
                          <span className="text-warning-600 font-medium">{record.overtime}h</span>
                        ) : (
                          '-'
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge className={getStatusColor(record.status)}>
                          {record.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Mobile Calendar View */}
      {viewMode === 'calendar' && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {mockAttendance.map((record) => (
            <Card
              key={record.id}
              className="cursor-pointer hover:shadow-md transition-shadow"
              onClick={() => setSelectedRecord(record)}
            >
              <CardContent className="p-4">
                <div className="flex items-center justify-between mb-3">
                  <span className="font-medium text-gray-900">{formatDate(record.date)}</span>
                  <Badge className={getStatusColor(record.status)}>
                    {record.status.toUpperCase()}
                  </Badge>
                </div>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-600">Clock In:</span>
                    <span className="font-medium">
                      {record.clockIn ? formatTime(record.clockIn, record.timezone) : '-'}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-600">Clock Out:</span>
                    <span className="font-medium">
                      {record.clockOut ? formatTime(record.clockOut, record.timezone) : '-'}
                    </span>
                  </div>
                  <div className="flex justify-between pt-2 border-t border-gray-200">
                    <span className="text-gray-600">Hours:</span>
                    <span className="font-bold text-primary-600">{record.hoursWorked || 0}h</span>
                  </div>
                  {record.overtime && (
                    <div className="flex justify-between">
                      <span className="text-gray-600">Overtime:</span>
                      <span className="font-medium text-warning-600">{record.overtime}h</span>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Attendance Detail Modal */}
      {selectedRecord && (
        <Modal
          isOpen={!!selectedRecord}
          onClose={() => setSelectedRecord(null)}
          title="Attendance Details"
        >
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Date</span>
              <span className="font-medium">{formatDate(selectedRecord.date)}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Status</span>
              <Badge className={getStatusColor(selectedRecord.status)}>
                {selectedRecord.status.toUpperCase()}
              </Badge>
            </div>
            {selectedRecord.clockIn && (
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Clock In</span>
                <span className="font-medium">
                  {formatTime(selectedRecord.clockIn, selectedRecord.timezone)}
                </span>
              </div>
            )}
            {selectedRecord.clockOut && (
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Clock Out</span>
                <span className="font-medium">
                  {formatTime(selectedRecord.clockOut, selectedRecord.timezone)}
                </span>
              </div>
            )}
            <div className="flex items-center justify-between">
              <span className="text-sm text-gray-600">Hours Worked</span>
              <span className="font-bold text-lg">{selectedRecord.hoursWorked || 0}h</span>
            </div>
            {selectedRecord.overtime && (
              <div className="flex items-center justify-between">
                <span className="text-sm text-gray-600">Overtime</span>
                <span className="font-medium text-warning-600">{selectedRecord.overtime}h</span>
              </div>
            )}
            <div className="pt-4 border-t border-gray-200">
              <p className="text-sm text-gray-600 mb-2">Timezone</p>
              <div className="flex items-center gap-2">
                <Badge variant="info">Your: {selectedRecord.timezone}</Badge>
                <Badge variant="info">Company: {user.companyTimezone}</Badge>
              </div>
            </div>
          </div>
        </Modal>
      )}
    </div>
  )
}

