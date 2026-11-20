<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contract_id',
    'contract_termin_id',
    'corrects_payment_record_id',
    'status',
    'payment_date',
    'payment_amount',
    'payment_reference',
    'created_by',
])]
class PaymentRecord extends Model
{
    public const STATUS_DRAFT = 'Draft';

    public const STATUS_CONFIRMED = 'Confirmed';

    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_CONFIRMED];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'payment_amount' => 'decimal:2',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function termin(): BelongsTo
    {
        return $this->belongsTo(ContractTermin::class, 'contract_termin_id');
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_payment_record_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_payment_record_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PaymentEvidence::class)->latest('uploaded_at')->latest('id');
    }

    /**
     * Route-model binding name for nested evidence routes.
     */
    public function paymentEvidences(): HasMany
    {
        return $this->evidences();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Only draft records can be removed; confirmed records are immutable and
     * require a correction entry instead.
     */
    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    public function isCorrection(): bool
    {
        return $this->corrects_payment_record_id !== null;
    }
}
