<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\SalesReport;
use App\Support\LocalTime;
use App\Support\Money;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta los reportes a CSV (UTF-8 con BOM para que Excel muestre bien los acentos).
 * Los montos se escriben en pesos con dos decimales y sin símbolo.
 */
class ReportCsvController extends Controller
{
    private const TYPES = ['ventas', 'platillos', 'metodos', 'tiempos'];

    public function __invoke(Request $request, string $tipo): StreamedResponse
    {
        abort_unless(in_array($tipo, self::TYPES, true), 404);

        $data = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $report = new SalesReport($data['desde'], $data['hasta']);
        $pesos = fn (int $cents) => Money::toPesosString($cents);
        $min = fn (?float $m) => $m === null ? '' : number_format($m, 1, '.', '');

        [$header, $rows] = match ($tipo) {
            'ventas' => [
                ['Fecha', 'Pagos', 'Ventas (MXN)', 'Propinas (MXN)'],
                $report->salesByDay()->map(fn ($d) => [$d['date'], $d['payments'], $pesos($d['sales_cents']), $pesos($d['tips_cents'])]),
            ],
            'platillos' => [
                ['Lugar', 'Platillo', 'Cantidad vendida', 'Ventas (MXN)'],
                $report->topProducts(100)->values()->map(fn ($p, $i) => [$i + 1, $p['name'], $p['quantity'], $pesos($p['sales_cents'])]),
            ],
            'metodos' => [
                ['Método', 'Pagos', 'Ventas (MXN)', 'Propinas (MXN)'],
                $report->byMethod()->map(fn ($m) => [$m['label'], $m['count'], $pesos($m['sales_cents']), $pesos($m['tips_cents'])]),
            ],
            'tiempos' => [
                ['Orden', 'Mesa', 'Fecha', 'Espera (min)', 'Preparación (min)', 'Total (min)'],
                $report->orderTimes()->map(fn ($t) => [
                    $t['order_id'], $t['table'], LocalTime::format($t['created_at'], 'Y-m-d H:i'),
                    $min($t['wait']), $min($t['prep']), $min($t['total']),
                ]),
            ],
        };

        $filename = "reporte-{$tipo}-{$data['desde']}_{$data['hasta']}.csv";

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, $row, escape: '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
