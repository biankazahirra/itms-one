<?php

namespace App\Rules;

use App\Support\TextNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueNormalized implements ValidationRule
{
    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly ?int $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $normalized = TextNormalizer::normalize($value);

        $query = DB::table($this->table)->select('id', $this->column);

        if ($this->ignoreId !== null) {
            $query->where('id', '!=', $this->ignoreId);
        }

        foreach ($query->cursor() as $row) {
            if (TextNormalizer::normalize($row->{$this->column}) === $normalized) {
                $fail('Nilai :attribute sudah digunakan.');

                return;
            }
        }
    }
}
