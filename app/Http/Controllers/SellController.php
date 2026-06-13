<?php

namespace App\Http\Controllers;

use App\Account;
use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\CustomerGroup;
use App\InvoiceScheme;
use App\Media;
use App\Product;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Transaction;
use App\TransactionSellLine;
use App\TypesOfService;
use App\User;
use App\Utils\BusinessUtil;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Warranty;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;

class SellController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $contactUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $productUtil;

    protected $modalExcelTempImages = [];

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ContactUtil $contactUtil, BusinessUtil $businessUtil, TransactionUtil $transactionUtil, ModuleUtil $moduleUtil, ProductUtil $productUtil)
    {
        $this->contactUtil = $contactUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
        $this->productUtil = $productUtil;

        $this->dummyPaymentLine = ['method' => '', 'amount' => 0, 'note' => '', 'card_transaction_number' => '', 'card_number' => '', 'card_type' => '', 'card_holder_name' => '', 'card_month' => '', 'card_year' => '', 'card_security' => '', 'cheque_number' => '', 'bank_account_number' => '',
            'is_return' => 0, 'transaction_no' => '', ];

        $this->shipping_status_colors = [
            'ordered' => 'bg-yellow',
            'packed' => 'bg-info',
            'shipped' => 'bg-navy',
            'delivered' => 'bg-green',
            'cancelled' => 'bg-red',
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['sell.view', 'sell.create', 'direct_sell.access', 'direct_sell.view', 'view_own_sell_only', 'view_commission_agent_sell', 'access_shipping', 'access_own_shipping', 'access_commission_agent_shipping', 'so.view_all', 'so.view_own'])) {
            abort(403, 'Unauthorized action.');
        }

        // Redirect non-AJAX requests to summary-sales page
        if (!request()->ajax()) {
            return redirect()->route('sells.summary-sales');
        }

        $business_id = request()->session()->get('user.business_id');
        \Log::info('SellController index - business_id: ' . $business_id . ', user_id: ' . auth()->id());
        $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        $is_tables_enabled = $this->transactionUtil->isModuleEnabled('tables');
        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');
        $is_types_service_enabled = $this->moduleUtil->isModuleEnabled('types_of_service');

        if (request()->ajax()) {
            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);
            $with = [];
            $shipping_statuses = $this->transactionUtil->shipping_statuses();

            $sale_type = ! empty(request()->input('sale_type')) ? request()->input('sale_type') : 'sell';

            $sells = $this->transactionUtil->getListSells($business_id, $sale_type);

            $permitted_locations = auth()->user()->permitted_locations();
            \Log::info('SellController index - permitted_locations: ' . print_r($permitted_locations, true));
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (! empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            $partial_permissions = ['view_own_sell_only', 'view_commission_agent_sell', 'access_own_shipping', 'access_commission_agent_shipping'];
            if (! auth()->user()->can('direct_sell.view')) {
                $sells->where(function ($q) {
                    if (auth()->user()->hasAnyPermission(['view_own_sell_only', 'access_own_shipping'])) {
                        $q->where('transactions.created_by', request()->session()->get('user.id'));
                    }

                    //if user is commission agent display only assigned sells
                    if (auth()->user()->hasAnyPermission(['view_commission_agent_sell', 'access_commission_agent_shipping'])) {
                        $q->orWhere('transactions.commission_agent', request()->session()->get('user.id'));
                    }
                });
            }

            $only_shipments = request()->only_shipments == 'false' ? true : false;
            if ($only_shipments) {
                $sells->whereNotNull('transactions.shipping_status');

                if (auth()->user()->hasAnyPermission(['access_pending_shipments_only'])) {
                    $sells->where('transactions.shipping_status', '!=', 'delivered');
                }
            }

            if (! $is_admin && ! $only_shipments && $sale_type != 'sales_order') {
                $payment_status_arr = [];
                if (auth()->user()->can('view_paid_sells_only')) {
                    $payment_status_arr[] = 'paid';
                }

                if (auth()->user()->can('view_due_sells_only')) {
                    $payment_status_arr[] = 'due';
                }

                if (auth()->user()->can('view_partial_sells_only')) {
                    $payment_status_arr[] = 'partial';
                }

                if (empty($payment_status_arr)) {
                    if (auth()->user()->can('view_overdue_sells_only')) {
                        $sells->OverDue();
                    }
                } else {
                    if (auth()->user()->can('view_overdue_sells_only')) {
                        $sells->where(function ($q) use ($payment_status_arr) {
                            $q->whereIn('transactions.payment_status', $payment_status_arr)
                            ->orWhere(function ($qr) {
                                $qr->OverDue();
                            });
                        });
                    } else {
                        $sells->whereIn('transactions.payment_status', $payment_status_arr);
                    }
                }
            }

            if (! empty(request()->input('payment_status')) && request()->input('payment_status') != 'overdue') {
                $sells->where('transactions.payment_status', request()->input('payment_status'));
            } elseif (request()->input('payment_status') == 'overdue') {
                $sells->whereIn('transactions.payment_status', ['due', 'partial'])
                    ->whereNotNull('transactions.pay_term_number')
                    ->whereNotNull('transactions.pay_term_type')
                    ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
            }

            //Add condition for location,used in sales representative expense report
            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (! empty(request()->input('rewards_only')) && request()->input('rewards_only') == true) {
                $sells->where(function ($q) {
                    $q->whereNotNull('transactions.rp_earned')
                    ->orWhere('transactions.rp_redeemed', '>', 0);
                });
            }

            if (! empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }
            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            //Check is_direct sell
            if (request()->has('is_direct_sale')) {
                $is_direct_sale = request()->is_direct_sale;
                if ($is_direct_sale == 0) {
                    $sells->where('transactions.is_direct_sale', 0);
                    $sells->whereNull('transactions.sub_type');
                }
            }

            //Add condition for commission_agent,used in sales representative sales with commission report
            if (request()->has('commission_agent')) {
                $commission_agent = request()->get('commission_agent');
                if (! empty($commission_agent)) {
                    $sells->where('transactions.commission_agent', $commission_agent);
                }
            }

            if (! empty(request()->input('source'))) {
                //only exception for woocommerce
                if (request()->input('source') == 'woocommerce') {
                    $sells->whereNotNull('transactions.woocommerce_order_id');
                } else {
                    $sells->where('transactions.source', request()->input('source'));
                }
            }

            if ($is_crm) {
                $sells->addSelect('transactions.crm_is_order_request');

                if (request()->has('crm_is_order_request')) {
                    $sells->where('transactions.crm_is_order_request', 1);
                }
            }

            if (request()->only_subscriptions) {
                $sells->where(function ($q) {
                    $q->whereNotNull('transactions.recur_parent_id')
                        ->orWhere('transactions.is_recurring', 1);
                });
            }

            if (! empty(request()->list_for) && request()->list_for == 'service_staff_report') {
                $sells->whereNotNull('transactions.res_waiter_id');
            }

            if (! empty(request()->res_waiter_id)) {
                $sells->where('transactions.res_waiter_id', request()->res_waiter_id);
            }

            if (! empty(request()->input('sub_type'))) {
                $sells->where('transactions.sub_type', request()->input('sub_type'));
            }

            if (! empty(request()->input('created_by'))) {
                $sells->where('transactions.created_by', request()->input('created_by'));
            }

            // TEMPORARILY DISABLED - Show all statuses
            /*
            if (! empty(request()->input('status'))) {
                $sells->where('transactions.status', request()->input('status'));
            }
            */

            if (! empty(request()->input('sales_cmsn_agnt'))) {
                $sells->where('transactions.commission_agent', request()->input('sales_cmsn_agnt'));
            }

            if (! empty(request()->input('service_staffs'))) {
                $sells->where('transactions.res_waiter_id', request()->input('service_staffs'));
            }

            $only_pending_shipments = request()->only_pending_shipments == 'true' ? true : false;
            if ($only_pending_shipments) {
                $sells->where('transactions.shipping_status', '!=', 'delivered')
                        ->whereNotNull('transactions.shipping_status');
                $only_shipments = true;
            }

            if (! empty(request()->input('shipping_status'))) {
                $sells->where('transactions.shipping_status', request()->input('shipping_status'));
            }

            if (! empty(request()->input('for_dashboard_sales_order'))) {
                $sells->whereIn('transactions.status', ['partial', 'ordered'])
                    ->orHavingRaw('so_qty_remaining > 0');
            }

            if ($sale_type == 'sales_order') {
                if (! auth()->user()->can('so.view_all') && auth()->user()->can('so.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            }

            if (! empty(request()->input('delivery_person'))) {
                $sells->where('transactions.delivery_person', request()->input('delivery_person'));
            }

            // Add document type filter
            if (request()->has('document_types') && !empty(request()->input('document_types'))) {
                $document_types = request()->input('document_types');
                
                if (is_array($document_types) && count($document_types) > 0) {
                    $sells->where(function($query) use ($document_types) {
                        if (in_array('final_bill', $document_types) && in_array('proforma', $document_types)) {
                            // Both selected - show both final bills and proforma invoices (default behavior)
                            // No additional filtering needed since getListSells already filters correctly
                        } elseif (in_array('final_bill', $document_types)) {
                            // Only final bills (IPAY)
                            $query->where('transactions.status', 'final');
                        } elseif (in_array('proforma', $document_types)) {
                            // Only proforma invoices (VT)
                            $query->where('transactions.status', 'draft')
                                  ->whereNull('transactions.payment_status')
                                  ->where(function($proformaQuery) {
                                      $proformaQuery->where('transactions.sub_status', 'proforma')
                                                   ->orWhere('transactions.document_type', 'proforma');
                                  });
                        }
                    });
                }
            }

            $sells->groupBy('transactions.id');

            if (! empty(request()->suspended)) {
                $transaction_sub_type = request()->get('transaction_sub_type');
                if (! empty($transaction_sub_type)) {
                    $sells->where('transactions.sub_type', $transaction_sub_type);
                } else {
                    $sells->where('transactions.sub_type', null);
                }

                $with = ['sell_lines'];

                if ($is_tables_enabled) {
                    $with[] = 'table';
                }

                if ($is_service_staff_enabled) {
                    $with[] = 'service_staff';
                }

                $sales = $sells->where('transactions.is_suspend', 1)
                            ->with($with)
                            ->addSelect('transactions.is_suspend', 'transactions.res_table_id', 'transactions.res_waiter_id', 'transactions.additional_notes')
                            ->get();

                return view('sale_pos.partials.suspended_sales_modal')->with(compact('sales', 'is_tables_enabled', 'is_service_staff_enabled', 'transaction_sub_type'));
            }

            $with[] = 'payment_lines';
            
            if (!empty($with)) {
                foreach ($with as $relation) {
                    if ($relation == 'payment_lines' && !empty(request()->input('payment_method'))) {
                        $sells->whereHas($relation, function ($query) {
                            $query->where('method', request()->input('payment_method'));
                        });
                    } else {
                        $sells->with($relation);
                    }
                }
            }

            //$business_details = $this->businessUtil->getDetails($business_id);
            if ($this->businessUtil->isModuleEnabled('subscription')) {
                $sells->addSelect('transactions.is_recurring', 'transactions.recur_parent_id');
            }
            $sales_order_statuses = Transaction::sales_order_statuses();
            // DEBUG: Log the final query and count
            $query_sql = $sells->toSql();
            $query_bindings = $sells->getBindings();
            \Log::info('SellController - Final Query SQL: ' . $query_sql);
            \Log::info('SellController - Query Bindings: ' . print_r($query_bindings, true));
            
            $total_count = $sells->count();
            \Log::info('SellController - Total records found: ' . $total_count);
            
            $datatable = Datatables::of($sells)
                ->addColumn(
                    'action',
                    function ($row) use ($only_shipments, $is_admin, $sale_type) {
                        $html = '<div class="btn-group">
                                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                                        data-toggle="dropdown" aria-expanded="false">'.
                                        __('messages.actions').
                                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                        </span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.view') || auth()->user()->can('view_own_sell_only')) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                        }
                        if (! $only_shipments) {
                            if ($row->is_direct_sale == 0) {
                                if (auth()->user()->can('sell.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            } elseif ($row->type == 'sales_order') {
                                if (auth()->user()->can('so.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            } else {
                                if (auth()->user()->can('direct_sell.update')) {
                                    $html .= '<li><a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'"><i class="fas fa-edit"></i> '.__('messages.edit').'</a></li>';
                                }
                            }

                            $delete_link = '<li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i> '.__('messages.delete').'</a></li>';
                            if ($row->is_direct_sale == 0) {
                                if (auth()->user()->can('sell.delete')) {
                                    $html .= $delete_link;
                                }
                            } elseif ($row->type == 'sales_order') {
                                if (auth()->user()->can('so.delete')) {
                                    $html .= $delete_link;
                                }
                            } else {
                                if (auth()->user()->can('direct_sell.delete')) {
                                    $html .= $delete_link;
                                }
                            }
                        }

                        if (config('constants.enable_download_pdf') && auth()->user()->can('print_invoice') && $sale_type != 'sales_order') {
                            $html .= '<li><a href="'.route('sell.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_pdf').'</a></li>';

                            if (! empty($row->shipping_status)) {
                                $html .= '<li><a href="'.route('packing.downloadPdf', [$row->id]).'" target="_blank"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.download_paking_pdf').'</a></li>';
                            }
                        }

                        // Single-document print actions for VT rows, with legacy IPAY support.
                        if (auth()->user()->can('print_invoice') && $sale_type != 'sales_order') {
                            $invoice_no = (string) ($row->invoice_no ?? '');
                            $is_vt_document = str_starts_with($invoice_no, 'VT') || $row->document_type == 'proforma' || $row->sub_status == 'proforma';
                            $is_ipay_document = str_starts_with($invoice_no, 'IPAY');

                            if ($is_vt_document) {
                                $html .= '<li><a href="'.route('tax-invoice.pdfprint.nodejs', ['id' => $row->id]).'" class="pdf-print-btn"><i class="fas fa-file-invoice" aria-hidden="true"></i> Print Tax Invoice</a></li>';
                                $html .= '<li><a href="'.route('billing-receipt.pdfprint.nodejs', ['id' => $row->id]).'" class="pdf-print-btn"><i class="fas fa-receipt" aria-hidden="true"></i> Print Billing Receipt</a></li>';
                            } elseif ($row->status == 'final' || $is_ipay_document) {
                                $html .= '<li><a href="'.route('billing-receipt.pdfprint.nodejs', ['id' => $row->id]).'" class="pdf-print-btn"><i class="fas fa-receipt" aria-hidden="true"></i> Print Billing Receipt</a></li>';
                            }
                        }

                        if (auth()->user()->can('sell.view') || auth()->user()->can('direct_sell.access')) {
                            if (! empty($row->document)) {
                                $document_name = ! empty(explode('_', $row->document, 2)[1]) ? explode('_', $row->document, 2)[1] : $row->document;
                                $html .= '<li><a href="'.url('uploads/documents/'.$row->document).'" download="'.$document_name.'"><i class="fas fa-download" aria-hidden="true"></i>'.__('purchase.download_document').'</a></li>';
                                if (isFileImage($document_name)) {
                                    $html .= '<li><a href="#" data-href="'.url('uploads/documents/'.$row->document).'" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>'.__('lang_v1.view_document').'</a></li>';
                                }
                            }
                        }

                        if ($is_admin || auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-truck" aria-hidden="true"></i>'.__('lang_v1.edit_shipping').'</a></li>';
                        }

                        if ($row->type == 'sell') {
                            if (auth()->user()->can('print_invoice')) {
                                $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'"><i class="fas fa-print" aria-hidden="true"></i> '.__('lang_v1.print_invoice').'</a></li>
                                    <li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?package_slip=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.packing_slip').'</a></li>';

                                $html .= '<li><a href="#" class="print-invoice" data-href="'.route('sell.printInvoice', [$row->id]).'?delivery_note=true"><i class="fas fa-file-alt" aria-hidden="true"></i> '.__('lang_v1.delivery_note').'</a></li>';
                            }
                            $html .= '<li class="divider"></li>';
                            if (! $only_shipments) {
                                if ($row->is_direct_sale == 0 && ! auth()->user()->can('sell.update') &&
                                auth()->user()->can('edit_pos_payment')) {
                                    $html .= '<li><a href="'.route('edit-pos-payment', [$row->id]).'" 
                                    ><i class="fas fa-money-bill-alt"></i> '.__('lang_v1.add_edit_payment').
                                    '</a></li>';
                                }

                                if (auth()->user()->can('sell.payments') ||
                                    auth()->user()->can('edit_sell_payment') ||
                                    auth()->user()->can('delete_sell_payment')) {
                                    if ($row->payment_status != 'paid') {
                                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]).'" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.add_payment').'</a></li>';
                                    }

                                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->id]).'" class="view_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('purchase.view_payments').'</a></li>';
                                }

                                if (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) {
                                    // $html .= '<li><a href="' . action([\App\Http\Controllers\SellController::class, 'duplicateSell'], [$row->id]) . '"><i class="fas fa-copy"></i> ' . __("lang_v1.duplicate_sell") . '</a></li>';

                                    $html .= '<li><a href="'.action([\App\Http\Controllers\SellReturnController::class, 'add'], [$row->id]).'"><i class="fas fa-undo"></i> '.__('lang_v1.sell_return').'</a></li>

                                    <li><a href="'.action([\App\Http\Controllers\SellPosController::class, 'showInvoiceUrl'], [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i> '.__('lang_v1.view_invoice_url').'</a></li>';
                                }
                            }

                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\NotificationController::class, 'getTemplate'], ['transaction_id' => $row->id, 'template_for' => 'new_sale']).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>'.__('lang_v1.new_sale_notification').'</a></li>';
                        } else {
                            $html .= '<li><a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'viewMedia'], ['model_id' => $row->id, 'model_type' => \App\Transaction::class, 'model_media_type' => 'shipping_document']).'" class="btn-modal" data-container=".view_modal"><i class="fas fa-paperclip" aria-hidden="true"></i>'.__('lang_v1.shipping_documents').'</a></li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    }
                )
->addColumn(
                    'quick_action',
                    function ($row) use ($sale_type) {
                        $html = '';
                        
                        if (auth()->user()->can('print_invoice') && $sale_type != 'sales_order') {
                            $invoice_no = (string) ($row->invoice_no ?? '');
                            $is_vt_document = str_starts_with($invoice_no, 'VT') || $row->document_type == 'proforma' || $row->sub_status == 'proforma';
                            $is_ipay_document = str_starts_with($invoice_no, 'IPAY');

                            if ($is_vt_document) {
                                $html = '<a href="'.route('tax-invoice.pdfprint.nodejs', ['id' => $row->id]).'" class="btn btn-xs btn-success pdf-print-btn quick-action-btn" title="Print Tax Invoice">
                                    <i class="fas fa-file-invoice"></i>
                                </a>';
                                $html .= ' <a href="'.route('billing-receipt.pdfprint.nodejs', ['id' => $row->id]).'" class="btn btn-xs btn-info pdf-print-btn quick-action-btn" title="Print Billing Receipt">
                                    <i class="fas fa-receipt"></i>
                                </a>';
                            } elseif ($row->status == 'final' || $is_ipay_document) {
                                $html = '<a href="'.route('billing-receipt.pdfprint.nodejs', ['id' => $row->id]).'" class="btn btn-xs btn-info pdf-print-btn quick-action-btn" title="Print Billing Receipt">
                                    <i class="fas fa-receipt"></i>
                                </a>';
                            }
                        }
                        
                        return $html;
                    }
                )
                ->removeColumn('id')
                ->editColumn(
                    'final_total',
                    '<span class="final-total" data-orig-value="{{$final_total}}">@format_currency($final_total)</span>'
                )
                ->editColumn(
                    'tax_amount',
                    '<span class="total-tax" data-orig-value="{{$tax_amount}}">@format_currency($tax_amount)</span>'
                )
                ->editColumn(
                    'total_paid',
                    '<span class="total-paid" data-orig-value="{{$total_paid}}">@format_currency($total_paid)</span>'
                )
                ->editColumn(
                    'total_before_tax',
                    '<span class="total_before_tax" data-orig-value="{{$total_before_tax}}">@format_currency($total_before_tax)</span>'
                )
                ->editColumn(
                    'discount_amount',
                    function ($row) {
                        $discount = ! empty($row->discount_amount) ? $row->discount_amount : 0;

                        if (! empty($discount) && $row->discount_type == 'percentage') {
                            $discount = $row->total_before_tax * ($discount / 100);
                        }

                        return '<span class="total-discount" data-orig-value="'.$discount.'">'.$this->transactionUtil->num_f($discount, true).'</span>';
                    }
                )
                ->editColumn('transaction_date', '{{@format_datetime($transaction_date)}}')
                ->editColumn(
                    'payment_status',
                    function ($row) {
                        $payment_status = Transaction::getPaymentStatus($row);

                        return (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id]);
                    }
                )
                ->editColumn(
                    'types_of_service_name',
                    '<span class="service-type-label" data-orig-value="{{$types_of_service_name}}" data-status-name="{{$types_of_service_name}}">{{$types_of_service_name}}</span>'
                )
                ->addColumn('total_remaining', function ($row) {
                    $total_remaining = $row->final_total - $row->total_paid;
                    $total_remaining_html = '<span class="payment_due" data-orig-value="'.$total_remaining.'">'.$this->transactionUtil->num_f($total_remaining, true).'</span>';

                    return $total_remaining_html;
                })
                ->addColumn('return_due', function ($row) {
                    $return_due_html = '';
                    if (! empty($row->return_exists)) {
                        $return_due = $row->amount_return - $row->return_paid;
                        $return_due_html .= '<a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'show'], [$row->return_transaction_id]).'" class="view_purchase_return_payment_modal"><span class="sell_return_due" data-orig-value="'.$return_due.'">'.$this->transactionUtil->num_f($return_due, true).'</span></a>';
                    }

                    return $return_due_html;
                })
                ->editColumn('invoice_no', function ($row) use ($is_crm) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }
                    if (! empty($row->return_exists)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.some_qty_returned_from_sell').'"><i class="fas fa-undo"></i></small>';
                    }
                    if (! empty($row->is_recurring)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="'.__('lang_v1.subscribed_invoice').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->recur_parent_id)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="'.__('lang_v1.subscription_invoice').'"><i class="fas fa-recycle"></i></small>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    if ($is_crm && ! empty($row->crm_is_order_request)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-yellow label-round no-print" title="'.__('crm::lang.order_request').'"><i class="fas fa-tasks"></i></small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('shipping_status', function ($row) use ($shipping_statuses) {
                    $status_color = ! empty($this->shipping_status_colors[$row->shipping_status]) ? $this->shipping_status_colors[$row->shipping_status] : 'bg-gray';
                    $status = ! empty($row->shipping_status) ? '<a href="#" class="btn-modal" data-href="'.action([\App\Http\Controllers\SellController::class, 'editShipping'], [$row->id]).'" data-container=".view_modal"><span class="label '.$status_color.'">'.$shipping_statuses[$row->shipping_status].'</span></a>' : '';

                    return $status;
                })
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br> @endif {{$name}}')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->addColumn('payment_methods', function ($row) use ($payment_types) {
                    $methods = array_unique($row->payment_lines->pluck('method')->toArray());
                    $count = count($methods);
                    $payment_method = '';
                    if ($count == 1) {
                        $payment_method = $payment_types[$methods[0]] ?? '';
                    } elseif ($count > 1) {
                        $payment_method = __('lang_v1.checkout_multi_pay');
                    }

                    $html = ! empty($payment_method) ? '<span class="payment-method" data-orig-value="'.$payment_method.'" data-status-name="'.$payment_method.'">'.$payment_method.'</span>' : '';

                    return $html;
                })
                ->addColumn('payment_ref_nos', function ($row) {
                    $refs = $row->payment_lines->pluck('payment_ref_no')->filter()->unique()->toArray();
                    if (empty($refs)) {
                        return '--';
                    }
                    
                    $html = '';
                    foreach ($refs as $ref) {
                        $html .= '<span class="label label-info" style="margin: 2px; display: inline-block;">' . $ref . '</span>';
                    }
                    return $html;
                })
                ->editColumn('status', function ($row) use ($sales_order_statuses, $is_admin) {
                    $status = '';

                    if ($row->type == 'sales_order') {
                        if ($is_admin && $row->status != 'completed') {
                            $status = '<span class="edit-so-status label '.$sales_order_statuses[$row->status]['class'].'" data-href="'.action([\App\Http\Controllers\SalesOrderController::class, 'getEditSalesOrderStatus'], ['id' => $row->id]).'">'.$sales_order_statuses[$row->status]['label'].'</span>';
                        } else {
                            $status = '<span class="label '.$sales_order_statuses[$row->status]['class'].'" >'.$sales_order_statuses[$row->status]['label'].'</span>';
                        }
                    }

                    return $status;
                })
                ->editColumn('so_qty_remaining', '{{@format_quantity($so_qty_remaining)}}')
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view') || auth()->user()->can('view_own_sell_only')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ]);

            $rawColumns = ['final_total', 'action', 'quick_action', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'discount_amount', 'tax_amount', 'total_before_tax', 'shipping_status', 'types_of_service_name', 'payment_methods', 'payment_ref_nos', 'return_due', 'conatct_name', 'status'];

            return $datatable->rawColumns($rawColumns)
                      ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $sales_representative = User::forDropdown($business_id, false, false, true);

        //Commission agent filter
        $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
        $commission_agents = [];
        if (! empty($is_cmsn_agent_enabled)) {
            $commission_agents = User::forDropdown($business_id, false, true, true);
        }

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $sources = $this->transactionUtil->getSources($business_id);
        if ($is_woocommerce) {
            $sources['woocommerce'] = 'Woocommerce';
        }

        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);


        return view('sell.index')
        ->with(compact('business_locations', 'customers', 'is_woocommerce', 'sales_representative', 'is_cmsn_agent_enabled', 'commission_agents', 'service_staffs', 'is_tables_enabled', 'is_service_staff_enabled', 'is_types_service_enabled', 'shipping_statuses', 'sources', 'payment_types'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $sale_type = request()->get('sale_type', '');

        if ($sale_type == 'sales_order') {
            if (! auth()->user()->can('so.create')) {
                abort(403, 'Unauthorized action.');
            }
        } else {
            if (! auth()->user()->can('direct_sell.access')) {
                abort(403, 'Unauthorized action.');
            }
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not, then check for users quota
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        } elseif (! $this->moduleUtil->isQuotaAvailable('invoices', $business_id)) {
            return $this->moduleUtil->quotaExpiredResponse('invoices', $business_id, action([\App\Http\Controllers\SellController::class, 'index']));
        }

        $walk_in_customer = $this->contactUtil->getWalkInCustomer($business_id);

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $business_locations = BusinessLocation::forDropdown($business_id, false, true);
        $bl_attributes = $business_locations['attributes'];
        $business_locations = $business_locations['locations'];

        $default_location = null;
        foreach ($business_locations as $id => $name) {
            $default_location = BusinessLocation::findOrFail($id);
            break;
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
        }

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);

        $payment_line = $this->dummyPaymentLine;
        $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

        //Selling Price Group Dropdown
        $price_groups = SellingPriceGroup::forDropdown($business_id);

        $default_price_group_id = ! empty($default_location->selling_price_group_id) && array_key_exists($default_location->selling_price_group_id, $price_groups) ? $default_location->selling_price_group_id : null;

        $default_datetime = $this->businessUtil->format_date('now', true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $invoice_schemes = InvoiceScheme::forDropdown($business_id);
        $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        if (! empty($default_location) && !empty($default_location->sale_invoice_scheme_id)) {
            $default_invoice_schemes = InvoiceScheme::where('business_id', $business_id)
                                        ->findorfail($default_location->sale_invoice_scheme_id);
        }
        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        //Types of service
        $types_of_service = [];
        if ($this->moduleUtil->isModuleEnabled('types_of_service')) {
            $types_of_service = TypesOfService::forDropdown($business_id);
        }

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $status = request()->get('status', '');

        $statuses = Transaction::sell_statuses();

        if ($sale_type == 'sales_order') {
            $status = 'ordered';
        }

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        $change_return = $this->dummyPaymentLine;

        // Get customer sources for radio buttons (full objects with logo)
        $customer_sources = \App\CustomerSource::getActiveForBusiness($business_id);

        // Get all users for responsible salesperson dropdown
        $all_users = User::forDropdown($business_id, false, false, false, true);

        return view('sell.create')
            ->with(compact(
                'business_details',
                'taxes',
                'walk_in_customer',
                'business_locations',
                'bl_attributes',
                'default_location',
                'commission_agent',
                'types',
                'customer_groups',
                'payment_line',
                'payment_types',
                'price_groups',
                'default_datetime',
                'pos_settings',
                'invoice_schemes',
                'default_invoice_schemes',
                'types_of_service',
                'accounts',
                'shipping_statuses',
                'status',
                'sale_type',
                'statuses',
                'is_order_request_enabled',
                'users',
                'default_price_group_id',
                'change_return',
                'customer_sources',
                'all_users'
            ));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // if (!auth()->user()->can('sell.view') && !auth()->user()->can('direct_sell.access') && !auth()->user()->can('view_own_sell_only')) {
        //     abort(403, 'Unauthorized action.');
        // }

        $business_id = request()->session()->get('user.business_id');
        $taxes = TaxRate::where('business_id', $business_id)
                            ->pluck('name', 'id');
        $query = Transaction::where('business_id', $business_id)
                    ->where('id', $id)
                    ->with(['contact', 'delivery_person_user', 'sell_lines' => function ($q) {
                        $q->whereNull('parent_sell_line_id');
                    }, 'sell_lines.product', 'sell_lines.product.unit', 'sell_lines.product.second_unit', 'sell_lines.variations', 'sell_lines.variations.product_variation', 'payment_lines', 'sell_lines.modifiers', 'sell_lines.lot_details', 'tax', 'sell_lines.sub_unit', 'table', 'service_staff', 'sell_lines.service_staff', 'types_of_service', 'sell_lines.warranties', 'media']);

        if (! auth()->user()->can('sell.view') && ! auth()->user()->can('direct_sell.access') && auth()->user()->can('view_own_sell_only')) {
            $query->where('transactions.created_by', request()->session()->get('user.id'));
        }

        $sell = $query->firstOrFail();

        $activities = Activity::forSubject($sell)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        $line_taxes = [];
        foreach ($sell->sell_lines as $key => $value) {
            if (! empty($value->sub_unit_id)) {
                $formated_sell_line = $this->transactionUtil->recalculateSellLineTotals($business_id, $value);
                $sell->sell_lines[$key] = $formated_sell_line;
            }

            if (! empty($taxes[$value->tax_id])) {
                if (isset($line_taxes[$taxes[$value->tax_id]])) {
                    $line_taxes[$taxes[$value->tax_id]] += ($value->item_tax * $value->quantity);
                } else {
                    $line_taxes[$taxes[$value->tax_id]] = ($value->item_tax * $value->quantity);
                }
            }
        }

        $payment_types = $this->transactionUtil->payment_types($sell->location_id, true);
        $order_taxes = [];
        if (! empty($sell->tax)) {
            if ($sell->tax->is_tax_group) {
                $order_taxes = $this->transactionUtil->sumGroupTaxDetails($this->transactionUtil->groupTaxDetails($sell->tax, $sell->tax_amount));
            } else {
                $order_taxes[$sell->tax->name] = $sell->tax_amount;
            }
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);
        $shipping_statuses = $this->transactionUtil->shipping_statuses();
        $shipping_status_colors = $this->shipping_status_colors;
        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;

        $statuses = Transaction::sell_statuses();

        if ($sell->type == 'sales_order') {
            $sales_order_statuses = Transaction::sales_order_statuses(true);
            $statuses = array_merge($statuses, $sales_order_statuses);
        }
        $status_color_in_activity = Transaction::sales_order_statuses();
        $sales_orders = $sell->salesOrders();

        return view('sale_pos.show')
            ->with(compact(
                'taxes',
                'sell',
                'payment_types',
                'order_taxes',
                'pos_settings',
                'shipping_statuses',
                'shipping_status_colors',
                'is_warranty_enabled',
                'activities',
                'statuses',
                'status_color_in_activity',
                'sales_orders',
                'line_taxes'
            ));
    }

    /**
     * Export current modal transaction data to Excel (with embedded images).
     *
     * @param  int  $id
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportModalExcel($id)
    {
        $user = auth()->user();
        $can_sell_all = $user->can('sell.view') || $user->can('direct_sell.access');
        $can_sell_own = $user->can('view_own_sell_only');
        $can_quotation_all = $user->can('quotation.view_all');
        $can_quotation_own = $user->can('quotation.view_own');

        if (! $can_sell_all && ! $can_sell_own && ! $can_quotation_all && ! $can_quotation_own) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $user_id = (int) request()->session()->get('user.id');

        $query = Transaction::where('business_id', $business_id)
            ->where('id', $id)
            ->with([
                'contact',
                'delivery_person_user',
                'sell_lines' => function ($q) {
                    $q->whereNull('parent_sell_line_id');
                },
                'sell_lines.product',
                'sell_lines.product.unit',
                'sell_lines.product.second_unit',
                'sell_lines.variations',
                'sell_lines.variations.product_variation',
                'payment_lines',
                'sell_lines.modifiers',
                'sell_lines.lot_details',
                'tax',
                'sell_lines.sub_unit',
                'table',
                'service_staff',
                'sell_lines.service_staff',
                'types_of_service',
                'sell_lines.warranties',
                'media',
            ]);

        $sell = $query->firstOrFail();
        $invoice_no = (string) ($sell->invoice_no ?? '');
        $is_vt_document = str_starts_with($invoice_no, 'VT')
            || $sell->document_type === 'proforma'
            || $sell->sub_status === 'proforma';
        $is_quotation_document = ((int) ($sell->is_quotation ?? 0) === 1)
            || $sell->document_type === 'quotation'
            || $sell->sub_status === 'quotation';

        if (! $is_vt_document && ! $is_quotation_document) {
            abort(422, 'Only VT or quotation documents can be exported from this button.');
        }

        $is_owner = ((int) ($sell->created_by ?? 0) === $user_id);
        if ($is_vt_document) {
            $can_view_this = $can_sell_all || ($can_sell_own && $is_owner);
        } else {
            $can_view_this = $can_quotation_all
                || ($can_quotation_own && $is_owner)
                || $can_sell_all
                || ($can_sell_own && $is_owner);
        }

        if (! $can_view_this) {
            abort(403, 'Unauthorized action.');
        }

        $document_type = $is_quotation_document ? 'quotation' : 'proforma';

        $payment_types = $this->transactionUtil->payment_types($sell->location_id, true);
        $order_taxes = [];
        if (! empty($sell->tax)) {
            if ($sell->tax->is_tax_group) {
                $order_taxes = $this->transactionUtil->sumGroupTaxDetails(
                    $this->transactionUtil->groupTaxDetails($sell->tax, $sell->tax_amount)
                );
            } else {
                $order_taxes[$sell->tax->name] = $sell->tax_amount;
            }
        }

        $business_details = $this->businessUtil->getDetails($business_id);
        $context = $this->resolveModalDocumentContext($sell);

        $spreadsheet = new Spreadsheet();

        $summary_sheet = $spreadsheet->getActiveSheet();
        $summary_sheet->setTitle('Summary_Items');
        $this->buildModalSummarySheet($summary_sheet, $sell, $order_taxes, $payment_types, $business_details, $context, $document_type);
        $items_start_row = 11;
        $this->appendModalItemsTable($summary_sheet, $sell, $items_start_row);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'invoice_' . $this->sanitizeFilenamePart($invoice_no) . '_' . $document_type . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            $this->cleanupModalExcelTempImages();
            unset($spreadsheet);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
            'Pragma' => 'public',
        ]);
    }

    private function buildModalSummarySheet(
        Worksheet $sheet,
        Transaction $sell,
        array $order_taxes,
        array $payment_types,
        $business_details,
        array $context,
        string $document_type
    ): void {
        $red = 'FFFF2D2D';
        $dark_red = 'FFB91C1C';
        $light_red = 'FFFFE9E9';
        $border = 'FF7F1D1D';
        $dark_text = 'FF1F2933';

        $sheet->setShowGridlines(false);
        $sheet->freezePane(null);
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setLeft(0.25)->setBottom(0.35);

        $column_widths = [
            'A' => 6,
            'B' => 20,
            'C' => 52,
            'D' => 16,
            'E' => 10,
            'F' => 12,
        ];
        foreach ($column_widths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getDefaultRowDimension()->setRowHeight(19);
        $sheet->getStyle('A1:F80')->getFont()->setName('Arial')->setSize(10)->getColor()->setARGB($dark_text);

        $sheet->mergeCells('A1:B2');
        $logo_embedded = $this->tryEmbedImage($sheet, 'https://www.rubyshop.co.th/storage/logo/rubyshop-no-bg-white-footer-1.png', 'A1', 42, 150);
        if (! $logo_embedded) {
            $sheet->setCellValue('A1', strtoupper((string) ($business_details->name ?? 'RUBYSHOP')));
        }
        $sheet->getStyle('A1:B2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $red]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => $border]]],
        ]);

        $sheet->mergeCells('C1:F2');
        $sheet->setCellValue('C1', strtoupper((string) ($context['doc_type_en'] ?? 'QUOTATION')));
        $sheet->getStyle('C1:F2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 18, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $red]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => $border]]],
        ]);

        $company_lines = array_filter([
            strtoupper((string) ($business_details->name ?? '')),
            ! empty($business_details->tax_number) ? 'Tax ID: ' . $business_details->tax_number : '',
            implode(', ', array_filter([
                $business_details->landmark ?? '',
                $business_details->city ?? '',
                $business_details->state ?? '',
                $business_details->country ?? '',
                $business_details->zip_code ?? '',
            ])),
            ! empty($business_details->mobile) ? 'Phone: ' . $business_details->mobile : '',
            ! empty($business_details->email) ? 'Email: ' . $business_details->email : '',
        ]);

        $sheet->mergeCells('A3:C3');
        $sheet->mergeCells('D3:F3');
        $sheet->mergeCells('A4:C4');
        $sheet->mergeCells('D4:F4');
        $sheet->setCellValue('A3', 'QUOTATION NO.');
        $sheet->setCellValue('D3', 'DATE');
        $sheet->setCellValue('A4', (string) ($sell->invoice_no ?? ''));
        $sheet->setCellValue('D4', $this->transactionUtil->format_date($sell->transaction_date, true));
        $sheet->getStyle('A3:F3')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $red]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $border]]],
        ]);
        $sheet->getStyle('A4:F4')->applyFromArray([
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $border]]],
        ]);

        $total_paid = 0.0;
        foreach ($sell->payment_lines as $payment_line) {
            $total_paid += ((int) $payment_line->is_return === 1 ? -1 : 1) * (float) $payment_line->amount;
        }

        $billing_address_text = '';
        $billing_address = $sell->billing_address(true);
        if (is_array($billing_address)) {
            unset($billing_address['name'], $billing_address['company']);
            $billing_address_text = implode(', ', array_filter($billing_address));
        }

        $customer_name = '';
        if (! empty($sell->contact)) {
            $supplier_name = $sell->contact->supplier_business_name ?? '';
            $contact_name = $sell->contact->name ?? '';
            if (! empty($supplier_name) && $supplier_name !== $contact_name) {
                $customer_name = $supplier_name . ' / ' . $contact_name;
            } else {
                $customer_name = $contact_name;
            }
        }

        $customer_lines = array_filter([
            $customer_name,
        ]);

        $from_lines = [
            'RUBYSHOP LIMITED PARTNERSHIP',
            '97/60 Laksi Land Village, Soi Kosum Ruamjai 39',
            'Don Mueang Subdistrict, Don Mueang District',
            'Bangkok 10210, Thailand',
        ];

        $sheet->mergeCells('A6:C6');
        $sheet->mergeCells('D6:F6');
        $sheet->setCellValue('A6', 'FROM');
        $sheet->setCellValue('D6', 'TO');
        $sheet->getStyle('A6:C6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $red]],
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => $border]]],
        ]);
        $sheet->getStyle('D6:F6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $red]],
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => $border]]],
        ]);

        $sheet->mergeCells('A7:C9');
        $sheet->mergeCells('D7:F9');
        $sheet->setCellValue('A7', implode("\n", $from_lines));
        $sheet->setCellValue('D7', implode("\n", $customer_lines));
        $sheet->getStyle('A7:C9')->applyFromArray([
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP, 'wrapText' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => $border]]],
        ]);
        $sheet->getStyle('D7:F9')->applyFromArray([
            'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP, 'wrapText' => true],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => $border]]],
        ]);

        if (! empty($context['related_vt_invoice_no']) || ! empty($context['related_ipay_invoice_no'])) {
            $sheet->mergeCells('A13:B13');
            $sheet->mergeCells('D13:E13');
            $sheet->setCellValue('A13', 'RELATED TAX-INVOICE');
            $sheet->setCellValue('C13', (string) ($context['related_vt_invoice_no'] ?? ''));
            $sheet->setCellValue('D13', 'RELATED BILLING-RECEIVE');
            $sheet->setCellValue('F13', (string) ($context['related_ipay_invoice_no'] ?? ''));
            $sheet->getStyle('A13:F13')->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $border]]],
            ]);
            $sheet->getStyle('A13:B13')->getFont()->setBold(true);
            $sheet->getStyle('D13:E13')->getFont()->setBold(true);
        }
    }

    private function appendModalItemsTable(Worksheet $sheet, Transaction $sell, int $start_row): void
    {
        $red = 'FFFF2D2D';
        $dark_red = 'FFB91C1C';
        $light_red = 'FFFFEFEF';
        $border = 'FF9F1D1D';

        $headers = ['#', 'Product Image', 'Description', 'SKU', 'Qty', 'Unit'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columns[$index] . $start_row, $header);
        }

        $sheet->getStyle('A' . $start_row . ':F' . $start_row)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $red]],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER, 'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $border]]],
        ]);
        $sheet->getStyle('B:F')->getAlignment()->setWrapText(true);

        $item_column_widths = [
            'A' => 6,
            'B' => 20,
            'C' => 52,
            'D' => 16,
            'E' => 10,
            'F' => 10,
        ];
        foreach ($item_column_widths as $column => $width) {
            $current_width = (float) $sheet->getColumnDimension($column)->getWidth();
            if ($current_width < $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }

        $row_no = $start_row + 1;
        $subtotal = 0.0;
        foreach ($sell->sell_lines as $index => $sell_line) {
            $description = '';
            if (! empty($sell_line->product)) {
                $description = trim((string) ($sell_line->product->second_name ?? ''));
                if ($description === '') {
                    $description = (string) $sell_line->product->name;
                }
                $variation_parts = [];
                $product_variation_name = (string) ($sell_line->variations->product_variation->name ?? '');
                $variation_name = (string) ($sell_line->variations->name ?? '');

                if (! $this->isDummyLabel($product_variation_name)) {
                    $variation_parts[] = trim($product_variation_name);
                }
                if (! $this->isDummyLabel($variation_name)) {
                    $variation_parts[] = trim($variation_name);
                }

                $variation_parts = array_values(array_unique(array_filter($variation_parts)));
                if (! empty($variation_parts)) {
                    $description .= ' - ' . implode(' - ', $variation_parts);
                }
            } else {
                $description = 'Product Label (Missing)';
            }

            $sku = (string) ($sell_line->variations->sub_sku ?? '');
            $quantity = (float) ($sell_line->quantity ?? 0);
            $unit_name = $this->translateUnitName((string) ($sell_line->sub_unit->short_name ?? ($sell_line->product->unit->short_name ?? '')));
            $unit_price = (float) ($sell_line->unit_price_before_discount ?? 0);
            $amount = $quantity * (float) ($sell_line->unit_price_inc_tax ?? 0);
            $image_url = (string) ($sell_line->product->image_url ?? '');
            $subtotal += $quantity * $unit_price;

            $sheet->setCellValue('A' . $row_no, $index + 1);
            $sheet->setCellValue('C' . $row_no, $description);
            $sheet->setCellValue('D' . $row_no, $sku);
            $sheet->setCellValue('E' . $row_no, $quantity);
            $sheet->setCellValue('F' . $row_no, $unit_name);

            $image_embedded = $this->tryEmbedImage($sheet, $image_url, 'B' . $row_no, 98, 125, $image_url);
            if (! $image_embedded) {
                $sheet->setCellValue('B' . $row_no, 'Image unavailable');
            } else {
                $sheet->getRowDimension($row_no)->setRowHeight(102);
            }

            $sheet->getStyle('A' . $row_no . ':F' . $row_no)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['argb' => $border]]],
                'alignment' => ['vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            $sheet->getStyle('A' . $row_no)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row_no . ':F' . $row_no)->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            $row_no++;
        }

        if ($row_no === $start_row + 1) {
            $sheet->setCellValue('A' . ($start_row + 1), 'No item rows found');
            $row_no++;
        }

        $last_row = max($start_row, $row_no - 1);
        $sheet->getStyle('A1:F' . $last_row)->applyFromArray([
            'borders' => ['outline' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF000000']]],
        ]);
    }

    private function buildModalImageLinksSheet(Worksheet $sheet, Transaction $sell): void
    {
        $sheet->setShowGridlines(false);
        $headers = ['#', 'Description', 'SKU', 'Raw Image URL'];
        $columns = ['A', 'B', 'C', 'D'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columns[$index] . '1', $header);
        }

        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(48);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(90);
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFF2D2D']],
        ]);
        $sheet->getStyle('A:D')->getAlignment()->setWrapText(true);

        $row_no = 2;
        foreach ($sell->sell_lines as $index => $sell_line) {
            $description = (string) ($sell_line->product->name ?? 'Product Label (Missing)');
            $sku = (string) ($sell_line->variations->sub_sku ?? '');
            $image_url = (string) ($sell_line->product->image_url ?? '');

            $sheet->setCellValue('A' . $row_no, $index + 1);
            $sheet->setCellValue('B' . $row_no, $description);
            $sheet->setCellValue('C' . $row_no, $sku);
            $sheet->setCellValue('D' . $row_no, $image_url);
            $row_no++;
        }
    }

    private function appendModalPaymentsTable(Worksheet $sheet, Transaction $sell, array $payment_types, int $start_row): void
    {
        $headers = ['#', 'Paid On', 'Reference No', 'Amount', 'Method', 'Note', 'Slip Image', 'Slip URL'];
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($headers as $index => $header) {
            $sheet->setCellValue($columns[$index] . $start_row, $header);
        }

        $sheet->getStyle('A' . $start_row . ':H' . $start_row)->getFont()->setBold(true);
        $sheet->getStyle('B:H')->getAlignment()->setWrapText(true);

        $payment_column_widths = [
            'A' => 6,
            'B' => 22,
            'C' => 22,
            'D' => 14,
            'E' => 18,
            'F' => 30,
            'G' => 18,
            'H' => 50,
        ];
        foreach ($payment_column_widths as $column => $width) {
            $current_width = (float) $sheet->getColumnDimension($column)->getWidth();
            if ($current_width < $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
        }

        $row_no = $start_row + 1;
        foreach ($sell->payment_lines as $index => $payment_line) {
            $method_key = (string) ($payment_line->method ?? '');
            $method_label = $payment_types[$method_key] ?? $method_key;
            $document_path = (string) ($payment_line->document_path ?? '');

            $sheet->setCellValue('A' . $row_no, $index + 1);
            $sheet->setCellValue('B' . $row_no, ! empty($payment_line->paid_on) ? $this->transactionUtil->format_date($payment_line->paid_on, true) : '');
            $sheet->setCellValue('C' . $row_no, (string) ($payment_line->payment_ref_no ?? ''));
            $sheet->setCellValue('D' . $row_no, ((int) $payment_line->is_return === 1 ? -1 : 1) * (float) ($payment_line->amount ?? 0));
            $sheet->setCellValue('E' . $row_no, $method_label);
            $sheet->setCellValue('F' . $row_no, strip_tags((string) ($payment_line->note ?? '')));
            $sheet->setCellValue('H' . $row_no, $document_path);

            $is_image = $this->isImageSource($document_path);
            if ($is_image && $this->tryEmbedImage($sheet, $document_path, 'G' . $row_no)) {
                $sheet->getRowDimension($row_no)->setRowHeight(80);
            } elseif (! empty($document_path)) {
                $sheet->setCellValue('G' . $row_no, $is_image ? 'Image unavailable' : 'Not an image document');
            } else {
                $sheet->setCellValue('G' . $row_no, '');
            }

            $row_no++;
        }

        if ($row_no === $start_row + 1) {
            $sheet->setCellValue('A' . ($start_row + 1), 'No payment rows found');
        }
    }

    private function getModalPaymentsStartRow(Transaction $sell, int $items_start_row): int
    {
        $item_rows = max(1, $sell->sell_lines->count());

        return $items_start_row + $item_rows + 14;
    }

    private function resolveModalDocumentContext(Transaction $sell): array
    {
        $invoice_no = (string) ($sell->invoice_no ?? '');
        $is_vt = substr($invoice_no, 0, 2) === 'VT';
        $is_ipay = substr($invoice_no, 0, 4) === 'IPAY';
        $is_quotation = ($sell->status == 'draft' && ($sell->sub_status == 'quotation' || $sell->is_quotation == 1));

        $doc_type_label = 'Document';
        $doc_type_en = 'Document';
        if ($is_vt) {
            $doc_type_label = 'Tax-Invoice / Invoice / Delivery Order';
            $doc_type_en = 'TAX INVOICE / INVOICE / DELIVERY ORDER';
        } elseif ($is_ipay) {
            $doc_type_label = 'Billing Receipt';
            $doc_type_en = 'BILLING RECEIPT';
        } elseif ($is_quotation) {
            $doc_type_label = 'Quotation';
            $doc_type_en = 'QUOTATION';
        }

        $related_ipay = null;
        $related_vt = null;

        if ($is_vt) {
            if (! empty($sell->linked_billing_receive_id)) {
                $related_ipay = Transaction::where('business_id', $sell->business_id)
                    ->where('id', $sell->linked_billing_receive_id)
                    ->first();
            }
            if (! $related_ipay) {
                $related_ipay = Transaction::where('linked_tax_invoice_id', $sell->id)
                    ->where('business_id', $sell->business_id)
                    ->where('status', 'final')
                    ->first();
            }
            if (! $related_ipay) {
                $related_ipay = Transaction::where('transfer_parent_id', $sell->id)
                    ->where('business_id', $sell->business_id)
                    ->where('status', 'final')
                    ->where('invoice_no', 'LIKE', 'IPAY%')
                    ->first();
            }
            if (! $related_ipay) {
                $payment_ref_ipay = $sell->payment_lines
                    ->pluck('payment_ref_no')
                    ->filter(function ($ref) {
                        return ! empty($ref) && str_starts_with((string) $ref, 'IPAY');
                    })
                    ->first();

                if (! empty($payment_ref_ipay)) {
                    $related_ipay = Transaction::where('business_id', $sell->business_id)
                        ->where('invoice_no', $payment_ref_ipay)
                        ->first();
                }
            }
        } elseif ($is_ipay) {
            if (! empty($sell->linked_tax_invoice_id)) {
                $related_vt = Transaction::where('business_id', $sell->business_id)
                    ->where('id', $sell->linked_tax_invoice_id)
                    ->first();
            }
            if (! $related_vt && ! empty($sell->transfer_parent_id)) {
                $related_vt = Transaction::where('business_id', $sell->business_id)
                    ->where('id', $sell->transfer_parent_id)
                    ->first();
            }
        }

        $payment_status_map = [
            'paid' => 'Paid (ชำระเงินแล้ว)',
            'due' => 'Due (รอดำเนินการ)',
            'partial' => 'Partial (ชำระบางส่วน)',
            'overdue' => 'Overdue (เกินกำหนด)',
        ];
        $payment_status_key = (string) ($sell->payment_status ?? '');
        $payment_status_label = $payment_status_map[$payment_status_key] ?? $payment_status_key;

        $status_label = '';
        if ($sell->status == 'draft' && $sell->is_quotation == 1) {
            $status_label = 'Quotation';
        } elseif ($sell->status == 'draft' && $sell->sub_status == 'proforma') {
            $status_label = 'Proforma';
        } elseif ($sell->status == 'final') {
            $status_label = 'Paid';
        } else {
            $statuses = Transaction::sell_statuses();
            $status_label = $statuses[$sell->status] ?? $sell->status;
        }

        return [
            'is_vt' => $is_vt,
            'is_ipay' => $is_ipay,
            'is_quotation' => $is_quotation,
            'doc_type_label' => $doc_type_label,
            'doc_type_en' => $doc_type_en,
            'status_label' => $status_label,
            'payment_status_label' => $payment_status_label,
            'related_ipay_invoice_no' => ! empty($related_ipay->invoice_no) ? (string) $related_ipay->invoice_no : '',
            'related_vt_invoice_no' => ! empty($related_vt->invoice_no) ? (string) $related_vt->invoice_no : '',
        ];
    }

    private function translateUnitName(string $unit_name): string
    {
        $unit_name = trim($unit_name);
        $map = [
            'อัน' => 'Piece',
            'ชิ้น' => 'Piece',
            'ชิ้นงาน' => 'Piece',
            'ตัว' => 'Piece',
            'แผ่น' => 'Sheet',
            'เครื่อง' => 'Machine',
            'ชุด' => 'Set',
            'กล่อง' => 'Box',
            'ถุง' => 'Bag',
            'คู่' => 'Pair',
            'แพ็ค' => 'Pack',
            'แพค' => 'Pack',
            'ม้วน' => 'Roll',
            'เส้น' => 'Line',
            'ขวด' => 'Bottle',
            'กระป๋อง' => 'Can',
            'กิโลกรัม' => 'Kg',
            'กรัม' => 'Gram',
            'ลิตร' => 'Liter',
            'เมตร' => 'Meter',
        ];

        return $map[$unit_name] ?? $unit_name;
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9\-_]/', '_', $value);
        if (empty($sanitized)) {
            return 'invoice';
        }

        return trim($sanitized, '_');
    }

    private function applyClickableHyperlink(Worksheet $sheet, string $coordinate, ?string $url): void
    {
        $normalized_url = $this->normalizeHyperlinkUrl($url);
        if (empty($normalized_url)) {
            return;
        }

        $escaped_url = str_replace('"', '""', $normalized_url);
        $sheet->setCellValue($coordinate, '=HYPERLINK("' . $escaped_url . '","' . $escaped_url . '")');
        $sheet->getStyle($coordinate)->getFont()->setUnderline(true);
        $sheet->getStyle($coordinate)->getFont()->getColor()->setARGB('FF0563C1');
    }

    private function normalizeHyperlinkUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        return null;
    }

    private function isDummyLabel(?string $value): bool
    {
        $label = strtoupper(trim((string) $value));

        return $label === '' || $label === 'DUMMY';
    }

    private function isImageSource(?string $source): bool
    {
        if (empty($source)) {
            return false;
        }

        $path = parse_url($source, PHP_URL_PATH);
        if (empty($path)) {
            $path = $source;
        }

        $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

        return in_array($extension, $image_extensions, true);
    }

    private function resolveLocalImagePath(?string $source): ?string
    {
        if (empty($source)) {
            return null;
        }

        $source = trim($source);
        if ($source === '') {
            return null;
        }

        if (is_file($source)) {
            return $source;
        }

        $parsed_path = parse_url($source, PHP_URL_PATH);
        if (! empty($parsed_path)) {
            $local_from_url = public_path(ltrim($parsed_path, '/'));
            if (is_file($local_from_url)) {
                return $local_from_url;
            }
        }

        $relative_local = public_path(ltrim($source, '/'));
        if (is_file($relative_local)) {
            return $relative_local;
        }

        return null;
    }

    private function resolveExcelImagePath(?string $source): ?string
    {
        $local_path = $this->resolveLocalImagePath($source);
        if (! empty($local_path)) {
            return $local_path;
        }

        return $this->downloadRemoteExcelImage($source);
    }

    private function downloadRemoteExcelImage(?string $source): ?string
    {
        $url = $this->normalizeHyperlinkUrl($source);
        if (empty($url) || ! $this->isImageSource($url)) {
            return null;
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true)) {
            $extension = 'img';
        }

        $temp_path = tempnam(sys_get_temp_dir(), 'quote_excel_img_');
        if ($temp_path === false) {
            return null;
        }

        $target_path = $temp_path . '.' . $extension;
        @rename($temp_path, $target_path);

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'follow_location' => 1,
                'ignore_errors' => true,
                'header' => "User-Agent: RubyShopExcelExport/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $image_contents = @file_get_contents($url, false, $context);
        if ($image_contents === false || strlen($image_contents) < 32) {
            @unlink($target_path);
            return null;
        }

        file_put_contents($target_path, $image_contents);
        if (@getimagesize($target_path) === false) {
            @unlink($target_path);
            return null;
        }

        $this->modalExcelTempImages[] = $target_path;
        return $target_path;
    }

    private function cleanupModalExcelTempImages(): void
    {
        foreach ($this->modalExcelTempImages as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->modalExcelTempImages = [];
    }

    private function tryEmbedImage(Worksheet $sheet, ?string $source, string $coordinate, int $height = 70, ?int $width = null, ?string $hyperlink_url = null): bool
    {
        $local_path = $this->resolveExcelImagePath($source);
        if (empty($local_path) || ! is_file($local_path)) {
            return false;
        }

        if (@getimagesize($local_path) === false) {
            return false;
        }

        try {
            $drawing = new Drawing();
            $drawing->setName('Embedded Image');
            $drawing->setDescription(basename($local_path));
            $drawing->setPath($local_path, true);
            $drawing->setCoordinates($coordinate);
            $drawing->setOffsetX(1);
            $drawing->setOffsetY(1);
            if (! empty($width)) {
                $drawing->setResizeProportional(true);
                $drawing->setWidthAndHeight($width, $height);
            } else {
                $drawing->setHeight($height);
            }
            $normalized_hyperlink = $this->normalizeHyperlinkUrl($hyperlink_url);
            if (! empty($normalized_hyperlink)) {
                $drawing->setHyperlink(new Hyperlink($normalized_hyperlink));
            }
            $drawing->setWorksheet($sheet);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (! auth()->user()->can('direct_sell.update') && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        //Check if the transaction can be edited or not.
        $edit_days = request()->session()->get('business.transaction_edit_days');
        if (! $this->transactionUtil->canBeEdited($id, $edit_days)) {
            return back()
                ->with('status', ['success' => 0,
                    'msg' => __('messages.transaction_edit_not_allowed', ['days' => $edit_days]), ]);
        }

        //Check if return exist then not allowed
        if ($this->transactionUtil->isReturnExist($id)) {
            return back()->with('status', ['success' => 0,
                'msg' => __('lang_v1.return_exist'), ]);
        }

        $business_id = request()->session()->get('user.business_id');

        $business_details = $this->businessUtil->getDetails($business_id);
        $taxes = TaxRate::forBusinessDropdown($business_id, true, true);

        $transaction = Transaction::where('business_id', $business_id)
                            ->with(['price_group', 'types_of_service', 'media', 'media.uploaded_by_user'])
                            ->whereIn('type', ['sell', 'sales_order'])
                            ->findorfail($id);

        if ($transaction->type == 'sales_order' && ! auth()->user()->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        $location_id = $transaction->location_id;
        $location_printer_type = BusinessLocation::find($location_id)->receipt_printer_type;

        $sell_details = TransactionSellLine::join(
                            'products AS p',
                            'transaction_sell_lines.product_id',
                            '=',
                            'p.id'
                        )
                        ->join(
                            'variations AS variations',
                            'transaction_sell_lines.variation_id',
                            '=',
                            'variations.id'
                        )
                        ->join(
                            'product_variations AS pv',
                            'variations.product_variation_id',
                            '=',
                            'pv.id'
                        )
                        ->leftjoin('variation_location_details AS vld', function ($join) use ($location_id) {
                            $join->on('variations.id', '=', 'vld.variation_id')
                                ->where('vld.location_id', '=', $location_id);
                        })
                        ->leftjoin('units', 'units.id', '=', 'p.unit_id')
                        ->leftjoin('units as u', 'p.secondary_unit_id', '=', 'u.id')
                        ->where('transaction_sell_lines.transaction_id', $id)
                        ->with(['warranties', 'so_line'])
                        ->select(
                            DB::raw("IF(pv.is_dummy = 0, CONCAT(p.name, ' (', pv.name, ':',variations.name, ')'), p.name) AS product_name"),
                            'p.id as product_id',
                            'p.enable_stock',
                            'p.name as product_actual_name',
                            'p.type as product_type',
                            'pv.name as product_variation_name',
                            'pv.is_dummy as is_dummy',
                            'variations.name as variation_name',
                            'variations.sub_sku',
                            'p.barcode_type',
                            'p.enable_sr_no',
                            'variations.id as variation_id',
                            'units.short_name as unit',
                            'units.allow_decimal as unit_allow_decimal',
                            'u.short_name as second_unit',
                            'transaction_sell_lines.secondary_unit_quantity',
                            'transaction_sell_lines.tax_id as tax_id',
                            'transaction_sell_lines.item_tax as item_tax',
                            'transaction_sell_lines.unit_price as default_sell_price',
                            'transaction_sell_lines.unit_price_inc_tax as sell_price_inc_tax',
                            'transaction_sell_lines.unit_price_before_discount as unit_price_before_discount',
                            'transaction_sell_lines.id as transaction_sell_lines_id',
                            'transaction_sell_lines.id',
                            'transaction_sell_lines.quantity as quantity_ordered',
                            'transaction_sell_lines.sell_line_note as sell_line_note',
                            'transaction_sell_lines.parent_sell_line_id',
                            'transaction_sell_lines.lot_no_line_id',
                            'transaction_sell_lines.line_discount_type',
                            'transaction_sell_lines.line_discount_amount',
                            'transaction_sell_lines.res_service_staff_id',
                            'units.id as unit_id',
                            'transaction_sell_lines.sub_unit_id',
                            'transaction_sell_lines.so_line_id',
                            DB::raw('vld.qty_available + transaction_sell_lines.quantity AS qty_available')
                        )
                        ->get();

        if (! empty($sell_details)) {
            foreach ($sell_details as $key => $value) {
                //If modifier or combo sell line then unset
                if (! empty($sell_details[$key]->parent_sell_line_id)) {
                    unset($sell_details[$key]);
                } else {
                    if ($transaction->status != 'final') {
                        $actual_qty_avlbl = $value->qty_available - $value->quantity_ordered;
                        $sell_details[$key]->qty_available = $actual_qty_avlbl;
                        $value->qty_available = $actual_qty_avlbl;
                    }

                    $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($value->qty_available, false, null, true);
                    $lot_numbers = [];
                    if (request()->session()->get('business.enable_lot_number') == 1) {
                        $lot_number_obj = $this->transactionUtil->getLotNumbersFromVariation($value->variation_id, $business_id, $location_id);
                        foreach ($lot_number_obj as $lot_number) {
                            //If lot number is selected added ordered quantity to lot quantity available
                            if ($value->lot_no_line_id == $lot_number->purchase_line_id) {
                                $lot_number->qty_available += $value->quantity_ordered;
                            }

                            $lot_number->qty_formated = $this->transactionUtil->num_f($lot_number->qty_available);
                            $lot_numbers[] = $lot_number;
                        }
                    }
                    $sell_details[$key]->lot_numbers = $lot_numbers;

                    if (! empty($value->sub_unit_id)) {
                        $value = $this->productUtil->changeSellLineUnit($business_id, $value);
                        $sell_details[$key] = $value;
                    }

                    if ($this->transactionUtil->isModuleEnabled('modifiers')) {
                        //Add modifier details to sel line details
                        $sell_line_modifiers = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'modifier')
                            ->get();
                        $modifiers_ids = [];
                        if (count($sell_line_modifiers) > 0) {
                            $sell_details[$key]->modifiers = $sell_line_modifiers;
                            foreach ($sell_line_modifiers as $sell_line_modifier) {
                                $modifiers_ids[] = $sell_line_modifier->variation_id;
                            }
                        }
                        $sell_details[$key]->modifiers_ids = $modifiers_ids;

                        //add product modifier sets for edit
                        $this_product = Product::find($sell_details[$key]->product_id);
                        if (count($this_product->modifier_sets) > 0) {
                            $sell_details[$key]->product_ms = $this_product->modifier_sets;
                        }
                    }

                    //Get details of combo items
                    if ($sell_details[$key]->product_type == 'combo') {
                        $sell_line_combos = TransactionSellLine::where('parent_sell_line_id', $sell_details[$key]->transaction_sell_lines_id)
                            ->where('children_type', 'combo')
                            ->get()
                            ->toArray();
                        if (! empty($sell_line_combos)) {
                            $sell_details[$key]->combo_products = $sell_line_combos;
                        }

                        //calculate quantity available if combo product
                        $combo_variations = [];
                        foreach ($sell_line_combos as $combo_line) {
                            $combo_variations[] = [
                                'variation_id' => $combo_line['variation_id'],
                                'quantity' => $combo_line['quantity'] / $sell_details[$key]->quantity_ordered,
                                'unit_id' => null,
                            ];
                        }
                        $sell_details[$key]->qty_available =
                        $this->productUtil->calculateComboQuantity($location_id, $combo_variations);

                        if ($transaction->status == 'final') {
                            $sell_details[$key]->qty_available = $sell_details[$key]->qty_available + $sell_details[$key]->quantity_ordered;
                        }

                        $sell_details[$key]->formatted_qty_available = $this->productUtil->num_f($sell_details[$key]->qty_available, false, null, true);
                    }
                }
            }
        }

        $commsn_agnt_setting = $business_details->sales_cmsn_agnt;
        $commission_agent = [];
        if ($commsn_agnt_setting == 'user') {
            $commission_agent = User::forDropdown($business_id);
        } elseif ($commsn_agnt_setting == 'cmsn_agnt') {
            $commission_agent = User::saleCommissionAgentsDropdown($business_id);
        }

        $types = [];
        if (auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }
        $customer_groups = CustomerGroup::forDropdown($business_id);

        $transaction->transaction_date = $this->transactionUtil->format_date($transaction->transaction_date, true);

        $pos_settings = empty($business_details->pos_settings) ? $this->businessUtil->defaultPosSettings() : json_decode($business_details->pos_settings, true);

        $waiters = [];
        if ($this->productUtil->isModuleEnabled('service_staff') && ! empty($pos_settings['inline_service_staff'])) {
            $waiters = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $invoice_schemes = [];
        $default_invoice_schemes = null;

        if ($transaction->status == 'draft') {
            $invoice_schemes = InvoiceScheme::forDropdown($business_id);
            $default_invoice_schemes = InvoiceScheme::getDefault($business_id);
        }

        $redeem_details = [];
        if (request()->session()->get('business.enable_rp') == 1) {
            $redeem_details = $this->transactionUtil->getRewardRedeemDetails($business_id, $transaction->contact_id);

            $redeem_details['points'] += $transaction->rp_redeemed;
            $redeem_details['points'] -= $transaction->rp_earned;
        }

        $edit_discount = auth()->user()->can('edit_product_discount_from_sale_screen');
        $edit_price = auth()->user()->can('edit_product_price_from_sale_screen');

        //Accounts
        $accounts = [];
        if ($this->moduleUtil->isModuleEnabled('account')) {
            $accounts = Account::forDropdown($business_id, true, false);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $common_settings = session()->get('business.common_settings');
        $is_warranty_enabled = ! empty($common_settings['enable_product_warranty']) ? true : false;
        $warranties = $is_warranty_enabled ? Warranty::forDropdown($business_id) : [];

        $statuses = Transaction::sell_statuses();

        $is_order_request_enabled = false;
        $is_crm = $this->moduleUtil->isModuleInstalled('Crm');
        if ($is_crm) {
            $crm_settings = Business::where('id', auth()->user()->business_id)
                                ->value('crm_settings');
            $crm_settings = ! empty($crm_settings) ? json_decode($crm_settings, true) : [];

            if (! empty($crm_settings['enable_order_request'])) {
                $is_order_request_enabled = true;
            }
        }

        $sales_orders = [];
        if (! empty($pos_settings['enable_sales_order']) || $is_order_request_enabled) {
            $sales_orders = Transaction::where('business_id', $business_id)
                                ->where('type', 'sales_order')
                                ->where('contact_id', $transaction->contact_id)
                                ->where(function ($q) use ($transaction) {
                                    $q->where('status', '!=', 'completed');

                                    if (! empty($transaction->sales_order_ids)) {
                                        $q->orWhereIn('id', $transaction->sales_order_ids);
                                    }
                                })
                                ->pluck('invoice_no', 'id');
        }

        $payment_types = $this->transactionUtil->payment_types($transaction->location_id, false, $business_id);

        $payment_lines = $this->transactionUtil->getPaymentDetails($id);
        //If no payment lines found then add dummy payment line.
        if (empty($payment_lines)) {
            $payment_lines[] = $this->dummyPaymentLine;
        }

        $change_return = $this->dummyPaymentLine;

        $customer_due = $this->transactionUtil->getContactDue($transaction->contact_id, $transaction->business_id);

        $customer_due = $customer_due != 0 ? $this->transactionUtil->num_f($customer_due, true) : '';

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        // Get customer sources for radio buttons (full objects with logo)
        $customer_sources = \App\CustomerSource::getActiveForBusiness($business_id);

        return view('sell.edit')
            ->with(compact('business_details', 'taxes', 'sell_details', 'transaction', 'commission_agent', 'types', 'customer_groups', 'pos_settings', 'waiters', 'invoice_schemes', 'default_invoice_schemes', 'redeem_details', 'edit_discount', 'edit_price', 'shipping_statuses', 'warranties', 'statuses', 'sales_orders', 'payment_types', 'accounts', 'payment_lines', 'change_return', 'is_order_request_enabled', 'customer_due', 'users', 'customer_sources'));
    }

    /**
     * Display a listing sell drafts.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDrafts()
    {
        if (! auth()->user()->can('draft.view_all') && ! auth()->user()->can('draft.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        return view('sale_pos.draft')
            ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Display a listing sell quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getQuotations()
    {
        if (! auth()->user()->can('quotation.view_all') && ! auth()->user()->can('quotation.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        return view('sale_pos.quotations')
                ->with(compact('business_locations', 'customers', 'sales_representative'));
    }

    /**
     * Send the datatable response for draft or quotations.
     *
     * @return \Illuminate\Http\Response
     */
    public function getDraftDatables()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $is_quotation = request()->input('is_quotation', 0);

            \Log::info('=== getDraftDatables Called ===');
            \Log::info('Business ID: ' . $business_id);
            \Log::info('Is Quotation: ' . $is_quotation);
            \Log::info('Request params: ', request()->all());

            $is_woocommerce = $this->moduleUtil->isModuleInstalled('Woocommerce');

            $sells = Transaction::leftJoin('contacts', 'transactions.contact_id', '=', 'contacts.id')
                ->leftJoin('users as u', 'transactions.created_by', '=', 'u.id')
                ->join(
                    'business_locations AS bl',
                    'transactions.location_id',
                    '=',
                    'bl.id'
                )
                ->leftJoin('transaction_sell_lines as tsl', function ($join) {
                    $join->on('transactions.id', '=', 'tsl.transaction_id')
                        ->whereNull('tsl.parent_sell_line_id');
                })
                ->where('transactions.business_id', $business_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'draft')
                ->select(
                    'transactions.id',
                    'transaction_date',
                    'invoice_no',
                    'transactions.final_total',
                    'contacts.name',
                    'contacts.mobile',
                    'contacts.supplier_business_name',
                    'bl.name as business_location',
                    'is_direct_sale',
                    'sub_status',
                    'document_type',
                    DB::raw('COUNT( DISTINCT tsl.id) as total_items'),
                    DB::raw('SUM(tsl.quantity) as total_quantity'),
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as added_by"),
                    'transactions.is_export'
                );

            if ($is_quotation == 1) {
                $sells->where('transactions.sub_status', 'quotation');

                if (! auth()->user()->can('quotation.view_all') && auth()->user()->can('quotation.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            } else {
                // Exclude proforma invoices from drafts since they now appear in main sells page
                $sells->where(function($query) {
                    $query->whereNull('transactions.sub_status')
                          ->orWhere('transactions.sub_status', '!=', 'proforma');
                });
                
                if (! auth()->user()->can('draft.view_all') && auth()->user()->can('draft.view_own')) {
                    $sells->where('transactions.created_by', request()->session()->get('user.id'));
                }
            }

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            if (! empty(request()->start_date) && ! empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transaction_date', '>=', $start)
                            ->whereDate('transaction_date', '<=', $end);
            }

            if (request()->has('location_id')) {
                $location_id = request()->get('location_id');
                if (! empty($location_id)) {
                    $sells->where('transactions.location_id', $location_id);
                }
            }

            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (! empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            if (! empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }

            if ($is_woocommerce) {
                $sells->addSelect('transactions.woocommerce_order_id');
            }

            $sells->groupBy('transactions.id');

            \Log::info('=== Query Built ===');
            \Log::info('SQL: ' . $sells->toSql());
            \Log::info('Bindings: ', $sells->getBindings());
            $count = $sells->count();
            \Log::info('Total rows found: ' . $count);

            return Datatables::of($sells)
                 ->addColumn(
                    'action', function ($row) {
                        $html = '<div class="btn-group">
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-info tw-w-max dropdown-toggle" 
                                    data-toggle="dropdown" aria-expanded="false">'.
                                    __('messages.actions').
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right" role="menu">
                                    <li>
                                    <a href="#" data-href="'.action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]).'" class="btn-modal" data-container=".view_modal">
                                        <i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'
                                    </a>
                                    </li>';

                        if (auth()->user()->can('draft.update') || auth()->user()->can('quotation.update')) {
                            if ($row->is_direct_sale == 1) {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            } else {
                                $html .= '<li>
                                            <a target="_blank" href="'.action([\App\Http\Controllers\SellPosController::class, 'edit'], [$row->id]).'">
                                                <i class="fas fa-edit"></i>'.__('messages.edit').'
                                            </a>
                                        </li>';
                            }
                        }

                      

                        if (config('constants.enable_download_pdf')) {
                            $sub_status = $row->sub_status == 'proforma' ? 'proforma' : '';
                            $html .= '<li>
                                        <a href="'.route('quotation.downloadPdf', ['id' => $row->id, 'sub_status' => $sub_status]).'" target="_blank">
                                            <i class="fas fa-print" aria-hidden="true"></i>'.__('lang_v1.download_pdf').'
                                        </a>
                                    </li>';
                        }

                        if ($row->document_type == 'quotation' || (!$row->document_type && $row->sub_status == 'quotation')) {
                            $html .= '<li>
                                        <a href="'.route('quotations.pdfprint.nodejs', ['id' => $row->id]).'" class="pdf-print-btn">
                                            <i class="fas fa-file-pdf" aria-hidden="true"></i>Print Quotation
                                        </a>
                                    </li>';
                                    
                            // Add Create Tax-Invoice (Proforma) button for quotations
                            if (auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) {
                                $html .= '<li>
                                            <a href="#" onclick="createTaxInvoice('.$row->id.')" class="create-tax-invoice-btn">
                                                <i class="fas fa-file-invoice" aria-hidden="true"></i>Create Tax-Invoice (Proforma)
                                            </a>
                                        </li>';
                            }
                        }

                        if ((auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access')) && config('constants.enable_convert_draft_to_invoice')) {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToInvoice'], [$row->id]).'" class="convert-draft"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_invoice').'</a>
                                    </li>';
                        }

                        if ($row->document_type != 'proforma' && $row->sub_status != 'proforma') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'convertToProforma'], [$row->id]).'" class="convert-to-proforma"><i class="fas fa-sync-alt"></i>'.__('lang_v1.convert_to_proforma').'</a>
                                    </li>';
                        }

                        if (auth()->user()->can('draft.delete') || auth()->user()->can('quotation.delete')) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]).'" class="delete-sale"><i class="fas fa-trash"></i>'.__('messages.delete').'</a>
                                </li>';
                        }

                        if ($row->sub_status == 'quotation') {
                            $html .= '<li>
                                        <a href="'.action([\App\Http\Controllers\SellPosController::class, 'copyQuotation'],[$row->id]).'" 
                                        class="copy_quotation"><i class="fas fa-copy"></i>'.
                                        __("lang_v1.copy_quotation").'</a>
                                    </li>
                                    <li>
                                        <a href="#" data-href="'.action("\App\Http\Controllers\NotificationController@getTemplate", ["transaction_id" => $row->id,"template_for" => "new_quotation"]).'" class="btn-modal" data-container=".view_modal"><i class="fa fa-envelope" aria-hidden="true"></i>' . __("lang_v1.new_quotation_notification") . '
                                        </a>
                                    </li>';

                            $html .= '<li>
                                        <a href="'.action("\App\Http\Controllers\SellPosController@showInvoiceUrl", [$row->id]).'" class="view_invoice_url"><i class="fas fa-eye"></i>'.__("lang_v1.view_quote_url").'</a>
                                    </li>';
                        }

                        $html .= '</ul></div>';

                        return $html;
                    })
                ->removeColumn('id')
                ->editColumn('invoice_no', function ($row) {
                    $invoice_no = $row->invoice_no;
                    if (! empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="'.__('lang_v1.synced_from_woocommerce').'"></i>';
                    }

                    if ($row->sub_status == 'proforma') {
                        $invoice_no .= '<br><span class="label bg-gray">'.__('lang_v1.proforma_invoice').'</span>';
                    }

                    if (! empty($row->is_export)) {
                        $invoice_no .= '</br><small class="label label-default no-print" title="'.__('lang_v1.export').'">'.__('lang_v1.export').'</small>';
                    }

                    return $invoice_no;
                })
                ->editColumn('transaction_date', '{{@format_date($transaction_date)}}')
                ->editColumn('final_total', '<span class="display_currency" data-currency_symbol="true">{{$final_total}}</span>')
                ->editColumn('total_items', '{{@format_quantity($total_items)}}')
                ->editColumn('total_quantity', '{{@format_quantity($total_quantity)}}')
                ->addColumn('conatct_name', '@if(!empty($supplier_business_name)) {{$supplier_business_name}}, <br>@endif {{$name}}')
                ->filterColumn('conatct_name', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->where('contacts.name', 'like', "%{$keyword}%")
                        ->orWhere('contacts.supplier_business_name', 'like', "%{$keyword}%");
                    });
                })
                ->filterColumn('added_by', function ($query, $keyword) {
                    $query->whereRaw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) like ?", ["%{$keyword}%"]);
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can('sell.view')) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]);
                        } else {
                            return '';
                        }
                    }, ])
                ->rawColumns(['action', 'invoice_no', 'transaction_date', 'final_total', 'conatct_name'])
                ->make(true);
        }
    }

    /**
     * Creates copy of the requested sale.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function duplicateSell($id)
    {
        if (! auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $transaction = Transaction::where('business_id', $business_id)
                            ->where('type', 'sell')
                            ->findorfail($id);
            $duplicate_transaction_data = [];
            foreach ($transaction->toArray() as $key => $value) {
                if (! in_array($key, ['id', 'created_at', 'updated_at'])) {
                    $duplicate_transaction_data[$key] = $value;
                }
            }
            $duplicate_transaction_data['status'] = 'draft';
            $duplicate_transaction_data['payment_status'] = null;
            $duplicate_transaction_data['transaction_date'] = \Carbon::now();
            $duplicate_transaction_data['created_by'] = $user_id;
            $duplicate_transaction_data['invoice_token'] = null;

            DB::beginTransaction();
            $duplicate_transaction_data['invoice_no'] = $this->transactionUtil->getInvoiceNumber($business_id, 'draft', $duplicate_transaction_data['location_id']);

            //Create duplicate transaction
            $duplicate_transaction = Transaction::create($duplicate_transaction_data);

            //Create duplicate transaction sell lines
            $duplicate_sell_lines_data = [];

            foreach ($transaction->sell_lines as $sell_line) {
                $new_sell_line = [];
                foreach ($sell_line->toArray() as $key => $value) {
                    if (! in_array($key, ['id', 'transaction_id', 'created_at', 'updated_at', 'lot_no_line_id'])) {
                        $new_sell_line[$key] = $value;
                    }
                }

                $duplicate_sell_lines_data[] = $new_sell_line;
            }

            $duplicate_transaction->sell_lines()->createMany($duplicate_sell_lines_data);

            DB::commit();

            $output = ['success' => 0,
                'msg' => trans('lang_v1.duplicate_sell_created_successfully'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        if (! empty($duplicate_transaction)) {
            if ($duplicate_transaction->is_direct_sale == 1) {
                return redirect()->action([\App\Http\Controllers\SellController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            } else {
                return redirect()->action([\App\Http\Controllers\SellPosController::class, 'edit'], [$duplicate_transaction->id])->with(['status', $output]);
            }
        } else {
            abort(404, 'Not Found.');
        }
    }

    /**
     * Shows modal to edit shipping details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editShipping($id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $transaction = Transaction::where('business_id', $business_id)
                                ->with(['media', 'media.uploaded_by_user'])
                                ->findorfail($id);

        $users = User::forDropdown($business_id, false, false, false);

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $activities = Activity::forSubject($transaction)
           ->with(['causer', 'subject'])
           ->where('activity_log.description', 'shipping_edited')
           ->latest()
           ->get();

        return view('sell.partials.edit_shipping')
               ->with(compact('transaction', 'shipping_statuses', 'activities', 'users'));
    }

    /**
     * Update shipping.
     *
     * @param  Request  $request, int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateShipping(Request $request, $id)
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $input = $request->only([
                'shipping_details', 'shipping_address',
                'shipping_status', 'delivered_to', 'delivery_person', 'shipping_custom_field_1', 'shipping_custom_field_2', 'shipping_custom_field_3', 'shipping_custom_field_4', 'shipping_custom_field_5',
            ]);


            $business_id = $request->session()->get('user.business_id');

            $transaction = Transaction::where('business_id', $business_id)
                                ->findOrFail($id);

            $transaction_before = $transaction->replicate();

            $transaction->update($input);

            $activity_property = ['update_note' => $request->input('shipping_note', '')];
            $this->transactionUtil->activityLog($transaction, 'shipping_edited', $transaction_before, $activity_property);

            $output = ['success' => 1,
                'msg' => trans('lang_v1.updated_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => trans('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display list of shipments.
     *
     * @return \Illuminate\Http\Response
     */
    public function shipments()
    {
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        if (! $is_admin && ! auth()->user()->hasAnyPermission(['access_shipping', 'access_own_shipping', 'access_commission_agent_shipping'])) {
            abort(403, 'Unauthorized action.');
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        $business_id = request()->session()->get('user.business_id');

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);

        $sales_representative = User::forDropdown($business_id, false, false, true);

        $is_service_staff_enabled = $this->transactionUtil->isModuleEnabled('service_staff');

        //Service staff filter
        $service_staffs = null;
        if ($this->productUtil->isModuleEnabled('service_staff')) {
            $service_staffs = $this->productUtil->serviceStaffDropdown($business_id);
        }

        $delevery_person = User::forDropdown($business_id, false, false, true);

        return view('sell.shipments')->with(compact('shipping_statuses'))
                ->with(compact('business_locations', 'customers', 'sales_representative', 'is_service_staff_enabled', 'service_staffs', 'delevery_person'));
    }

    public function viewMedia($model_id)
    {
        if (request()->ajax()) {
            $model_type = request()->input('model_type');
            $business_id = request()->session()->get('user.business_id');

            $query = Media::where('business_id', $business_id)
                        ->where('model_id', $model_id)
                        ->where('model_type', $model_type);

            $title = __('lang_v1.attachments');
            if (! empty(request()->input('model_media_type'))) {
                $query->where('model_media_type', request()->input('model_media_type'));
                $title = __('lang_v1.shipping_documents');
            }

            $medias = $query->get();

            return view('sell.view_media')->with(compact('medias', 'title'));
        }
    }

    public function resetMapping()
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Unauthorized action.');
        }

        Artisan::call('pos:mapPurchaseSell');

        echo 'Mapping reset success';
        exit;
    }

    /**
     * Display summary sales page (Final Bills + Proforma Invoices only)
     *
     * @return \Illuminate\Http\Response
     */
    public function summarySales()
    {
        if (!auth()->user()->can('sell.view') && !auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        $business_locations = BusinessLocation::forDropdown($business_id, false);
        $customers = Contact::customersDropdown($business_id, false);
        $sales_representative = User::forDropdown($business_id, false, false, true);

        $is_cmsn_agent_enabled = request()->session()->get('business.sales_cmsn_agnt');
        $commission_agents = [];
        if (!empty($is_cmsn_agent_enabled)) {
            $commission_agents = User::forDropdown($business_id, false, true, true);
        }

        $shipping_statuses = $this->transactionUtil->shipping_statuses();

        return view('sell.summary_sales')
            ->with(compact(
                'business_locations',
                'customers', 
                'sales_representative',
                'is_cmsn_agent_enabled',
                'commission_agents',
                'shipping_statuses',
                'is_admin'
            ));
    }

    /**
     * Single-document summary mode:
     * Always show VT-side rows only (one row per sale), even when IPAY exists.
     */
    private function applySummarySalesBaseVtFilter($sells): void
    {
        $sells->where(function ($query) {
            $query->where('transactions.invoice_no', 'LIKE', 'VT%')
                ->orWhere('transactions.document_type', 'proforma')
                ->orWhere('transactions.sub_status', 'proforma');
        });
    }

    /**
     * Billing-Receive view in single-document mode:
     * VT rows with payment received, plus legacy linked IPAY fallback.
     */
    private function applySummarySalesBillingReceiveFilter($sells): void
    {
        $sells->where(function ($query) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('transaction_payments as tp')
                    ->whereColumn('tp.transaction_id', 'transactions.id')
                    ->where('tp.is_return', 0)
                    ->where('tp.amount', '>', 0);
            })
                ->orWhereNotNull('transactions.linked_billing_receive_id')
                ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transactions as br')
                        ->whereColumn('br.business_id', 'transactions.business_id')
                        ->where('br.type', 'sell')
                        ->where('br.invoice_no', 'LIKE', 'IPAY%')
                        ->whereColumn('br.transfer_parent_id', 'transactions.id');
                })
                ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transactions as br')
                        ->whereColumn('br.business_id', 'transactions.business_id')
                        ->where('br.type', 'sell')
                        ->where('br.invoice_no', 'LIKE', 'IPAY%')
                        ->whereColumn('br.linked_tax_invoice_id', 'transactions.id');
                });
        });
    }

    /**
     * Hide ad-hoc/local VT rows that are not part of synced flow and have no billing relation.
     * This keeps summary-sales focused on canonical synced VT/IPAY documents.
     */
    private function applySummarySalesCanonicalOnlyFilter($sells): void
    {
        $sells->where(function ($query) {
            $query->whereNotNull('transactions.old_pos_sale_id')
                ->orWhere('transactions.sync_source', 'old_pos')
                ->orWhereNotNull('transactions.linked_billing_receive_id')
                ->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('transactions as br')
                        ->whereColumn('br.business_id', 'transactions.business_id')
                        ->where('br.type', 'sell')
                        ->where('br.invoice_no', 'LIKE', 'IPAY%')
                        ->where(function ($q) {
                            $q->whereColumn('br.transfer_parent_id', 'transactions.id')
                                ->orWhereColumn('br.linked_tax_invoice_id', 'transactions.id');
                        });
                });
        });
    }

    private function applySummarySalesPaymentStatusFilter($sells, ?string $paymentStatusFilter): void
    {
        $is_overdue_filter = $paymentStatusFilter == 'overdue';
        if (!empty($paymentStatusFilter) && !$is_overdue_filter) {
            $sells->where('transactions.payment_status', $paymentStatusFilter);
        } elseif ($is_overdue_filter) {
            $sells->whereIn('transactions.payment_status', ['due', 'partial'])
                ->whereNotNull('transactions.pay_term_number')
                ->whereNotNull('transactions.pay_term_type')
                ->whereRaw("IF(transactions.pay_term_type='days', DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number DAY) < CURDATE(), DATE_ADD(transactions.transaction_date, INTERVAL transactions.pay_term_number MONTH) < CURDATE())");
        }
    }

    /**
     * Get summary sales data for DataTables (Final Bills + Proforma Invoices only)
     */
    public function getSummarySalesData()
    {
        if (!auth()->user()->can('sell.view') && !auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $is_admin = $this->businessUtil->is_admin(auth()->user());

            $sells = $this->transactionUtil->getListSells($business_id);

            // Single-document mode: VT rows are canonical; IPAY rows are hidden from list.
            $document_filter = request()->get('document_filter', 'both');
            $payment_status_filter = request()->input('payment_status');
            $this->applySummarySalesBaseVtFilter($sells);
            $this->applySummarySalesCanonicalOnlyFilter($sells);

            if ($document_filter == 'billing_receive') {
                $this->applySummarySalesBillingReceiveFilter($sells);
            }
            $this->applySummarySalesPaymentStatusFilter($sells, $payment_status_filter);

            $permitted_locations = auth()->user()->permitted_locations();
            if ($permitted_locations != 'all') {
                $sells->whereIn('transactions.location_id', $permitted_locations);
            }

            //Add condition for created_by,used in sales representative sales report
            if (request()->has('created_by')) {
                $created_by = request()->get('created_by');
                if (!empty($created_by)) {
                    $sells->where('transactions.created_by', $created_by);
                }
            }

            if (!empty(request()->customer_id)) {
                $customer_id = request()->customer_id;
                $sells->where('contacts.id', $customer_id);
            }
            if (!empty(request()->location_id)) {
                $location_id = request()->location_id;
                $sells->where('transactions.location_id', $location_id);
            }

            if (!empty(request()->start_date) && !empty(request()->end_date)) {
                $start = request()->start_date;
                $end = request()->end_date;
                $sells->whereDate('transactions.transaction_date', '>=', $start)
                            ->whereDate('transactions.transaction_date', '<=', $end);
            }

            //Add condition for commission_agent,used in sales representative sales with commission report
            if (request()->has('commission_agent')) {
                $commission_agent = request()->get('commission_agent');
                if (!empty($commission_agent)) {
                    $sells->where('transactions.commission_agent', $commission_agent);
                }
            }

            if (!empty(request()->input('source'))) {
                //only exception for woocommerce
                if (request()->input('source') == 'woocommerce') {
                    $sells->whereNotNull('transactions.woocommerce_order_id');
                } else {
                    $sells->where('transactions.source', request()->input('source'));
                }
            }

            if (!empty(request()->input('shipping_status'))) {
                $sells->where('transactions.shipping_status', request()->input('shipping_status'));
            }

            if (!empty(request()->res_waiter_id)) {
                $sells->where('transactions.res_waiter_id', request()->res_waiter_id);
            }

            $only_shipments = request()->only_shipments == 'true' ? true : false;
            if ($only_shipments) {
                $sells->whereNotNull('transactions.shipping_status');
            }

            if (!$is_admin && request()->session()->get('user.view_own_sell_only') == 1) {
                $sells->where('transactions.created_by', request()->session()->get('user.id'));
            }

            $sells->addSelect('transactions.res_table_id', 'transactions.res_waiter_id', 'transactions.additional_notes', 'transactions.linked_billing_receive_id');
            $sells->addSelect(DB::raw('SUM((tsl.quantity - tsl.quantity_returned) * tsl.item_tax) as line_tax_total'));
            $sells->addSelect(DB::raw('SUM((tsl.quantity - tsl.quantity_returned) * (tsl.unit_price_inc_tax - tsl.unit_price)) as calc_line_tax_total'));

            $sells->groupBy('transactions.id');

            // Load payment_lines relationship for payment status click functionality
            $sells->with('payment_lines');

            return Datatables::of($sells)
                ->addColumn('action', function ($row) use ($is_admin) {
                    $html = '<div class="btn-group">
                                <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                                    data-toggle="dropdown" aria-expanded="false">' .
                                    __("messages.actions") .
                                    '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                                    </span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right" role="menu">';

                    if (auth()->user()->can("sell.view") || auth()->user()->can("direct_sell.access")) {
                        $html .= '<li><a href="' . action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]) . '"><i class="fas fa-eye" aria-hidden="true"></i>' . __("messages.view") . '</a></li>';
                    }
                    if (!empty($row->document)) {
                        $html .= '<li><a href="' . url('uploads/documents/' . $row->document) . '" download="' . $row->document . '"><i class="fas fa-download" aria-hidden="true"></i>' . __("purchase.download_document") . '</a></li>';
                        if (isFileImage($row->document)) {
                            $html .= '<li><a href="#" data-href="' . url('uploads/documents/' . $row->document) . '" class="view_uploaded_document"><i class="fas fa-image" aria-hidden="true"></i>' . __("lang_v1.view_document") . '</a></li>';
                        }
                    }

                    if (auth()->user()->can("sell.update") || auth()->user()->can("direct_sell.access")) {
                        $html .= '<li><a href="' . action([\App\Http\Controllers\SellController::class, 'edit'], [$row->id]) . '"><i class="fas fa-edit"></i>' . __("messages.edit") . '</a></li>';
                    }

                    if (auth()->user()->can("sell.delete")) {
                        $html .= '<li><a href="' . action([\App\Http\Controllers\SellPosController::class, 'destroy'], [$row->id]) . '" class="delete-sale"><i class="fas fa-trash"></i>' . __("messages.delete") . '</a></li>';
                    }

                    $html .= '<li class="divider"></li>';

                    $invoice_no = (string) ($row->invoice_no ?? '');
                    $is_vt_document = str_starts_with($invoice_no, 'VT') || $row->document_type == 'proforma' || $row->sub_status == 'proforma';

                    if ($is_vt_document) {
                        $html .= '<li><a href="#" class="print-invoice-api" data-id="' . $row->id . '" data-document-type="proforma"><i class="fas fa-print" aria-hidden="true"></i>' . __("lang_v1.print_proforma") . '</a></li>';
                        $html .= '<li><a href="#" class="print-invoice-api" data-id="' . $row->id . '" data-document-type="final"><i class="fas fa-print" aria-hidden="true"></i>' . __("lang_v1.print_invoice") . '</a></li>';
                    } elseif ($row->status == 'final') {
                        $html .= '<li><a href="#" class="print-invoice-api" data-id="' . $row->id . '" data-document-type="final"><i class="fas fa-print" aria-hidden="true"></i>' . __("lang_v1.print_invoice") . '</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->removeColumn('id')
                ->editColumn('final_total', function ($row) {
                    return '<span class="display_currency final_total" data-currency_symbol="true">' . $row->final_total . '</span>';
                })
                ->editColumn('tax_amount', function ($row) {
                    $tax_amount = (float) ($row->tax_amount ?? 0);
                    if ($tax_amount <= 0 && !empty($row->line_tax_total)) {
                        $tax_amount = (float) $row->line_tax_total;
                    }
                    if ($tax_amount <= 0 && !empty($row->calc_line_tax_total)) {
                        $tax_amount = (float) $row->calc_line_tax_total;
                    }
                    if ($tax_amount <= 0 && isset($row->final_total, $row->total_before_tax)) {
                        $tax_amount = (float) $row->final_total - (float) $row->total_before_tax;
                    }
                    if ($tax_amount < 0) {
                        $tax_amount = 0;
                    }
                    return '<span class="display_currency" data-currency_symbol="true">' . $tax_amount . '</span>';
                })
                ->editColumn('total_paid', function ($row) {
                    $total_paid = 0;
                    if (!empty($row->total_paid)) {
                        $total_paid = $row->total_paid - $row->total_change_return;
                    }
                    return '<span class="display_currency" data-currency_symbol="true">' . $total_paid . '</span>';
                })
                ->editColumn('total_remaining', function ($row) {
                    $total_remaining = $row->final_total - $row->total_paid + $row->total_change_return;
                    return '<span class="display_currency final_total" data-currency_symbol="true">' . $total_remaining . '</span>';
                })
                ->editColumn('transaction_date', function ($row) {
                    return $this->transactionUtil->format_date($row->transaction_date, true);
                })
                ->editColumn('invoice_no', function ($row) {
                    $invoice_no = $row->invoice_no;
                    if (!empty($row->woocommerce_order_id)) {
                        $invoice_no .= ' <i class="fab fa-wordpress text-primary no-print" title="' . __("lang_v1.synced_from_woocommerce") . '"></i>';
                    }
                    if (!empty($row->return_exists)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.some_qty_returned') . '"><i class="fas fa-undo"></i></small>';
                    }
                    if (!empty($row->is_recurring)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-red label-round no-print" title="' . __('lang_v1.subscribed_invoice') . '"><i class="fas fa-recycle"></i></small>';
                    }

                    if (!empty($row->recur_parent_id)) {
                        $invoice_no .= ' &nbsp;<small class="label bg-info label-round no-print" title="' . __('lang_v1.subscription_invoice') . '"><i class="fas fa-recycle"></i></small>';
                    }

                    // Remove document type badges from invoice number column - they now appear in document_type column only

                    return $invoice_no;
                })
                ->editColumn('payment_status', function ($row) {
                    $payment_status = Transaction::getPaymentStatus($row);
                    if (empty($payment_status)) {
                        $payment_status = 'due';
                    }
                    $clickable_class = '';
                    $click_data = '';

                    // Add click handlers based on payment status
                    if ($payment_status == 'due' || $payment_status == 'partial') {
                        $clickable_class = 'clickable-payment-status add_payment_modal';
                        $click_data = 'href="' . action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->id]) . '"';
                    } elseif ($payment_status == 'paid') {
                        // For paid status, use edit_payment to allow editing the payment
                        $first_payment = $row->payment_lines()->first();
                        if ($first_payment) {
                            $clickable_class = 'clickable-payment-status edit_payment';
                            $click_data = 'data-href="' . action([\App\Http\Controllers\TransactionPaymentController::class, 'edit'], [$first_payment->id]) . '"';
                        }
                    }
                    
                    // Create custom payment status with Thai translations
                    $payment_display = $payment_status;
                    if ($payment_status == 'paid') {
                        $payment_display = 'Paid (ชำระเงินแล้ว)';
                    } elseif ($payment_status == 'due') {
                        $payment_display = 'Due (รอดำเนินการ)';
                    }
                    
                    // Get the original payment status HTML structure but replace the text
                    $payment_html = (string) view('sell.partials.payment_status', ['payment_status' => $payment_status, 'id' => $row->id, 'for_pdf' => false]);
                    
                    // Replace the payment status text with our custom display text
                    if ($payment_status == 'paid') {
                        $payment_html = str_replace('Paid', $payment_display, $payment_html);
                    } elseif ($payment_status == 'due') {
                        $payment_html = str_replace('Due', $payment_display, $payment_html);
                    }
                    
                    if ($clickable_class) {
                        return '<a class="' . $clickable_class . '" ' . $click_data . ' style="cursor: pointer;">' . $payment_html . '</a>';
                    }
                    
                    return $payment_html;
                })
                ->addColumn('document_type', function ($row) {
                    // Determine document type based on invoice_no prefix
                    $invoiceNo = $row->invoice_no ?? '';

                    if (substr($invoiceNo, 0, 2) === 'VT') {
                        // VT = Tax-Invoice
                        return '<span class="label bg-red">Tax-Invoice</span>';
                    } elseif ($row->document_type == 'proforma' || $row->sub_status == 'proforma') {
                        return '<span class="label bg-red">Tax-Invoice</span>';
                    } elseif ($row->status == 'final') {
                        return '<span class="label bg-green">Billing-Received</span>';
                    } else {
                        return '<span class="label bg-default">Draft</span>';
                    }
                })
                ->addColumn('total_change_return', function ($row) {
                    return $row->total_change_return;
                })
                ->setRowAttr([
                    'data-href' => function ($row) {
                        if (auth()->user()->can("sell.view") || auth()->user()->can("direct_sell.access")) {
                            return  action([\App\Http\Controllers\SellController::class, 'show'], [$row->id]) ;
                        } else {
                            return '';
                        }
                    }])
                ->rawColumns(['final_total', 'action', 'total_paid', 'total_remaining', 'payment_status', 'invoice_no', 'tax_amount', 'document_type'])
                ->make(true);
        }
    }

    /**
     * Get monthly and yearly sales summary for Summary Sales page
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSalesSummaryStats()
    {
        if (!auth()->user()->can('sell.view') && !auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->businessUtil->is_admin(auth()->user());

        // Get current month and year
        $currentMonth = date('m');
        $currentYear = date('Y');

        // Single-document mode: use VT rows as canonical sales records.
        $baseQuery = Transaction::where('business_id', $business_id)
            ->where('type', 'sell')
            ->where(function($query) {
                $query->where('invoice_no', 'LIKE', 'VT%')
                    ->orWhere('document_type', 'proforma')
                    ->orWhere('sub_status', 'proforma');
            });

        // Apply location permission
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $baseQuery->whereIn('location_id', $permitted_locations);
        }

        // Apply own sell view restriction
        if (!$is_admin && request()->session()->get('user.view_own_sell_only') == 1) {
            $baseQuery->where('created_by', request()->session()->get('user.id'));
        }

        // Monthly sales (current month)
        $monthlySales = (clone $baseQuery)
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->sum('final_total');

        $monthlyCount = (clone $baseQuery)
            ->whereMonth('transaction_date', $currentMonth)
            ->whereYear('transaction_date', $currentYear)
            ->count();

        // Yearly sales (current year)
        $yearlySales = (clone $baseQuery)
            ->whereYear('transaction_date', $currentYear)
            ->sum('final_total');

        $yearlyCount = (clone $baseQuery)
            ->whereYear('transaction_date', $currentYear)
            ->count();

        // Previous month comparison
        $prevMonth = $currentMonth == 1 ? 12 : $currentMonth - 1;
        $prevMonthYear = $currentMonth == 1 ? $currentYear - 1 : $currentYear;

        $prevMonthlySales = (clone $baseQuery)
            ->whereMonth('transaction_date', $prevMonth)
            ->whereYear('transaction_date', $prevMonthYear)
            ->sum('final_total');

        // Previous year comparison
        $prevYearlySales = (clone $baseQuery)
            ->whereYear('transaction_date', $currentYear - 1)
            ->sum('final_total');

        // Calculate percentage changes
        $monthlyChange = $prevMonthlySales > 0
            ? round((($monthlySales - $prevMonthlySales) / $prevMonthlySales) * 100, 1)
            : ($monthlySales > 0 ? 100 : 0);

        $yearlyChange = $prevYearlySales > 0
            ? round((($yearlySales - $prevYearlySales) / $prevYearlySales) * 100, 1)
            : ($yearlySales > 0 ? 100 : 0);

        return response()->json([
            'success' => true,
            'data' => [
                'monthly' => [
                    'total' => $monthlySales,
                    'count' => $monthlyCount,
                    'change' => $monthlyChange,
                    'month_name' => date('F'),
                    'year' => $currentYear
                ],
                'yearly' => [
                    'total' => $yearlySales,
                    'count' => $yearlyCount,
                    'change' => $yearlyChange,
                    'year' => $currentYear
                ]
            ]
        ]);
    }

    /**
     * Export Summary Sales data to CSV or XLSX
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function exportSummarySales()
    {
        if (!auth()->user()->can('sell.view') && !auth()->user()->can('sell.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $is_admin = $this->businessUtil->is_admin(auth()->user());
        $format = request()->get('format', 'csv');
        $allColumns = [
            'date' => 'Date',
            'invoice_no' => 'Invoice No',
            'document_type' => 'Document Type',
            'customer' => 'Customer',
            'location' => 'Location',
            'payment_status' => 'Payment Status',
            'total_amount' => 'Total Amount',
            'total_paid' => 'Total Paid',
            'total_remaining' => 'Total Remaining',
            'tax' => 'Tax',
        ];

        $columnsParam = request()->get('columns');
        if (is_string($columnsParam)) {
            $selectedColumns = array_filter(explode(',', $columnsParam));
        } elseif (is_array($columnsParam)) {
            $selectedColumns = $columnsParam;
        } else {
            $selectedColumns = [];
        }
        $selectedColumns = array_values(array_filter($selectedColumns, function ($key) use ($allColumns) {
            return array_key_exists($key, $allColumns);
        }));
        if (empty($selectedColumns)) {
            $selectedColumns = array_keys($allColumns);
        }

        // Build query using the same logic as getSummarySalesData
        $sells = $this->transactionUtil->getListSells($business_id);
        $sells->addSelect(DB::raw('SUM((tsl.quantity - tsl.quantity_returned) * tsl.item_tax) as line_tax_total'));
        $sells->addSelect(DB::raw('SUM((tsl.quantity - tsl.quantity_returned) * (tsl.unit_price_inc_tax - tsl.unit_price)) as calc_line_tax_total'));

        // Apply document type filter (single-document mode)
        $document_filter = request()->get('document_filter', 'both');
        $payment_status_filter = request()->input('payment_status');

        $this->applySummarySalesBaseVtFilter($sells);
        if ($document_filter == 'billing_receive') {
            $this->applySummarySalesBillingReceiveFilter($sells);
        }
        $this->applySummarySalesPaymentStatusFilter($sells, $payment_status_filter);

        // Apply location permission
        $permitted_locations = auth()->user()->permitted_locations();
        if ($permitted_locations != 'all') {
            $sells->whereIn('transactions.location_id', $permitted_locations);
        }

        // Apply filters
        if (!empty(request()->customer_id)) {
            $sells->where('contacts.id', request()->customer_id);
        }
        if (!empty(request()->location_id)) {
            $sells->where('transactions.location_id', request()->location_id);
        }

        // Date range filter
        if (!empty(request()->start_date) && !empty(request()->end_date)) {
            $start = request()->start_date;
            $end = request()->end_date;
            $sells->whereDate('transactions.transaction_date', '>=', $start)
                  ->whereDate('transactions.transaction_date', '<=', $end);
        }

        // Apply own sell view restriction
        if (!$is_admin && request()->session()->get('user.view_own_sell_only') == 1) {
            $sells->where('transactions.created_by', request()->session()->get('user.id'));
        }

        $sells->groupBy('transactions.id');

        // Get the data
        $data = $sells->get();

        // Prepare export data
        $exportData = [];
        foreach ($data as $row) {
            // Determine document type
            $invoiceNo = $row->invoice_no ?? '';
            if (substr($invoiceNo, 0, 2) === 'VT') {
                $docType = 'Tax-Invoice';
            } elseif ($row->document_type == 'proforma' || $row->sub_status == 'proforma') {
                $docType = 'Tax-Invoice';
            } else {
                $docType = 'Other';
            }

            // Calculate totals
            $total_paid = !empty($row->total_paid) ? $row->total_paid - $row->total_change_return : 0;
            $total_remaining = $row->final_total - $row->total_paid + $row->total_change_return;

            // Get payment status
            $payment_status = Transaction::getPaymentStatus($row);

            $tax_amount = (float) ($row->tax_amount ?? 0);
            if ($tax_amount <= 0 && !empty($row->line_tax_total)) {
                $tax_amount = (float) $row->line_tax_total;
            }
            if ($tax_amount <= 0 && !empty($row->calc_line_tax_total)) {
                $tax_amount = (float) $row->calc_line_tax_total;
            }
            if ($tax_amount <= 0 && isset($row->final_total, $row->total_before_tax)) {
                $tax_amount = (float) $row->final_total - (float) $row->total_before_tax;
            }
            if ($tax_amount < 0) {
                $tax_amount = 0;
            }

            $rowData = [
                'date' => $this->transactionUtil->format_date($row->transaction_date, true),
                'invoice_no' => $row->invoice_no,
                'document_type' => $docType,
                'customer' => $row->name ?? '',
                'location' => $row->location_name ?? '',
                'payment_status' => ucfirst($payment_status),
                'total_amount' => number_format($row->final_total, 2),
                'total_paid' => number_format($total_paid, 2),
                'total_remaining' => number_format($total_remaining, 2),
                'tax' => number_format($tax_amount, 2),
            ];

            $exportRow = [];
            foreach ($selectedColumns as $key) {
                $exportRow[$allColumns[$key]] = $rowData[$key] ?? '';
            }
            $exportData[] = $exportRow;
        }

        // Generate filename
        $startDate = request()->start_date ?? date('Y-m-d');
        $endDate = request()->end_date ?? date('Y-m-d');
        $filename = 'summary_sales_' . $startDate . '_to_' . $endDate;

        if ($format === 'xlsx') {
            // Export as XLSX
            if (ob_get_contents()) {
                ob_end_clean();
            }
            ob_start();

            return collect($exportData)->downloadExcel(
                $filename . '.xlsx',
                null,
                true
            );
        } else {
            // Export as CSV
            return response()->streamDownload(function () use ($exportData) {
                $output = fopen('php://output', 'w');

                // Add BOM for UTF-8
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

                // Add headers
                if (!empty($exportData)) {
                    fputcsv($output, array_keys($exportData[0]));
                }

                // Add data rows
                foreach ($exportData as $row) {
                    fputcsv($output, $row);
                }

                fclose($output);
            }, $filename . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }
    }

    /**
     * Get related IPAY invoice for a VT transaction
     *
     * @param int $id - VT transaction ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRelatedIpay($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            // Find VT transaction
            $vt_transaction = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->where('invoice_no', 'LIKE', 'VT%')
                ->first();

            if (!$vt_transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'VT transaction not found'
                ]);
            }

            // Find related IPAY transaction using multiple methods:
            // 1. Check linked_billing_receive_id (new system)
            // 2. Check transfer_parent_id (old/legacy system)
            $ipay_transaction = null;

            // Method 1: Check linked_billing_receive_id (preferred)
            if ($vt_transaction->linked_billing_receive_id) {
                $ipay_transaction = Transaction::where('business_id', $business_id)
                    ->where('id', $vt_transaction->linked_billing_receive_id)
                    ->where('invoice_no', 'LIKE', 'IPAY%')
                    ->first();
            }

            // Method 2: Check transfer_parent_id (fallback for old data)
            if (!$ipay_transaction) {
                $ipay_transaction = Transaction::where('business_id', $business_id)
                    ->where('transfer_parent_id', $id)
                    ->where('invoice_no', 'LIKE', 'IPAY%')
                    ->orderBy('created_at', 'DESC')
                    ->first();
            }

            // Method 3: Reverse link on IPAY (linked_tax_invoice_id -> VT id)
            if (!$ipay_transaction) {
                $ipay_transaction = Transaction::where('business_id', $business_id)
                    ->where('linked_tax_invoice_id', $id)
                    ->where('invoice_no', 'LIKE', 'IPAY%')
                    ->orderBy('created_at', 'DESC')
                    ->first();
            }

            // Method 4: payment_ref_no from transaction_payments (authoritative for migrated/synced bills)
            // Example: VT2026/0370 -> payment_ref_no IPAY2026/11174
            $payment_ref_ipay = null;
            if (!$ipay_transaction) {
                $payment_ref_ipay = (string) DB::table('transaction_payments')
                    ->where('transaction_id', $vt_transaction->id)
                    ->whereNotNull('payment_ref_no')
                    ->where('payment_ref_no', 'LIKE', 'IPAY%')
                    ->orderByDesc('id')
                    ->value('payment_ref_no');
                $payment_ref_ipay = trim($payment_ref_ipay);
            }

            if (!$ipay_transaction && $payment_ref_ipay !== '') {
                $ipay_transaction = Transaction::where('business_id', $business_id)
                    ->where('invoice_no', $payment_ref_ipay)
                    ->orderBy('created_at', 'DESC')
                    ->first();
            }

            if ($ipay_transaction) {
                return response()->json([
                    'success' => true,
                    'ipay' => [
                        'id' => $ipay_transaction->id,
                        'invoice_no' => $ipay_transaction->invoice_no,
                        'final_total' => $ipay_transaction->final_total,
                        'synthetic' => false,
                        'source' => 'transaction',
                    ]
                ]);
            }

            // Method 5: No IPAY transaction row, but payment_ref_no already carries the real IPAY number.
            // Return a synthetic mapping using VT transaction id for print context.
            if ($payment_ref_ipay !== '') {
                return response()->json([
                    'success' => true,
                    'ipay' => [
                        'id' => $vt_transaction->id,
                        'invoice_no' => $payment_ref_ipay,
                        'final_total' => $vt_transaction->final_total,
                        'synthetic' => true,
                        'source' => 'payment_ref_no',
                    ]
                ]);
            }

            // Method 6: Paid VT fallback (only confirms payment status; no IPAY reference found)
            $received_payment_total = (float) DB::table('transaction_payments')
                ->where('transaction_id', $vt_transaction->id)
                ->where('is_return', 0)
                ->sum('amount');
            $has_received_payment = $received_payment_total > 0 || in_array((string) $vt_transaction->payment_status, ['paid', 'partial'], true);

            if ($has_received_payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment exists but no IPAY reference found in payment_ref_no'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No related IPAY found'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Get related VT (Tax Invoice) for an IPAY (Billing Receipt)
     *
     * @param int $id IPAY transaction ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRelatedVt($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            // Find IPAY transaction
            $ipay_transaction = Transaction::where('business_id', $business_id)
                ->where('id', $id)
                ->where('invoice_no', 'LIKE', 'IPAY%')
                ->first();

            if (!$ipay_transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'IPAY transaction not found'
                ]);
            }

            // Find related VT transaction using multiple methods:
            // 1. Check linked_tax_invoice_id (new system)
            // 2. Check transfer_parent_id (old/legacy system)
            $vt_transaction = null;

            // Method 1: Check linked_tax_invoice_id (preferred)
            if ($ipay_transaction->linked_tax_invoice_id) {
                $vt_transaction = Transaction::where('business_id', $business_id)
                    ->where('id', $ipay_transaction->linked_tax_invoice_id)
                    ->where('invoice_no', 'LIKE', 'VT%')
                    ->first();
            }

            // Method 2: Check transfer_parent_id (fallback for old data)
            if (!$vt_transaction && $ipay_transaction->transfer_parent_id) {
                $vt_transaction = Transaction::where('business_id', $business_id)
                    ->where('id', $ipay_transaction->transfer_parent_id)
                    ->where('invoice_no', 'LIKE', 'VT%')
                    ->first();
            }

            // Method 3: Reverse link on VT (linked_billing_receive_id -> IPAY id)
            if (!$vt_transaction) {
                $vt_transaction = Transaction::where('business_id', $business_id)
                    ->where('linked_billing_receive_id', $ipay_transaction->id)
                    ->where('invoice_no', 'LIKE', 'VT%')
                    ->orderBy('created_at', 'DESC')
                    ->first();
            }

            // Method 4: Invoice-number equivalent (IPAY2026/0394 -> VT2026/0394)
            if (!$vt_transaction && !empty($ipay_transaction->invoice_no) && str_starts_with($ipay_transaction->invoice_no, 'IPAY')) {
                $vt_equivalent = 'VT' . substr($ipay_transaction->invoice_no, 4);
                $vt_transaction = Transaction::where('business_id', $business_id)
                    ->where('invoice_no', $vt_equivalent)
                    ->orderBy('created_at', 'DESC')
                    ->first();
            }

            if ($vt_transaction) {
                return response()->json([
                    'success' => true,
                    'vt' => [
                        'id' => $vt_transaction->id,
                        'invoice_no' => $vt_transaction->invoice_no,
                        'final_total' => $vt_transaction->final_total
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No related VT found'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }
}
