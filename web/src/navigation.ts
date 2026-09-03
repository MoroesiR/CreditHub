/**
 * The module map.
 *
 * One list drives three things: the links in the header, the routes the router
 * registers, and the permission each route is guarded by. Each entry is a
 * single link: clicking it opens the module rather than asking the user to
 * pick from a menu first.
 */
export interface Module {
  path: string
  label: string
  permission: string
  summary: string
  /**
   * Roles that work in this module day to day, and so get it in the header.
   * Everyone else keeps the permission and reaches the same screens by
   * following a link, they just do not carry a menu item they never use.
   * Omit to show the module to anyone holding the permission.
   */
  primaryFor?: string[]
}

export const MODULES: Module[] = [
  {
    path: '/clients',
    primaryFor: ['admin', 'loan-officer'],
    label: 'Clients',
    permission: 'clients.view',
    summary:
      'Register borrowers, capture their employment and banking details, and link them to the recruiter who introduced them.',
  },
  {
    path: '/recruiters',
    primaryFor: ['admin', 'loan-officer'],
    label: 'Recruiters',
    permission: 'recruiters.view',
    summary:
      'Register recruiters and track the clients they introduce and the commission each introduction has earned.',
  },
  {
    path: '/applications',
    primaryFor: ['admin', 'loan-officer', 'credit-manager', 'auditor'],
    label: 'Applications',
    permission: 'applications.view',
    summary:
      'Capture loan applications against an affordability assessment, submit them for a decision, and record the outcome.',
  },
  {
    path: '/disbursements',
    primaryFor: ['admin', 'disbursement-officer', 'auditor'],
    label: 'Disbursements',
    permission: 'disbursements.view',
    summary:
      'The payout queue. Verify that a loan is approved and signed, then release the funds to the client.',
  },
  {
    path: '/repayments',
    primaryFor: ['admin', 'collections-officer', 'credit-manager', 'auditor'],
    label: 'Repayments',
    permission: 'repayments.view',
    summary:
      'Loans with money out against them, what has been received back, and which accounts are behind.',
  },
  {
    path: '/commissions',
    primaryFor: ['admin', 'disbursement-officer', 'auditor'],
    label: 'Commissions',
    permission: 'commissions.view',
    summary:
      'Commission earned per recruiter, priced by the scheme in force when the loan was disbursed, and released for payment.',
  },
  {
    path: '/reports',
    primaryFor: ['admin', 'credit-manager', 'auditor'],
    label: 'Reports',
    permission: 'reports.view',
    summary: 'Book performance, decision turnaround, and commission exposure.',
  },
  {
    path: '/change-requests',
    primaryFor: ['admin', 'loan-officer'],
    label: 'Change requests',
    permission: 'change-requests.view',
    summary:
      'Corrections to a client or recruiter: asked for by an officer, decided by an administrator.',
  },
  {
    path: '/users',
    primaryFor: ['admin'],
    label: 'Users',
    permission: 'users.view',
    summary: 'Staff accounts and the roles assigned to them.',
  },
]
