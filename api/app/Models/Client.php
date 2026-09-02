<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'client_number', 'first_name', 'last_name', 'id_number', 'date_of_birth', 'gender', 'phone', 'email',
    'address_line1', 'address_line2', 'city', 'province', 'postal_code', 'country',
    'employer_name', 'job_title', 'employment_status',
    'bank_name', 'bank_account_number', 'bank_branch_code',
    'recruiter_id', 'registered_by',
])]
class Client extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date_of_birth' => 'immutable_date'];
    }

    /**
     * @return BelongsTo<Recruiter, $this>
     */
    public function recruiter(): BelongsTo
    {
        return $this->belongsTo(Recruiter::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * @return HasMany<AffordabilityAssessment, $this>
     */
    public function affordabilityAssessments(): HasMany
    {
        return $this->hasMany(AffordabilityAssessment::class);
    }

    /**
     * The assessment a decision should be read against: the most recent one.
     *
     * @return HasOne<AffordabilityAssessment, $this>
     */
    public function latestAffordability(): HasOne
    {
        return $this->hasOne(AffordabilityAssessment::class)->latestOfMany('assessed_at');
    }

    /**
     * @return HasMany<LoanApplication, $this>
     */
    public function loanApplications(): HasMany
    {
        return $this->hasMany(LoanApplication::class);
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('client_number', 'like', $like)
                ->orWhere('id_number', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
