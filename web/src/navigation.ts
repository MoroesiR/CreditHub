/**
 * The module map.
 *
 * One list drives three things: the links in the header, the routes the router
 * registers, and the permission each route is guarded by. A module with
 * `children` renders as a dropdown; each child carries its own permission, so
 * a role that may search clients but not register them sees only the one item.
 */
export interface ModuleChild {
  path: string
  label: string
  permission: string
  summary: string
}

export interface Module {
  path: string
  label: string
  permission: string
  summary: string
  children?: ModuleChild[]
}

export const MODULES: Module[] = [
  {
    path: '/clients',
    label: 'Clients',
    permission: 'clients.view',
    summary:
      'Register borrowers, capture their employment and banking details, and link them to the recruiter who introduced them.',
    children: [
      {
        path: '/clients/register',
        label: 'Register client',
        permission: 'clients.create',
        summary: 'Capture a new borrower, their affordability, and the recruiter who introduced them.',
      },
      {
        path: '/clients',
        label: 'Search clients',
        permission: 'clients.view',
        summary: 'Find a client by name, client number, ID number, phone or email.',
      },
    ],
  },
  {
    path: '/recruiters',
    label: 'Recruiters',
    permission: 'recruiters.view',
    summary:
      'Register recruiters and track the clients they introduce and the commission each introduction has earned.',
    children: [
      {
        path: '/recruiters/register',
        label: 'Register recruiter',
        permission: 'recruiters.create',
        summary: 'Capture a recruiter and the account their commission is paid into.',
      },
      {
        path: '/recruiters',
        label: 'Search recruiters',
        permission: 'recruiters.view',
        summary: 'Find a recruiter by name, recruiter number, ID number or phone.',
      },
    ],
  },
  {
    path: '/applications',
    label: 'Applications',
    permission: 'applications.view',
    summary:
      'Capture loan applications against an affordability assessment, submit them for a decision, and record the outcome.',
    children: [
      {
        path: '/applications/create',
        label: 'Create loan application',
        permission: 'applications.create',
        summary: 'Price a loan against the affordability on file and send it for a decision.',
      },
      {
        path: '/applications',
        label: 'Track loan applications',
        permission: 'applications.view',
        summary: 'Every application and where it has reached.',
      },
    ],
  },
  {
    path: '/agreements',
    label: 'Agreements',
    permission: 'agreements.view',
    summary:
      'Generate the credit agreement for an approved application and capture the client signature that releases it for payment.',
  },
  {
    path: '/disbursements',
    label: 'Disbursements',
    permission: 'disbursements.view',
    summary:
      'The payout queue. Verify that a loan is approved and signed, then release the funds to the client.',
  },
  {
    path: '/commissions',
    label: 'Commissions',
    permission: 'commissions.view',
    summary:
      'Commission earned per recruiter, priced by the scheme in force when the loan was disbursed, and released for payment.',
  },
  {
    path: '/reports',
    label: 'Reports',
    permission: 'reports.view',
    summary: 'Book performance, decision turnaround, and commission exposure.',
  },
  {
    path: '/users',
    label: 'Users',
    permission: 'users.view',
    summary: 'Staff accounts and the roles assigned to them.',
  },
]
