<?php

use App\Models\RecycleBinItem;
use App\Models\User;
use App\Services\RecycleBinService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('recycle_test_records', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->softDeletes();
        $table->timestamps();
    });

    config(['recycle_bin.types.widget' => RecycleTestRecord::class]);
    RecycleTestRecord::$purgeable = true;

    $this->user = User::factory()->create();
    $this->service = app(RecycleBinService::class);
});

it('soft deletes an entity and registers a 30 day recycle bin entry', function () {
    $record = RecycleTestRecord::create(['name' => 'Draft dokumen']);

    $item = $this->service->register('widget', $record, $this->user);

    expect($record->fresh()->trashed())->toBeTrue()
        ->and($item->entity_type)->toBe('widget')
        ->and($item->entity_id)->toBe($record->id)
        ->and($item->deleted_by)->toBe($this->user->id)
        ->and((int) abs($item->deleted_at->diffInDays($item->expires_at)))->toBe(30);

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'widget',
        'entity_id' => $record->id,
        'action' => 'deleted',
    ]);
});

it('restores an entity and removes its recycle bin entry', function () {
    $record = RecycleTestRecord::create(['name' => 'Draft dokumen']);
    $item = $this->service->register('widget', $record, $this->user);

    $this->service->restore($item);

    expect($record->fresh()->trashed())->toBeFalse()
        ->and(RecycleBinItem::count())->toBe(0);

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'widget',
        'entity_id' => $record->id,
        'action' => 'restored',
    ]);
});

it('purges expired entries when no protection applies', function () {
    $record = RecycleTestRecord::create(['name' => 'Draft dokumen']);
    $item = $this->service->register('widget', $record, $this->user);
    $item->forceFill(['expires_at' => now()->subDay()])->save();

    $result = $this->service->purgeExpired();

    expect($result)->toBe(['purged' => 1, 'blocked' => 0])
        ->and(RecycleTestRecord::withTrashed()->find($record->id))->toBeNull()
        ->and(RecycleBinItem::count())->toBe(0);
});

it('keeps expired entries when the entity is protected', function () {
    RecycleTestRecord::$purgeable = false;

    $record = RecycleTestRecord::create(['name' => 'Dokumen final']);
    $item = $this->service->register('widget', $record, $this->user);
    $item->forceFill(['expires_at' => now()->subDay()])->save();

    $result = $this->service->purgeExpired();

    expect($result)->toBe(['purged' => 0, 'blocked' => 1])
        ->and(RecycleBinItem::count())->toBe(1);

    $this->assertDatabaseHas('activity_logs', [
        'entity_type' => 'widget',
        'entity_id' => $record->id,
        'action' => 'purge_blocked',
    ]);
});

it('does not purge unknown entity types', function () {
    $item = RecycleBinItem::create([
        'entity_type' => 'unknown_module',
        'entity_id' => 99,
        'deleted_by' => $this->user->id,
        'deleted_at' => now()->subDays(31),
        'expires_at' => now()->subDay(),
    ]);

    $result = $this->service->purgeExpired();

    expect($result)->toBe(['purged' => 0, 'blocked' => 1])
        ->and(RecycleBinItem::find($item->id))->not->toBeNull();
});

it('keeps unexpired entries in the recycle bin', function () {
    $record = RecycleTestRecord::create(['name' => 'Draft dokumen']);
    $this->service->register('widget', $record, $this->user);

    $result = $this->service->purgeExpired();

    expect($result)->toBe(['purged' => 0, 'blocked' => 0])
        ->and(RecycleBinItem::count())->toBe(1);
});

it('refuses to register entities that cannot be soft deleted', function () {
    expect(fn () => $this->service->register('user', $this->user, $this->user))
        ->toThrow(LogicException::class);
});

class RecycleTestRecord extends Model
{
    use SoftDeletes;

    public static bool $purgeable = true;

    protected $table = 'recycle_test_records';

    protected $guarded = [];

    public function canBePurged(): bool
    {
        return self::$purgeable;
    }
}
