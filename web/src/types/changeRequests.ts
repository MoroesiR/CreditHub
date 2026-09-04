export type ChangeRequestStatus = 'pending' | 'approved' | 'rejected'

export interface ChangeRequestDocument {
  id: number
  type: 'id_copy' | 'bank_statement' | 'payslip'
  type_label: string
  original_name: string
  size_bytes: number
}

/**
 * What has to be produced for a given field, mirroring the server's rule.
 * The server refuses without it either way; this is so the officer is asked
 * for the right document while filling the form rather than on submission.
 */
export const EVIDENCE_FOR: Record<string, ChangeRequestDocument['type']> = {
  first_name: 'id_copy',
  last_name: 'id_copy',
  id_number: 'id_copy',
  bank_name: 'bank_statement',
  bank_account_number: 'bank_statement',
  employer_name: 'payslip',
  job_title: 'payslip',
  employment_status: 'payslip',
}

export const EVIDENCE_LABELS: Record<ChangeRequestDocument['type'], string> = {
  id_copy: 'ID copy',
  bank_statement: 'Bank statement',
  payslip: 'Payslip',
}

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
  documents?: ChangeRequestDocument[]
  requested_by?: string
  reviewed_by?: string | null
  reviewed_at: string | null
  review_note: string | null
  created_at: string | null
}
