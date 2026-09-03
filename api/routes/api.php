<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AgreementController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\CommissionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DisbursementController;
use App\Http\Controllers\Api\V1\LoanApplicationController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RecruiterController;
use App\Http\Controllers\Api\V1\ReferenceController;
use App\Http\Controllers\Api\V1\RepaymentController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Support\Permissions;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| This file is the authorisation contract. Every route is either explicitly
| public or sits behind `auth:sanctum`, and any route that needs more than a
| valid session states the permission it requires here, not in a controller.
|
*/

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Each tile is permission-checked again as it is built, so this route
        // needs no permission of its own beyond being signed in.
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->name('dashboard.summary');

        // Reference data for the SPA's form controls. Read-only and harmless,
        // so being signed in is enough.
        Route::get('reference/banks', [ReferenceController::class, 'banks'])
            ->name('reference.banks');

        Route::get('reference/provinces', [ReferenceController::class, 'provinces'])
            ->name('reference.provinces');

        Route::get('clients', [ClientController::class, 'index'])
            ->middleware('permission:'.Permissions::CLIENTS_VIEW)
            ->name('clients.index');

        Route::post('clients', [ClientController::class, 'store'])
            ->middleware('permission:'.Permissions::CLIENTS_CREATE)
            ->name('clients.store');

        Route::get('clients/{client}', [ClientController::class, 'show'])
            ->middleware('permission:'.Permissions::CLIENTS_VIEW)
            ->name('clients.show');

        // A user's own notifications. Scoped to the signed-in user by the
        // relation itself, so there is no permission to declare.
        Route::get('notifications', [NotificationController::class, 'index'])
            ->name('notifications.index');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])
            ->name('notifications.read-all');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('notifications.read');

        Route::get('applications/{application}/agreement', [AgreementController::class, 'show'])
            ->middleware('permission:'.Permissions::AGREEMENTS_VIEW)
            ->name('agreements.show');

        Route::post('applications/{application}/agreement', [AgreementController::class, 'generate'])
            ->middleware('permission:'.Permissions::AGREEMENTS_GENERATE)
            ->name('agreements.generate');

        Route::post('applications/{application}/agreement/signature', [AgreementController::class, 'sign'])
            ->middleware('permission:'.Permissions::AGREEMENTS_SIGN)
            ->name('agreements.sign');

        Route::get(
            'applications/{application}/agreement/{agreement}/{kind}',
            [AgreementController::class, 'image'],
        )
            ->middleware('permission:'.Permissions::AGREEMENTS_VIEW)
            ->whereIn('kind', ['signature', 'photo'])
            ->name('agreements.image');

        Route::get('applications', [LoanApplicationController::class, 'index'])
            ->middleware('permission:'.Permissions::APPLICATIONS_VIEW)
            ->name('applications.index');

        Route::post('applications', [LoanApplicationController::class, 'store'])
            ->middleware('permission:'.Permissions::APPLICATIONS_CREATE)
            ->name('applications.store');

        // Declared before {application} so "quote" is not read as an id.
        Route::post('applications/quote', [LoanApplicationController::class, 'quote'])
            ->middleware('permission:'.Permissions::APPLICATIONS_CREATE)
            ->name('applications.quote');

        Route::get('applications/{application}', [LoanApplicationController::class, 'show'])
            ->middleware('permission:'.Permissions::APPLICATIONS_VIEW)
            ->name('applications.show');

        // Supporting documents are streamed through the app, never served as
        // static files: a payslip reachable by guessing a URL is a breach.
        Route::get(
            'applications/{application}/documents/{document}',
            [LoanApplicationController::class, 'downloadDocument'],
        )
            ->middleware('permission:'.Permissions::APPLICATIONS_VIEW)
            ->name('applications.documents.download');

        Route::get('applications/{application}/audit-trail', [LoanApplicationController::class, 'auditTrail'])
            ->middleware('permission:'.Permissions::APPLICATIONS_VIEW)
            ->name('applications.audit-trail');

        // The credit decision. Separate from capture, and held by a role that
        // cannot release money.
        Route::post('applications/{application}/decision', [LoanApplicationController::class, 'decide'])
            ->middleware('permission:'.Permissions::APPLICATIONS_DECIDE)
            ->name('applications.decide');

        // The payout queue. Held by a department that cannot approve a loan.
        Route::get('disbursements', [DisbursementController::class, 'index'])
            ->middleware('permission:'.Permissions::DISBURSEMENTS_VIEW)
            ->name('disbursements.index');

        Route::get('disbursements/{disbursement}', [DisbursementController::class, 'show'])
            ->middleware('permission:'.Permissions::DISBURSEMENTS_VIEW)
            ->name('disbursements.show');

        Route::post('disbursements/{disbursement}/verification', [DisbursementController::class, 'verify'])
            ->middleware('permission:'.Permissions::DISBURSEMENTS_VERIFY)
            ->name('disbursements.verify');

        Route::post('disbursements/{disbursement}/hold', [DisbursementController::class, 'hold'])
            ->middleware('permission:'.Permissions::DISBURSEMENTS_VERIFY)
            ->name('disbursements.hold');

        // Releasing the money is gated separately from checking the file.
        Route::post('disbursements/{disbursement}/payment', [DisbursementController::class, 'pay'])
            ->middleware('permission:'.Permissions::DISBURSEMENTS_PAY)
            ->name('disbursements.pay');

        // Money coming back in. Receipting is held by a desk that cannot pay
        // anything out, so one person cannot cover a missing payout with a
        // receipt that was never received.
        Route::get('loans', [RepaymentController::class, 'loans'])
            ->middleware('permission:'.Permissions::REPAYMENTS_VIEW)
            ->name('loans.index');

        Route::get('loans/{application}/repayments', [RepaymentController::class, 'index'])
            ->middleware('permission:'.Permissions::REPAYMENTS_VIEW)
            ->name('repayments.index');

        Route::post('loans/{application}/repayments', [RepaymentController::class, 'store'])
            ->middleware('permission:'.Permissions::REPAYMENTS_RECORD)
            ->name('repayments.store');

        Route::post('loans/{application}/repayments/{repayment}/reversal', [RepaymentController::class, 'reverse'])
            ->middleware('permission:'.Permissions::REPAYMENTS_RECORD)
            ->name('repayments.reverse');

        Route::get('reports/portfolio', [ReportController::class, 'portfolio'])
            ->middleware('permission:'.Permissions::REPORTS_VIEW)
            ->name('reports.portfolio');

        Route::get('commissions', [CommissionController::class, 'index'])
            ->middleware('permission:'.Permissions::COMMISSIONS_VIEW)
            ->name('commissions.index');

        Route::post('commissions/{commission}/payment', [CommissionController::class, 'pay'])
            ->middleware('permission:'.Permissions::COMMISSIONS_PAY)
            ->name('commissions.pay');

        Route::get('recruiters', [RecruiterController::class, 'index'])
            ->middleware('permission:'.Permissions::RECRUITERS_VIEW)
            ->name('recruiters.index');

        Route::post('recruiters', [RecruiterController::class, 'store'])
            ->middleware('permission:'.Permissions::RECRUITERS_CREATE)
            ->name('recruiters.store');

        // Declared before the {recruiter} routes so "unlinked-clients" is not
        // captured as a recruiter id.
        Route::get('recruiters/unlinked-clients', [RecruiterController::class, 'unlinkedClients'])
            ->middleware('permission:'.Permissions::CLIENTS_VIEW)
            ->name('recruiters.unlinked-clients');

        Route::get('recruiters/{recruiter}', [RecruiterController::class, 'show'])
            ->middleware('permission:'.Permissions::RECRUITERS_VIEW)
            ->name('recruiters.show');

        Route::get('recruiters/{recruiter}/clients', [RecruiterController::class, 'clients'])
            ->middleware('permission:'.Permissions::RECRUITERS_VIEW)
            ->name('recruiters.clients');

        Route::get('recruiters/{recruiter}/audit-trail', [RecruiterController::class, 'auditTrail'])
            ->middleware('permission:'.Permissions::RECRUITERS_VIEW)
            ->name('recruiters.audit-trail');

        // Attaching an introduction changes who gets paid, so it is gated on
        // being able to amend a client, not merely to view recruiters.
        Route::post('recruiters/{recruiter}/clients', [RecruiterController::class, 'linkClient'])
            ->middleware('permission:'.Permissions::CLIENTS_UPDATE)
            ->name('recruiters.link-client');
    });
});
