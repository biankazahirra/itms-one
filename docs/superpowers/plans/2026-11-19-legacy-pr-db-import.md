# Legacy PR Database Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Import the clean, relevant rows of `pr_db.sql` into `itms_admin_db` through application services, with a dry-run report and a record of every skipped row.

**Architecture:** A small parser turns phpMyAdmin `INSERT` statements into plain rows. A pure mapper applies the approved field rules and produces import rows plus exceptions. An Artisan command writes a CSV report in dry-run mode and, with `--apply`, sends only approved rows through `PurchaseRequestService`, `ContractService`, and `LhstpService`. No schema, UI, or legacy database changes.

**Tech Stack:** Laravel 13, PHP 8.5, Pest, existing application services, private `local` disk for reports.

**Spec:** `docs/superpowers/specs/2026-11-19-legacy-pr-db-import-design.md`

## Global Constraints

- Never connect to, modify, rename, or delete the legacy database or the source dump. Read the dump file only.
- No new tables, migrations, dependencies, or UI.
- No invented business values: no guessed price, quantity, date, title, or contract payment terms.
- Use application services so document numbers, validation, transactions, snapshots, and activity logs stay consistent.
- Back up `itms_admin_db` before `--apply`. See `project_docs/ITMS_Administrative_App_Deployment_Guide.md`.
- Imported PRs and LHSTPs stay `Draft`; no finalization and no document generation.
- Source fields with no target (`unit`, `spesifikasi`, `gl_account`, `cost_center`, `no_io`, `discount`, `id_barang`, `dari`, `jangka_waktu_bulan`) go into the report, not into new database fields.
- Server-side validation stays authoritative; mapper output is still validated by the services.

## Review Focus

- **A row with `0000-00-00` date:** mapper must skip it; test `skips zero dates` in Task 2.
- **A duplicate source business key:** contract ID 2 wins for PO `3550002557`, and a second `--apply` makes no new rows; tests `keeps only approved contract ID` and `is idempotent` in Tasks 2 and 4.
- **A PR whose only item is invalid:** PR must be skipped, not created with zero items; test `skips PR with no valid items` in Task 2.
- **A malformed SQL string value (comma, quote, escaped newline):** reader must return the original text; test `parses quoted values with commas and escapes` in Task 1.
- **A dry-run accidentally writing data:** command without `--apply` must create zero database rows; test `dry run writes no database rows` in Task 3.

---

### Task 1: SQL dump reader

**Files:**
- Create: `app/Support/Legacy/SqlDumpReader.php`
- Test: `tests/Feature/LegacyImport/SqlDumpReaderTest.php`
- Fixture: `tests/Fixtures/legacy-pr-mini.sql`

**Interfaces:**
- Consumes: a local SQL dump file path.
- Produces: `SqlDumpReader::read(string $path): array<string, list<array<string, string|null>>>` — keys are table names, each value is a list of rows with column names from the `INSERT` statement.

- [ ] **Step 1: Write the failing test**

```php
it('parses quoted values with commas and escapes', function () {
    $rows = app(SqlDumpReader::class)->read(base_path('tests/Fixtures/legacy-pr-mini.sql'));

    expect($rows['purchase_requisition'][0]['perihal'])
        ->toBe('Lisensi Office 365, Autocad, & Power BI')
        ->and($rows['detail_anggaran'][0]['spesifikasi'])->toBe("Merk A\nBaris 2")
        ->and($rows['vendor'])->toHaveCount(2);
});
```

The fixture must contain real `CREATE TABLE` and `INSERT INTO` syntax from the dump, including a `NULL` value, an empty string, a quoted comma, and `\n` or `\r\n` inside a text value.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/LegacyImport/SqlDumpReaderTest.php`
Expected: FAIL because class `SqlDumpReader` does not exist.

- [ ] **Step 3: Implement `SqlDumpReader::read()`**

Parse only `INSERT INTO \`table\` (columns) VALUES (values);` statements. Split value tuples on commas outside single quotes; strip one layer of surrounding single quotes; convert SQL `''` to `'` and `\n`/`\r\n` escapes to real newlines; convert bare `NULL` to `null`. Ignore comments, `CREATE TABLE`, and all other statements. Throw `InvalidArgumentException` for an unreadable file or an `INSERT` with mismatched column/value counts.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/LegacyImport/SqlDumpReaderTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Legacy/SqlDumpReader.php tests/Feature/LegacyImport/SqlDumpReaderTest.php tests/Fixtures/legacy-pr-mini.sql
git commit -m "Add legacy SQL dump reader"
```

---

### Task 2: Mapping and filtering rules

**Files:**
- Create: `app/Support/Legacy/LegacyRowMapper.php`
- Test: `tests/Feature/LegacyImport/LegacyRowMapperTest.php`

**Interfaces:**
- Consumes: the array from `SqlDumpReader::read()`.
- Produces: `LegacyRowMapper::map(array $rows): array{departments: list<string>, vendors: list<array{legacy_ids: list<string>, company_name: string}>, purchase_requests: list<array{source_id: string, subject: string, pr_date: string, category: string|null, department: string, items: list<array{budget_name: string, quantity: string, unit_price: string}>}>, contracts: list<array{source_id: string, po_number: string, contract_date: string, vendor_company_name: string, work_name: string, representative_name: string, representative_position: string, start_date: string, end_date: string, contract_value: string}>, lhstps: list<array{source_id: string, contract_source_id: string, lhstp_number: string, lhstp_date: string, work_period: string, payment_amount: string, ppn_rate: string, cost_center: string, realization_start: string, realization_end: string, reference_document_type: string, reference_document_number: string, reference_document_date: string, work_conclusion: string, payment_terms: string}>, exceptions: list<array{table: string, source_id: string, reason: string}>}`.
- Later tasks consume `departments`, `vendors`, `purchase_requests`, `contracts`, `lhstps`, and `exceptions` exactly as typed above.

- [ ] **Step 1: Write the failing tests**

```php
it('consolidates department and vendor names', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect($result['departments'])->toBe(['Accounting', 'Human Capital', 'ITMS'])
        ->and($result['vendors'][0]['legacy_ids'])->toBe(['1', '2']);
});

it('skips contracts whose payment rules are missing', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect($result['contracts'])->toBe([])
        ->and($result['exceptions'])->toContain([
            'table' => 'kontrak', 'source_id' => '1', 'reason' => 'Duplicate PO; contract ID 2 kept',
        ]);
});

it('skips PR with no valid items', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect(array_column($result['purchase_requests'], 'source_id'))->not->toContain('33');
});

it('skips zero dates', function () {
    $result = app(LegacyRowMapper::class)->map(rowsFromFixture());

    expect(array_column($result['lhstps'], 'source_id'))->not->toContain('3');
});
```

`rowsFromFixture()` calls `app(SqlDumpReader::class)->read(...)` on the Task 1 fixture. Extend the fixture with the duplicate PO, an invalid PR item, a PR whose items are all invalid, and a zero-date LHSTP.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/LegacyImport/LegacyRowMapperTest.php`
Expected: FAIL because class `LegacyRowMapper` does not exist.

- [ ] **Step 3: Implement `LegacyRowMapper::map()`**

Apply the design document exactly: department consolidation, vendor merge, category flags, new PR numbers left to the service, `estimasi_harga` as `unit_price` with `quantity × unit_price` computed by the service, contract ID 2 as the surviving duplicate PO, and skip invalid items/PRs/LHSTPs. Skip every contract because the dump has no `ppn_status`, `payment_method`, or payment amount; those values are not guessed, so `contracts` is empty for this dump and every contract row gets an exception. Skip every LHSTP whose contract was skipped or whose required new fields are absent. Append one exception row per skipped source row with a short English reason. Treat `null`, empty string, `0`, and `0000-00-00` as unusable for required fields. Use `Lhstp::REFERENCE_BERITA_ACARA` when `nomor_berita_acara` is present.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/LegacyImport/LegacyRowMapperTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Support/Legacy/LegacyRowMapper.php tests/Feature/LegacyImport/LegacyRowMapperTest.php tests/Fixtures/legacy-pr-mini.sql
git commit -m "Add legacy import mapping rules"
```

---

### Task 3: Dry-run command and exception report

**Files:**
- Create: `app/Http/Controllers/` — none. Create: `app/Console/Commands/ImportLegacyPrDatabase.php`
- Create: `app/Support/Legacy/ImportReport.php`
- Test: `tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php`

**Interfaces:**
- Consumes: `SqlDumpReader::read()`, `LegacyRowMapper::map()`.
- Produces: `php artisan data:import-legacy-pr-db --file=<path>` with optional `--apply`, `--force`, `--user=<user id>`, and `--backup-confirmed`. Dry run writes `storage/app/imports/legacy-pr-<timestamp>-<uniqid>.csv` with columns `table,source_id,status,target_id,target_number,reason` and no database rows.
- `ImportReport::write(string $path, array $mapped, array $imported): void` — later tasks rely on this signature; `imported` maps `table:source_id` to `['id' => int, 'number' => string]`.

- [ ] **Step 1: Write the failing tests**

```php
it('dry run writes no database rows', function () {
    Storage::fake('local');

    $this->artisan('data:import-legacy-pr-db', ['--file' => fixturePath()])
        ->assertSuccessful();

    expect(PurchaseRequest::count())->toBe(0)
        ->and(Storage::disk('local')->files('imports'))->not->toBeEmpty();
});

it('lists skipped rows with reasons in the report', function () {
    Storage::fake('local');

    $this->artisan('data:import-legacy-pr-db', ['--file' => fixturePath()])->assertSuccessful();
    $csv = Storage::disk('local')->get(Storage::disk('local')->files('imports')[0]);

    expect($csv)->toContain('kontrak,1,skipped')
        ->toContain('Duplicate PO; contract ID 2 kept');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php`
Expected: FAIL because command `data:import-legacy-pr-db` does not exist.

- [ ] **Step 3: Implement `ImportReport` and the command's dry-run path**

Register the command by placing it in `app/Console/Commands` (auto-discovered). Require `--file`; validate that the file exists. In dry-run mode, call reader and mapper, write the CSV through `Storage::disk('local')`, print counts of mapped and skipped rows, and return `self::SUCCESS`. Do not call any service and do not write business tables.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportLegacyPrDatabase.php app/Support/Legacy/ImportReport.php tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php
git commit -m "Add dry-run legacy import command and report"
```

---

### Task 4: Apply mode

**Files:**
- Modify: `app/Console/Commands/ImportLegacyPrDatabase.php`
- Modify: `tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php`

**Interfaces:**
- Consumes: `LegacyRowMapper::map()` output and `ImportReport::write()`.
- Produces: `--apply` creates departments, vendors, and PRs through services. `--force` repeats already-reported rows. Every record gets one report line with its new ID and number. In apply mode `--user=<user id>` is required; it names the admin account recorded as creator/updater. For this dump, `contracts` and `lhstps` are empty, so no contracts or LHSTPs are created.

- [ ] **Step 1: Write the failing tests**

```php
it('applies mapped rows through application services', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->artisan('data:import-legacy-pr-db', ['--file' => fixturePath(), '--apply' => true, '--user' => $user->id])
        ->assertSuccessful();

    expect(Vendor::where('company_name', 'PT Kosera Karya Prima')->count())->toBe(1)
        ->and(Department::where('name', 'Accounting')->exists())->toBeTrue()
        ->and(PurchaseRequest::where('pr_number', 'PR-000001')->exists())->toBeTrue()
        ->and(Contract::count())->toBe(0)
        ->and(PurchaseRequest::first()->status)->toBe(PurchaseRequest::STATUS_DRAFT);
});

it('is idempotent', function () {
    Storage::fake('local');
    $user = User::factory()->create();

    foreach (range(1, 2) as $run) {
        $this->actingAs($user)
            ->artisan('data:import-legacy-pr-db', ['--file' => fixturePath(), '--apply' => true, '--user' => $user->id])
            ->assertSuccessful();
    }

    expect(PurchaseRequest::count())->toBe(1)
        ->and(Contract::count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php`
Expected: FAIL because `--apply` currently has no effect.

- [ ] **Step 3: Implement apply mode**

Create missing departments and vendors with Eloquent (they have no dedicated creation service). Call `PurchaseRequestService::create()`, and call `ContractService::create()` or `LhstpService::create()` only when the mapper produced such rows. Load the user named by `--user` with `User::findOrFail()`. Use one database transaction per record; a service validation failure appends an exception row and continues. Before the first write, check that a backup path was supplied via `--backup-confirmed` or abort with a clear message; the operator creates the backup using the documented script. Skip source keys already present in the newest CSV report unless `--force` is set.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportLegacyPrDatabase.php tests/Feature/LegacyImport/ImportLegacyPrDatabaseTest.php
git commit -m "Apply legacy import rows through application services"
```

---

### Task 5: Real dry run and documentation

**Files:**
- Modify: `project_docs/DECISION_LOG.md`
- Modify: `project_docs/ITMS_Administrative_App_Deployment_Guide.md`

**Interfaces:**
- Consumes: the finished command and the real dump at `C:\Users\Administrator\Downloads\pr_db.sql`.

- [ ] **Step 1: Run the real dry run**

Run:

```powershell
php artisan data:import-legacy-pr-db --file="C:\Users\Administrator\Downloads\pr_db.sql"
```

Expected: exit code 0; printed counts match the design inventory; `storage/app/imports/legacy-pr-*.csv` contains every skipped row with a reason; `PurchaseRequest::count()` in `itms_admin_db` is unchanged.

- [ ] **Step 2: Document the operator procedure**

Append a short section to the deployment guide: backup first, dry run, read the CSV, run `--apply --backup-confirmed`, verify counts, keep the CSV as the source-to-target map. State that the source dump is never modified.

- [ ] **Step 3: Run the full gate**

Run: `php artisan test` and `vendor/bin/pint --test`
Expected: all tests pass; Pint passes.

- [ ] **Step 4: Commit**

```bash
git add project_docs/DECISION_LOG.md project_docs/ITMS_Administrative_App_Deployment_Guide.md
git commit -m "Document legacy import procedure and dry-run result"
```

No `--apply` run happens in this plan. Applying the import to `itms_admin_db` needs a separate explicit instruction after the report is reviewed.
