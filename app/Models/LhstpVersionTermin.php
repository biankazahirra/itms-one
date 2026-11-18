<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lhstp_version_id',
    'contract_termin_id',
    'termin_name_snapshot',
    'percentage_snapshot',
    'nominal_snapshot',
    'created_at',
])]
class LhstpVersionTermin extends Model
{
    /**
     * Termin reservations are append-only and use created_at.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'percentage_snapshot' => 'decimal:4',
            'nominal_snapshot' => 'decimal:2',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(LhstpVersion::class, 'lhstp_version_id');
    }

    public function contractTermin(): BelongsTo
    {
        return $this->belongsTo(ContractTermin::class, 'contract_termin_id');
    }
}
