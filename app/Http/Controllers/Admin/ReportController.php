<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Reports/Index', [
            'reports' => $this->reports->definitions(),
        ]);
    }

    public function show(Request $request, string $key): Response
    {
        return Inertia::render('Admin/Reports/Show', [
            'report' => $this->reports->run($key, $this->filters($request)),
            'branches' => Branch::active()
                ->orderByRaw("FIELD(type, 'main', 'sub')")
                ->orderBy('name')
                ->get(['id', 'name']),
            'reports' => $this->reports->definitions(),
            'filters' => $this->filters($request),
            'currency' => setting('currency_symbol'),
        ]);
    }

    /**
     * The same report, as a file. Excel for anyone who wants to pivot it,
     * PDF for anyone who wants to hand it to someone.
     */
    public function export(Request $request, string $key, string $format): BinaryFileResponse|HttpResponse
    {
        $report = $this->reports->run($key, $this->filters($request));

        $filename = Str::slug($report['title'].' '.$report['period']['from'].' to '.$report['period']['to']);

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.report', [
                'report' => $report,
                'business' => setting('business_name'),
                'tagline' => setting('business_tagline'),
                'currency' => setting('currency_symbol'),
            ])
                ->setPaper('a4', 'landscape')
                ->download("{$filename}.pdf");
        }

        return Excel::download(new ReportExport($report), "{$filename}.xlsx");
    }

    /**
     * @return array{from: ?string, to: ?string, branch: ?int}
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->string('from')->value() ?: null,
            'to' => $request->string('to')->value() ?: null,
            'branch' => $request->integer('branch') ?: null,
        ];
    }
}
