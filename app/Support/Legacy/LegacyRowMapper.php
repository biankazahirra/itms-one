<?php

namespace App\Support\Legacy;

class LegacyRowMapper
{
    /** @var array<string, string> */
    private const DEPARTMENTS = [
        'Divisi Accounting' => 'Accounting',
        'Accounting Department' => 'Accounting',
        'Divisi HC' => 'Human Capital',
        'Divisi Human Capital' => 'Human Capital',
        'Human Capital Department' => 'Human Capital',
        'Divisi ITMS' => 'ITMS',
        'IT & Management System Department' => 'ITMS',
    ];

    /** @var array<string, string> */
    private const CATEGORIES = [
        'pr_investment' => 'Investment',
        'pr_services' => 'Services',
        'pr_office_supplies' => 'Office Supplies',
        'pr_fixed_asset' => 'Fixed Asset',
        'pr_maintenance' => 'Maintenance',
    ];

    /**
     * Map raw legacy rows to import rows and explain every skipped row.
     *
     * @param  array<string, list<array<string, string|null>>>  $rows
     * @return array{
     *     departments: list<string>,
     *     vendors: list<array{legacy_ids: list<string>, company_name: string}>,
     *     purchase_requests: list<array{source_id: string, subject: string, pr_date: string, category: string|null, department: string, items: list<array{budget_name: string, quantity: string, unit_price: string}>}>,
     *     contracts: list<array<string, string>>,
     *     lhstps: list<array<string, string>>,
     *     exceptions: list<array{table: string, source_id: string, reason: string}>
     * }
     */
    public function map(array $rows): array
    {
        $exceptions = [];
        $departments = $this->departments($rows['purchase_requisition'] ?? []);
        $vendors = $this->vendors($rows['vendor'] ?? []);
        [$purchaseRequests, $prExceptions] = $this->purchaseRequests(
            $rows['purchase_requisition'] ?? [],
            $rows['detail_anggaran'] ?? [],
        );
        [$contracts, $contractExceptions] = $this->contracts($rows['kontrak'] ?? []);
        [$lhstps, $lhstpExceptions] = $this->lhstps($rows['lhstp'] ?? []);

        return [
            'departments' => $departments,
            'vendors' => $vendors,
            'purchase_requests' => $purchaseRequests,
            'contracts' => $contracts,
            'lhstps' => $lhstps,
            'exceptions' => array_merge($exceptions, $prExceptions, $contractExceptions, $lhstpExceptions),
        ];
    }

    /**
     * @param  list<array<string, string|null>>  $prs
     * @return list<string>
     */
    private function departments(array $prs): array
    {
        $names = [];

        foreach ($prs as $pr) {
            foreach (['kepada', 'dari'] as $field) {
                $name = self::DEPARTMENTS[$this->text($pr[$field] ?? null)] ?? null;

                if ($name !== null) {
                    $names[$name] = true;
                }
            }
        }

        $result = array_keys($names);
        sort($result);

        return $result;
    }

    /**
     * @param  list<array<string, string|null>>  $vendors
     * @return list<array{legacy_ids: list<string>, company_name: string}>
     */
    private function vendors(array $vendors): array
    {
        $grouped = [];

        foreach ($vendors as $vendor) {
            $name = $this->text($vendor['nama_vendor'] ?? null);

            if ($name === '') {
                continue;
            }

            $grouped[$name][] = $this->text($vendor['id_vendor'] ?? null);
        }

        return array_map(
            fn (string $name, array $ids): array => [
                'legacy_ids' => $ids,
                'company_name' => $name,
            ],
            array_keys($grouped),
            array_values($grouped),
        );
    }

    /**
     * @param  list<array<string, string|null>>  $prs
     * @param  list<array<string, string|null>>  $items
     * @return array{0: list<array<string, mixed>>, 1: list<array{table: string, source_id: string, reason: string}>}
     */
    private function purchaseRequests(array $prs, array $items): array
    {
        $byPr = [];

        foreach ($items as $item) {
            $byPr[$this->text($item['id_pr'] ?? null)][] = $item;
        }

        $mapped = [];
        $exceptions = [];

        foreach ($prs as $pr) {
            $id = $this->text($pr['id_pr'] ?? null);
            $subject = $this->text($pr['perihal'] ?? null);
            $date = $this->text($pr['tanggal'] ?? null);
            $department = self::DEPARTMENTS[$this->text($pr['kepada'] ?? null)] ?? null;
            $category = $this->category($pr);

            if ($subject === '' || ! $this->validDate($date) || $department === null) {
                $exceptions[] = $this->exception('purchase_requisition', $id, 'Missing subject, date, or department');

                continue;
            }

            $validItems = [];

            foreach ($byPr[$id] ?? [] as $item) {
                $name = $this->text($item['nama_anggaran'] ?? null);
                $quantity = $this->text($item['qty'] ?? null);
                $price = $this->text($item['estimasi_harga'] ?? null);

                if ($name === '' || (float) $quantity <= 0 || (float) $price <= 0) {
                    $exceptions[] = $this->exception(
                        'detail_anggaran',
                        $this->text($item['id_detail'] ?? null),
                        'Missing or invalid name, quantity, or price',
                    );

                    continue;
                }

                $validItems[] = [
                    'budget_name' => $name,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                ];
            }

            if ($validItems === []) {
                $exceptions[] = $this->exception('purchase_requisition', $id, 'No valid items');

                continue;
            }

            $mapped[] = [
                'source_id' => $id,
                'subject' => $subject,
                'pr_date' => $date,
                'category' => $category,
                'department' => $department,
                'items' => $validItems,
            ];
        }

        return [$mapped, $exceptions];
    }

    /** @param array<string, string|null> $pr */
    private function category(array $pr): ?string
    {
        $found = [];

        foreach (self::CATEGORIES as $field => $label) {
            if ((int) ($pr[$field] ?? 0) !== 0) {
                $found[] = $label;
            }
        }

        return count($found) === 1 ? $found[0] : null;
    }

    /**
     * @param  list<array<string, string|null>>  $contracts
     * @return array{0: list<array<string, string>>, 1: list<array{table: string, source_id: string, reason: string}>}
     */
    private function contracts(array $contracts): array
    {
        $byPo = [];

        foreach ($contracts as $contract) {
            $byPo[$this->text($contract['nomor_purchase_order'] ?? null)][] = $contract;
        }

        $kept = [];

        foreach ($byPo as $po => $group) {
            $ids = array_map(fn (array $contract): int => (int) ($contract['id_kontrak'] ?? 0), $group);
            $kept[$po] = (string) max($ids);
        }

        $exceptions = [];

        foreach ($contracts as $contract) {
            $id = $this->text($contract['id_kontrak'] ?? null);
            $po = $this->text($contract['nomor_purchase_order'] ?? null);

            $reason = $id !== $kept[$po]
                ? "Duplicate PO; contract ID {$kept[$po]} kept"
                : 'Missing payment rules';

            $exceptions[] = $this->exception('kontrak', $id, $reason);
        }

        return [[], $exceptions];
    }

    /**
     * @param  list<array<string, string|null>>  $lhstps
     * @return array{0: list<array<string, string>>, 1: list<array{table: string, source_id: string, reason: string}>}
     */
    private function lhstps(array $lhstps): array
    {
        $exceptions = [];

        foreach ($lhstps as $lhstp) {
            $id = $this->text($lhstp['id_lhstp'] ?? null);
            $date = $this->text($lhstp['tanggal_lhstp'] ?? null);

            $reason = ! $this->validDate($date)
                ? 'Invalid date'
                : 'Contract not imported';

            $exceptions[] = $this->exception('lhstp', $id, $reason);
        }

        return [[], $exceptions];
    }

    private function exception(string $table, string $id, string $reason): array
    {
        return ['table' => $table, 'source_id' => $id, 'reason' => $reason];
    }

    private function validDate(string $value): bool
    {
        return $value !== '' && $value !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function text(?string $value): string
    {
        return trim((string) $value);
    }
}
