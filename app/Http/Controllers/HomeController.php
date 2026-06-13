<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Charts\CommonChart;
use App\Currency;
use App\Media;
use App\Product;
use App\Transaction;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use App\Utils\RestaurantUtil;
use App\Utils\TransactionUtil;
use App\Utils\ProductUtil;
use App\Utils\Util;
use App\VariationLocationDetails;
use Datatables;
use DB;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    protected $commonUtil;

    protected $restUtil;
    protected $productUtil;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        BusinessUtil $businessUtil,
        TransactionUtil $transactionUtil,
        ModuleUtil $moduleUtil,
        Util $commonUtil,
        RestaurantUtil $restUtil,
        ProductUtil $productUtil,
    ) {
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->commonUtil = $commonUtil;
        $this->restUtil = $restUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = auth()->user();
        if ($user->user_type == 'user_customer') {
            return redirect()->action([\Modules\Crm\Http\Controllers\DashboardController::class, 'index']);
        }

        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! auth()->user()->can('dashboard.data')) {
            return view('home.index');
        }

        $fy = $this->businessUtil->getCurrentFinancialYear($business_id);

        $currency = Currency::where('id', request()->session()->get('business.currency_id'))->first();
        //ensure start date starts from at least 30 days before to get sells last 30 days
        $least_30_days = \Carbon::parse($fy['start'])->subDays(30)->format('Y-m-d');

        //get all sells
        $sells_this_fy = $this->transactionUtil->getSellsCurrentFy($business_id, $least_30_days, $fy['end']);

        $all_locations = BusinessLocation::forDropdown($business_id)->toArray();

        //Chart for sells last 30 days
        $labels = [];
        $all_sell_values = [];
        $dates = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = \Carbon::now()->subDays($i)->format('Y-m-d');
            $dates[] = $date;

            $labels[] = date('j M Y', strtotime($date));

            $total_sell_on_date = $sells_this_fy->where('date', $date)->sum('total_sells');

            if (! empty($total_sell_on_date)) {
                $all_sell_values[] = (float) $total_sell_on_date;
            } else {
                $all_sell_values[] = 0;
            }
        }

        //Group sells by location
        $location_sells = [];
        foreach ($all_locations as $loc_id => $loc_name) {
            $values = [];
            foreach ($dates as $date) {
                $total_sell_on_date_location = $sells_this_fy->where('date', $date)->where('location_id', $loc_id)->sum('total_sells');

                if (! empty($total_sell_on_date_location)) {
                    $values[] = (float) $total_sell_on_date_location;
                } else {
                    $values[] = 0;
                }
            }
            $location_sells[$loc_id]['loc_label'] = $loc_name;
            $location_sells[$loc_id]['values'] = $values;
        }

        $sells_chart_1 = new CommonChart;

        $sells_chart_1->labels($labels)
                        ->options($this->__chartOptions(__(
                            'home.total_sells',
                            ['currency' => $currency->code]
                            )));

        if (! empty($location_sells)) {
            foreach ($location_sells as $location_sell) {
                $sells_chart_1->dataset($location_sell['loc_label'], 'line', $location_sell['values']);
            }
        }

        if (count($all_locations) > 1) {
            $sells_chart_1->dataset(__('report.all_locations'), 'line', $all_sell_values);
        }

        $labels = [];
        $values = [];
        $date = strtotime($fy['start']);
        $last = date('m-Y', strtotime($fy['end']));
        $fy_months = [];
        do {
            $month_year = date('m-Y', $date);
            $fy_months[] = $month_year;

            $labels[] = \Carbon::createFromFormat('m-Y', $month_year)
                            ->format('M-Y');
            $date = strtotime('+1 month', $date);

            $total_sell_in_month_year = $sells_this_fy->where('yearmonth', $month_year)->sum('total_sells');

            if (! empty($total_sell_in_month_year)) {
                $values[] = (float) $total_sell_in_month_year;
            } else {
                $values[] = 0;
            }
        } while ($month_year != $last);

        $fy_sells_by_location_data = [];

        foreach ($all_locations as $loc_id => $loc_name) {
            $values_data = [];
            foreach ($fy_months as $month) {
                $total_sell_in_month_year_location = $sells_this_fy->where('yearmonth', $month)->where('location_id', $loc_id)->sum('total_sells');

                if (! empty($total_sell_in_month_year_location)) {
                    $values_data[] = (float) $total_sell_in_month_year_location;
                } else {
                    $values_data[] = 0;
                }
            }
            $fy_sells_by_location_data[$loc_id]['loc_label'] = $loc_name;
            $fy_sells_by_location_data[$loc_id]['values'] = $values_data;
        }

        $sells_chart_2 = new CommonChart;
        $sells_chart_2->labels($labels)
                    ->options($this->__chartOptions(__(
                        'home.total_sells',
                        ['currency' => $currency->code]
                            )));
        if (! empty($fy_sells_by_location_data)) {
            foreach ($fy_sells_by_location_data as $location_sell) {
                $sells_chart_2->dataset($location_sell['loc_label'], 'line', $location_sell['values']);
            }
        }
        if (count($all_locations) > 1) {
            $sells_chart_2->dataset(__('report.all_locations'), 'line', $values);
        }

        //Get Dashboard widgets from module
        $module_widgets = $this->moduleUtil->getModuleData('dashboard_widget');

        $widgets = [];

        foreach ($module_widgets as $widget_array) {
            if (! empty($widget_array['position'])) {
                $widgets[$widget_array['position']][] = $widget_array['widget'];
            }
        }

        $common_settings = ! empty(session('business.common_settings')) ? session('business.common_settings') : [];


        return view('home.index', compact('sells_chart_1', 'sells_chart_2', 'widgets', 'all_locations', 'common_settings', 'is_admin'));
    }

    /**
     * Dashboard V2 (sales-focused).
     *
     * @return \Illuminate\Http\Response
     */
    public function dashboardV2(Request $request)
    {
        if (!auth()->user()->can('dashboard.data')) {
            abort(403, 'Unauthorized action.');
        }

        $view_data = $this->buildDashboardV2Data($request, true);

        return view('dashboard_v2', $view_data);
    }

    /**
     * Export Dashboard V2 data as CSVs (zipped).
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function dashboardV2Export(Request $request)
    {
        if (!auth()->user()->can('dashboard.data')) {
            abort(403, 'Unauthorized action.');
        }

        $data = $this->buildDashboardV2Data($request, false);

        $filename = 'dashboard_v2_' . $data['range_start']->format('Y-m-d') . '_to_' . $data['range_end']->format('Y-m-d') . '.csv';
        $csvContent = $this->buildDashboardV2SingleCsv($data);

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function dashboardV2AiSuggestions(Request $request)
    {
        if (!auth()->user()->can('dashboard.data')) {
            abort(403, 'Unauthorized action.');
        }

        Log::info('Dashboard V2 AI suggestions requested', [
            'user_id' => $request->session()->get('user.id'),
            'business_id' => $request->session()->get('user.business_id'),
            'period' => $request->get('period'),
            'year' => $request->get('year'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ]);

        $cacheMinutes = (int) config('services.openai.cache_minutes', 60);
        $cacheKey = $this->buildDashboardV2AiCacheKey($request, 'suggestions');

        if ($cacheMinutes > 0) {
            $cached = Cache::get($cacheKey);
            if (!empty($cached)) {
                $cached['cached'] = true;
                return response()->json($cached);
            }
        }

        $summary = $this->buildDashboardV2AiSummary($request);
        Log::info('Dashboard V2 AI summary prepared', [
            'period' => $summary['period'] ?? null,
            'range_start' => $summary['range_start'] ?? null,
            'range_end' => $summary['range_end'] ?? null,
            'top_products_count' => isset($summary['top_products']) ? count($summary['top_products']) : 0,
            'category_count' => isset($summary['category_breakdown']) ? count($summary['category_breakdown']) : 0,
        ]);
        $input = $this->buildDashboardV2AiSuggestionsInput($summary);
        $result = $this->callOpenAi($input, null, 2500, $this->getDashboardV2AiSuggestionsFormat());

        if (!$result['success']) {
            Log::error('Dashboard V2 AI suggestions failed', [
                'message' => $result['message'] ?? 'unknown',
            ]);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'AI request failed.',
            ], 500);
        }

        $parsed = $this->parseDashboardV2AiJson($result['text'] ?? '');
        if (empty($parsed)) {
            Log::warning('Dashboard V2 AI suggestions returned non-JSON', [
                'text' => $result['text'] ?? '',
            ]);
            $parsed = [
                'title_en' => 'AI Suggestions',
                'title_th' => 'คำแนะนำ AI',
                'bullets_en' => [$result['text'] ?? 'No response'],
                'bullets_th' => [],
                'risks_en' => [],
                'risks_th' => [],
                'confidence' => 'low',
                'assumptions_en' => [],
                'assumptions_th' => [],
            ];
        }

        $payload = [
            'success' => true,
            'data' => $this->normalizeDashboardV2AiData($parsed),
            'response_id' => $result['response_id'] ?? null,
            'cached' => false,
        ];

        if ($cacheMinutes > 0) {
            Cache::put($cacheKey, $payload, now()->addMinutes($cacheMinutes));
        }

        return response()->json($payload);
    }

    public function dashboardV2AiChat(Request $request)
    {
        if (!auth()->user()->can('dashboard.data')) {
            abort(403, 'Unauthorized action.');
        }

        $message = trim((string) $request->input('message', ''));
        if ($message === '') {
            Log::warning('Dashboard V2 AI chat missing message', [
                'user_id' => $request->session()->get('user.id'),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Message is required.',
            ], 422);
        }

        Log::info('Dashboard V2 AI chat requested', [
            'user_id' => $request->session()->get('user.id'),
            'business_id' => $request->session()->get('user.business_id'),
            'period' => $request->get('period'),
            'year' => $request->get('year'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ]);

        $historyInput = $request->input('history', []);
        if (!is_array($historyInput)) {
            $historyInput = [];
        }

        $summary = $this->buildDashboardV2AiSummary($request);
        Log::info('Dashboard V2 AI chat summary prepared', [
            'period' => $summary['period'] ?? null,
            'range_start' => $summary['range_start'] ?? null,
            'range_end' => $summary['range_end'] ?? null,
        ]);
        $input = $this->buildDashboardV2AiChatInput($summary, $message, $historyInput);
        $previousResponseId = $request->input('previous_response_id');
        $result = $this->callOpenAi($input, $previousResponseId, 3000, $this->getDashboardV2AiChatFormat());

        if (!$result['success']) {
            Log::error('Dashboard V2 AI chat failed', [
                'message' => $result['message'] ?? 'unknown',
            ]);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'AI request failed.',
            ], 500);
        }

        $parsed = $this->parseDashboardV2AiJson($result['text'] ?? '');
        if (empty($parsed)) {
            Log::warning('Dashboard V2 AI chat returned non-JSON', [
                'text' => $result['text'] ?? '',
            ]);
            $parsed = [
                'reply_en' => $result['text'] ?? '',
                'reply_th' => '',
            ];
        }

        $payload = [
            'success' => true,
            'reply_en' => (string) ($parsed['reply_en'] ?? ''),
            'reply_th' => (string) ($parsed['reply_th'] ?? ''),
        ];

        return response()->json($payload);
    }

    public function dashboardV2AiPurchasePlan(Request $request)
    {
        if (!auth()->user()->can('dashboard.data')) {
            abort(403, 'Unauthorized action.');
        }

        $budget = floatval($request->input('monthly_budget', 2200000));
        $coverDays = intval($request->input('cover_days', 60));
        $excludeProducts = trim((string) $request->input('exclude_products', ''));

        if ($budget <= 0 || $coverDays <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid budget or cover days.',
            ], 422);
        }

        Log::info('Dashboard V2 AI purchase plan requested', [
            'user_id' => $request->session()->get('user.id'),
            'business_id' => $request->session()->get('user.business_id'),
            'budget' => $budget,
            'cover_days' => $coverDays,
            'exclude_products' => $excludeProducts,
            'period' => $request->get('period'),
        ]);

        $summary = $this->buildDashboardV2AiSummary($request);
        
        $input = $this->buildDashboardV2AiPurchasePlanInput($summary, $budget, $coverDays, $excludeProducts);
        // Keep max tokens modest to avoid long model runtimes that can hit web server timeouts
        $result = $this->callOpenAi($input, null, 900, $this->getDashboardV2AiPurchasePlanFormat());

        if (!$result['success']) {
            Log::error('Dashboard V2 AI purchase plan failed', [
                'message' => $result['message'] ?? 'unknown',
            ]);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'AI request failed.',
            ], 500);
        }

        $parsed = $this->parseDashboardV2AiJson($result['text'] ?? '');
        if (empty($parsed)) {
            Log::warning('Dashboard V2 AI purchase plan returned non-JSON', [
                'text' => $result['text'] ?? '',
            ]);
            $parsed = [
                'reply_en' => $result['text'] ?? '',
                'reply_th' => '',
            ];
        }

        $payload = [
            'success' => true,
            'reply_en' => (string) ($parsed['reply_en'] ?? ''),
            'reply_th' => (string) ($parsed['reply_th'] ?? ''),
        ];

        return response()->json($payload);
    }

    private function buildDashboardV2CsvString(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    private function buildDashboardV2SingleCsv(array $data): string
    {
        $handle = fopen('php://temp', 'r+');
        fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $writeSection = function (string $title, array $headers, array $rows) use ($handle) {
            fputcsv($handle, [$title]);
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fputcsv($handle, ['']);
        };

        $filterRows = [
            ['Period', $data['period']],
            ['Selected Year', $data['selected_year']],
            ['Custom Start', $data['custom_start'] ?? ''],
            ['Custom End', $data['custom_end'] ?? ''],
            ['Range Start', $data['range_start']->format('Y-m-d')],
            ['Range End', $data['range_end']->format('Y-m-d')],
            ['Invoice Filter', 'invoice_no LIKE VT%'],
        ];
        $writeSection('Filters', ['Field', 'Value'], $filterRows);

        $summaryRows = [
            ['Total Revenue (Ex VAT)', (float) ($data['total_revenue_ex_vat'] ?? 0)],
            ['Total VAT', (float) ($data['total_vat'] ?? 0)],
            ['Total Revenue (Inc VAT)', (float) $data['total_revenue']],
            ['Total Orders', (int) $data['total_orders']],
            ['Products Sold', (float) $data['products_sold']],
            ['Customers', (int) $data['total_customers']],
        ];
        $writeSection('Summary', ['Metric', 'Value'], $summaryRows);

        $trendRows = [];
        foreach ($data['trend_labels'] as $i => $label) {
            $trendRows[] = [
                $label,
                (float) ($data['trend_revenue'][$i] ?? 0),
                (float) ($data['trend_qty'][$i] ?? 0),
            ];
        }
        $writeSection('Trend', ['Label', 'Revenue', 'Quantity'], $trendRows);

        $topProductRows = [];
        foreach ($data['top_products'] as $product) {
            $topProductRows[] = [
                $product->name ?? '',
                (float) ($product->qty ?? 0),
                (float) ($product->total ?? 0),
            ];
        }
        $writeSection('Top Products', ['Product', 'Qty', 'Total'], $topProductRows);

        $categoryRows = [];
        foreach ($data['category_breakdown'] as $category) {
            $categoryRows[] = [
                $category['name'] ?? '',
                (float) ($category['total'] ?? 0),
                (float) ($category['percent'] ?? 0),
            ];
        }
        $writeSection('Category Breakdown', ['Category', 'Total', 'Percent'], $categoryRows);

        $productRows = [];
        foreach ($data['period_products'] as $product) {
            $productRows[] = [
                $product->id ?? '',
                $product->name ?? '',
                (float) ($product->price_inc_tax ?? 0),
                (float) ($product->stock ?? 0),
                (float) ($product->qty_sold ?? 0),
                (float) ($product->total_sold ?? 0),
            ];
        }
        $writeSection(
            'Products Sold',
            ['Product ID', 'Product', 'Price Inc Tax', 'Stock', 'Qty Sold', 'Total Sold'],
            $productRows
        );

        $notSoldRows = [];
        foreach ($data['not_sold_products'] as $product) {
            $notSoldRows[] = [
                $product->id ?? '',
                $product->name ?? '',
                (float) ($product->price_inc_tax ?? 0),
                (float) ($product->stock ?? 0),
            ];
        }
        $writeSection('Products Not Sold', ['Product ID', 'Product', 'Price Inc Tax', 'Stock'], $notSoldRows);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv ?: '';
    }

    private function buildDashboardV2AiCacheKey(Request $request, string $suffix): string
    {
        $businessId = $request->session()->get('user.business_id');
        $userId = $request->session()->get('user.id');
        $payload = [
            'business' => $businessId,
            'user' => $userId,
            'period' => $request->get('period', 'month'),
            'year' => $request->get('year'),
            'start' => $request->get('start_date'),
            'end' => $request->get('end_date'),
        ];

        return 'dashv2_ai:' . $suffix . ':' . md5(json_encode($payload));
    }

    private function buildDashboardV2AiSummary(Request $request): array
    {
        $business_id = $request->session()->get('user.business_id');
        $period = $request->get('period', 'month');
        $now = \Carbon::now();
        $selected_year = (int) $request->get('year', $now->year);
        $custom_start = $request->get('start_date');
        $custom_end = $request->get('end_date');

        if (!empty($custom_start) && !empty($custom_end)) {
            $start = \Carbon::parse($custom_start)->startOfDay();
            $end = \Carbon::parse($custom_end)->endOfDay();
        } else {
            switch ($period) {
                case 'day':
                    $start = $now->copy()->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                case 'week':
                    $start = $now->copy()->startOfWeek();
                    $end = $now->copy()->endOfWeek();
                    break;
                case 'year':
                    $start = \Carbon::create($selected_year, 1, 1)->startOfYear();
                    $end = \Carbon::create($selected_year, 12, 31)->endOfDay();
                    break;
                case 'month':
                default:
                    $period = 'month';
                    $start = $now->copy()->startOfMonth();
                    $end = $now->copy()->endOfMonth();
                    break;
            }
        }

        $base_transactions = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->where('invoice_no', 'LIKE', 'VT%')
            ->whereBetween('transaction_date', [$start, $end]);

        $total_revenue = (float) (clone $base_transactions)->sum('final_total');
        $order_tax_total = (float) (clone $base_transactions)->sum('tax_amount');
        $product_tax_total = (float) DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('SUM((tsl.quantity - tsl.quantity_returned) * (CASE WHEN COALESCE(tsl.item_tax, 0) > 0 THEN tsl.item_tax WHEN (COALESCE(tsl.unit_price_inc_tax, 0) - COALESCE(tsl.unit_price, 0)) > 0 THEN (tsl.unit_price_inc_tax - tsl.unit_price) ELSE 0 END)) as total_tax')
            ->value('total_tax');
        $total_vat = $order_tax_total + $product_tax_total;
        $total_revenue_ex_vat = max($total_revenue - $total_vat, 0);
        $total_orders = (int) (clone $base_transactions)->count();
        $total_customers = (int) (clone $base_transactions)->distinct('contact_id')->count('contact_id');

        $products_sold = (float) DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->sum(DB::raw('(tsl.quantity - tsl.quantity_returned)'));

        $trend_labels = [];
        $trend_revenue = [];
        $trend_qty = [];

        if ($period === 'day') {
            $revenue_map = (clone $base_transactions)
                ->selectRaw('HOUR(transaction_date) as label, SUM(final_total) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $qty_map = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.invoice_no', 'LIKE', 'VT%')
                ->whereBetween('t.transaction_date', [$start, $end])
                ->whereNull('tsl.parent_sell_line_id')
                ->selectRaw('HOUR(t.transaction_date) as label, SUM(tsl.quantity - tsl.quantity_returned) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            for ($h = 0; $h < 24; $h++) {
                $trend_labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
                $trend_revenue[] = (float) ($revenue_map[$h] ?? 0);
                $trend_qty[] = (float) ($qty_map[$h] ?? 0);
            }
        } elseif ($period === 'year') {
            $revenue_map = (clone $base_transactions)
                ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as label, SUM(final_total) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $qty_map = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.invoice_no', 'LIKE', 'VT%')
                ->whereBetween('t.transaction_date', [$start, $end])
                ->whereNull('tsl.parent_sell_line_id')
                ->selectRaw('DATE_FORMAT(t.transaction_date, "%Y-%m") as label, SUM(tsl.quantity - tsl.quantity_returned) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $label = $cursor->format('Y-m');
                $trend_labels[] = $cursor->format('M');
                $trend_revenue[] = (float) ($revenue_map[$label] ?? 0);
                $trend_qty[] = (float) ($qty_map[$label] ?? 0);
                $cursor->addMonth();
            }
        } else {
            $revenue_map = (clone $base_transactions)
                ->selectRaw('DATE(transaction_date) as label, SUM(final_total) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $qty_map = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.invoice_no', 'LIKE', 'VT%')
                ->whereBetween('t.transaction_date', [$start, $end])
                ->whereNull('tsl.parent_sell_line_id')
                ->selectRaw('DATE(t.transaction_date) as label, SUM(tsl.quantity - tsl.quantity_returned) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $label = $cursor->format('Y-m-d');
                $trend_labels[] = $cursor->format('j M');
                $trend_revenue[] = (float) ($revenue_map[$label] ?? 0);
                $trend_qty[] = (float) ($qty_map[$label] ?? 0);
                $cursor->addDay();
            }
        }

        $top_products = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('p.name as name, SUM(tsl.quantity - tsl.quantity_returned) as qty, SUM(tsl.unit_price_inc_tax * (tsl.quantity - tsl.quantity_returned)) as total')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->name ?? '',
                    'qty' => (float) ($row->qty ?? 0),
                    'total' => (float) ($row->total ?? 0),
                ];
            })
            ->values()
            ->all();

        $category_rows = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('COALESCE(c.name, "Uncategorized") as category, SUM(tsl.unit_price_inc_tax * (tsl.quantity - tsl.quantity_returned)) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $category_total = $category_rows->sum('total');
        $category_breakdown = [];
        foreach ($category_rows as $row) {
            $percent = $category_total > 0 ? round(($row->total / $category_total) * 100, 1) : 0;
            $category_breakdown[] = [
                'name' => $row->category,
                'total' => (float) $row->total,
            'percent' => $percent,
            ];
        }

        $period_products = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('p.id, p.name, tsl.variation_id, SUM(tsl.quantity - tsl.quantity_returned) as qty_sold, SUM(tsl.unit_price_inc_tax * (tsl.quantity - tsl.quantity_returned)) as total_sold')
            ->groupBy('p.id', 'p.name', 'tsl.variation_id')
            ->orderByDesc('total_sold')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id ?? '',
                    'name' => $row->name ?? '',
                    'variation_id' => $row->variation_id ?? '',
                    'qty_sold' => (float) ($row->qty_sold ?? 0),
                    'total_sold' => (float) ($row->total_sold ?? 0),
                ];
            })
            ->values()
            ->all();

        $sold_product_ids = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.product_id')
            ->groupBy('tsl.product_id');

        $not_sold_count = DB::table('products as p')
            ->where('p.business_id', $business_id)
            ->whereNotIn('p.id', $sold_product_ids)
            ->count();

        return [
            'period' => $period,
            'range_start' => $start->format('Y-m-d'),
            'range_end' => $end->format('Y-m-d'),
            'selected_year' => $selected_year,
            'custom_start' => $custom_start,
            'custom_end' => $custom_end,
            'totals' => [
                'total_revenue' => $total_revenue,
                'total_revenue_ex_vat' => $total_revenue_ex_vat,
                'total_vat' => $total_vat,
                'total_orders' => $total_orders,
                'total_customers' => $total_customers,
                'products_sold' => $products_sold,
            ],
            'trend' => [
                'labels' => $trend_labels,
                'revenue' => $trend_revenue,
                'quantity' => $trend_qty,
            ],
            'top_products' => $top_products,
            'category_breakdown' => $category_breakdown,
            'top_sold_products' => $period_products,
            'not_sold_count' => $not_sold_count,
        ];
    }

    private function buildDashboardV2AiSuggestionsInput(array $summary): array
    {
        $system = 'You are a supply planning assistant. Use only the provided data. Do not assume inventory or stock. If data is insufficient, say so. Provide concise, practical suggestions. Return JSON that matches the required schema.';
        $user = [
            'task' => 'Provide demand-based ordering suggestions and risks for the current filter range.',
            'data' => $summary,
        ];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => json_encode($user)],
        ];
    }

    private function buildDashboardV2AiChatInput(array $summary, string $message, array $history = []): array
    {
        $system = "You are a supply planning assistant. Use only the provided data. Do not assume inventory or stock. Return JSON that matches the required schema.\n\nContext Data:\n" . json_encode($summary);

        $messages = [
            ['role' => 'system', 'content' => $system],
        ];

        foreach ($history as $h) {
            if (!is_array($h)) continue;
            $role = isset($h['role']) && $h['role'] === 'assistant' ? 'assistant' : 'user';
            $content = isset($h['content']) ? trim((string)$h['content']) : '';
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $lastHistory = end($messages);
        if (!$lastHistory || $lastHistory['role'] !== 'user' || $lastHistory['content'] !== $message) {
            $messages[] = ['role' => 'user', 'content' => $message];
        }

        return $messages;
    }

    private function buildDashboardV2AiPurchasePlanInput(array $summary, float $budget, int $coverDays, string $excludeProducts = ''): array
    {
        $system = "You are an expert purchasing and supply chain assistant. Use the provided sales data to generate a strategic purchasing plan. Do not assume inventory or stock not present in the data. Return JSON that matches the required schema, translating the final plan carefully and professionally to Thai in the `reply_th` field.\n\nContext Data:\n" . json_encode($summary);

        $totalBudget = $budget * ($coverDays / 30);
        $message = "Please generate an itemized purchasing plan to cover the next $coverDays days. The allocated monthly budget is " . number_format($budget, 2) . ", meaning the total budget for this period is " . number_format($totalBudget, 2) . ". Analyze the top selling products from the historical data provided, their prices, and suggest quantities to reorder that balance the budget effectively while maximizing revenue. Please provide a detailed response formatted using Markdown.";

        if (!empty($excludeProducts)) {
            $message .= "\n\nIMPORTANT: Do not recommend purchasing any of the following excluded products: " . $excludeProducts . ". Ignore them in your allocation even if they have high sales.";
        }

        $messages = [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $message]
        ];

        return $messages;
    }

    private function callOpenAi(array $input, ?string $previousResponseId = null, int $maxTokens = 600, ?array $textFormat = null): array
    {
        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            Log::error('OpenAI API key missing');
            return [
                'success' => false,
                'message' => 'OpenAI API key is not configured.',
            ];
        }

        $model = (string) config('services.openai.model', 'gpt-4o-mini');
        if ($model === 'gpt-5-mini') {
            $model = 'gpt-4o-mini';
        }

        $payload = [
            'model' => $model,
            'messages' => $input,
            'max_tokens' => $maxTokens,
        ];

        if (!empty($textFormat)) {
            $payload['response_format'] = $textFormat;
        }

        try {
            Log::info('OpenAI API request', [
                'model' => $payload['model'],
                'max_tokens' => $payload['max_tokens'],
                'has_previous_response' => !empty($previousResponseId),
                'input_bytes' => strlen(json_encode($payload['messages'])),
                'payload_messages' => $payload['messages'] // Added detailed payload logging
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ];
            $org = (string) config('services.openai.organization');
            if ($org !== '') {
                $headers['OpenAI-Organization'] = $org;
            }

            $response = Http::withHeaders($headers)
                ->withOptions([
                    'expect' => false,
                    'version' => 1.1,
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // force IPv4 to avoid IPv6 stalls
                    ],
                ])
                ->timeout((int) config('services.openai.timeout', 60))
                ->connectTimeout(5)
                ->retry(2, 500) // retry brief network hiccups; stays under FastCGI 30s budget
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->ok()) {
                $body = $response->body();
                Log::error('OpenAI API error response', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                $message = 'OpenAI API error: ' . $response->status();
                if (config('app.debug') && !empty($body)) {
                    $message .= ' - ' . substr($body, 0, 1000);
                }
                return [
                    'success' => false,
                    'message' => $message,
                ];
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? '';

            Log::info('OpenAI API response received', [
                'response_id' => $body['id'] ?? null,
                'text_preview' => substr($text, 0, 500) . '...' // Added log for the response text
            ]);

            if (trim($text) === '') {
                Log::warning('OpenAI API returned empty output text', [
                    'response' => $body,
                ]);
            }

            return [
                'success' => true,
                'text' => $text,
                'response_id' => $body['id'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('OpenAI API exception', [
                'message' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function getDashboardV2AiSuggestionsFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'dashboard_v2_ai_suggestions',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title_en' => ['type' => 'string'],
                        'title_th' => ['type' => 'string'],
                        'bullets_en' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'bullets_th' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'risks_en' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'risks_th' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'assumptions_en' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'assumptions_th' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                    ],
                    'required' => [
                        'title_en',
                        'title_th',
                        'bullets_en',
                        'bullets_th',
                        'risks_en',
                        'risks_th',
                        'assumptions_en',
                        'assumptions_th',
                        'confidence',
                    ],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ]
        ];
    }

    private function getDashboardV2AiChatFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'dashboard_v2_ai_chat',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reply_en' => ['type' => 'string'],
                        'reply_th' => ['type' => 'string'],
                    ],
                    'required' => ['reply_en', 'reply_th'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ]
        ];
    }

    private function getDashboardV2AiPurchasePlanFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'purchase_plan',
                'description' => 'A strategic product purchasing plan with English and Thai formats, plus suggested items details.',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reply_en' => [
                            'type' => 'string',
                            'description' => 'The English response formatted in markdown.',
                        ],
                        'reply_th' => [
                            'type' => 'string',
                            'description' => 'The Thai translation of the response formatted in markdown.',
                        ],
                        'suggested_items' => [
                            'type' => 'array',
                            'description' => 'List of suggested items to purchase based on the plan',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'variation_id' => [
                                        'type' => 'integer',
                                        'description' => 'The variation_id of the product'
                                    ],
                                    'quantity' => [
                                        'type' => 'number',
                                        'description' => 'The suggested quantity to purchase'
                                    ]
                                ],
                                'required' => ['variation_id', 'quantity'],
                                'additionalProperties' => false
                            ]
                        ]
                    ],
                    'required' => ['reply_en', 'reply_th', 'suggested_items'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ];
    }

    private function parseDashboardV2AiJson(string $text): ?array
    {
        $clean = trim($text);
        if ($clean === '') {
            return null;
        }

        if (preg_match('/```(?:json)?(.*?)```/s', $clean, $matches)) {
            $clean = trim($matches[1]);
        }

        $decoded = json_decode($clean, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function normalizeDashboardV2AiData(array $data): array
    {
        $normalizeList = function ($value) {
            if (is_string($value)) {
                return [$value];
            }
            if (is_array($value)) {
                return array_values(array_filter($value, function ($item) {
                    return is_string($item) && trim($item) !== '';
                }));
            }
            return [];
        };

        return [
            'title_en' => (string) ($data['title_en'] ?? 'AI Suggestions'),
            'title_th' => (string) ($data['title_th'] ?? 'คำแนะนำ AI'),
            'bullets_en' => $normalizeList($data['bullets_en'] ?? []),
            'bullets_th' => $normalizeList($data['bullets_th'] ?? []),
            'risks_en' => $normalizeList($data['risks_en'] ?? []),
            'risks_th' => $normalizeList($data['risks_th'] ?? []),
            'assumptions_en' => $normalizeList($data['assumptions_en'] ?? []),
            'assumptions_th' => $normalizeList($data['assumptions_th'] ?? []),
            'confidence' => (string) ($data['confidence'] ?? 'medium'),
        ];
    }

    private function buildDashboardV2Data(Request $request, bool $paginateNotSold = true): array
    {
        $business_id = $request->session()->get('user.business_id');
        $period = $request->get('period', 'month');
        $now = \Carbon::now();
        $selected_year = (int) $request->get('year', $now->year);
        $custom_start = $request->get('start_date');
        $custom_end = $request->get('end_date');

        if (!empty($custom_start) && !empty($custom_end)) {
            $start = \Carbon::parse($custom_start)->startOfDay();
            $end = \Carbon::parse($custom_end)->endOfDay();
        } else {
            switch ($period) {
                case 'day':
                    $start = $now->copy()->startOfDay();
                    $end = $now->copy()->endOfDay();
                    break;
                case 'week':
                    $start = $now->copy()->startOfWeek();
                    $end = $now->copy()->endOfWeek();
                    break;
                case 'year':
                    $start = \Carbon::create($selected_year, 1, 1)->startOfYear();
                    $end = \Carbon::create($selected_year, 12, 31)->endOfDay();
                    break;
                case 'month':
                default:
                    $period = 'month';
                    // Strict Month Logic: Match Old POS/Server.js YYYY-MM logic
                    $start = $now->copy()->startOfMonth();
                    $end = $now->copy()->endOfMonth();
                    break;
            }
        }
        $year_options = range($now->year, $now->year - 5);

        // Core Query - Consistent with Old POS "Sales" logic
        $base_transactions = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            // Remove Invoice No Filter if Old POS didn't have it, but keeping it if it's a specific requirement for the new system.
            // Old POS didn't seem to filter by Invoice No 'VT%', but user might want it retained.
            // I will keep it but add a comment that this restricts to specific invoice series.
            ->where('invoice_no', 'LIKE', 'VT%')
            ->whereBetween('transaction_date', [$start, $end]);

        // Revenue Calculation: Sum of final_total (Old POS: grand_total)
        $total_revenue = (float) (clone $base_transactions)->sum('final_total');
        $order_tax_total = (float) (clone $base_transactions)->sum('tax_amount');
        $product_tax_total = (float) DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('SUM((tsl.quantity - tsl.quantity_returned) * (CASE WHEN COALESCE(tsl.item_tax, 0) > 0 THEN tsl.item_tax WHEN (COALESCE(tsl.unit_price_inc_tax, 0) - COALESCE(tsl.unit_price, 0)) > 0 THEN (tsl.unit_price_inc_tax - tsl.unit_price) ELSE 0 END)) as total_tax')
            ->value('total_tax');
        $total_vat = $order_tax_total + $product_tax_total;
        $total_revenue_ex_vat = max($total_revenue - $total_vat, 0);

        $total_orders = (clone $base_transactions)->count();
        $total_customers = (clone $base_transactions)->distinct('contact_id')->count('contact_id');

        $products_sold = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->sum(DB::raw('(tsl.quantity - tsl.quantity_returned)'));

        $trend_labels = [];
        $trend_revenue = [];
        $trend_qty = [];

        if ($period === 'day') {
            $revenue_map = (clone $base_transactions)
                ->selectRaw('HOUR(transaction_date) as label, SUM(final_total) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $qty_map = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.invoice_no', 'LIKE', 'VT%')
                ->whereBetween('t.transaction_date', [$start, $end])
                ->whereNull('tsl.parent_sell_line_id')
                ->selectRaw('HOUR(t.transaction_date) as label, SUM(tsl.quantity - tsl.quantity_returned) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            for ($h = 0; $h < 24; $h++) {
                $trend_labels[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
                $trend_revenue[] = (float) ($revenue_map[$h] ?? 0);
                $trend_qty[] = (float) ($qty_map[$h] ?? 0);
            }
        } elseif ($period === 'year') {
            $revenue_map = (clone $base_transactions)
                ->selectRaw('DATE_FORMAT(transaction_date, "%Y-%m") as label, SUM(final_total) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $qty_map = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.invoice_no', 'LIKE', 'VT%')
                ->whereBetween('t.transaction_date', [$start, $end])
                ->whereNull('tsl.parent_sell_line_id')
                ->selectRaw('DATE_FORMAT(t.transaction_date, "%Y-%m") as label, SUM(tsl.quantity - tsl.quantity_returned) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $label = $cursor->format('Y-m');
                $trend_labels[] = $cursor->format('M');
                $trend_revenue[] = (float) ($revenue_map[$label] ?? 0);
                $trend_qty[] = (float) ($qty_map[$label] ?? 0);
                $cursor->addMonth();
            }
        } else {
            $revenue_map = (clone $base_transactions)
                ->selectRaw('DATE(transaction_date) as label, SUM(final_total) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $qty_map = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->where('t.business_id', $business_id)
                ->where('t.type', 'sell')
                ->where('t.status', 'final')
                ->where('t.invoice_no', 'LIKE', 'VT%')
                ->whereBetween('t.transaction_date', [$start, $end])
                ->whereNull('tsl.parent_sell_line_id')
                ->selectRaw('DATE(t.transaction_date) as label, SUM(tsl.quantity - tsl.quantity_returned) as total')
                ->groupBy('label')
                ->pluck('total', 'label')
                ->toArray();

            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $label = $cursor->format('Y-m-d');
                $trend_labels[] = $cursor->format('j M');
                $trend_revenue[] = (float) ($revenue_map[$label] ?? 0);
                $trend_qty[] = (float) ($qty_map[$label] ?? 0);
                $cursor->addDay();
            }
        }

        $top_products = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('p.name as name, SUM(tsl.quantity - tsl.quantity_returned) as qty, SUM(tsl.unit_price_inc_tax * (tsl.quantity - tsl.quantity_returned)) as total')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $category_rows = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('COALESCE(c.name, "Uncategorized") as category, SUM(tsl.unit_price_inc_tax * (tsl.quantity - tsl.quantity_returned)) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $category_total = $category_rows->sum('total');
        $category_colors = ['#6C5CE7', '#00B894', '#0984E3', '#E17055', '#FDCB6E', '#00CEC9'];
        $category_breakdown = [];
        foreach ($category_rows as $index => $row) {
            $percent = $category_total > 0 ? round(($row->total / $category_total) * 100, 1) : 0;
            $category_breakdown[] = [
                'name' => $row->category,
                'total' => $row->total,
                'percent' => $percent,
                'color' => $category_colors[$index % count($category_colors)],
            ];
        }

        $period_sales = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->selectRaw('p.id, p.name, p.image, SUM(tsl.quantity - tsl.quantity_returned) as qty_sold, SUM(tsl.unit_price_inc_tax * (tsl.quantity - tsl.quantity_returned)) as total_sold')
            ->groupBy('p.id', 'p.name', 'p.image');

        $stock_sub = DB::table('variations as v')
            ->leftJoin('variation_location_details as vld', 'v.id', '=', 'vld.variation_id')
            ->selectRaw('v.product_id, SUM(COALESCE(vld.qty_available, 0)) as stock')
            ->groupBy('v.product_id');

        $price_sub = DB::table('variations as v')
            ->selectRaw('v.product_id, MIN(v.sell_price_inc_tax) as price_inc_tax')
            ->groupBy('v.product_id');

        $period_products = $period_sales
            ->leftJoinSub($stock_sub, 'stock', function ($join) {
                $join->on('stock.product_id', '=', 'p.id');
            })
            ->leftJoinSub($price_sub, 'price', function ($join) {
                $join->on('price.product_id', '=', 'p.id');
            })
            ->addSelect('stock.stock', 'price.price_inc_tax')
            ->orderByDesc('total_sold')
            ->get();

        $sold_product_ids = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->where('t.invoice_no', 'LIKE', 'VT%')
            ->whereBetween('t.transaction_date', [$start, $end])
            ->whereNull('tsl.parent_sell_line_id')
            ->select('tsl.product_id')
            ->groupBy('tsl.product_id');

        $not_sold_query = DB::table('products as p')
            ->leftJoinSub($stock_sub, 'stock', function ($join) {
                $join->on('stock.product_id', '=', 'p.id');
            })
            ->leftJoinSub($price_sub, 'price', function ($join) {
                $join->on('price.product_id', '=', 'p.id');
            })
            ->where('p.business_id', $business_id)
            ->whereNotIn('p.id', $sold_product_ids)
            ->select('p.id', 'p.name', 'p.image', 'stock.stock', 'price.price_inc_tax')
            ->orderByDesc('price.price_inc_tax');

        $not_sold_products = $paginateNotSold
            ? $not_sold_query->paginate(12, ['*'], 'not_sold_page')
            : $not_sold_query->get();

        return [
            'period' => $period,
            'range_start' => $start,
            'range_end' => $end,
            'selected_year' => $selected_year,
            'year_options' => $year_options,
            'custom_start' => $custom_start,
            'custom_end' => $custom_end,
            'total_revenue' => $total_revenue,
            'total_revenue_ex_vat' => $total_revenue_ex_vat,
            'total_vat' => $total_vat,
            'total_orders' => $total_orders,
            'total_customers' => $total_customers,
            'products_sold' => $products_sold,
            'trend_labels' => $trend_labels,
            'trend_revenue' => $trend_revenue,
            'trend_qty' => $trend_qty,
            'top_products' => $top_products,
            'category_breakdown' => $category_breakdown,
            'period_products' => $period_products,
            'not_sold_products' => $not_sold_products,
        ];
    }

    /**
     * Retrieves purchase and sell details for a given time period.
     *
     * @return \Illuminate\Http\Response
     */
    public function getTotals()
    {
        if (request()->ajax()) {
            $start = request()->start;
            $end = request()->end;
            $location_id = request()->location_id;
            $business_id = request()->session()->get('user.business_id');

            // get user id parameter
            $created_by = request()->user_id;

            $purchase_details = $this->transactionUtil->getPurchaseTotals($business_id, $start, $end, $location_id, $created_by);

            $sell_details = $this->transactionUtil->getSellTotals($business_id, $start, $end, $location_id, $created_by);

            $total_ledger_discount = $this->transactionUtil->getTotalLedgerDiscount($business_id, $start, $end);

            $purchase_details['purchase_due'] = $purchase_details['purchase_due'] - $total_ledger_discount['total_purchase_discount'];

            $transaction_types = [
                'purchase_return', 'sell_return', 'expense',
            ];

            $transaction_totals = $this->transactionUtil->getTransactionTotals(
                $business_id,
                $transaction_types,
                $start,
                $end,
                $location_id,
                $created_by
            );

            $total_purchase_inc_tax = ! empty($purchase_details['total_purchase_inc_tax']) ? $purchase_details['total_purchase_inc_tax'] : 0;
            $total_purchase_return_inc_tax = $transaction_totals['total_purchase_return_inc_tax'];

            $output = $purchase_details;
            $output['total_purchase'] = $total_purchase_inc_tax;
            $output['total_purchase_return'] = $total_purchase_return_inc_tax;
            $output['total_purchase_return_paid'] = $this->transactionUtil->getTotalPurchaseReturnPaid($business_id, $start, $end, $location_id);

            $total_sell_inc_tax = ! empty($sell_details['total_sell_inc_tax']) ? $sell_details['total_sell_inc_tax'] : 0;
            $total_sell_return_inc_tax = ! empty($transaction_totals['total_sell_return_inc_tax']) ? $transaction_totals['total_sell_return_inc_tax'] : 0;
            $output['total_sell_return_paid'] = $this->transactionUtil->getTotalSellReturnPaid($business_id, $start, $end, $location_id);

            $output['total_sell'] = $total_sell_inc_tax;
            $output['total_sell_return'] = $total_sell_return_inc_tax;

            $output['invoice_due'] = $sell_details['invoice_due'] - $total_ledger_discount['total_sell_discount'];
            $output['total_expense'] = $transaction_totals['total_expense'];

            //NET = TOTAL SALES - INVOICE DUE - EXPENSE
            $output['net'] = $output['total_sell'] - $output['invoice_due'] - $output['total_expense'];

            return $output;
        }
    }

    /**
     * Retrieves sell products whose available quntity is less than alert quntity.
     *
     * @return \Illuminate\Http\Response
     */
    public function getProductStockAlert()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $permitted_locations = auth()->user()->permitted_locations();
            $products = $this->productUtil->getProductAlert($business_id, $permitted_locations);

            return Datatables::of($products)
                ->editColumn('product', function ($row) {
                    if ($row->type == 'single') {
                        return $row->product.' ('.$row->sku.')';
                    } else {
                        return $row->product.' - '.$row->product_variation.' - '.$row->variation.' ('.$row->sub_sku.')';
                    }
                })
                ->editColumn('stock', function ($row) {
                    $stock = $row->stock ? $row->stock : 0;

                    return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false>'.(float) $stock.'</span> '.$row->unit;
                })
                ->removeColumn('sku')
                ->removeColumn('sub_sku')
                ->removeColumn('unit')
                ->removeColumn('type')
                ->removeColumn('product_variation')
                ->removeColumn('variation')
                ->rawColumns([2])
                ->make(false);
        }
    }

    /**
     * Retrieves payment dues for the purchases.
     *
     * @return \Illuminate\Http\Response
     */
    public function getPurchasePaymentDues()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $today = \Carbon::now()->format('Y-m-d H:i:s');

            $query = Transaction::join(
                'contacts as c',
                'transactions.contact_id',
                '=',
                'c.id'
            )
                    ->leftJoin(
                        'transaction_payments as tp',
                        'transactions.id',
                        '=',
                        'tp.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'purchase')
                    ->where('transactions.payment_status', '!=', 'paid')
                    ->whereRaw("DATEDIFF( DATE_ADD( transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY), '$today') <= 7");

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $query->where('transactions.location_id', request()->input('location_id'));
            }

            $dues = $query->select(
                'transactions.id as id',
                'c.name as supplier',
                'c.supplier_business_name',
                'ref_no',
                'final_total',
                DB::raw('SUM(tp.amount) as total_paid')
            )
                        ->groupBy('transactions.id');

            return Datatables::of($dues)
                ->addColumn('due', function ($row) {
                    $total_paid = ! empty($row->total_paid) ? $row->total_paid : 0;
                    $due = $row->final_total - $total_paid;

                    return '<span class="display_currency" data-currency_symbol="true">'.
                    $due.'</span>';
                })
                ->addColumn('action', '@can("purchase.create") <a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'addPayment\'], [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang("purchase.add_payment")</a> @endcan')
                ->removeColumn('supplier_business_name')
                ->editColumn('supplier', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$supplier}}')
                ->editColumn('ref_no', function ($row) {
                    if (auth()->user()->can('purchase.view')) {
                        return  '<a href="#" data-href="'.action([\App\Http\Controllers\PurchaseController::class, 'show'], [$row->id]).'"
                                    class="btn-modal" data-container=".view_modal">'.$row->ref_no.'</a>';
                    }

                    return $row->ref_no;
                })
                ->removeColumn('id')
                ->removeColumn('final_total')
                ->removeColumn('total_paid')
                ->rawColumns([0, 1, 2, 3])
                ->make(false);
        }
    }

    /**
     * Retrieves payment dues for the purchases.
     *
     * @return \Illuminate\Http\Response
     */
    public function getSalesPaymentDues()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $today = \Carbon::now()->format('Y-m-d H:i:s');

            $query = Transaction::join(
                'contacts as c',
                'transactions.contact_id',
                '=',
                'c.id'
            )
                    ->leftJoin(
                        'transaction_payments as tp',
                        'transactions.id',
                        '=',
                        'tp.transaction_id'
                    )
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.type', 'sell')
                    ->where('transactions.payment_status', '!=', 'paid')
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("DATEDIFF( DATE_ADD( transaction_date, INTERVAL IF(transactions.pay_term_type = 'days', transactions.pay_term_number, 30 * transactions.pay_term_number) DAY), '$today') <= 7");

            //Check for permitted locations of a user
            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $query->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->input('location_id'))) {
                $query->where('transactions.location_id', request()->input('location_id'));
            }

            $dues = $query->select(
                'transactions.id as id',
                'c.name as customer',
                'c.supplier_business_name',
                'transactions.invoice_no',
                'final_total',
                DB::raw('SUM(tp.amount) as total_paid')
            )
                        ->groupBy('transactions.id');

            return Datatables::of($dues)
                ->addColumn('due', function ($row) {
                    $total_paid = ! empty($row->total_paid) ? $row->total_paid : 0;
                    $due = $row->final_total - $total_paid;

                    return '<span class="display_currency" data-currency_symbol="true">'.
                    $due.'</span>';
                })
                ->editColumn('invoice_no', function ($row) {
                    if (auth()->user()->can('sell.view')) {
                        return  '<a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'"
                                    class="btn-modal" data-container=".view_modal">'.$row->invoice_no.'</a>';
                    }

                    return $row->invoice_no;
                })
                ->addColumn('action', '@if(auth()->user()->can("sell.create") || auth()->user()->can("direct_sell.access")) <a href="{{action([\App\Http\Controllers\TransactionPaymentController::class, \'addPayment\'], [$id])}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang("purchase.add_payment")</a> @endif')
                ->editColumn('customer', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$customer}}')
                ->removeColumn('supplier_business_name')
                ->removeColumn('id')
                ->removeColumn('final_total')
                ->removeColumn('total_paid')
                ->rawColumns([0, 1, 2, 3])
                ->make(false);
        }
    }

    public function loadMoreNotifications()
    {
        $notifications = auth()->user()->notifications()->orderBy('created_at', 'DESC')->paginate(10);

        if (request()->input('page') == 1) {
            auth()->user()->unreadNotifications->markAsRead();
        }
        $notifications_data = $this->commonUtil->parseNotifications($notifications);

        return view('layouts.partials.notification_list', compact('notifications_data'));
    }

    /**
     * Function to count total number of unread notifications
     *
     * @return json
     */
    public function getTotalUnreadNotifications()
    {
        $unread_notifications = auth()->user()->unreadNotifications;
        $total_unread = $unread_notifications->count();

        $notification_html = '';
        $modal_notifications = [];
        foreach ($unread_notifications as $unread_notification) {
            if (isset($data['show_popup'])) {
                $modal_notifications[] = $unread_notification;
                $unread_notification->markAsRead();
            }
        }
        if (! empty($modal_notifications)) {
            $notification_html = view('home.notification_modal')->with(['notifications' => $modal_notifications])->render();
        }

        return [
            'total_unread' => $total_unread,
            'notification_html' => $notification_html,
        ];
    }

    private function __chartOptions($title)
    {
        return [
            'yAxis' => [
                'title' => [
                    'text' => $title,
                ],
            ],
            'legend' => [
                'align' => 'right',
                'verticalAlign' => 'top',
                'floating' => true,
                'layout' => 'vertical',
                'padding' => 20,
            ],
        ];
    }

    public function getCalendar()
    {
        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->restUtil->is_admin(auth()->user(), $business_id);
        $is_superadmin = auth()->user()->can('superadmin');
        if (request()->ajax()) {
            $data = [
                'start_date' => request()->start,
                'end_date' => request()->end,
                'user_id' => ($is_admin || $is_superadmin) && ! empty(request()->user_id) ? request()->user_id : auth()->user()->id,
                'location_id' => ! empty(request()->location_id) ? request()->location_id : null,
                'business_id' => $business_id,
                'events' => request()->events ?? [],
                'color' => '#007FFF',
            ];
            $events = [];

            if (in_array('bookings', $data['events'])) {
                $events = $this->restUtil->getBookingsForCalendar($data);
            }

            $module_events = $this->moduleUtil->getModuleData('calendarEvents', $data);

            foreach ($module_events as $module_event) {
                $events = array_merge($events, $module_event);
            }

            return $events;
        }

        $all_locations = BusinessLocation::forDropdown($business_id)->toArray();
        $users = [];
        if ($is_admin) {
            $users = User::forDropdown($business_id, false);
        }

        $event_types = [
            'bookings' => [
                'label' => __('restaurant.bookings'),
                'color' => '#007FFF',
            ],
        ];
        $module_event_types = $this->moduleUtil->getModuleData('eventTypes');
        foreach ($module_event_types as $module_event_type) {
            $event_types = array_merge($event_types, $module_event_type);
        }

        return view('home.calendar')->with(compact('all_locations', 'users', 'event_types'));
    }

    public function showNotification($id)
    {
        $notification = DatabaseNotification::find($id);

        $data = $notification->data;

        $notification->markAsRead();

        return view('home.notification_modal')->with([
            'notifications' => [$notification],
        ]);
    }

    public function attachMediasToGivenModel(Request $request)
    {
        if ($request->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $model_id = $request->input('model_id');
                $model = $request->input('model_type');
                $model_media_type = $request->input('model_media_type');

                DB::beginTransaction();

                //find model to which medias are to be attached
                $model_to_be_attached = $model::where('business_id', $business_id)
                                        ->findOrFail($model_id);

                Media::uploadMedia($business_id, $model_to_be_attached, $request, 'file', false, $model_media_type);

                DB::commit();

                $output = [
                    'success' => true,
                    'msg' => __('lang_v1.success'),
                ];
            } catch (Exception $e) {
                DB::rollBack();

                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    public function getUserLocation($latlng)
    {
        $latlng_array = explode(',', $latlng);

        $response = $this->moduleUtil->getLocationFromCoordinates($latlng_array[0], $latlng_array[1]);

        return ['address' => $response];
    }
}
