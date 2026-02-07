'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { CreateProjectModal } from './CreateProjectModal'
import { Project, User } from '@/types'
import { formatDate, getProjectStatusColor, formatProjectStatus, calculateDaysRemaining } from '@/lib/utils'
import { ArrowLeft, Calendar, Clock, Users, Edit, CheckCircle, User as UserIcon } from 'lucide-react'
import { EmptyState } from '@/components/ui/EmptyState'

// Mock data - Replace with API calls
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
  {
    id: '4',
    employeeId: 'emp004',
    name: 'Alice Williams',
    email: 'alice@company.com',
    role: 'employee_internal',
    department: 'Marketing',
    designation: 'Marketing Manager',
    timezone: 'Asia/Kolkata',
    companyTimezone: 'Asia/Kolkata',
  },
]

interface ProjectDetailContentProps {
  projectId: string
}

export default function ProjectDetailContent({ projectId }: ProjectDetailContentProps) {
  const { user } = useAuth()
  const router = useRouter()
  const [isEditModalOpen, setIsEditModalOpen] = useState(false)

  // Mock project data - Replace with API call
  const mockProject: Project | null = {
    project_id: 'proj001',
    project_name: 'Website Redesign',
    start_date: new Date().toISOString(),
    end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
    duration_days: 30,
    assigned_employees: ['1', '2'],
    status: 'active',
    created_by: 'manager001',
    description: 'Complete redesign of company website with modern UI/UX principles and improved performance.',
  }

  const [project, setProject] = useState<Project | null>(mockProject)

  if (!user) return null

  const isManager = user.role === 'admin' || user.role === 'manager'
  const isEmployee = user.role === 'employee_internal' || user.role === 'employee_remote'

  // Check if employee has access to this project
  if (isEmployee && project && !project.assigned_employees.includes(user.id)) {
    return (
      <EmptyState
        icon={<Users className="h-12 w-12 text-gray-400 mb-4" />}
        title="Access Denied"
        description="You don't have access to view this project."
        action={
          <Button onClick={() => router.push('/projects')}>
            Back to Projects
          </Button>
        }
      />
    )
  }

  if (!project) {
    return (
      <EmptyState
        icon={<Users className="h-12 w-12 text-gray-400 mb-4" />}
        title="Project Not Found"
        description="The project you're looking for doesn't exist."
        action={
          <Button onClick={() => router.push('/projects')}>
            Back to Projects
          </Button>
        }
      />
    )
  }

  const assignedEmployees = mockEmployees.filter(emp => project.assigned_employees.includes(emp.id))
  const daysRemaining = calculateDaysRemaining(project.end_date)

  const handleUpdateProject = (projectData: Omit<Project, 'project_id' | 'created_by'>) => {
    if (project) {
      setProject({
        ...projectData,
        project_id: project.project_id,
        created_by: project.created_by,
      })
    }
    setIsEditModalOpen(false)
  }

  const handleMarkCompleted = () => {
    if (project) {
      setProject({
        ...project,
        status: 'completed',
      })
    }
  }

  const handleReassign = () => {
    // This would open a reassign modal in a real implementation
    // For now, we'll just show the edit modal
    setIsEditModalOpen(true)
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => router.push('/projects')}
          className="p-2"
        >
          <ArrowLeft className="h-5 w-5" />
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold text-gray-900">{project.project_name}</h1>
          <p className="mt-1 text-sm text-gray-600">Project Details</p>
        </div>
        {isManager && (
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => setIsEditModalOpen(true)}>
              <Edit className="h-4 w-4 mr-2" />
              Edit
            </Button>
            {project.status !== 'completed' && (
              <Button variant="primary" size="sm" onClick={handleMarkCompleted}>
                <CheckCircle className="h-4 w-4 mr-2" />
                Mark Completed
              </Button>
            )}
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Project Info */}
          <Card>
            <CardHeader>
              <CardTitle>Project Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div>
                <label className="text-sm font-medium text-gray-500">Project Name</label>
                <p className="mt-1 text-lg text-gray-900">{project.project_name}</p>
              </div>

              {project.description && (
                <div>
                  <label className="text-sm font-medium text-gray-500">Description</label>
                  <p className="mt-1 text-gray-700">{project.description}</p>
                </div>
              )}

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Calendar className="h-4 w-4" />
                    Start Date
                  </label>
                  <p className="mt-1 text-gray-900">{formatDate(project.start_date)}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Calendar className="h-4 w-4" />
                    End Date
                  </label>
                  <p className="mt-1 text-gray-900">{formatDate(project.end_date)}</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500 flex items-center gap-2">
                    <Clock className="h-4 w-4" />
                    Duration
                  </label>
                  <p className="mt-1 text-gray-900">{project.duration_days} days</p>
                </div>
                <div>
                  <label className="text-sm font-medium text-gray-500">Status</label>
                  <div className="mt-1">
                    <Badge className={getProjectStatusColor(project.status)}>
                      {formatProjectStatus(project.status)}
                    </Badge>
                  </div>
                </div>
              </div>

              {isEmployee && project.status === 'active' && (
                <div className="pt-4 border-t border-gray-200">
                  <div className="p-4 bg-primary-50 rounded-lg border border-primary-200">
                    <p className="text-sm font-medium text-primary-900">
                      {daysRemaining} {daysRemaining === 1 ? 'day' : 'days'} remaining
                    </p>
                    <p className="text-xs text-primary-700 mt-1">
                      Project ends on {formatDate(project.end_date)}
                    </p>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Assigned Employees */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Users className="h-5 w-5" />
                Assigned Employees ({assignedEmployees.length})
              </CardTitle>
            </CardHeader>
            <CardContent>
              {assignedEmployees.length > 0 ? (
                <div className="space-y-3">
                  {assignedEmployees.map((employee) => (
                    <div key={employee.id} className="flex items-center gap-3">
                      <div className="h-10 w-10 rounded-full bg-primary-600 flex items-center justify-center text-white font-medium">
                        {employee.name.charAt(0).toUpperCase()}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 truncate">
                          {employee.name}
                        </p>
                        <p className="text-xs text-gray-500 truncate">
                          {employee.employeeId} • {employee.department || 'No department'}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-gray-500 text-center py-4">No employees assigned</p>
              )}

              {isManager && (
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full mt-4"
                  onClick={handleReassign}
                >
                  <Edit className="h-4 w-4 mr-2" />
                  Reassign Employees
                </Button>
              )}
            </CardContent>
          </Card>

          {/* Quick Actions (Manager Only) */}
          {isManager && (
            <Card>
              <CardHeader>
                <CardTitle>Quick Actions</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2">
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full justify-start"
                  onClick={() => setIsEditModalOpen(true)}
                >
                  <Edit className="h-4 w-4 mr-2" />
                  Edit Project
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="w-full justify-start"
                  onClick={handleReassign}
                >
                  <Users className="h-4 w-4 mr-2" />
                  Reassign Employees
                </Button>
                {project.status !== 'completed' && (
                  <Button
                    variant="primary"
                    size="sm"
                    className="w-full justify-start"
                    onClick={handleMarkCompleted}
                  >
                    <CheckCircle className="h-4 w-4 mr-2" />
                    Mark as Completed
                  </Button>
                )}
              </CardContent>
            </Card>
          )}
        </div>
      </div>

      {/* Edit Modal */}
      {isManager && project && (
        <CreateProjectModal
          isOpen={isEditModalOpen}
          onClose={() => setIsEditModalOpen(false)}
          onSubmit={handleUpdateProject}
          employees={mockEmployees}
          currentUserId={user.id}
          project={project}
        />
      )}
    </div>
  )
}

