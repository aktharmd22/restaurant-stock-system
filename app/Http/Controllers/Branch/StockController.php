<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Stock\StockViewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "What have we actually got?" - the branch view, without the reserved column,
 * which only means anything at the main store.
 */
class StockController extends Controller
{
    public function index(Request $request, StockViewService $stock): Response
    {
        $branch = $request->user()->branch;

        $rows = $stock->forBranch(
            $branch->id,
            $request->string('search')->trim()->value() ?: null,
            $request->integer('category') ?: null,
        );

        return Inertia::render('Branch/Stock', [
            'rows' => $rows,
            'categories' => Category::active()->ordered()->get(['id', 'name']),
            'lowCount' => $rows->where('is_low', true)->count(),
            'filters' => [
                'search' => $request->string('search')->value(),
                'category' => $request->integer('category') ?: null,
            ],
        ]);
    }
}
