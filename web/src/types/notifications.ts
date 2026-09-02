export interface AppNotification {
  id: string
  title: string
  body: string
  action_url: string
  approved?: boolean
  application_number?: string
  read_at: string | null
  created_at: string | null
}
