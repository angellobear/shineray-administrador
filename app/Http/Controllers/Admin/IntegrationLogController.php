<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Integration;
use App\Http\Controllers\Controller;
use App\Models\IntegrationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'integration' => ['sometimes', 'nullable', 'string', 'max:30'],
            'event' => ['sometimes', 'nullable', 'string', 'max:60'],
            'level' => ['sometimes', 'nullable', 'in:info,error'],
        ]);

        $logs = IntegrationLog::query()
            ->when($filters['integration'] ?? null, fn ($query, string $integration) => $query->where('integration', $integration))
            ->when($filters['event'] ?? null, fn ($query, string $event) => $query->where('event', 'like', "%{$event}%"))
            ->when($filters['level'] ?? null, fn ($query, string $level) => $query->where('level', $level))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (IntegrationLog $log): array => [
                'id' => $log->id,
                'integration' => $log->integration->value,
                'event' => $log->event,
                'level' => $log->level,
                'loggable_type' => $log->loggable_type === null ? null : class_basename($log->loggable_type),
                'loggable_id' => $log->loggable_id,
                'payload' => $log->payload,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/logs/index', [
            'logs' => $logs,
            'filters' => $filters,
            'integrations' => array_map(fn (Integration $i): string => $i->value, Integration::cases()),
        ]);
    }
}
