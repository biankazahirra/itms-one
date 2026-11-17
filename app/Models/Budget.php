<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'rab_number',
    'purchase_request_id',
    'status',
    'current_version_id',
    'finalized_at',
    'archived_at',
    'created_by',
    'updated_by',
])]
class Budget extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_FINAL = 'Final';

    public const STATUS_ARCHIVED = 'Archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_FINAL, self::STATUS_ARCHIVED];

    public const LOCATION = 'Cilegon';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class)->orderBy('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class, 'current_version_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class)->withTrashed();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'entity_id')
            ->where('entity_type', GeneratedDocument::ENTITY_BUDGET);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'entity_id')
            ->where('entity_type', 'budget')
            ->latest('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeDraft(Builder $query): void
    {
        $query->where('status', self::STATUS_DRAFT);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * A RAB may only be purged when no finalized history or document depends on it.
     */
    public function canBePurged(): bool
    {
        return $this->isDraft()
            && ! $this->versions()->whereNotNull('finalized_at')->exists()
            && ! $this->documents()->exists();
    }
}
