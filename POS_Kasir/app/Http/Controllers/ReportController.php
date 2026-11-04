<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApiResponse;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('owner.reports', [
            'scopeLabel' => $user->scopeLabel(),
            'isAdmin' => $user->isAdmin(),
        ]);
    }


    /**
     * Statistik eksekutif harian untuk dashboard owner.
     * Membantu pengambilan keputusan: tren omset, jam tersibuk,
     * rata-rata transaksi, tingkat batal, stok kritis, top menu.
     */
    public function getDailyStats(Request $request): JsonResponse
    {
        $user       = $request->user();
        $ownerTypes = $user->ownerTypes();
        $today      = now()->toDateString();
        $yesterday  = now()->subDay()->toDateString();

        // --- Helper closure untuk base query ---
        $baseQuery = fn (string $date) => DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('s.payment_status', 'paid')
            ->where('s.order_status', '!=', 'canceled')
            ->whereDate('si.created_at', $date);

        // --- Omset hari ini ---
        $todaySummary = (clone $baseQuery($today))
            ->selectRaw('COUNT(DISTINCT s.id) as total_orders')
            ->selectRaw('SUM(si.quantity) as total_items')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(s.subtotal_amount,0) > 0 THEN si.subtotal * s.total_amount / s.subtotal_amount ELSE si.subtotal END), 0) as net_revenue')
            ->first();

        // --- Omset kemarin (pembanding) ---
        $yesterdaySummary = (clone $baseQuery($yesterday))
            ->selectRaw('COUNT(DISTINCT s.id) as total_orders')
            ->selectRaw('COALESCE(SUM(CASE WHEN COALESCE(s.subtotal_amount,0) > 0 THEN si.subtotal * s.total_amount / s.subtotal_amount ELSE si.subtotal END), 0) as net_revenue')
            ->first();

        // --- Rata-rata nilai transaksi hari ini ---
        $avgOrder = $todaySummary->total_orders > 0
            ? round((float) $todaySummary->net_revenue / (int) $todaySummary->total_orders, 0)
            : 0;

        // --- Persentase perubahan omset vs kemarin ---
        $revenueChange = null;
        if ((float) $yesterdaySummary->net_revenue > 0) {
            $revenueChange = round(
                (((float) $todaySummary->net_revenue - (float) $yesterdaySummary->net_revenue)
                    / (float) $yesterdaySummary->net_revenue) * 100,
                1
            );
        }

        // --- Tingkat pembatalan hari ini ---
        $totalOrdersToday = DB::table('sales as s')
            ->join('sale_items as si', 'si.sale_id', '=', 's.id')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->whereDate('s.created_at', $today)
            ->distinct('s.id')
            ->count('s.id');

        $canceledToday = DB::table('sales as s')
            ->join('sale_items as si', 'si.sale_id', '=', 's.id')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('s.order_status', 'canceled')
            ->whereDate('s.created_at', $today)
            ->distinct('s.id')
            ->count('s.id');

        $cancelRate = $totalOrdersToday > 0
            ? round(($canceledToday / $totalOrdersToday) * 100, 1)
            : 0;

        // --- Jam tersibuk hari ini (berdasarkan jumlah item masuk) ---
        $peakHour = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('s.order_status', '!=', 'canceled')
            ->whereDate('si.created_at', $today)
            ->selectRaw('HOUR(si.created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        // --- Top 5 menu terlaris hari ini ---
        $topMenus = (clone $baseQuery($today))
            ->select('p.name as product_name')
            ->selectRaw('SUM(si.quantity) as qty_sold')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get();

        // --- Stok kritis (≤5 porsi) sesuai kategori owner ---
        $criticalStock = DB::table('products as p')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('p.is_active', 1)
            ->where('p.stock', '<=', 5)
            ->orderBy('p.stock')
            ->limit(5)
            ->get(['p.name', 'p.stock']);

        // --- Transaksi belum lunas hari ini ---
        $unpaidToday = DB::table('sales as s')
            ->join('sale_items as si', 'si.sale_id', '=', 's.id')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('s.payment_status', 'unpaid')
            ->where('s.order_status', '!=', 'canceled')
            ->whereDate('s.created_at', $today)
            ->distinct('s.id')
            ->count('s.id');

        return ApiResponse::success([
            'date'                  => $today,
            'net_revenue'           => (float) $todaySummary->net_revenue,
            'yesterday_revenue'     => (float) $yesterdaySummary->net_revenue,
            'revenue_change_pct'    => $revenueChange,
            'total_orders'          => (int) $todaySummary->total_orders,
            'yesterday_orders'      => (int) $yesterdaySummary->total_orders,
            'total_items'           => (int) $todaySummary->total_items,
            'avg_order_value'       => $avgOrder,
            'cancel_rate'           => $cancelRate,
            'canceled_count'        => $canceledToday,
            'unpaid_today'          => $unpaidToday,
            'peak_hour'             => $peakHour ? (int) $peakHour->hour : null,
            'peak_hour_count'       => $peakHour ? (int) $peakHour->count : 0,
            'top_menus'             => $topMenus,
            'critical_stock'        => $criticalStock,
        ]);
    }


    /**
     * Report rows as JSON for the owner reports table.
     */
    public function getData(Request $request): JsonResponse
    {
        $user = $request->user();
        [$startDate, $endDate] = $this->resolvePeriod($request);

        $rows = $this->queryReportData($user->ownerTypes(), $startDate, $endDate);

        // Existing response
        $response = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'owner_type' => $user->ownerTypes(),
            'scope_label' => $user->scopeLabel(),
            'gross_revenue' => (float) $rows->sum('subtotal'),
            'total_discount' => (float) $rows->sum('discount'),
            'total_revenue' => (float) $rows->sum('net_subtotal'),
            'total_items_sold' => (int) $rows->sum('quantity'),
            'total_orders' => $rows->pluck('invoice_number')->unique()->count(),
            'items' => $rows,
        ];
        return ApiResponse::success($response);
    }


    /**
     * Pareto analysis – top 80% revenue products and low‑selling items.
     */
    public function paretoData(Request $request): JsonResponse
    {
        $user = $request->user();
        [$startDate, $endDate] = $this->resolvePeriod($request);

        $base = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.payment_status', 'paid')
            ->whereBetween('sales.created_at', [$startDate, $endDate]);

        if (!$user->isAdmin()) {
            $base->where('products.owner_type', $user->ownerType);
        }

        $agg = $base->select(
                'products.id',
                'products.name as product',
                'products.owner_type',
                DB::raw('SUM(sale_items.subtotal) as revenue'),
                DB::raw('SUM(sale_items.quantity) as qty')
            )
            ->groupBy('products.id', 'products.name', 'products.owner_type')
            ->orderByDesc('revenue')
            ->get();

        $totalRevenue = $agg->sum('revenue');
        $cumulative = 0;
        $top = [];
        foreach ($agg as $item) {
            $cumulative += $item->revenue;
            $perc = $totalRevenue ? ($cumulative / $totalRevenue) * 100 : 0;
            $top[] = (object)[
                'product' => $item->product,
                'revenue' => $item->revenue,
                'qty' => $item->qty,
                'cumulative_perc' => round($perc, 2),
            ];
            if ($perc >= 80) {
                break;
            }
        }

        // Low selling products – smallest qty, limit 5
        $low = $agg->sortBy('qty')->take(5)->values();

        return ApiResponse::success([
            'total_revenue' => $totalRevenue,
            'top_products' => $top,
            'low_products' => $low,
        ]);
    }


    /**
     * Stream the report as an .xlsx attachment.
     */
    public function exportExcel(Request $request): Response
    {
        $user = $request->user();
        [$startDate, $endDate] = $this->resolvePeriod($request);

        $rows = $this->queryReportData($user->ownerTypes(), $startDate, $endDate);
        $fileScope = $this->fileScope($user);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Laporan Penjualan');

        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'LAPORAN PENJUALAN - '.$fileScope);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', "Periode: {$startDate} s/d {$endDate}");
        $sheet->getStyle('A2')->getFont()->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = ['No', 'No. Invoice', 'Tanggal & Waktu', 'Nama Pelanggan', 'Nama Produk', 'Jumlah', 'Harga Satuan (Rp)', 'Subtotal (Rp)', 'Diskon (Rp)', 'Netto (Rp)'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col.'4', $header);
            $sheet->getStyle($col.'4')->getFont()->setBold(true);
            $sheet->getStyle($col.'4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2563EB');
            $sheet->getStyle($col.'4')->getFont()->getColor()->setRGB('FFFFFF');
            $sheet->getStyle($col.'4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        $rowNum = 5;
        $no = 1;
        $grandTotal = 0;

        foreach ($rows as $item) {
            $sheet->setCellValue("A{$rowNum}", $no++);
            $sheet->setCellValue("B{$rowNum}", $item->invoice_number);
            $sheet->setCellValue("C{$rowNum}", $item->created_at);
            $sheet->setCellValue("D{$rowNum}", $item->customer_name);
            $sheet->setCellValue("E{$rowNum}", $item->product_name);
            $sheet->setCellValue("F{$rowNum}", $item->quantity);
            $sheet->setCellValue("G{$rowNum}", $item->unit_price);
            $sheet->setCellValue("H{$rowNum}", $item->subtotal);
            $sheet->setCellValue("I{$rowNum}", $item->discount);
            $sheet->setCellValue("J{$rowNum}", $item->net_subtotal);

            $sheet->getStyle("F{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("G{$rowNum}:J{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');

            $grandTotal += (float) $item->net_subtotal;
            $rowNum++;
        }

        $sheet->mergeCells("A{$rowNum}:G{$rowNum}");
        $sheet->setCellValue("A{$rowNum}", 'TOTAL PENDAPATAN (OMSET BERSIH)');
        $sheet->setCellValue("H{$rowNum}", (float) $rows->sum('subtotal'));
        $sheet->setCellValue("I{$rowNum}", (float) $rows->sum('discount'));
        $sheet->setCellValue("J{$rowNum}", $grandTotal);
        $sheet->getStyle("A{$rowNum}:J{$rowNum}")->getFont()->setBold(true);
        $sheet->getStyle("H{$rowNum}:J{$rowNum}")->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'J') as $colId) {
            $sheet->getColumnDimension($colId)->setAutoSize(true);
        }

        $filename = "Laporan_{$fileScope}_{$startDate}_{$endDate}.xlsx";

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');
        $contents = (string) ob_get_clean();

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }


    /**
     * Return sales trend data for charts (daily or weekly).
     */
    public function trendData(Request $request): JsonResponse
    {
        $user = $request->user();
        [$startDate, $endDate] = $this->resolvePeriod($request);
        $period = $request->query('period', 'daily'); // daily or weekly

        $base = DB::table('sales')
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.payment_status', 'paid')
            ->whereBetween('sales.created_at', [$startDate, $endDate]);

        if (!$user->isAdmin()) {
            $base->where('products.owner_type', $user->ownerType);
        }

        // Daily totals
        if ($period === 'daily') {
            $totals = $base->clone()
                ->select(DB::raw('DATE(sales.created_at) as label'), DB::raw('SUM(sales.total_amount) as value'))
                ->groupBy('label')
                ->orderBy('label')
                ->get();
        } else { // weekly
            $totals = $base->clone()
                ->select(DB::raw('YEARWEEK(sales.created_at) as label'), DB::raw('SUM(sales.total_amount) as value'))
                ->groupBy('label')
                ->orderBy('label')
                ->get();
        }

        // Top selling products (by quantity) within period
        $topProducts = $base->clone()
            ->select('products.name as product', DB::raw('SUM(sale_items.quantity) as sold'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('sold')
            ->limit(5)
            ->get();

        return ApiResponse::success([
            'labels' => $totals->pluck('label'),
            'values' => $totals->pluck('value'),
            'top_products' => $topProducts,
        ]);
    }


    /**
     * Stream the report as a PDF attachment.
     */
    public function exportPdf(Request $request): Response
    {
        $user = $request->user();
        [$startDate, $endDate] = $this->resolvePeriod($request);

        $rows = $this->queryReportData($user->ownerTypes(), $startDate, $endDate);
        $fileScope = $this->fileScope($user);

        $options = new Options;
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.pdf', [
            'rows' => $rows,
            'scopeLabel' => $user->scopeLabel(),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'grossRevenue' => (float) $rows->sum('subtotal'),
            'totalDiscount' => (float) $rows->sum('discount'),
            'totalRevenue' => (float) $rows->sum('net_subtotal'),
            'totalItems' => (int) $rows->sum('quantity'),
        ])->render());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = "Laporan_{$fileScope}_{$startDate}_{$endDate}.pdf";

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }


    /**
     * @return array{0: string, 1: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return [
            $data['start_date'] ?? now()->format('Y-m-01'),
            $data['end_date'] ?? now()->format('Y-m-d'),
        ];
    }


    /**
     * Short scope token used in exported file names and titles.
     */
    private function fileScope(User $user): string
    {
        return $user->isAdmin() ? 'semua-kategori' : $user->ownerType;
    }


    /**
     * Paid order items for the given owner types within a date range.
     *
     * @param  list<string>  $ownerTypes
     * @return Collection<int, object>
     */
    private function queryReportData(array $ownerTypes, string $startDate, string $endDate): Collection
    {
        $rows = DB::table('sale_items as si')
            ->join('products as p', 'si.product_id', '=', 'p.id')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->join('sales as s', 'si.sale_id', '=', 's.id')
            ->whereIn('c.owner_type', $ownerTypes)
            ->where('s.payment_status', 'paid')
            ->where('s.order_status', '!=', 'canceled')
            ->whereBetween(DB::raw('DATE(si.created_at)'), [$startDate, $endDate])
            ->orderByDesc('si.created_at')
            ->select([
                'si.id as item_id',
                'si.quantity',
                'si.unit_price',
                'si.subtotal',
                'si.item_status',
                'si.created_at',
                'p.name as product_name',
                'c.name as category_name',
                'c.owner_type',
                's.invoice_number',
                's.customer_name',
                's.service_type',
                's.payment_method',
                's.payment_status',
                's.promo_code',
            ])
            ->selectRaw('CASE WHEN COALESCE(s.subtotal_amount, 0) > 0 THEN si.subtotal * s.total_amount / s.subtotal_amount ELSE si.subtotal END AS net_subtotal')
            ->get();

        // Diskon dicatat per transaksi; tiap item menanggung bagian sesuai porsi harganya.
        return $rows->map(function ($row) {
            $row->subtotal = (float) $row->subtotal;
            $row->net_subtotal = round((float) $row->net_subtotal, 2);
            $row->discount = round($row->subtotal - $row->net_subtotal, 2);

            return $row;
        });
    }
}
