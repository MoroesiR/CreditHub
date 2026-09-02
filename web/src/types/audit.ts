/** Mirrors the API's AuditEventResource. */
export interface AuditEvent {
  id: number
  action: string
  summary: string
  actor_name: string
  ip_address: string | null
  created_at: string | null
}
