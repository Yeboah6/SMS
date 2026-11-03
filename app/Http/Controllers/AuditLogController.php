<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:120'],
        ]);
        $search = trim($filters['search'] ?? '');
        $action = $filters['action'] ?? '';

        $logs = AuditLog::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('summary', 'like', '%'.$search.'%')
                        ->orWhere('actor_name', 'like', '%'.$search.'%')
                        ->orWhere('subject_name', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%');
                });
            })
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'summary' => $log->summary,
                'actorName' => $log->actor_name ?? 'System',
                'subjectName' => $log->subject_name,
                'subjectType' => class_basename($log->subject_type),
                'changedFields' => $log->changed_fields ?? [],
                'occurredAt' => $log->occurred_at->toIso8601String(),
            ]);

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => [
                'search' => $search,
                'action' => $action,
            ],
        ]);
    }
}
