<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\StockRequest;
use App\Services\Stock\LowStockService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(LowStockService $lowStock): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'waiting' => StockRequest::waiting()->count(),
                'to_send' => StockRequest::awaitingDispatch()->count(),
                'in_transit' => StockRequest::inTransit()->count(),
                'low_stock' => $lowStock->lowCountEverywhere(),
            ],

            // Late first, then oldest. The admin works top to bottom.
            'needsAction' => StockRequest::with('fromBranch')
                ->withCount('lines')
                ->waiting()
                ->mostUrgentFirst()
                ->limit(10)
                ->get()
                ->map(fn (StockRequest $request) => RequestPresenter::summary($request)),

            'inTransit' => StockRequest::with('fromBranch')
                ->withCount('lines')
                ->where('status', RequestStatus::Sent)
                ->orderBy('dispatched_at')
                ->limit(5)
                ->get()
                ->map(fn (StockRequest $request) => RequestPresenter::summary($request)),
        ]);
    }
}
