<?php

namespace App\Models;

use App\Services\PrivateFileService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'payment_record_id',
    'original_filename',
    'storage_path',
    'mime_type',
    'file_size',
    'checksum_sha256',
    'uploaded_by',
    'uploaded_at',
])]
class PaymentEvidence extends Model
{
    use SoftDeletes;

    /**
     * Evidence rows are stored in `payment_evidences`; the inflector treats
     * "evidence" as uncountable, so the table name is set explicitly.
     *
     * @var string
     */
    protected $table = 'payment_evidences';

    /**
     * Evidence rows are append-only and have no updated_at column.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Formats accepted for payment evidence, in kilobytes.
     */
    public const MAX_FILE_SIZE_KB = 10240;

    /**
     * @var list<string>
     */
    public const PREVIEW_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

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
        static::forceDeleting(function (PaymentEvidence $evidence): void {
            app(PrivateFileService::class)->delete($evidence->storage_path);
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentRecord::class, 'payment_record_id');
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
