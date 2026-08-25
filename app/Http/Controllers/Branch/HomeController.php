<?php

namespace App\Http\Controllers\Branch;

use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestPresenter;
use App\Models\StockRequest;
use App\Services\CutoffService;
use App\Services\Stock\LowStockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The branch home screen answers three questions without any tapping:
 * where is today's request, how long until the cut-off, and what is running out.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, CutoffService $cutoff, LowStockService $lowStock): Response
    {
        $user = $request->user();
        $branch = $user->branch;

        $latest = StockRequest::with(['lines'])
            ->withCount('lines')
            ->where('from_branch_id', $branch->id)
            ->whereNotIn('status', [RequestStatus::Cancelled])
            ->latest('submitted_at')
            ->first();

        $lowStockRows = $lowStock->itemsForBranch($branch->id);

        return Inertia::render('Branch/Home', [
            'greeting' => $this->greeting(),

            // A laptop has room to show the whole picture at once.
            'stats' => [
                'waiting' => StockRequest::where('from_branch_id', $branch->id)
                    ->where('status', RequestStatus::Waiting)->count(),
                'on_the_way' => StockRequest::where('from_branch_id', $branch->id)
                    ->where('status', RequestStatus::Sent)->count(),
                'running_low' => $lowStockRows->where('is_low', true)->count(),
                'on_shelf' => $lowStockRows->filter(fn (array $row) => $row['on_hand'] > 0)->count(),
            ],
            'latest' => $latest ? RequestPresenter::summary($latest) : null,
            'cutoff' => $cutoff->countdown($branch),
            'runningLow' => $lowStockRows->where('is_low', true)
                ->sortBy('fill_ratio')
                ->take(8)
                ->values(),
            'toReceive' => StockRequest::where('from_branch_id', $branch->id)
                ->where('status', RequestStatus::Sent)
                ->count(),
        ]);
    }

    /** Kitchens run early and late, so the greeting should match the shift. */
    private function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
