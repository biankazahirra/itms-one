<?php

namespace App\Services;

use App\Models\RecycleBinItem;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class RecycleBinService
{
    /**
     * Retention period before an expired entry may be purged.
     */
    public const RETENTION_DAYS = 30;

    public function __construct(private readonly ActivityLogService $activity) {}

    /**
     * Move a soft-deletable entity into the recycle bin.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function register(string $entityType, Model $entity, User $actor, ?array $metadata = null): RecycleBinItem
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($entity), true)) {
            throw new LogicException('Recycle bin entities must use SoftDeletes.');
        }

        return DB::transaction(function () use ($entityType, $entity, $actor, $metadata): RecycleBinItem {
            $entity->delete();

            $item = RecycleBinItem::create([
                'entity_type' => $entityType,
                'entity_id' => $entity->getKey(),
                'deleted_by' => $actor->id,
                'deleted_at' => now(),
                'expires_at' => now()->addDays(self::RETENTION_DAYS),
                'restore_metadata' => $metadata,
            ]);

            $this->activity->record(
                $entityType,
                (int) $entity->getKey(),
                'deleted',
                'Memindahkan data ke Recycle Bin.',
                null,
                null,
                $actor,
            );

            return $item;
        });
    }

    /**
     * Restore a recycle bin entry and remove it from the bin.
     */
    public function restore(RecycleBinItem $item): Model
    {
        $modelClass = $this->resolveModel($item->entity_type);

        return DB::transaction(function () use ($item, $modelClass): Model {
            /** @var Model $entity */
            $entity = $modelClass::withTrashed()->findOrFail($item->entity_id);

            if (method_exists($entity, 'trashed') && $entity->trashed()) {
                $entity->restore();
            }

            $actor = User::find($item->deleted_by);

            $this->activity->record(
                $item->entity_type,
                (int) $entity->getKey(),
                'restored',
                'Memulihkan data dari Recycle Bin.',
                null,
                null,
                $actor,
            );

            $item->delete();

            return $entity;
        });
    }

    /**
     * Purge expired entries daily. Entries whose dependencies or lifecycle
     * state make deletion unsafe are kept and the reason is logged.
     *
     * @return array{purged: int, blocked: int}
     */
    public function purgeExpired(?DateTimeInterface $now = null): array
    {
        $result = ['purged' => 0, 'blocked' => 0];

        $items = RecycleBinItem::query()
            ->where('expires_at', '<=', $now ?? now())
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $reason = $this->purge($item);

            if ($reason === null) {
                $result['purged']++;
            } else {
                $result['blocked']++;
            }
        }

        return $result;
    }

    /**
     * @return string|null Null when purged, otherwise the blocking reason.
     */
    private function purge(RecycleBinItem $item): ?string
    {
        try {
            $modelClass = $this->resolveModel($item->entity_type);
        } catch (InvalidArgumentException) {
            $this->logBlocked($item, 'Tipe data tidak dikenali.');

            return 'Tipe data tidak dikenali.';
        }

        /** @var Model|null $entity */
        $entity = $modelClass::withTrashed()->find($item->entity_id);

        if ($entity === null) {
            $item->delete();

            return null;
        }

        if (method_exists($entity, 'canBePurged') && ! $entity->canBePurged()) {
            $reason = 'Data masih memiliki riwayat atau dependensi yang dilindungi.';
            $this->logBlocked($item, $reason);

            return $reason;
        }

        DB::transaction(function () use ($item, $entity): void {
            $entity->forceDelete();
            $item->delete();
        });

        return null;
    }

    private function logBlocked(RecycleBinItem $item, string $reason): void
    {
        $this->activity->record(
            $item->entity_type,
            (int) $item->entity_id,
            'purge_blocked',
            $reason,
            null,
            null,
            User::find($item->deleted_by),
        );
    }

    /**
     * @return class-string<Model>
     */
    private function resolveModel(string $entityType): string
    {
        $modelClass = config('recycle_bin.types.'.$entityType);

        if (! is_string($modelClass) || ! is_a($modelClass, Model::class, true)) {
            throw new InvalidArgumentException("Unknown recycle bin entity type [{$entityType}].");
        }

        return $modelClass;
    }
}
