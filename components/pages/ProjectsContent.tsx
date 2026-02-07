'use client'

import React, { useState } from 'react'
import { useRouter } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/Table'
import { CreateProjectModal } from './CreateProjectModal'
import { Project, User } from '@/types'
import { formatDate, getProjectStatusColor, formatProjectStatus, calculateDaysRemaining } from '@/lib/utils'
import { Plus, FolderKanban, Users, Calendar, Clock } from 'lucide-react'
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

const mockProjects: Project[] = [
  {
    project_id: 'proj001',
    project_name: 'Website Redesign',
    start_date: new Date().toISOString(),
    end_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString(),
    duration_days: 30,
    assigned_employees: ['1', '2'],
    status: 'active',
    created_by: 'manager001',
    description: 'Complete redesign of company website',
  },
  {
    project_id: 'proj002',
    project_name: 'Mobile App Development',
    start_date: new Date(Date.now() + 10 * 24 * 60 * 60 * 1000).toISOString(),
    end_date: new Date(Date.now() + 60 * 24 * 60 * 60 * 1000).toISOString(),
    duration_days: 50,
    assigned_employees: ['1', '2', '3'],
    status: 'upcoming',
    created_by: 'manager001',
    description: 'Development of new mobile application',
  },
  {
    project_id: 'proj003',
    project_name: 'Q4 Marketing Campaign',
    start_date: new Date(Date.now() - 20 * 24 * 60 * 60 * 1000).toISOString(),
    end_date: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString(),
    duration_days: 15,
    assigned_employees: ['3', '4'],
    status: 'completed',
    created_by: 'manager001',
    description: 'Q4 marketing campaign execution',
  },
]

export default function ProjectsContent() {
  const { user } = useAuth()
  const router = useRouter()
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false)
  const [projects, setProjects] = useState<Project[]>(mockProjects)

  if (!user) return null

  const isManager = user.role === 'admin' || user.role === 'manager'
  const isEmployee = user.role === 'employee_internal' || user.role === 'employee_remote'

  // Filter projects for employees - only show assigned projects
  const displayedProjects = isEmployee
    ? projects.filter(project => project.assigned_employees.includes(user.id))
    : projects

  const handleCreateProject = (projectData: Omit<Project, 'project_id' | 'created_by'>) => {
    const newProject: Project = {
      ...projectData,
      project_id: `proj${Date.now()}`,
      created_by: user.id,
    }
    setProjects([newProject, ...projects])
  }

  const handleProjectClick = (projectId: string) => {
    router.push(`/projects/${projectId}`)
  }

  const getEmployeeNames = (employeeIds: string[]): string[] => {
    return employeeIds
      .map(id => mockEmployees.find(emp => emp.id === id))
      .filter(Boolean)
      .map(emp => emp!.name)
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            {isEmployee ? 'My Projects' : 'Projects'}
          </h1>
          <p className="mt-1 text-sm text-gray-600">
            {isEmployee
              ? 'View your assigned projects and timelines'
              : 'Manage and track all projects'}
          </p>
        </div>
        {isManager && (
          <Button onClick={() => setIsCreateModalOpen(true)}>
            <Plus className="h-4 w-4 mr-2" />
            Create Project
          </Button>
        )}
      </div>

      {displayedProjects.length === 0 ? (
        <EmptyState
          icon={<FolderKanban className="h-12 w-12 text-gray-400 mb-4" />}
          title={isEmployee ? 'No projects assigned yet' : 'No projects created'}
          description={
            isEmployee
              ? 'You haven\'t been assigned to any projects yet.'
              : 'Get started by creating your first project.'
          }
          action={
            isManager ? (
              <Button onClick={() => setIsCreateModalOpen(true)}>
                <Plus className="h-4 w-4 mr-2" />
                Create Project
              </Button>
            ) : undefined
          }
        />
      ) : (
        <Card>
          <CardHeader>
            <CardTitle>
              {isEmployee ? 'My Projects' : 'All Projects'} ({displayedProjects.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Project Name</TableHead>
                    <TableHead>Start Date</TableHead>
                    <TableHead>End Date</TableHead>
                    <TableHead>Duration</TableHead>
                    {isManager && <TableHead>Employees</TableHead>}
                    <TableHead>Status</TableHead>
                    {isEmployee && <TableHead>Days Remaining</TableHead>}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {displayedProjects.map((project) => {
                    const employeeNames = getEmployeeNames(project.assigned_employees)
                    const daysRemaining = calculateDaysRemaining(project.end_date)

                    return (
                      <TableRow
                        key={project.project_id}
                        className="cursor-pointer hover:bg-gray-50"
                        onClick={() => handleProjectClick(project.project_id)}
                      >
                        <TableCell className="font-medium text-gray-900">
                          {project.project_name}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-gray-400" />
                            <span>{formatDate(project.start_date)}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <Calendar className="h-4 w-4 text-gray-400" />
                            <span>{formatDate(project.end_date)}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-2">
                            <Clock className="h-4 w-4 text-gray-400" />
                            <span>{project.duration_days} days</span>
                          </div>
                        </TableCell>
                        {isManager && (
                          <TableCell>
                            <div className="flex items-center gap-2">
                              <Users className="h-4 w-4 text-gray-400" />
                              <span className="text-sm text-gray-600">
                                {employeeNames.length > 0
                                  ? `${employeeNames.length} ${employeeNames.length === 1 ? 'employee' : 'employees'}`
                                  : 'No employees'}
                              </span>
                            </div>
                          </TableCell>
                        )}
                        <TableCell>
                          <Badge className={getProjectStatusColor(project.status)}>
                            {formatProjectStatus(project.status)}
                          </Badge>
                        </TableCell>
                        {isEmployee && (
                          <TableCell>
                            {project.status === 'active' ? (
                              <span className="text-sm font-medium text-gray-900">
                                {daysRemaining} {daysRemaining === 1 ? 'day' : 'days'}
                              </span>
                            ) : project.status === 'upcoming' ? (
                              <span className="text-sm text-gray-500">Starts soon</span>
                            ) : (
                              <span className="text-sm text-gray-500">Completed</span>
                            )}
                          </TableCell>
                        )}
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Mobile Card View for Employees */}
      {isEmployee && displayedProjects.length > 0 && (
        <div className="lg:hidden space-y-4">
          {displayedProjects.map((project) => {
            const daysRemaining = calculateDaysRemaining(project.end_date)
            return (
              <Card key={project.project_id} className="cursor-pointer" onClick={() => handleProjectClick(project.project_id)}>
                <CardContent className="p-4">
                  <div className="flex items-start justify-between mb-3">
                    <h3 className="text-lg font-semibold text-gray-900">{project.project_name}</h3>
                    <Badge className={getProjectStatusColor(project.status)}>
                      {formatProjectStatus(project.status)}
                    </Badge>
                  </div>
                  <div className="space-y-2 text-sm text-gray-600">
                    <div className="flex items-center gap-2">
                      <Calendar className="h-4 w-4" />
                      <span>
                        {formatDate(project.start_date)} - {formatDate(project.end_date)}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Clock className="h-4 w-4" />
                      <span>{project.duration_days} days</span>
                    </div>
                    {project.status === 'active' && (
                      <div className="pt-2 border-t border-gray-200">
                        <span className="font-medium text-gray-900">
                          {daysRemaining} {daysRemaining === 1 ? 'day' : 'days'} remaining
                        </span>
                      </div>
                    )}
                  </div>
                </CardContent>
              </Card>
            )
          })}
        </div>
      )}

      <CreateProjectModal
        isOpen={isCreateModalOpen}
        onClose={() => setIsCreateModalOpen(false)}
        onSubmit={handleCreateProject}
        employees={mockEmployees}
        currentUserId={user.id}
      />
    </div>
  )
}

