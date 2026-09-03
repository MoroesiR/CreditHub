const money = new Intl.NumberFormat('en-ZA', {
  style: 'currency',
  currency: 'ZAR',
  maximumFractionDigits: 0,
})

const number = new Intl.NumberFormat('en-ZA')

export function formatMoney(value: number): string {
  return money.format(value)
}

export function formatNumber(value: number): string {
  return number.format(value)
}

export function formatDate(value: string | null): string {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium' }).format(new Date(value))
}

export function formatDateTime(value: string | null): string {
  if (!value) {
    return '-'
  }

  return new Intl.DateTimeFormat('en-ZA', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}
