export type ChangeRequestStatus = 'pending' | 'approved' | 'rejected'

export interface ChangeRequest {
  id: number
  status: ChangeRequestStatus
  status_label: string
  reason: string
  changes: Record<string, string | null>
  replaced_values: Record<string, string | null> | null
  subject_kind: 'client' | 'recruiter' | 'record'
  subject_id: number
  subject_label?: string
  requested_by?: string
  reviewed_by?: string | null
  reviewed_at: string | null
  review_note: string | null
  created_at: string | null
}
