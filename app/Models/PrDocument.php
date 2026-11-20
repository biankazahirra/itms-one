<?php

namespace App\Models;

use App\Services\PrivateFileService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'purchase_request_id',
    'category',
    'original_filename',
    'storage_path',
    'mime_type',
    'file_size',
    'checksum_sha256',
    'uploaded_by',
    'uploaded_at',
    'notes',
])]
class PrDocument extends Model
{
    use SoftDeletes;

    public const CATEGORIES = [
        'PR Submission Form',
        'Supporting Documents',
        'Official PR Document',
        'Procurement Documents',
        'Other Documents',
    ];

    /**
     * Formats that may be displayed inline. Other accepted formats are
     * download-only.
     *
     * @var list<string>
     */
    public const PREVIEW_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Uploads accepted by the archive, in kilobytes.
     */
    public const MAX_FILE_SIZE_KB = 10240;

    /**
     * User uploads are append-only and have no updated_at column.
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
            'uploaded_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * A permanent purge removes the stored file along with the metadata.
     */
    protected static function booted(): void
    {
        static::forceDeleting(function (PrDocument $document): void {
            app(PrivateFileService::class)->delete($document->storage_path);
        });
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function canPreview(): bool
    {
        return in_array($this->mime_type, self::PREVIEW_MIME_TYPES, true);
    }
}
