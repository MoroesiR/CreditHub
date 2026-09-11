# CreditHub

A loan management system for a small lender: register the client, work out what
they can afford, decide the application, sign the agreement, pay the money out,
and collect it back, with recruiter commission priced and paid alongside it.

The point of the design is **separation of duties**. Originating a loan,
approving it, releasing the funds and receipting repayments are four different
permissions held by four different roles, and no role holds more than one of
them. Every change is written to an append-only audit trail as it happens.

---

## Stack

| | |
|---|---|
| API | Laravel 13, PHP 8.3+, JSON only |
| Auth | Sanctum bearer tokens |
| Database | MySQL 8 or MariaDB 10.4+ |
| Front end | React 19, TypeScript, Vite, TanStack Query, React Hook Form + Zod, Tailwind 4 |
| Checks | Pest (API), ESLint and `tsc` (web) |

The API and the SPA are separate applications in one repository, `api/` and
`web/`. They share nothing but the HTTP contract.

---

## The pipeline

```
Recruiter (existing, or registered on the spot)
        │
        ▼
Client registration ──► Affordability assessment
        │
        ▼
Loan application ──► Credit decision
  + ID copy               │
  + bank statement   ┌────┴─────┐
  + payslip       Declined   Approved
                                │
                                ▼
                  Agreement generated → signed
                  (drawn signature + photograph)
                                │
                                ▼
                  ┌─── DISBURSEMENT ────┐
                  │  verify             │
                  │  pay the loan       │
                  │  pay the commission │
                  └─────────┬───────────┘
                            ▼
                  ┌─── COLLECTIONS ─────┐
                  │  receipt repayments │
                  │  track arrears      │
                  └─────────────────────┘
```

Origination captures. Credit decides. Disbursement verifies and pays.
Collections receipts what comes back. Each hand-off is a permission boundary,
not a convention, and whoever submitted a file may not decide it even if they
hold both permissions.

Corrections to a record go through an administrator rather than being edited in
place, and have to carry the document that supports them.

---

## Roles

| Role | Holds | Explicitly does not hold |
|---|---|---|
| System Administrator | Oversight of everything, staff accounts, role assignment, corrections | Verify or release a payout, pay commission |
| Loan Officer | Recruiters, clients, affordability, capture and submit applications, agreements | Decide, pay, receipt |
| Credit Manager | Approve or decline applications, repayment sight, reports | Release funds, pay commission |
| Disbursement Officer | Verify and release loan and commission payments | Approve applications, receipt repayments |
| Collections Officer | Receipt repayments, reverse a receipt | Originate, approve or pay anything out |
| Auditor | Read across the pipeline, reports | Any write |

An administrator can grant any permission but cannot move money. Holding both
would leave nothing for anyone else to check.

Roles and permissions are database rows, not enums: granting a permission is a
data change, not a deployment. The catalogue that seeds them lives in
[`api/app/Support/Permissions.php`](api/app/Support/Permissions.php) and
[`api/app/Support/Roles.php`](api/app/Support/Roles.php), and
[`AccessControlTest`](api/tests/Unit/AccessControlTest.php) asserts the
separations above rather than merely that the seed ran.

---

## Money

**Loans** are priced on reducing balance, the way an amortising agreement
actually works, so the quote survives comparison with another lender's. The
rate lives in
[`InstalmentCalculator`](api/app/Services/Loans/InstalmentCalculator.php) and is
not accepted from the request: an officer who can set the rate per file can
price one client differently from another.

An application is refused if the instalment exceeds the disposable income on
the client's assessment. The assessment, the recruiter and the quote are copied
onto the application at capture, so none of them drift afterwards.

A client borrows **one loan at a time**. While a loan is being repaid, or an
earlier application is anywhere between capture and payout, a new one is
refused: two files assessed separately against the same income can each look
affordable and be unaffordable together. The block lifts the moment the balance
reaches zero. The capture screen says so as soon as the client is picked, and
the service refuses for the same reason if anything gets past the screen.

**Fees** are the two the National Credit Act allows besides interest, capped in
[`FeeSchedule`](api/app/Support/FeeSchedule.php):

| Fee | Charged | Before VAT |
|---|---|---|
| Initiation | once, financed with the loan | R165 plus 10% of the advance above R1 000, capped at R1 050 |
| Service | monthly, with the instalment | R60 |

Interest is 28.75% a year on the amount financed. The quote breaks the
instalment into each of these, so an officer can explain it line by line. The
caps are set by regulation and amended from time to time, which is why they
live in one file.

**Commission** is a tiered percentage, capped above the entry band, so the rate
stays realistic as loans grow:

| Loan amount | Rate | Cap |
|---|---|---|
| R0 to R2 000 | 10% | none |
| R2 000.01 to R10 000 | 7% | R900 |
| R10 000.01 to R50 000 | 5% | R2 500 |
| R50 000.01 and above | 3% | R4 000 |

R1 000 earns R100, R5 000 earns R350, R20 000 earns R1 000. It is priced when
the loan is **paid out**, not when it is approved, because an approved loan
that is never disbursed earns nobody anything. Schemes are versioned and every
commission row stores the version it was priced under, so a payout from last
year can still be explained this year.

**Repayments** are measured against a schedule written at payout, one row per
instalment, split into interest, service fee and capital. The quote and the
schedule come from the same amortisation, so a loan paid exactly as quoted
ends at exactly R0.00.

Each receipt is applied in the order section 126(3) of the Act sets: interest,
then fees and charges, then capital, and only against instalments that have
actually fallen due. A short payment still clears what the month has cost and
the loan runs longer; an early one reduces capital rather than prepaying
interest that has not accrued. Arrears are whatever has fallen due and not been
met.

Receipts are recorded, never edited. One captured in error is corrected by a
reversal that sits beside the original, so the account history stays a record
of what happened.

---

## Documents

Supporting documents are required at submission and are held on the private
disk under generated names, never under the name the browser supplied and never
in `public/`. They are streamed through an authenticated route, so a payslip is
not reachable by guessing a URL, and are read in a modal rather than downloaded.

A change request has to carry the right proof: a bank statement for banking
details, a payslip for employment, an ID copy for a name or identity number.
Approving the request replaces the matching document on the client's file.

---

## Running it locally

**Requirements:** PHP 8.3+, Composer, Node 20+, MySQL 8 or MariaDB 10.4+.

### API

```sh
cd api
composer install
cp .env.example .env
php artisan key:generate
```

Create the database named in `.env`, then:

```sh
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
```

Outside production, `--seed` also builds a demo book through the same services
the application uses: 8 clients, 4 recruiters, and 7 applications standing at
every stage from awaiting a decision to settled, one of them paying on time and
one three instalments behind. It does nothing if the database already has
clients.

### Web

```sh
cd web
npm install
cp .env.example .env
npm run dev                  # http://localhost:5173
```

In development the SPA calls `/api/v1` on its own origin and Vite proxies it to
the API, so there is no CORS round trip and no dependence on whether `localhost`
resolves to IPv4 or IPv6. `VITE_PROXY_TARGET` sets where it forwards;
`VITE_API_URL` takes an absolute URL for a production build.

### Demo sign-ins

Seeded outside production only. The password is `password` for all of them.

| Email | Role |
|---|---|
| `admin@credithub.test` | System Administrator |
| `officer@credithub.test` | Loan Officer |
| `credit@credithub.test` | Credit Manager |
| `payouts@credithub.test` | Disbursement Officer |
| `collections@credithub.test` | Collections Officer |
| `auditor@credithub.test` | Auditor |

To walk the pipeline end to end: capture an application as the officer, approve
it as the credit manager, sign it back as the officer, then verify and pay it
as the payouts desk.

---

## Checks

```sh
cd api && ./vendor/bin/pest        # tests
cd api && ./vendor/bin/pint        # formatting
cd web && npm run lint             # eslint, type-aware
cd web && npm run build            # tsc and production build
```

The feature tests take loans through the real services end to end: a second
loan refused while the first is running, borrowing reopening on settlement, the
order a payment is applied in, a reversal netting to zero, commission earned at
payout and not at approval, and a payout refused until it is verified.

CI runs all of it on every push and pull request.

---

## API

Base URL `/api/v1`, 53 routes. Every one is either explicitly public or behind
`auth:sanctum`, and anything needing more states its permission on the route
line in [`api/routes/api.php`](api/routes/api.php), which is the authorisation
contract for the whole application.

| Area | Routes |
|---|---|
| Auth | `POST /auth/login` (public, rate limited), `GET /auth/me`, `POST /auth/logout` |
| Clients | search, register, profile, documents, borrowing eligibility |
| Recruiters | search, register, clients introduced, audit trail |
| Applications | capture with documents, decide, agreement, signature, audit trail |
| Disbursements | queue, verify, hold, pay |
| Repayments | loan book, receipt, reverse |
| Commissions | list, pay |
| Change requests | raise with proof, approve, reject |
| Admin | staff accounts, roles, portfolio report, notifications |

---

## Built

Each slice worked end to end before the next started.

- [x] Foundation: API and SPA skeletons, MySQL, roles and permissions, token auth, CI
- [x] Clients, recruiters, affordability, South African ID and bank validation
- [x] Applications: pricing, affordability refusal, required documents, credit decision
- [x] Agreements: generated terms, drawn signature, photograph at signing
- [x] Disbursements: verification and payment as separate acts, commission priced at payout
- [x] Repayments: receipts, reversals, arrears
- [x] Change requests: corrections routed through an administrator with proof
- [x] Reports, staff accounts, notifications, audit trail throughout
- [x] Fees to the regulated caps, a stored repayment schedule, receipts applied in the statutory order
- [x] One loan per client at a time, a seeded demo book, tests over the money paths

### Not built

- No client-facing portal. Borrowers never sign in; every screen is staff facing.
- No restructures or payment holidays. The stored schedule could carry them,
  but nothing writes one yet.
- No settlement quote, payment arrangement or client statement.
- No PDF of the agreement for the client to take away.
- Notifications are in-app only. No mail or SMS gateway is wired up.
