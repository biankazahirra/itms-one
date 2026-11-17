<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'entity_type',
    'entity_id',
    'version_id',
    'document_format',
    'document_kind',
    'original_filename',
    'storage_path',
    'mime_type',
    'file_size',
    'checksum_sha256',
    'generated_by',
    'generated_at',
])]
class GeneratedDocument extends Model
{
    public const ENTITY_PURCHASE_REQUEST = 'purchase_request';

    public const ENTITY_BUDGET = 'budget';

    public const FORMAT_PDF = 'PDF';

    /**
     * Documents are append-only and have no updated_at column.
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
            'file_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
