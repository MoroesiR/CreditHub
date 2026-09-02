export interface DashboardTile {
  key: string
  label: string
  value: number
  caption: string
  format: 'number' | 'money'
  href: string
}
