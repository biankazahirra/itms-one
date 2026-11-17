<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'budget_id',
    'version_number',
    'source_pr_version_id',
    'work_name',
    'rab_date',
    'location',
    'grand_total',
    'created_by',
    'finalized_by',
    'finalized_at',
])]
class BudgetVersion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rab_date' => 'date',
            'grand_total' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class)->orderBy('sort_order');
    }

    public function sourcePurchaseRequestVersion(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestVersion::class, 'source_pr_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'version_id')
            ->where('entity_type', GeneratedDocument::ENTITY_BUDGET);
    }

    public function isFinal(): bool
    {
        return $this->finalized_at !== null;
    }

    /**
     * Display label such as V01.
     */
    public function label(): string
    {
        return 'V'.str_pad((string) $this->version_number, 2, '0', STR_PAD_LEFT);
    }
}
