<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Budget;
use App\Models\Contract;
use App\Models\Lhstp;
use App\Models\LhstpVersion;
use App\Models\PurchaseRequest;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    /**
     * Modules shown on the status cards with their lifecycle statuses.
     *
     * @var array<string, array{0: class-string, 1: array<int, string>}>
     */
    private const MODULES = [
        'purchase_request' => [PurchaseRequest::class, PurchaseRequest::STATUSES],
        'budget' => [Budget::class, Budget::STATUSES],
        'contract' => [Contract::class, Contract::STATUSES],
        'lhstp' => [Lhstp::class, Lhstp::STATUSES],
    ];

    public function __construct(private readonly ContractStatusService $contractStatus) {}

    /**
     * @return array<string, array<string, int>>
     */
    public function documentCounts(): array
    {
        $this->contractStatus->syncAll();

        $counts = [];

        foreach (self::MODULES as $key => [$model, $statuses]) {
            $found = $model::query()
                ->select('status', DB::raw('count(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            foreach ($statuses as $status) {
                $counts[$key][$status] = (int) ($found[$status] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * Non archived contracts whose end date falls in the next 30 days.
     *
     * @return Collection<int, Contract>
     */
    public function endingContracts(int $days = 30): Collection
    {
        return Contract::query()
            ->with('vendor')
            ->notArchived()
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays($days))
            ->orderBy('end_date')
            ->get();
    }

    /**
     * Sum the current versions of Final LHSTP records dated inside the period.
     *
     * @return array{count: int, total: string}
     */
    public function paymentSummary(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $query = LhstpVersion::query()
            ->join('lhstps', 'lhstps.current_version_id', '=', 'lhstp_versions.id')
            ->where('lhstps.status', Lhstp::STATUS_FINAL)
            ->whereDate('lhstp_versions.lhstp_date', '>=', $from)
            ->whereDate('lhstp_versions.lhstp_date', '<=', $to);

        return [
            'count' => (int) (clone $query)->count('lhstp_versions.id'),
            'total' => number_format((float) $query->sum('lhstp_versions.total_payment'), 2, '.', ''),
        ];
    }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function recentActivity(int $limit = 8): Collection
    {
        return ActivityLog::query()
            ->with('user')
            ->latest('created_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
