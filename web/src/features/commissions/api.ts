import { api } from '@/lib/api'
import type { Paginated } from '@/types/clients'
import type { Commission } from '@/types/disbursements'

export async function fetchCommissions(params: {
  status?: string
  page?: number
}): Promise<Paginated<Commission>> {
  const { data } = await api.get<Paginated<Commission>>('/commissions', {
    params: { ...params, status: params.status || undefined },
  })

  return data
}

export async function payCommission(id: number): Promise<Commission> {
  const { data } = await api.post<{ data: Commission }>(`/commissions/${id}/payment`)

  return data.data
}
