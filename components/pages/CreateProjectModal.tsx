'use client'

import React, { useState, useEffect } from 'react'
import { Modal } from '@/components/ui/Modal'
import { Input } from '@/components/ui/Input'
import { Button } from '@/components/ui/Button'
import { Project, User } from '@/types'
import { formatDate, calculateDurationDays } from '@/lib/utils'

interface CreateProjectModalProps {
  isOpen: boolean
  onClose: () => void
  onSubmit: (project: Omit<Project, 'project_id' | 'created_by'>) => void
  employees: User[]
  currentUserId: string
  project?: Project // Optional project for edit mode
}

export function CreateProjectModal({ isOpen, onClose, onSubmit, employees, currentUserId, project }: CreateProjectModalProps) {
  const isEditMode = !!project
  
  const [projectName, setProjectName] = useState('')
  const [startDate, setStartDate] = useState('')
  const [timePeriod, setTimePeriod] = useState('')
  const [selectedEmployees, setSelectedEmployees] = useState<string[]>([])
  const [status, setStatus] = useState<'upcoming' | 'active' | 'completed'>('upcoming')
  const [description, setDescription] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [endDate, setEndDate] = useState('')

  // Initialize form when modal opens or project changes
  useEffect(() => {
    if (isOpen) {
      if (project) {
        setProjectName(project.project_name)
        setStartDate(new Date(project.start_date).toISOString().split('T')[0])
        setTimePeriod(String(project.duration_days))
        setSelectedEmployees(project.assigned_employees)
        setStatus(project.status)
        setDescription(project.description || '')
        setEndDate(new Date(project.end_date).toISOString().split('T')[0])
      } else {
        setProjectName('')
        setStartDate('')
        setTimePeriod('')
        setSelectedEmployees([])
        setStatus('upcoming')
        setDescription('')
        setEndDate('')
      }
      setErrors({})
    }
  }, [isOpen, project])

  // Calculate end date from start date and time period
  useEffect(() => {
    if (startDate && timePeriod) {
      const days = parseInt(timePeriod, 10)
      if (!isNaN(days) && days > 0) {
        const start = new Date(startDate)
        const end = new Date(start)
        end.setDate(end.getDate() + days - 1) // Subtract 1 to include start date
        setEndDate(end.toISOString().split('T')[0])
      } else {
        setEndDate('')
      }
    } else if (!isEditMode) {
      setEndDate('')
    }
  }, [startDate, timePeriod, isEditMode])

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    const newErrors: Record<string, string> = {}

    if (!projectName.trim()) {
      newErrors.projectName = 'Project name is required'
    }
    if (!startDate) {
      newErrors.startDate = 'Start date is required'
    }
    if (!timePeriod || parseInt(timePeriod, 10) <= 0) {
      newErrors.timePeriod = 'Time period must be greater than 0'
    }
    if (selectedEmployees.length === 0) {
      newErrors.employees = 'At least one employee must be assigned'
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors)
      return
    }

    const duration = calculateDurationDays(startDate, endDate || startDate)

    onSubmit({
      project_name: projectName.trim(),
      start_date: startDate,
      end_date: endDate || startDate,
      duration_days: duration,
      assigned_employees: selectedEmployees,
      status,
      description: description.trim() || undefined,
    })

    // Reset form
    setProjectName('')
    setStartDate('')
    setTimePeriod('')
    setSelectedEmployees([])
    setStatus('upcoming')
    setDescription('')
    setErrors({})
    onClose()
  }

  const handleEmployeeToggle = (employeeId: string) => {
    setSelectedEmployees(prev =>
      prev.includes(employeeId)
        ? prev.filter(id => id !== employeeId)
        : [...prev, employeeId]
    )
  }

  const handleClose = () => {
    setProjectName('')
    setStartDate('')
    setTimePeriod('')
    setSelectedEmployees([])
    setStatus('upcoming')
    setDescription('')
    setErrors({})
    onClose()
  }

  return (
    <Modal isOpen={isOpen} onClose={handleClose} title={isEditMode ? "Edit Project" : "Create New Project"} size="lg">
      <form onSubmit={handleSubmit} className="space-y-6">
        <Input
          label="Project Name"
          value={projectName}
          onChange={(e) => setProjectName(e.target.value)}
          error={errors.projectName}
          placeholder="Enter project name"
          required
        />

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input
            label="Start Date"
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            error={errors.startDate}
            required
          />

          <Input
            label="Time Period (Days)"
            type="number"
            min="1"
            value={timePeriod}
            onChange={(e) => setTimePeriod(e.target.value)}
            error={errors.timePeriod}
            placeholder="e.g., 30"
            required
          />
        </div>

        {endDate && (
          <div className="p-3 bg-primary-50 rounded-lg border border-primary-200">
            <p className="text-sm text-gray-600">
              <span className="font-medium">End Date:</span>{' '}
              {formatDate(endDate, 'MMM dd, yyyy')}
            </p>
          </div>
        )}

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Assign Employees <span className="text-danger-500">*</span>
          </label>
          {errors.employees && (
            <p className="text-sm text-danger-600 mb-2">{errors.employees}</p>
          )}
          <div className="border border-gray-300 rounded-lg p-4 max-h-48 overflow-y-auto">
            {employees.length === 0 ? (
              <p className="text-sm text-gray-500">No employees available</p>
            ) : (
              <div className="space-y-2">
                {employees.map((employee) => (
                  <label
                    key={employee.id}
                    className="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded"
                  >
                    <input
                      type="checkbox"
                      checked={selectedEmployees.includes(employee.id)}
                      onChange={() => handleEmployeeToggle(employee.id)}
                      className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span className="text-sm text-gray-700">
                      {employee.name} ({employee.employeeId})
                    </span>
                  </label>
                ))}
              </div>
            )}
          </div>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Status
          </label>
          <select
            value={status}
            onChange={(e) => setStatus(e.target.value as 'upcoming' | 'active' | 'completed')}
            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
            <option value="upcoming">Upcoming</option>
            <option value="active">Active</option>
            <option value="completed">Completed</option>
          </select>
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">
            Description (Optional)
          </label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            rows={3}
            className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent resize-none"
            placeholder="Enter project description..."
          />
        </div>

        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Button type="button" variant="secondary" onClick={handleClose}>
            Cancel
          </Button>
          <Button type="submit" variant="primary">
            {isEditMode ? 'Update Project' : 'Create Project'}
          </Button>
        </div>
      </form>
    </Modal>
  )
}

