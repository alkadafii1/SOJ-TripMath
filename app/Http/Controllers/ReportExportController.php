<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    /**
     * Unduh laporan sebagai CSV. Varian "excel" memakai titik koma sebagai pemisah supaya terbuka
     * rapi di Excel berlokal Indonesia; keduanya diawali BOM agar huruf UTF-8 terbaca benar.
     */
    public function __invoke(Request $request, string $report, ReportService $reports): StreamedResponse
    {
        abort_unless(Gate::allows('report.export') && Gate::allows('view-finance'), 403);
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);

        [$period] = ReportPeriod::resolve(
            (string) $request->query('period', 'month'),
            $request->query('from'),
            $request->query('to'),
        );

        $delimiter = $request->query('format') === 'excel' ? ';' : ',';
        $rows = $reports->csvRows($reports->report($report, $period));
        $filename = sprintf('%s_%s-%s.csv', $report, $period->from->format('Ymd'), $period->to->format('Ymd'));

        return response()->streamDownload(function () use ($rows, $delimiter) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row, $delimiter);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
