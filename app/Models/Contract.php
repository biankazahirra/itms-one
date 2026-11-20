<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'po_number',
    'contract_date',
    'vendor_id',
    'purchase_request_id',
    'work_name',
    'representative_name',
    'representative_position',
    'start_date',
    'end_date',
    'contract_value',
    'ppn_status',
    'payment_method',
    'monthly_payment_amount',
    'full_financing_amount',
    'status',
    'archived_at',
    'created_by',
    'updated_by',
])]
class Contract extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'Aktif';

    public const STATUS_INACTIVE = 'Nonaktif';

    public const STATUS_ARCHIVED = 'Archived';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_ARCHIVED];

    public const PPN_INCLUDED = 'Termasuk PPN';

    public const PPN_EXCLUDED = 'Belum termasuk PPN';

    public const PPN_STATUSES = [self::PPN_INCLUDED, self::PPN_EXCLUDED];

    public const PAYMENT_MONTHLY = 'Bulanan';

    public const PAYMENT_TERMIN = 'Termin';

    public const PAYMENT_FULL = 'Full Financing';

    public const PAYMENT_METHODS = [self::PAYMENT_MONTHLY, self::PAYMENT_TERMIN, self::PAYMENT_FULL];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'contract_value' => 'decimal:2',
            'monthly_payment_amount' => 'decimal:2',
            'full_financing_amount' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function termins(): HasMany
    {
        return $this->hasMany(ContractTermin::class)->orderBy('sort_order');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ContractAttachment::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ContractActivityLog::class)->latest('created_at');
    }

    public function lhstps(): HasMany
    {
        return $this->hasMany(Lhstp::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentRecord::class)
            ->orderByDesc('payment_date')
            ->orderByDesc('id');
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

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED || $this->archived_at !== null;
    }

    /**
     * Archived status is manual and never replaced by automatic expiry.
     */
    public function effectiveStatus(): string
    {
        if ($this->isArchived()) {
            return self::STATUS_ARCHIVED;
        }

        return $this->end_date !== null && $this->end_date->lt(today())
            ? self::STATUS_INACTIVE
            : self::STATUS_ACTIVE;
    }

    /**
     * Inactive contracts stay eligible; archived contracts cannot be used
     * for a new LHSTP.
     */
    public function isSelectableForLhstp(): bool
    {
        return ! $this->trashed() && ! $this->isArchived();
    }

    /**
     * Keep purged contracts from orphaning stored attachments, the contract
     * change history, its LHSTP records or payment records.
     */
    public function canBePurged(): bool
    {
        return ! $this->attachments()->exists()
            && ! $this->activityLogs()->exists()
            && ! $this->lhstps()->exists()
            && ! $this->payments()->exists();
    }
}
