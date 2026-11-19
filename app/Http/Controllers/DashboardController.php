<?php

namespace App\Http\Controllers;

use App\Services\DashboardQueryService;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Resolve the selected payment period: a month or an explicit date range.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string, 3: string, 4: string, 5: string}
     */
    private function period(Request $request): array
    {
        $month = (string) $request->query('month', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $from = $dateFrom !== '' ? Carbon::parse($dateFrom) : null;
        $to = $dateTo !== '' ? Carbon::parse($dateTo) : null;

        if ($from !== null && $to !== null && $from->lte($to)) {
            return [
                $from->startOfDay(),
                $to->endOfDay(),
                Format::tanggal($from).' – '.Format::tanggal($to),
                '',
                $dateFrom,
                $dateTo,
            ];
        }

        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $month = now()->format('Y-m');
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();

        return [
            $start,
            $start->copy()->endOfMonth(),
            $start->locale('id')->translatedFormat('F Y'),
            $month,
            '',
            '',
        ];
    }

    public function __invoke(Request $request, DashboardQueryService $dashboard): View
    {
        [$from, $to, $periodLabel, $month, $dateFrom, $dateTo] = $this->period($request);

        return view('dashboard', [
            'counts' => $dashboard->documentCounts(),
            'endingContracts' => $dashboard->endingContracts(),
            'summary' => $dashboard->paymentSummary($from, $to),
            'activities' => $dashboard->recentActivity(),
            'periodLabel' => $periodLabel,
            'month' => $month,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }
}
