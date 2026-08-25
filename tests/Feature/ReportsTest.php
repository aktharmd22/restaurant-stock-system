<?php

use App\Enums\RoleName;
use App\Enums\WastageReason;
use App\Services\Reports\ReportService;
use App\Services\Requests\RequestWorkflowService;
use App\Services\Stock\StockOperationsService;
use App\Support\Quantity;

beforeEach(function () {
    seedRoles();

    $this->reports = app(ReportService::class);
    $this->main = mainBranch();
    $this->park = subBranch('PARK');

    $this->onion = kgItem('Onion');
    giveStock($this->main, $this->onion, 100, 0.02);

    $this->staff = userWithRole(RoleName::BranchStaff, $this->park);
    $this->admin = userWithRole(RoleName::MainAdmin, $this->main);
});

it('runs every report without falling over', function () {
    foreach ($this->reports->definitions() as $definition) {
        $report = $this->reports->run($definition['key']);

        expect($report)->toHaveKeys(['title', 'columns', 'rows', 'totals', 'period']);
    }
});

it('covers this month unless told otherwise', function () {
    $report = $this->reports->run('consumption');

    expect($report['period']['from'])->toBe(now()->startOfMonth()->toDateString())
        ->and($report['period']['to'])->toBe(now()->toDateString());
});

it('shows the gap between what was asked for and what arrived', function () {
    $workflow = app(RequestWorkflowService::class);

    $request = $workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $line = $request->lines->first();

    $workflow->approve($request, $this->admin, [$line->id => ['qty' => 8, 'reason_code' => 'out_of_stock']]);
    $workflow->dispatch($request->fresh(), $this->admin, [$line->id => 8]);
    $workflow->receive($request->fresh(), $this->staff, [$line->id => ['qty' => 6, 'reason' => 'damaged']]);

    $row = collect($this->reports->run('request_variance')['rows'])->firstWhere('name', 'Onion');

    expect($row['asked'])->toBe('10 kg')
        ->and($row['approved'])->toBe('8 kg')
        ->and($row['sent'])->toBe('8 kg')
        ->and($row['arrived'])->toBe('6 kg')
        ->and($row['shortfall'])->toBe('4 kg')
        ->and($row['filled'])->toBe('60%');
});

it('values what a branch received at what the main store paid', function () {
    $workflow = app(RequestWorkflowService::class);

    $request = $workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $workflow->approveAll($request, $this->admin);
    $workflow->dispatch($request->fresh(), $this->admin);
    $workflow->receive($request->fresh(), $this->staff);

    $row = collect($this->reports->run('cost_per_branch')['rows'])
        ->firstWhere('branch', 'PARK Branch');

    // 10 kg at 2 paise a gram is 200 rupees.
    expect($row['transferred'])->toBe(200.0);
});

it('shows waste as a share of what actually arrived', function () {
    $workflow = app(RequestWorkflowService::class);
    $operations = app(StockOperationsService::class);

    $request = $workflow->submit($this->park, $this->staff, [['item_id' => $this->onion->id, 'qty' => 10]]);
    $workflow->approveAll($request, $this->admin);
    $workflow->dispatch($request->fresh(), $this->admin);
    $workflow->receive($request->fresh(), $this->staff);

    $operations->recordWastage(
        $this->park,
        Quantity::fromOrderUnit(1, $this->onion),
        WastageReason::Spoiled,
        $this->staff,
    );

    $report = $this->reports->run('wastage');

    expect($report['rows'])->toHaveCount(1)
        ->and($report['totals']['Waste as share of what arrived'])->toBe('10%');
});

it('shows what prices have been doing', function () {
    giveStock($this->main, $this->onion, 50, 0.03);

    $row = collect($this->reports->run('price_trend')['rows'])->firstWhere('name', 'Onion');

    // Per kilo, from per-gram costs of 0.02 and 0.03.
    expect($row['lowest'])->toBe(20.0)
        ->and($row['highest'])->toBe(30.0)
        ->and($row['swing'])->toBe('50%');
});

/*
|--------------------------------------------------------------------------
| Getting the numbers out of the building
|--------------------------------------------------------------------------
*/

it('saves a report as a spreadsheet', function () {
    $response = $this->actingAs($this->admin)->get('/admin/reports/stock_on_hand/export/xlsx');

    $response->assertOk();

    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('saves a report as a PDF', function () {
    $response = $this->actingAs($this->admin)->get('/admin/reports/request_variance/export/pdf');

    $response->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('refuses a made-up file type', function () {
    $this->actingAs($this->admin)->get('/admin/reports/stock_on_hand/export/exe')->assertNotFound();
});

it('opens a report screen with its filters', function () {
    $this->actingAs($this->admin)
        ->get('/admin/reports/consumption?from=2026-08-01&to=2026-08-20')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Show')
            ->where('report.period.from', '2026-08-01')
            ->where('report.period.to', '2026-08-20'));
});

it('keeps branch people out of the reports', function () {
    $this->actingAs($this->staff)->get('/admin/reports')->assertForbidden();
    $this->actingAs($this->staff)->get('/admin/reports/cost_per_branch/export/xlsx')->assertForbidden();
});
