<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sequence_key', 'next_value', 'updated_at'])]
class NumberSequence extends Model
{
    /**
     * Sequences only track their last update; there is no created_at column.
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
            'next_value' => 'integer',
            'updated_at' => 'datetime',
        ];
    }
}
