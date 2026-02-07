export type UserRole = 'admin' | 'manager' | 'employee_internal' | 'employee_remote'

export interface User {
  id: string
  employeeId: string
  name: string
  email: string
  role: UserRole
  department?: string
  designation?: string
  timezone: string
  companyTimezone: string
  avatar?: string
}

export interface AttendanceRecord {
  id: string
  date: string
  clockIn?: string
  clockOut?: string
  status: 'present' | 'absent' | 'leave' | 'wfh' | 'holiday'
  hoursWorked?: number
  overtime?: number
  timezone: string
}

export interface LeaveBalance {
  type: 'PL' | 'CL' | 'EL' | 'ACL'
  total: number
  used: number
  available: number
}

export interface LeaveRequest {
  id: string
  employeeId: string
  employeeName: string
  type: 'PL' | 'CL' | 'EL' | 'ACL'
  startDate: string
  endDate: string
  days: number
  reason: string
  status: 'pending' | 'approved' | 'rejected'
  appliedDate: string
}

export interface PayrollRecord {
  id: string
  employeeId: string
  employeeName: string
  month: string
  year: number
  baseSalary: number
  allowances: number
  deductions: number
  overtime: number
  netSalary: number
  status: 'pending' | 'processed' | 'paid'
}

export interface DashboardStats {
  totalEmployees: number
  presentToday: number
  absentToday: number
  onLeaveToday: number
  overtimeHours: number
}

export type ProjectStatus = 'upcoming' | 'active' | 'completed'

export interface Project {
  project_id: string
  project_name: string
  start_date: string
  end_date: string
  duration_days: number
  assigned_employees: string[] // Array of employee IDs
  status: ProjectStatus
  created_by: string // Manager ID
  description?: string
}

