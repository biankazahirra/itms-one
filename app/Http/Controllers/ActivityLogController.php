<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $module = (string) $request->query('module', '');
        $action = (string) $request->query('action', '');
        $dateFrom = (string) $request->query('date_from', '');
        $dateTo = (string) $request->query('date_to', '');

        $logs = ActivityLog::query()
            ->with('user')
            ->when(array_key_exists($module, ActivityLog::ENTITY_LABELS), fn ($query) => $query->where('entity_type', $module))
            ->when(array_key_exists($action, ActivityLog::ACTION_LABELS), fn ($query) => $query->where('action', $action))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest('created_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('activity.index', [
            'logs' => $logs,
            'module' => $module,
            'action' => $action,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'modules' => ActivityLog::ENTITY_LABELS,
            'actions' => ActivityLog::ACTION_LABELS,
        ]);
    }
}
