import { api } from '@/lib/api'
import type { AppNotification } from '@/types/notifications'

export async function fetchNotifications(): Promise<{
  data: AppNotification[]
  unread: number
}> {
  const { data } = await api.get<{ data: AppNotification[]; meta: { unread: number } }>(
    '/notifications',
  )

  return { data: data.data, unread: data.meta.unread }
}

export async function markNotificationRead(id: string): Promise<void> {
  await api.post(`/notifications/${id}/read`)
}

export async function markAllNotificationsRead(): Promise<void> {
  await api.post('/notifications/read-all')
}
