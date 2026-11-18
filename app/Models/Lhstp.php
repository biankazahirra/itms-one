<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'lhstp_number',
    'contract_id',
    'status',
    'current_version_id',
    'finalized_at',
    'archived_at',
    'created_by',
    'updated_by',
])]
class Lhstp extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'Draft';

    public const STATUS_FINAL = 'Final';

    public const STATUS_ARCHIVED = 'Archived';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_FINAL, self::STATUS_ARCHIVED];

    public const REFERENCE_BERITA_ACARA = 'Surat Berita Acara';

    public const REFERENCE_PURCHASE_ORDER = 'Surat Purchase Order';

    public const REFERENCE_OTHER = 'Dokumen Lainnya';

    public const REFERENCE_TYPES = [
        self::REFERENCE_BERITA_ACARA,
        self::REFERENCE_PURCHASE_ORDER,
        self::REFERENCE_OTHER,
    ];

    public const DEFAULT_COST_CENTER = '3111440';

    public const DEFAULT_PPN_RATE = '12';

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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LhstpVersion::class)->orderBy('version_number');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(LhstpVersion::class, 'current_version_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GeneratedDocument::class, 'entity_id')
            ->where('entity_type', GeneratedDocument::ENTITY_LHSTP);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'entity_id')
            ->where('entity_type', 'lhstp')
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

    public function scopeNotArchived(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_ARCHIVED);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinal(): bool
    {
        return $this->status === self::STATUS_FINAL;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED || $this->archived_at !== null;
    }

    /**
     * Only a draft with no finalized version can be deleted; finalized
     * versions and their termin reservations are retained.
     */
    public function canBeDeleted(): bool
    {
        return $this->isDraft() && ! $this->versions()->whereNotNull('finalized_at')->exists();
    }

    /**
     * Keep purged records from orphaning generated documents or history.
     */
    public function canBePurged(): bool
    {
        return ! GeneratedDocument::query()
            ->where('entity_type', GeneratedDocument::ENTITY_LHSTP)
            ->where('entity_id', $this->id)
            ->exists();
    }
}
