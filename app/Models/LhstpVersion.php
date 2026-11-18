<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'lhstp_id',
    'version_number',
    'po_number_snapshot',
    'contract_date_snapshot',
    'vendor_name_snapshot',
    'vendor_address_snapshot',
    'work_name_snapshot',
    'contract_value_snapshot',
    'contract_start_snapshot',
    'contract_end_snapshot',
    'representative_name_snapshot',
    'representative_position_snapshot',
    'payment_method_snapshot',
    'ppn_status_snapshot',
    'lhstp_date',
    'work_period',
    'realization_start',
    'realization_end',
    'realization_days',
    'reference_document_type',
    'reference_document_name',
    'reference_document_number',
    'reference_document_date',
    'work_conclusion',
    'payment_terms',
    'payment_amount',
    'ppn_rate',
    'dpp_lain',
    'ppn_amount',
    'total_payment',
    'terbilang',
    'cost_center',
    'opname_date',
    'signature_date',
    'created_by',
    'finalized_by',
    'finalized_at',
])]
class LhstpVersion extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contract_date_snapshot' => 'date',
            'contract_start_snapshot' => 'date',
            'contract_end_snapshot' => 'date',
            'lhstp_date' => 'date',
            'realization_start' => 'date',
            'realization_end' => 'date',
            'reference_document_date' => 'date',
            'opname_date' => 'date',
            'signature_date' => 'date',
            'finalized_at' => 'datetime',
            'realization_days' => 'integer',
            'contract_value_snapshot' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'ppn_rate' => 'decimal:4',
            'dpp_lain' => 'decimal:2',
            'ppn_amount' => 'decimal:2',
            'total_payment' => 'decimal:2',
        ];
    }

    public function lhstp(): BelongsTo
    {
        return $this->belongsTo(Lhstp::class);
    }

    public function termin(): HasOne
    {
        return $this->hasOne(LhstpVersionTermin::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function isFinal(): bool
    {
        return $this->finalized_at !== null;
    }

    public function label(): string
    {
        return 'V'.str_pad((string) $this->version_number, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Contract duration in whole months used by the printed sentence.
     *
     * An end date within three days of the next whole month is rounded up,
     * e.g. 1 June to 31 October counts as five months.
     */
    public function contractMonths(): int
    {
        $start = $this->contract_start_snapshot;
        $end = $this->contract_end_snapshot;

        $months = (int) $start->diffInMonths($end);
        $daysToNextMonth = $end->diffInDays($start->copy()->addMonths($months + 1));

        return $daysToNextMonth >= 1 && $daysToNextMonth <= 3
            ? $months + 1
            : $months;
    }
}
