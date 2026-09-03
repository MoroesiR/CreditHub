import { Route, Routes } from 'react-router-dom'

import { AppLayout } from '@/components/AppLayout'
import { ProtectedRoute } from '@/components/ProtectedRoute'
import { RequirePermission } from '@/components/RequirePermission'
import { LoginPage } from '@/features/auth/LoginPage'
import { ClientSearchPage } from '@/features/clients/ClientSearchPage'
import { AgreementPage } from '@/features/agreements/AgreementPage'
import { ChangeRequestsPage } from '@/features/change-requests/ChangeRequestsPage'
import { CommissionsPage } from '@/features/commissions/CommissionsPage'
import { DisbursementQueuePage } from '@/features/disbursements/DisbursementQueuePage'
import { RepaymentsPage } from '@/features/repayments/RepaymentsPage'
import { ReportsPage } from '@/features/reports/ReportsPage'
import { UsersPage } from '@/features/users/UsersPage'
import { ApplicationDetailPage } from '@/features/applications/ApplicationDetailPage'
import { CreateApplicationPage } from '@/features/applications/CreateApplicationPage'
import { TrackApplicationsPage } from '@/features/applications/TrackApplicationsPage'
import { ClientProfilePage } from '@/features/clients/ClientProfilePage'
import { RegisterClientPage } from '@/features/clients/RegisterClientPage'
import { RecruiterDetailPage } from '@/features/recruiters/RecruiterDetailPage'
import { RecruiterSearchPage } from '@/features/recruiters/RecruiterSearchPage'
import { RegisterRecruiterPage } from '@/features/recruiters/RegisterRecruiterPage'
import { MODULES } from '@/navigation'
import { DashboardPage } from '@/pages/DashboardPage'
import { ModulePage } from '@/pages/ModulePage'
import { NotFoundPage } from '@/pages/NotFoundPage'

/** Modules whose screens are built; the rest fall through to a placeholder. */
const BUILT = new Set([
  '/clients',
  '/recruiters',
  '/applications',
  '/disbursements',
  '/commissions',
  '/reports',
  '/repayments',
  '/users',
  '/change-requests',
])

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<AppLayout />}>
          <Route index element={<DashboardPage />} />

          <Route
            path="/clients"
            element={
              <RequirePermission permission="clients.view">
                <ClientSearchPage />
              </RequirePermission>
            }
          />
          <Route
            path="/clients/:id"
            element={
              <RequirePermission permission="clients.view">
                <ClientProfilePage />
              </RequirePermission>
            }
          />
          <Route
            path="/clients/register"
            element={
              <RequirePermission permission="clients.create">
                <RegisterClientPage />
              </RequirePermission>
            }
          />

          <Route
            path="/recruiters"
            element={
              <RequirePermission permission="recruiters.view">
                <RecruiterSearchPage />
              </RequirePermission>
            }
          />
          <Route
            path="/recruiters/register"
            element={
              <RequirePermission permission="recruiters.create">
                <RegisterRecruiterPage />
              </RequirePermission>
            }
          />
          <Route
            path="/recruiters/:id"
            element={
              <RequirePermission permission="recruiters.view">
                <RecruiterDetailPage />
              </RequirePermission>
            }
          />

          <Route
            path="/applications"
            element={
              <RequirePermission permission="applications.view">
                <TrackApplicationsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/applications/create"
            element={
              <RequirePermission permission="applications.create">
                <CreateApplicationPage />
              </RequirePermission>
            }
          />
          <Route
            path="/applications/:id/agreement"
            element={
              <RequirePermission permission="agreements.view">
                <AgreementPage />
              </RequirePermission>
            }
          />
          <Route
            path="/applications/:id"
            element={
              <RequirePermission permission="applications.view">
                <ApplicationDetailPage />
              </RequirePermission>
            }
          />

          <Route
            path="/disbursements"
            element={
              <RequirePermission permission="disbursements.view">
                <DisbursementQueuePage />
              </RequirePermission>
            }
          />
          <Route
            path="/change-requests"
            element={
              <RequirePermission permission="change-requests.view">
                <ChangeRequestsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/commissions"
            element={
              <RequirePermission permission="commissions.view">
                <CommissionsPage />
              </RequirePermission>
            }
          />

          <Route
            path="/repayments"
            element={
              <RequirePermission permission="repayments.view">
                <RepaymentsPage />
              </RequirePermission>
            }
          />
          <Route
            path="/users"
            element={
              <RequirePermission permission="users.view">
                <UsersPage />
              </RequirePermission>
            }
          />
          <Route
            path="/reports"
            element={
              <RequirePermission permission="reports.view">
                <ReportsPage />
              </RequirePermission>
            }
          />

          {MODULES.filter((module) => !BUILT.has(module.path)).map((module) => (
            <Route
              key={module.path}
              path={module.path}
              element={
                <RequirePermission permission={module.permission}>
                  <ModulePage module={module} />
                </RequirePermission>
              }
            />
          ))}
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
