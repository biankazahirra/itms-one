<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contract_id', 'user_id', 'action', 'before_values', 'after_values', 'created_at'])]
class ContractActivityLog extends Model
{
    /**
     * Change history is append-only and has no updated_at column.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Indonesian labels for the fields tracked in contract history.
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'po_number' => 'Nomor PO',
        'contract_date' => 'Tanggal kontrak',
        'vendor_id' => 'Vendor',
        'work_name' => 'Nama pekerjaan',
        'representative_name' => 'Nama perwakilan',
        'representative_position' => 'Jabatan perwakilan',
        'start_date' => 'Tanggal mulai',
        'end_date' => 'Tanggal berakhir',
        'contract_value' => 'Nilai kontrak',
        'ppn_status' => 'Status PPN',
        'payment_method' => 'Metode pembayaran',
        'monthly_payment_amount' => 'Nominal bulanan',
        'full_financing_amount' => 'Nominal full financing',
        'status' => 'Status',
        'purchase_request_id' => 'PR terkait',
        'termins' => 'Jadwal termin',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
