<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'entity_type', 'entity_id', 'action', 'description', 'before_values', 'after_values', 'created_at'])]
class ActivityLog extends Model
{
    /**
     * Indonesian labels for the modules used in activity entries.
     *
     * @var array<string, string>
     */
    public const ENTITY_LABELS = [
        'purchase_request' => 'Purchase Requisition',
        'budget' => 'RAB',
        'contract' => 'Kontrak',
        'lhstp' => 'LHSTP',
    ];

    /**
     * Indonesian labels for the recorded action codes.
     *
     * @var array<string, string>
     */
    public const ACTION_LABELS = [
        'created' => 'Dibuat',
        'updated' => 'Diperbarui',
        'finalized' => 'Difinalisasi',
        'revised' => 'Direvisi',
        'revision_created' => 'Revisi dibuat',
        'archived' => 'Diarsipkan',
        'unarchived' => 'Batal diarsipkan',
        'deleted' => 'Dipindahkan ke Recycle Bin',
        'restored' => 'Dipulihkan',
        'document_generated' => 'Dokumen dibuat',
        'attachment_added' => 'Lampiran ditambahkan',
        'attachment_removed' => 'Lampiran dihapus',
        'purge_blocked' => 'Penghapusan permanen diblokir',
        'stage_updated' => 'Tahap tracking diperbarui',
        'unlinked' => 'Kaitan PR dilepas',
        'document_uploaded' => 'Dokumen ditambahkan',
        'payment_recorded' => 'Pembayaran dicatat',
        'payment_confirmed' => 'Pembayaran dikonfirmasi',
        'payment_corrected' => 'Koreksi pembayaran dicatat',
        'payment_deleted' => 'Draf pembayaran dihapus',
        'evidence_uploaded' => 'Bukti pembayaran ditambahkan',
        'evidence_deleted' => 'Bukti pembayaran dihapus',
    ];

    /**
     * Activity logs are append-only and have no updated_at column.
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
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
