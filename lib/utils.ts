import { type ClassValue, clsx } from 'clsx'
import { format } from 'date-fns'
import { formatInTimeZone, utcToZonedTime, zonedTimeToUtc } from 'date-fns-tz'

export function cn(...inputs: ClassValue[]) {
  return clsx(inputs)
}

export function formatTime(date: Date | string, timezone: string): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date
  return formatInTimeZone(dateObj, timezone, 'HH:mm')
}

export function formatDate(date: Date | string, formatStr: string = 'MMM dd, yyyy'): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date
  return format(dateObj, formatStr)
}

export function formatDateTime(date: Date | string, timezone: string): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date
  return formatInTimeZone(dateObj, timezone, 'MMM dd, yyyy HH:mm')
}

export function convertToTimezone(date: Date | string, timezone: string): Date {
  const dateObj = typeof date === 'string' ? new Date(date) : date
  return utcToZonedTime(dateObj, timezone)
}

export function calculateHours(clockIn: string, clockOut: string): number {
  const start = new Date(clockIn)
  const end = new Date(clockOut)
  const diffMs = end.getTime() - start.getTime()
  return Math.round((diffMs / (1000 * 60 * 60)) * 10) / 10
}

export function getStatusColor(status: string): string {
  const colors: Record<string, string> = {
    present: 'bg-success-100 text-success-700 border-success-200',
    absent: 'bg-danger-100 text-danger-700 border-danger-200',
    leave: 'bg-warning-100 text-warning-700 border-warning-200',
    wfh: 'bg-primary-100 text-primary-700 border-primary-200',
    holiday: 'bg-gray-100 text-gray-700 border-gray-200',
    pending: 'bg-warning-100 text-warning-700 border-warning-200',
    approved: 'bg-success-100 text-success-700 border-success-200',
    rejected: 'bg-danger-100 text-danger-700 border-danger-200',
  }
  return colors[status] || 'bg-gray-100 text-gray-700 border-gray-200'
}

export function getRoleBadgeColor(role: string): string {
  const colors: Record<string, string> = {
    admin: 'bg-purple-100 text-purple-700 border-purple-200',
    manager: 'bg-blue-100 text-blue-700 border-blue-200',
    employee_internal: 'bg-green-100 text-green-700 border-green-200',
    employee_remote: 'bg-orange-100 text-orange-700 border-orange-200',
  }
  return colors[role] || 'bg-gray-100 text-gray-700 border-gray-200'
}

export function formatRole(role: string): string {
  const roles: Record<string, string> = {
    admin: 'Admin / HR',
    manager: 'Manager',
    employee_internal: 'Employee (Internal)',
    employee_remote: 'Employee (Remote)',
  }
  return roles[role] || role
}

export function getProjectStatusColor(status: string): string {
  const colors: Record<string, string> = {
    upcoming: 'bg-gray-100 text-gray-700 border-gray-200',
    active: 'bg-primary-100 text-primary-700 border-primary-200',
    completed: 'bg-success-100 text-success-700 border-success-200',
  }
  return colors[status] || 'bg-gray-100 text-gray-700 border-gray-200'
}

export function formatProjectStatus(status: string): string {
  const statuses: Record<string, string> = {
    upcoming: 'Upcoming',
    active: 'Active',
    completed: 'Completed',
  }
  return statuses[status] || status
}

export function calculateDaysRemaining(endDate: string): number {
  const end = new Date(endDate)
  const now = new Date()
  const diffTime = end.getTime() - now.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays > 0 ? diffDays : 0
}

export function calculateDurationDays(startDate: string, endDate: string): number {
  const start = new Date(startDate)
  const end = new Date(endDate)
  const diffTime = end.getTime() - start.getTime()
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

