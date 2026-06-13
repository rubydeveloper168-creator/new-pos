<?php

namespace App\Http\Controllers;

use App\Product;
use App\TransactionSellLine;
use App\WarrantyServiceCycle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class WarrantyCheckController extends Controller
{
    /**
     * Display the warranty check page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('warranty_check.index');
    }

    /**
     * Display warranty service calendar.
     *
     * @return \Illuminate\Http\Response|array
     */
    public function calendar()
    {
        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $start = request()->start ? Carbon::parse(request()->start)->startOfDay() : Carbon::today()->startOfMonth();
            $end = request()->end ? Carbon::parse(request()->end)->endOfDay() : Carbon::today()->endOfMonth();
            $event_types = request()->input('events', ['service_3_month', 'service_6_month']);

            return $this->getWarrantyCalendarEvents($business_id, $start, $end, $event_types);
        }

        $selectedYear = (int) request('year', Carbon::today()->year);
        $yearStart = Carbon::create($selectedYear, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($selectedYear, 12, 31)->endOfDay();
        $yearEvents = $this->getWarrantyCalendarEvents($business_id, $yearStart, $yearEnd, ['service_3_month', 'service_6_month']);
        $yearOverview = $this->groupCalendarEventsByMonth($yearEvents);

        $calendar_event_types = [
            'service_3_month' => [
                'label' => '3 Month Service',
                'color' => '#6b7280',
            ],
            'service_6_month' => [
                'label' => '6 Month Service',
                'color' => '#dc2626',
            ],
        ];

        $availableYears = [];
        for ($year = $selectedYear - 1; $year <= $selectedYear + 2; $year++) {
            $availableYears[$year] = $year;
        }

        return view('warranty_check.calendar')->with(compact('calendar_event_types', 'selectedYear', 'availableYears', 'yearOverview'));
    }

    /**
     * Product configuration table data.
     *
     * @return mixed
     */
    public function productData()
    {
        $business_id = request()->session()->get('user.business_id');

        $products = Product::where('business_id', $business_id)
            ->select([
                'id',
                'image',
                'name',
                'sku',
                'warranty_check_status',
                'service_cycle_3_month',
                'service_cycle_6_month',
                'updated_at',
            ]);

        return DataTables::of($products)
            ->addColumn('product_image', function ($row) {
                return '<img src="'.$row->image_url.'" alt="'.e($row->name).'" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;">';
            })
            ->editColumn('updated_at', function ($row) {
                return Carbon::parse($row->updated_at)->format('Y-m-d H:i');
            })
            ->addColumn('warranty_status', function ($row) {
                return $this->renderWarrantyConfigurationBadge($row->warranty_check_status);
            })
            ->addColumn('cycle_configuration', function ($row) {
                $cycles = [];

                if (! empty($row->service_cycle_3_month)) {
                    $cycles[] = '<span class="label bg-yellow">3 Month</span>';
                }
                if (! empty($row->service_cycle_6_month)) {
                    $cycles[] = '<span class="label bg-blue">6 Month</span>';
                }

                return empty($cycles) ? '<span class="label label-default">No cycles</span>' : implode(' ', $cycles);
            })
            ->addColumn('action', function ($row) {
                $url = action([\App\Http\Controllers\WarrantyCheckController::class, 'editProduct'], [$row->id]);

                return '<button data-href="'.$url.'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-edit"></i> Edit</button>';
            })
            ->rawColumns(['product_image', 'warranty_status', 'cycle_configuration', 'action'])
            ->make(true);
    }

    /**
     * Sold products warranty/service table data.
     *
     * @return mixed
     */
    public function soldProductData()
    {
        $business_id = request()->session()->get('user.business_id');
        $today = Carbon::today();

        $query = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('warranty_service_cycles as cycle3', function ($join) {
                $join->on('cycle3.sell_line_id', '=', 'tsl.id')
                    ->where('cycle3.cycle_months', '=', 3);
            })
            ->leftJoin('warranty_service_cycles as cycle6', function ($join) {
                $join->on('cycle6.sell_line_id', '=', 'tsl.id')
                    ->where('cycle6.cycle_months', '=', 6);
            })
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('tsl.parent_sell_line_id')
            ->where(function ($query) {
                $query->where('p.warranty_check_status', 'has_warranty')
                    ->orWhere('p.service_cycle_3_month', 1)
                    ->orWhere('p.service_cycle_6_month', 1);
            })
            ->select([
                'tsl.id',
                't.invoice_no',
                't.transaction_date',
                'p.name as product_name',
                'p.sku',
                'p.warranty_check_status',
                'p.service_cycle_3_month',
                'p.service_cycle_6_month',
                'c.name as customer_name',
                'cycle3.status as cycle3_status',
                'cycle3.note as cycle3_note',
                'cycle3.notified_at as cycle3_notified_at',
                'cycle3.completed_at as cycle3_completed_at',
                'cycle6.status as cycle6_status',
                'cycle6.note as cycle6_note',
                'cycle6.notified_at as cycle6_notified_at',
                'cycle6.completed_at as cycle6_completed_at',
            ]);

        return DataTables::of($query)
            ->editColumn('transaction_date', function ($row) {
                return Carbon::parse($row->transaction_date)->format('Y-m-d');
            })
            ->editColumn('customer_name', function ($row) {
                return ! empty($row->customer_name) ? $row->customer_name : 'Walk-in customer';
            })
            ->addColumn('warranty_day_count', function ($row) use ($today) {
                if ($row->warranty_check_status !== 'has_warranty') {
                    return '-';
                }

                $purchase_date = Carbon::parse($row->transaction_date);
                $elapsed_days = max(0, $purchase_date->diffInDays($today, false));
                $elapsed_days = min($elapsed_days, 365);

                return $elapsed_days.' / 365';
            })
            ->addColumn('warranty_status', function ($row) use ($today) {
                return $this->renderWarrantyProgressBadge($row->warranty_check_status, $row->transaction_date, $today);
            })
            ->addColumn('cycle_3_status', function ($row) use ($today) {
                return $this->renderServiceCycleBadge(
                    ! empty($row->service_cycle_3_month),
                    3,
                    $row->transaction_date,
                    $row->cycle3_status,
                    $row->cycle3_notified_at,
                    $row->cycle3_completed_at,
                    $today
                );
            })
            ->addColumn('cycle_6_status', function ($row) use ($today) {
                return $this->renderServiceCycleBadge(
                    ! empty($row->service_cycle_6_month),
                    6,
                    $row->transaction_date,
                    $row->cycle6_status,
                    $row->cycle6_notified_at,
                    $row->cycle6_completed_at,
                    $today
                );
            })
            ->addColumn('action', function ($row) {
                $url = action([\App\Http\Controllers\WarrantyCheckController::class, 'editService'], [$row->id]);

                return '<button data-href="'.$url.'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal" data-container=".view_modal"><i class="glyphicon glyphicon-cog"></i> Update</button>';
            })
            ->filter(function ($query) {
                $search = request('search.value');

                if (! empty($search)) {
                    $like = '%'.$search.'%';

                    $query->where(function ($subQuery) use ($like) {
                        $subQuery->where('t.invoice_no', 'like', $like)
                            ->orWhere('c.name', 'like', $like)
                            ->orWhere('p.name', 'like', $like)
                            ->orWhere('p.sku', 'like', $like);
                    });
                }
            }, false)
            ->rawColumns(['warranty_status', 'cycle_3_status', 'cycle_6_status', 'action'])
            ->make(true);
    }

    /**
     * Edit product warranty/service configuration.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editProduct($id)
    {
        $business_id = request()->session()->get('user.business_id');
        $product = Product::where('business_id', $business_id)->findOrFail($id);

        return view('warranty_check.partials.edit_product')->with(compact('product'));
    }

    /**
     * Update product warranty/service configuration.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|array
     */
    public function updateProduct(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        try {
            $product = Product::where('business_id', $business_id)->findOrFail($id);

            $status = $request->input('warranty_check_status');
            if (! in_array($status, [null, '', 'has_warranty'], true)) {
                throw new \InvalidArgumentException('Invalid warranty status.');
            }

            $product->warranty_check_status = $status ?: null;
            $product->service_cycle_3_month = $request->boolean('service_cycle_3_month');
            $product->service_cycle_6_month = $request->boolean('service_cycle_6_month');
            $product->save();

            $output = [
                'success' => true,
                'msg' => 'Warranty configuration updated successfully.',
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if (! $request->ajax()) {
            return redirect()
                ->back()
                ->with('status', $output);
        }

        return $output;
    }

    /**
     * Edit sell line service statuses.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editService($id)
    {
        $business_id = request()->session()->get('user.business_id');

        $sell_line = TransactionSellLine::with([
            'product',
            'transaction.contact',
            'warranty_service_cycles',
        ])
            ->whereHas('transaction', function ($query) use ($business_id) {
                $query->where('business_id', $business_id)
                    ->where('type', 'sell')
                    ->where('status', 'final');
            })
            ->whereNull('parent_sell_line_id')
            ->findOrFail($id);

        $service_cycles = $sell_line->warranty_service_cycles->keyBy('cycle_months');

        return view('warranty_check.partials.edit_service')->with(compact('sell_line', 'service_cycles'));
    }

    /**
     * Update sell line service statuses.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse|array
     */
    public function updateService(Request $request, $id)
    {
        $business_id = request()->session()->get('user.business_id');

        \Log::info('WarrantyCheck updateService request received', [
            'sell_line_id' => $id,
            'business_id' => $business_id,
            'is_ajax' => $request->ajax(),
            'cycle_3_status' => $request->input('cycle_3_status'),
            'cycle_3_note' => $request->input('cycle_3_note'),
            'cycle_6_status' => $request->input('cycle_6_status'),
            'cycle_6_note' => $request->input('cycle_6_note'),
        ]);

        try {
            $sell_line = TransactionSellLine::with(['product', 'transaction'])
                ->whereHas('transaction', function ($query) use ($business_id) {
                    $query->where('business_id', $business_id)
                        ->where('type', 'sell')
                        ->where('status', 'final');
                })
                ->whereNull('parent_sell_line_id')
                ->findOrFail($id);

            \Log::info('WarrantyCheck updateService sell line loaded', [
                'sell_line_id' => $sell_line->id,
                'transaction_id' => $sell_line->transaction_id,
                'invoice_no' => optional($sell_line->transaction)->invoice_no,
                'product_id' => $sell_line->product_id,
                'product_name' => optional($sell_line->product)->name,
                'service_cycle_3_month' => ! empty(optional($sell_line->product)->service_cycle_3_month),
                'service_cycle_6_month' => ! empty(optional($sell_line->product)->service_cycle_6_month),
            ]);

            DB::beginTransaction();

            $this->upsertCycleStatus(
                $sell_line,
                3,
                ! empty($sell_line->product->service_cycle_3_month),
                $request->input('cycle_3_status'),
                $request->input('cycle_3_note')
            );

            $this->upsertCycleStatus(
                $sell_line,
                6,
                ! empty($sell_line->product->service_cycle_6_month),
                $request->input('cycle_6_status'),
                $request->input('cycle_6_note')
            );

            DB::commit();

            $savedCycles = WarrantyServiceCycle::where('sell_line_id', $sell_line->id)
                ->orderBy('cycle_months')
                ->get(['cycle_months', 'status', 'note', 'notified_at', 'completed_at'])
                ->toArray();

            \Log::info('WarrantyCheck updateService committed', [
                'sell_line_id' => $sell_line->id,
                'saved_cycles' => $savedCycles,
            ]);

            $output = [
                'success' => true,
                'msg' => 'Service status updated successfully.',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if (! $request->ajax()) {
            return redirect()
                ->back()
                ->with('status', $output);
        }

        return $output;
    }

    /**
     * Persist a cycle status.
     *
     * @param  \App\TransactionSellLine  $sell_line
     * @param  int  $cycle_months
     * @param  bool  $enabled
     * @param  string|null  $status
     * @param  string|null  $note
     * @return void
     */
    protected function upsertCycleStatus(TransactionSellLine $sell_line, $cycle_months, $enabled, $status, $note)
    {
        \Log::info('WarrantyCheck upsertCycleStatus start', [
            'sell_line_id' => $sell_line->id,
            'cycle_months' => $cycle_months,
            'enabled' => $enabled,
            'incoming_status' => $status,
            'incoming_note' => $note,
        ]);

        if (! $enabled) {
            \Log::info('WarrantyCheck upsertCycleStatus skipped because cycle disabled', [
                'sell_line_id' => $sell_line->id,
                'cycle_months' => $cycle_months,
            ]);
            return;
        }

        $allowed_statuses = ['pending', 'notified', 'completed'];
        $status = in_array($status, $allowed_statuses, true) ? $status : 'pending';

        $payload = [
            'status' => $status,
            'note' => $note,
            'updated_by' => request()->session()->get('user.id'),
        ];

        if ($status === 'pending') {
            $payload['notified_at'] = null;
            $payload['completed_at'] = null;
        } elseif ($status === 'notified') {
            $payload['notified_at'] = now();
            $payload['completed_at'] = null;
        } elseif ($status === 'completed') {
            $payload['notified_at'] = now();
            $payload['completed_at'] = now();
        }

        WarrantyServiceCycle::updateOrCreate(
            [
                'sell_line_id' => $sell_line->id,
                'cycle_months' => $cycle_months,
            ],
            $payload
        );

        \Log::info('WarrantyCheck upsertCycleStatus saved', [
            'sell_line_id' => $sell_line->id,
            'cycle_months' => $cycle_months,
            'payload' => $payload,
        ]);
    }

    /**
     * Render product configuration badge.
     *
     * @param  string|null  $status
     * @return string
     */
    protected function renderWarrantyConfigurationBadge($status)
    {
        if ($status === 'has_warranty') {
            return '<span class="label bg-green">Has warranty</span>';
        }

        return '<span class="label label-default">Not set</span>';
    }

    /**
     * Render warranty progress badge.
     *
     * @param  string|null  $status
     * @param  string  $purchase_date
     * @param  \Carbon\Carbon  $today
     * @return string
     */
    protected function renderWarrantyProgressBadge($status, $purchase_date, Carbon $today)
    {
        if ($status !== 'has_warranty') {
            return '<span class="label label-default">No warranty</span>';
        }

        $expiry_date = Carbon::parse($purchase_date)->addDays(365);

        if ($today->greaterThan($expiry_date)) {
            return '<span class="label bg-red">Out of warranty</span>';
        }

        $days_left = $today->diffInDays($expiry_date, false);

        return '<span class="label bg-green">Active ('.$days_left.' days left)</span>';
    }

    /**
     * Render service cycle badge.
     *
     * @param  bool  $enabled
     * @param  int  $cycle_months
     * @param  string  $purchase_date
     * @param  string|null  $status
     * @param  string|null  $notified_at
     * @param  string|null  $completed_at
     * @param  \Carbon\Carbon  $today
     * @return string
     */
    protected function renderServiceCycleBadge($enabled, $cycle_months, $purchase_date, $status, $notified_at, $completed_at, Carbon $today)
    {
        if (! $enabled) {
            return '<span class="label label-default">N/A</span>';
        }

        $due_date = Carbon::parse($purchase_date)->addMonths($cycle_months);

        if ($status === 'completed' || ! empty($completed_at)) {
            return '<span class="label bg-green">Completed</span>';
        }

        if ($status === 'notified' || ! empty($notified_at)) {
            return '<span class="label bg-yellow">Notified</span>';
        }

        if ($today->greaterThanOrEqualTo($due_date)) {
            return '<span class="label bg-yellow">Due now</span>';
        }

        return '<span class="label label-default">Upcoming</span>';
    }

    /**
     * Build warranty service calendar events.
     *
     * @param  int  $business_id
     * @param  \Carbon\Carbon  $start
     * @param  \Carbon\Carbon  $end
     * @param  array  $event_types
     * @return array
     */
    protected function getWarrantyCalendarEvents($business_id, Carbon $start, Carbon $end, array $event_types = [])
    {
        $include3Month = in_array('service_3_month', $event_types);
        $include6Month = in_array('service_6_month', $event_types);

        \Log::info('WarrantyCheck calendar event query start', [
            'business_id' => $business_id,
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
            'event_types' => $event_types,
        ]);

        $queryStart = $start->copy()->subMonths(6)->startOfDay();
        $queryEnd = $end->copy()->endOfDay();

        $sell_lines = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('warranty_service_cycles as cycle3', function ($join) {
                $join->on('cycle3.sell_line_id', '=', 'tsl.id')
                    ->where('cycle3.cycle_months', '=', 3);
            })
            ->leftJoin('warranty_service_cycles as cycle6', function ($join) {
                $join->on('cycle6.sell_line_id', '=', 'tsl.id')
                    ->where('cycle6.cycle_months', '=', 6);
            })
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('tsl.parent_sell_line_id')
            ->whereBetween('t.transaction_date', [$queryStart, $queryEnd])
            ->where(function ($query) {
                $query->where('p.service_cycle_3_month', 1)
                    ->orWhere('p.service_cycle_6_month', 1);
            })
            ->select([
                'tsl.id',
                't.invoice_no',
                't.transaction_date',
                'p.name as product_name',
                'p.sku',
                'p.service_cycle_3_month',
                'p.service_cycle_6_month',
                'c.name as customer_name',
                'cycle3.status as cycle3_status',
                'cycle6.status as cycle6_status',
            ])
            ->get();

        \Log::info('WarrantyCheck calendar sell lines fetched', [
            'count' => $sell_lines->count(),
        ]);

        $events = [];

        foreach ($sell_lines as $sell_line) {
            $purchaseDate = Carbon::parse($sell_line->transaction_date);
            $customerName = ! empty($sell_line->customer_name) ? $sell_line->customer_name : 'Walk-in customer';

            if ($include3Month && ! empty($sell_line->service_cycle_3_month)) {
                $dueDate = $purchaseDate->copy()->addMonths(3);

                if ($dueDate->betweenIncluded($start, $end)) {
                    $events[] = $this->buildWarrantyCalendarEvent(
                        $sell_line,
                        3,
                        $dueDate,
                        '#6b7280',
                        $customerName,
                        $sell_line->cycle3_status
                    );
                }
            }

            if ($include6Month && ! empty($sell_line->service_cycle_6_month)) {
                $dueDate = $purchaseDate->copy()->addMonths(6);

                if ($dueDate->betweenIncluded($start, $end)) {
                    $events[] = $this->buildWarrantyCalendarEvent(
                        $sell_line,
                        6,
                        $dueDate,
                        '#dc2626',
                        $customerName,
                        $sell_line->cycle6_status
                    );
                }
            }
        }

        \Log::info('WarrantyCheck calendar events built', [
            'count' => count($events),
        ]);

        return $events;
    }

    /**
     * Build one calendar event.
     *
     * @param  object  $sell_line
     * @param  int  $cycle_months
     * @param  \Carbon\Carbon  $due_date
     * @param  string  $color
     * @param  string  $customer_name
     * @param  string|null  $status
     * @return array
     */
    protected function buildWarrantyCalendarEvent($sell_line, $cycle_months, Carbon $due_date, $color, $customer_name, $status = null)
    {
        $statusLabel = $status ? ucfirst($status) : 'Pending';
        $eventColor = $color;
        $eventClass = $cycle_months === 3 ? 'is-3m' : 'is-6m';

        if ($status === 'notified') {
            $eventColor = '#f59e0b';
            $eventClass = 'is-notified';
        } elseif ($status === 'completed') {
            $eventColor = '#16a34a';
            $eventClass = 'is-completed';
        }

        \Log::info('WarrantyCheck calendar event built', [
            'sell_line_id' => $sell_line->id,
            'invoice_no' => $sell_line->invoice_no,
            'product_name' => $sell_line->product_name,
            'cycle_months' => $cycle_months,
            'due_date' => $due_date->toDateString(),
            'status' => $status,
            'status_label' => $statusLabel,
            'event_color' => $eventColor,
            'event_class' => $eventClass,
        ]);

        return [
            'id' => 'sell-line-'.$sell_line->id.'-cycle-'.$cycle_months,
            'title' => $sell_line->product_name.' - '.$cycle_months.'M',
            'title_html' => '<strong>'.$sell_line->product_name.'</strong><br><small>'.$customer_name.' | '.$cycle_months.'M | '.$statusLabel.'</small>',
            'start' => $due_date->toDateString(),
            'end' => $due_date->copy()->addDay()->toDateString(),
            'allDay' => true,
            'backgroundColor' => $eventColor,
            'borderColor' => $eventColor,
            'textColor' => '#ffffff',
            'description' => 'Invoice: '.$sell_line->invoice_no.' | SKU: '.$sell_line->sku.' | Customer: '.$customer_name.' | Status: '.$statusLabel,
            'edit_url' => action([\App\Http\Controllers\WarrantyCheckController::class, 'editService'], [$sell_line->id]),
            'event_class' => $eventClass,
            'status' => strtolower($statusLabel),
        ];
    }

    /**
     * Group calendar events by month for year overview.
     *
     * @param  array  $events
     * @return array
     */
    protected function groupCalendarEventsByMonth(array $events)
    {
        $months = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = [
                'label' => Carbon::create(null, $month, 1)->format('F'),
                'events' => [],
            ];
        }

        foreach ($events as $event) {
            $month = Carbon::parse($event['start'])->month;
            $months[$month]['events'][] = $event;
        }

        foreach ($months as $month => $data) {
            usort($months[$month]['events'], function ($left, $right) {
                return strcmp($left['start'], $right['start']);
            });
        }

        return $months;
    }
}
