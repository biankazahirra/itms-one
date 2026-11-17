<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * Allocate the next document number for a sequence key.
     *
     * The sequence row is locked inside a transaction so concurrent requests
     * cannot receive the same number. Numbers are never reused.
     */
    public function next(string $sequenceKey): string
    {
        $value = DB::transaction(function () use ($sequenceKey): int {
            DB::table('number_sequences')->insertOrIgnore([
                'sequence_key' => $sequenceKey,
                'next_value' => 1,
                'updated_at' => now(),
            ]);

            $sequence = NumberSequence::query()
                ->where('sequence_key', $sequenceKey)
                ->lockForUpdate()
                ->firstOrFail();

            $value = $sequence->next_value;

            $sequence->forceFill([
                'next_value' => $value + 1,
                'updated_at' => now(),
            ])->save();

            return $value;
        });

        return $sequenceKey.'-'.str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }
}
