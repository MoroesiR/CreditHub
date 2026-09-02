import { api } from '@/lib/api'
import type { DashboardTile } from '@/types/dashboard'

export async function fetchDashboardSummary(): Promise<DashboardTile[]> {
  const { data } = await api.get<{ data: DashboardTile[] }>('/dashboard/summary')

  return data.data
}
