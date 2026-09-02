# CreditHub

A loan management system for a small lender: register the client, work out what
they can afford, decide the application, sign the agreement, and pay the money
out - with recruiter commission calculated and paid alongside it.

The point of the design is the **separation between the department that
originates a loan and the department that pays it**. Approving a loan and
releasing the funds are different permissions held by different roles, and no
role holds both.

---

## Stack

| | |
|---|---|
| API | Laravel 13, PHP 8.3+, JSON only |
| Auth | Sanctum bearer tokens, token abilities mirror the holder's permissions |
| Database | MySQL 8 |
| Front end | React 19, TypeScript, Vite, TanStack Query, React Hook Form + Zod, Tailwind 4 |
| Tests | Pest (API), ESLint + `tsc` (web) |

The API and the SPA are separate applications in one repository - `api/` and
`web/`. They share nothing but the HTTP contract.

---

## The pipeline

```
Recruiter (existing, or registered on the spot)
        │
        ▼
Client registration ──► Affordability assessment
        │                       │
        │                       ▼
        └───────────► Loan application ──► Credit decision
                                                │
                          ┌─────────────────────┴──────────┐
                       Declined                        Approved
                                                           │
                                                           ▼
                                             Agreement generated → signed
                                                           │
                                                           ▼
                                             ┌── DISBURSEMENT ───────┐
                                             │  verify               │
                                             │  pay the loan         │
                                             │  pay the commission   │
                                             └───────────────────────┘
```

Origination captures and recommends. Credit decides. Disbursement verifies and
pays. Each hand-off is a permission boundary, not a convention.

---

## Roles

| Role | Holds | Explicitly does not hold |
|---|---|---|
| System Administrator | Everything, including staff accounts | - |
| Loan Officer | Recruiters, clients, affordability, capture and submit applications, agreements | Decide, pay |
| Credit Manager | Approve or decline applications, reports | Release funds, pay commission |
| Disbursement Officer | Verify and release loan and commission payments | Approve applications |
| Auditor | Read everything, reports | Any write |

Roles and permissions are database rows, not enums: granting a permission is a
data change, not a deployment. The catalogue that seeds them lives in
[`api/app/Support/Permissions.php`](api/app/Support/Permissions.php) and
[`api/app/Support/Roles.php`](api/app/Support/Roles.php), and a test asserts the
two never drift apart.

---

## Commission

Recruiters earn on the loans they bring in. Commission is a tiered percentage
with a cap per tier, so the rate stays realistic as loan sizes grow:

| Loan amount | Rate | Cap |
|---|---|---|
| R0 – R2 000 | 10% | - |
| R2 001 – R10 000 | 7% | R900 |
| R10 001 – R50 000 | 5% | R2 500 |

A R1 000 loan earns R100; R5 000 earns R350; R30 000 earns R1 500. Schemes are
versioned and every commission record stores the version it was priced under,
so a payout from last year can still be explained this year.

*Commission calculation ships with the recruiter module - see Status below.*

---

## Running it locally

**Requirements:** PHP 8.3+, Composer, Node 20+, MySQL 8.

### API

```sh
cd api
composer install
cp .env.example .env
php artisan key:generate
```

Create the database, then:

```sh
php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

### Web

```sh
cd web
npm install
cp .env.example .env
npm run dev                  # http://localhost:5173
```

`VITE_API_URL` in `web/.env` points at the API; `FRONTEND_URL` in `api/.env` is
the origin CORS allows. Both default to the ports above.

### Demo sign-ins

Seeded outside production only. Password is `password` for all of them.

| Email | Role |
|---|---|
| `admin@credithub.test` | System Administrator |
| `officer@credithub.test` | Loan Officer |
| `credit@credithub.test` | Credit Manager |
| `payouts@credithub.test` | Disbursement Officer |
| `auditor@credithub.test` | Auditor |

---

## Checks

```sh
cd api && ./vendor/bin/pest        # tests
cd api && ./vendor/bin/pint        # formatting
cd web && npm run lint             # eslint, type-aware
cd web && npm run build            # tsc + production build
```

CI runs all of it on every push and pull request.

---

## API

Base URL `/api/v1`. Every route is either explicitly public or behind
`auth:sanctum`; anything needing more states its permission on the route line
in [`api/routes/api.php`](api/routes/api.php), which is the authorisation
contract for the whole application.

| Method | Path | Auth |
|---|---|---|
| `POST` | `/auth/login` | public, rate limited |
| `GET` | `/auth/me` | token |
| `POST` | `/auth/logout` | token |

---

## Status

Built in slices, each one working end to end before the next starts.

- [x] **Foundation** - API and SPA skeletons, MySQL, roles and permissions, token authentication, sign-in screen, CI
- [ ] Recruiters and clients, with commission schemes
- [ ] Affordability assessment
- [ ] Applications and the credit decision
- [ ] Agreements and signature capture
- [ ] Disbursement queue, loan and commission payment
