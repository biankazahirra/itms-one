<?php

namespace App\Services;

use App\Support\Money;
use Illuminate\Validation\ValidationException;
use RoundingMode;

class TerminAllocationService
{
    /**
     * Build termin rows and assign the rounding remainder to the final termin
     * so the nominal amounts sum exactly to the contract value.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{termin_name: string, percentage: string, nominal_amount: string, sort_order: int}>
     */
    public function build(string $contractValue, array $rows): array
    {
        $rows = array_values($rows);
        $percentageTotal = '0';

        foreach ($rows as $row) {
            $percentageTotal = bcadd($percentageTotal, Money::scale($row['percentage'], 4), 4);
        }

        if (bccomp($percentageTotal, '100.0000', 4) !== 0) {
            throw ValidationException::withMessages([
                'termins' => 'Total persentase termin harus tepat 100%.',
            ]);
        }

        $value = Money::scale($contractValue, 2);
        $result = [];
        $allocated = '0';

        foreach ($rows as $index => $row) {
            $percentage = Money::scale($row['percentage'], 4);
            $isLast = $index === array_key_last($rows);

            $nominal = $isLast
                ? bcsub($value, $allocated, 2)
                : bcround(bcdiv(bcmul($value, $percentage, 8), '100', 8), 2, RoundingMode::HalfAwayFromZero);

            $allocated = bcadd($allocated, $nominal, 2);

            $result[] = [
                'termin_name' => trim((string) $row['termin_name']),
                'percentage' => $percentage,
                'nominal_amount' => $nominal,
                'sort_order' => $index + 1,
            ];
        }

        return $result;
    }
}
