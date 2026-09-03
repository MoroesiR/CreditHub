import { api } from '@/lib/api'
import type { PortfolioReport } from '@/types/reports'

export async function fetchPortfolioReport(): Promise<PortfolioReport> {
  const { data } = await api.get<{ data: PortfolioReport }>('/reports/portfolio')

  return data.data
}
