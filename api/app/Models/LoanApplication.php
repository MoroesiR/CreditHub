<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoanApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'application_number', 'client_id', 'recruiter_id', 'affordability_assessment_id',
    'amount', 'term_months', 'interest_rate', 'purpose', 'status',
    'monthly_instalment', 'total_repayable', 'disposable_income_at_capture',
    'submitted_at', 'submitted_by', 'decided_at', 'decided_by', 'decline_reason',
])]
class LoanApplication extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'interest_rate' => 'float',
            'monthly_instalment' => 'float',
            'total_repayable' => 'float',
            'disposable_income_at_capture' => 'float',
            'status' => LoanApplicationStatus::class,
            'submitted_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Recruiter, $this>
     */
    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(Recruiter::class);
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * @return BelongsTo<AffordabilityAssessment, $this>
     */
    public function affordabilityAssessment(): BelongsTo
    {
        return $this->belongsTo(AffordabilityAssessment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @return HasOne<LoanAgreement, $this>
     */
    public function agreement(): HasOne
    {
        return $this->hasOne(LoanAgreement::class);
    }

    /**
     * @return HasOne<Disbursement, $this>
     */
    public function disbursement(): HasOne
    {
        return $this->hasOne(Disbursement::class);
    }

    /**
     * @return HasOne<Commission, $this>
     */
    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
