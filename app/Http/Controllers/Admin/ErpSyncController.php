<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Cart\Jobs\DetectAbandonedCartsJob;
use App\Domain\ErpSync\Jobs\SyncClientsB2bJob;
use App\Domain\ErpSync\Jobs\SyncImagesJob;
use App\Domain\ErpSync\Jobs\SyncPoliciesB2bJob;
use App\Domain\ErpSync\Jobs\SyncProductsJob;
use App\Domain\ErpSync\Jobs\SyncTransportistasB2bJob;
use App\Enums\ErpSyncType;
use App\Http\Controllers\Controller;
use App\Models\ErpSyncRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Bitácora de sincronizaciones y botón "Sincronizar ahora" (reemplaza las rutas públicas sync-*). */
class ErpSyncController extends Controller
{
    /** @var array<string, class-string> */
    private const JOBS = [
        'products' => SyncProductsJob::class,
        'images' => SyncImagesJob::class,
        'clients_b2b' => SyncClientsB2bJob::class,
        'policies_b2b' => SyncPoliciesB2bJob::class,
        'transportistas_b2b' => SyncTransportistasB2bJob::class,
        'abandoned_carts' => DetectAbandonedCartsJob::class,
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate(['type' => ['sometimes', 'nullable', 'string', 'max:40']]);

        $runs = ErpSyncRun::query()
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->latest('started_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ErpSyncRun $run): array => [
                'id' => $run->id,
                'type' => $run->type->value,
                'status' => $run->status->value,
                'started_at' => $run->started_at->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
                'fetched_count' => $run->fetched_count,
                'created_count' => $run->created_count,
                'updated_count' => $run->updated_count,
                'unpublished_count' => $run->unpublished_count,
                'error' => $run->error,
                'metadata' => $run->metadata,
            ]);

        return Inertia::render('admin/sync/index', [
            'runs' => $runs,
            'filters' => $filters,
            'types' => array_keys(self::JOBS),
            'enabled' => (bool) config('shineray.erp_sync.enabled'),
            'crons' => [
                'products' => config('shineray.erp_sync.products_cron'),
                'images' => config('shineray.erp_sync.images_cron'),
                'b2b' => config('shineray.erp_sync.b2b_cron'),
            ],
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate(['type' => ['required', 'in:'.implode(',', array_keys(self::JOBS))]]);
        $job = self::JOBS[$validated['type']];

        dispatch(new $job);

        $label = ErpSyncType::tryFrom($validated['type'])->value ?? $validated['type'];
        Inertia::flash('toast', ['type' => 'success', 'message' => "Sincronización '{$label}' encolada."]);

        return to_route('admin.sync.index');
    }
}
