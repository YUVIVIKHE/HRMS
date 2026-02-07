'use client'

import { useEffect } from 'react'
import { useRouter, useParams } from 'next/navigation'
import { useAuth } from '@/contexts/AuthContext'
import { AppLayout } from '@/components/layout/AppLayout'
import ProjectDetailContent from '@/components/pages/ProjectDetailContent'

export default function ProjectDetailPage() {
  const { isAuthenticated } = useAuth()
  const router = useRouter()
  const params = useParams()
  const projectId = params?.project_id as string

  useEffect(() => {
    if (!isAuthenticated) {
      router.push('/')
    }
  }, [isAuthenticated, router])

  if (!isAuthenticated || !projectId) {
    return null
  }

  return (
    <AppLayout>
      <ProjectDetailContent projectId={projectId} />
    </AppLayout>
  )
}

