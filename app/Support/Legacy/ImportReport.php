<?php

namespace App\Support\Legacy;

use Illuminate\Support\Facades\Storage;

class ImportReport
{
    /**
     * Write the source-to-target map and every skip reason to a private CSV.
     *
     * @param  array<string, mixed>  $mapped
     * @param  array<string, array{id: int, number: string}>  $imported
     */
    public function write(string $path, array $mapped, array $imported): void
    {
        $lines = ['table,source_id,status,target_id,target_number,reason'];

        foreach ($mapped as $table => $rows) {
            if ($table === 'exceptions') {
                continue;
            }

            $targetTable = match ($table) {
                'purchase_requests' => 'purchase_request',
                default => rtrim($table, 's'),
            };

            foreach ($rows as $row) {
                $sourceId = $row['source_id'] ?? $row['company_name'] ?? '';
                $key = $targetTable.':'.$sourceId;
                $target = $imported[$key] ?? null;

                $lines[] = implode(',', [
                    $this->field($targetTable),
                    $this->field((string) $sourceId),
                    $target === null ? 'planned' : 'imported',
                    $target['id'] ?? '',
                    $this->field((string) ($target['number'] ?? '')),
                    '',
                ]);
            }
        }

        foreach ($mapped['exceptions'] ?? [] as $exception) {
            $lines[] = implode(',', [
                $this->field($exception['table']),
                $this->field($exception['source_id']),
                'skipped',
                '',
                '',
                $this->field($exception['reason']),
            ]);
        }

        Storage::disk('local')->put($path, implode("\n", $lines)."\n");
    }

    private function field(string $value): string
    {
        if (! str_contains($value, ',')) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
