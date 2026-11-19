# Legacy PR Database Import — Design

Date: 2026-11-19
Status: Draft, awaiting approval

## Intent

Import data from `C:\Users\Administrator\Downloads\pr_db.sql` into `itms_admin_db` without changing the source dump or any legacy database. Only records that map cleanly and remain meaningful in the new application are imported. Everything else is reported, not silently changed.

## Goals

- Reuse vendors, PR headers/items, contracts, and usable LHSTP records.
- Preserve source identifiers in an import report so old and new records can be matched.
- Use application services so new numbers, validation, transactions, snapshots, and activity logs follow the approved rules.
- Produce a dry-run report before anything is written.

## Non-goals

- No RAB, inventory, user, department master, satuan master, document, or activity history import. The dump contains none of that data.
- No modification, rename, or deletion of the source dump or legacy database.
- No invention of missing business text, prices, dates, or identifiers.
- No change to the approved database schema or application UI.
- No finalization of imported Draft PRs or LHSTPs.

## Constraints

- `AGENTS.md`: work is a new task outside Phases 0–8 and needs explicit approval before implementation.
- New PRs require a unique `pr_number`, a real recipient department, at least one item with `quantity > 0` and a price.
- New contracts require a unique `po_number`, `ppn_status`, `payment_method`, and representative data.
- New LHSTPs require a valid number and date plus snapshots, conclusion, payment terms, and PPN calculation.
- All imports run inside database transactions and through `PurchaseRequestService`, `ContractService`, and `LhstpService`.
- A full backup of `itms_admin_db` is taken before applying any import.

## Source inventory (measured from the dump)

| Table | Rows | Usable / issue |
|---|---|---|
| `purchase_requisition` | 134 | All `Draft`; no PR number; 73 without category; 21 without items |
| `detail_anggaran` | 324 | 84 null prices; 4 null quantities; 1 orphan row |
| `vendor` | 3 | IDs 1 and 2 have the same name |
| `kontrak` | 3 | IDs 1 and 2 share PO `3550002557`; no PPN/payment method/termin |
| `lhstp` | 8 | 3 blank numbers and zero dates; 1 duplicated number; PPN and totals null; required new fields absent |
| `master_barang` | 2 | Only `rim` and `pcs` units; no direct target table |
| `mutasi_inventori` | 0 | Nothing to import |

## Field mapping

### Departments (new master data, derived from existing text)

- `Divisi Accounting`, `Accounting Department` → `Accounting`
- `Divisi HC`, `Divisi Human Capital`, `Human Capital Department` → `Human Capital`
- `Divisi ITMS`, `IT & Management System Department` → `ITMS`

`purchase_requisition.dari` has no target field; it is recorded in the report only.

### Vendors

- `PT Krakatau Information Technology` (IDs 1 and 2) → one vendor.
- `PT Kosera Karya Prima` → one vendor.
- Addresses, NPWP, and contact are null, so they stay null.

### PR

- `perihal` → `subject`; `tanggal` → `pr_date`.
- Category flags → single `category`: `Investment`, `Services`, `Office Supplies`, `Fixed Asset`, `Maintenance`. No flag → null.
- `kepada` → recipient department by mapping above.
- No PR number in source → new number from `DocumentNumberService`, ordered by `tanggal`, then `id_pr`.
- `id_pr` → stored in the import report as the source key.

### PR items

- `nama_anggaran` → `budget_name`; `qty` → `quantity`; `estimasi_harga` → `unit_price`; `qty × estimasi_harga` → `estimated_total`.
- `sort_order` follows source `id_detail`.
- `unit`, `spesifikasi`, `gl_account`, `cost_center`, `no_io`, `discount`, `id_barang` have no PR item target; they are listed in the report and dropped.
- Rows with null price or null/zero quantity are skipped. A PR whose items are all skipped is itself skipped.

### Contracts

- `nomor_purchase_order` → `po_number`; `nama_pekerjaan` → `work_name`; `tanggal_kontrak` → `contract_date`.
- `nilai_kontrak` → `contract_value`; start/end dates map directly.
- `nama_penandatangan_vendor` / `jabatan_penandatangan_vendor` map directly.
- `jangka_waktu_bulan` has no target; recorded in report only.
- Missing `ppn_status`, `payment_method`, and payment amount are not guessed. Affected contracts are skipped unless a rule is approved below.

### LHSTP

- Only rows with a non-empty `nomor_lhstp` and a real date are candidates.
- `tanggal_lhstp` → `lhstp_date`; `nomor_berita_acara` → reference number; `tanggal_berita_acara` → reference date.
- `periode_pekerjaan` → `work_period`; realization dates and days map directly.
- `nilai_pembayaran`, `persentase_ppn`, `dpp_lain`, `nilai_ppn`, `total_pembayaran` feed `LhstpPaymentCalculator` instead of being copied verbatim.
- `terbilang`, `keterangan_ppn`, `cost_center` map where fields exist.
- Rows missing required new fields (conclusion, payment terms, opname/signature dates) are skipped unless values are supplied below.
- Imported LHSTPs stay `Draft`; they are not finalized and no documents are generated.

## Approach

Chosen: **C — agree mapping and conflict rules, then import clean data through services.**

Rejected: A (automatic clean import with too much guessing) and B (read-only staging archive that cannot be used in workflows).

Implementation shape:

1. One Artisan command `data:import-legacy-pr-db` with `--apply` flag. Default run is dry-run.
2. The command reads the SQL dump from an explicit `--file` path. It parses source rows into plain arrays; it never connects to or writes the legacy database.
3. Pure mapping/filtering helpers produce a list of records plus reasons for every skipped row.
4. Dry-run prints counts and writes a CSV report under `storage/app/imports/` with source id → planned new record, plus every skip reason.
5. `--apply` reruns the mapping, takes a backup first, then imports record by record through application services inside transactions. Failures are reported per record and do not roll back unrelated records.
6. The report is kept as the only source-to-target map; no new database table is added.
7. Idempotency: a second `--apply` run detects existing source keys in the report and skips them unless `--force` is given.

## Testing

- Unit/feature tests for each mapping rule using a small fixture derived from the dump shape, not the full real dump.
- Dry-run test: no database rows written; report contains expected skips.
- Apply test: valid PR/vendor/contract rows created; invalid rows skipped; service validation still applies.
- Test that a second apply without `--force` makes no new records.
- Test that source data outside the mapping (unit, spec, GL) never reaches new fields.
- Full suite and Pint must pass before any real import.

## Decisions still needed before implementation

1. **Duplicate PO `3550002557`:** import only contract ID 1, only ID 2, or neither? Their LHSTPs point to ID 2.
2. **Incomplete LHSTPs:** skip all rows that lack required new fields, or supply the missing conclusion/payment terms/dates?
3. **PR numbering:** confirm that new sequential `PR-000xxx` numbers are acceptable, with old `id_pr` kept only in the report.
4. **Empty-price/quantity items:** skip those items, or should the whole affected PR be skipped?

## Builder check

No fresh reviewer subagent available in this session; a self-review of this document was used. Sensible defaults are marked above as my calls: department consolidation, vendor merge, category mapping, service-based import, dry-run default, and report-only mapping. The four items listed under "Decisions still needed" change what gets built and need your answer.
