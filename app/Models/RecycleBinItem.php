<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['entity_type', 'entity_id', 'deleted_by', 'deleted_at', 'expires_at', 'restore_metadata'])]
class RecycleBinItem extends Model
{
    /**
     * Recycle bin rows carry their own deleted_at and have no created_at/updated_at columns.
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
            'deleted_at' => 'datetime',
            'expires_at' => 'datetime',
            'restore_metadata' => 'array',
        ];
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
