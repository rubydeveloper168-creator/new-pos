@extends('layouts.app')
@section('title', __( 'sale.list_pos'))

@section('content')
<style>
#pos_list_receipt_modal .modal-dialog {
    width: 514px;
    max-width: calc(100vw - 14px);
    margin: 6px auto;
}
#pos_list_receipt_modal .modal-content {
    background: #fff;
    border: none;
    border-radius: 0;
    box-shadow: 0 10px 26px rgba(0, 0, 0, 0.42);
}
#pos_list_receipt_modal .modal-header {
    border-bottom: 0;
    padding: 2px 4px 0;
    min-height: 20px;
}
#pos_list_receipt_modal .modal-header .close {
    margin-top: -3px;
    font-size: 40px;
    line-height: 1;
    color: #b8b8b8;
    opacity: 1;
    text-shadow: none;
    font-weight: 300;
}
#pos_list_receipt_modal .modal-body {
    padding: 0 4px 4px;
    max-height: none;
    overflow: visible;
}
#pos_list_receipt_body {
    background: #fff;
    border: 0;
    /* padding: 2px 6px 6px; */
    color: #111;
    font-size: 14px !important;
}
#pos_list_receipt_body * {
    font-size: 14px !important;
}
#pos_list_receipt_body #wrapper,
#pos_list_receipt_body #receiptData,
#pos_list_receipt_body #receipt-data,
#pos_list_receipt_body .ticket,
#pos_list_receipt_body .text-box,
#pos_list_receipt_body .row,
#pos_list_receipt_body .col-xs-12,
#pos_list_receipt_body .col-xs-8,
#pos_list_receipt_body .col-xs-6,
#pos_list_receipt_body .col-xs-4,
#pos_list_receipt_body .col-md-12 {
    background: transparent !important;
}
#pos_list_receipt_body table,
#pos_list_receipt_body tbody,
#pos_list_receipt_body thead,
#pos_list_receipt_body tfoot,
#pos_list_receipt_body tr,
#pos_list_receipt_body td,
#pos_list_receipt_body th {
    background-color: transparent !important;
}
#pos_list_receipt_modal .modal-footer {
    border: 1px solid #c9c9c9;
    padding: 0;
    margin: 8px 12px 0;
    display: flex;
}
#pos_list_receipt_modal .pos-list-receipt-btn {
    flex: 1 1 0;
    margin: 0;
    border-radius: 0;
    border: 0;
    min-height: 26px;
    font-size: 13px;
    font-weight: 600;
}
#pos_list_receipt_modal #pos_list_receipt_print {
    color: #fff;
    background: #2f7ec1;
}
#pos_list_receipt_modal #pos_list_receipt_email {
    color: #fff;
    background: #5cb85c;
    border-left: 1px solid rgba(255, 255, 255, 0.35);
    border-right: 1px solid rgba(255, 255, 255, 0.35);
}
#pos_list_receipt_modal #pos_list_receipt_email[disabled] {
    opacity: 0.85;
    cursor: not-allowed;
}
#pos_list_receipt_modal .pos-list-receipt-close {
    color: #303030;
    background: #ededed;
    border-left: 1px solid #c9c9c9;
}
#pos_list_receipt_modal .pos-list-receipt-print-note {
    margin: 0 12px 12px;
    padding: 8px 10px 10px;
    font-size: 12px;
    color: #3c3c3c;
    background: #efefef;
    border-top: 0;
    line-height: 1.42;
    font-weight: 600;
}
#pos_list_receipt_modal .pos-list-receipt-print-note > div:first-child {
    font-weight: 700;
}
#pos_list_receipt_body .row {
    margin-left: 0;
    margin-right: 0;
}
#pos_list_receipt_body .ticket {
    background: transparent !important;
}
</style>

<!-- POS list receipt modal interceptor -->
<script>
(function() {
    var receiptHtmlCache = '';
    var receiptTitleCache = '';
    var receiptLinkCache = '';

    function normalizeReceiptHtml(html) {
        var raw = String(html || '');
        var bodyMatch = raw.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
        var styles = raw.match(/<style[\s\S]*?<\/style>/gi);
        var styleHtml = styles ? styles.join('') : '';
        return bodyMatch && bodyMatch[1] ? styleHtml + bodyMatch[1] : raw;
    }

    function injectRubyLogoIfNeeded(container) {
        if (!container) {
            return;
        }
        if (container.querySelector('img[src*="invoice_logos"], img[src*="ruby-logo"], img[src*="rubyshop"]')) {
            return;
        }

        var heading = container.querySelector('h2');
        if (!heading || !heading.textContent || !heading.textContent.trim()) {
            return;
        }

        var img = document.createElement('img');
        img.src = 'https://sale.rubyshop.co.th/assets/uploads/logos/ruby-logo1.jpg';
        img.alt = heading.textContent.trim();
        img.style.cssText = 'max-height:95px;width:auto;display:block;margin:0 auto 6px;';
        heading.parentNode.replaceChild(img, heading);
    }

    function updateEmailButtonState() {
        var btn = document.getElementById('pos_list_receipt_email');
        if (!btn) {
            return;
        }
        btn.disabled = !receiptLinkCache;
    }

    function ensureReceiptModal() {
        if (document.getElementById('pos_list_receipt_modal')) {
            return;
        }

        var wrapper = document.createElement('div');
        wrapper.innerHTML =
            '<div class="modal fade no-print" id="pos_list_receipt_modal" tabindex="-1" role="dialog" aria-hidden="true">' +
                '<div class="modal-dialog" role="document">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<button type="button" class="close" data-dismiss="modal" aria-label="Close">' +
                                '<span aria-hidden="true">&times;</span>' +
                            '</button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<div id="pos_list_receipt_body"></div>' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button id="pos_list_receipt_print" type="button" class="pos-list-receipt-btn">พิมพ์</button>' +
                            '<button id="pos_list_receipt_email" type="button" class="pos-list-receipt-btn">อีเมล์</button>' +
                            '<button type="button" data-dismiss="modal" class="pos-list-receipt-btn pos-list-receipt-close">ปิด</button>' +
                        '</div>' +
                        '<div class="pos-list-receipt-print-note">' +
                            '<div>PLEASE DON\'T FORGET TO DISABLE THE HEADER AND FOOTER IN BROWSER PRINT SETTINGS. YOU CAN SET ZOOM/SCALE AS YOU NEED.</div>' +
                            '<div><strong>FF:</strong> File &gt; Print Setup &gt; Margin &amp; Header/Footer Make All --Blank--</div>' +
                            '<div><strong>Chrome:</strong> Menu &gt; Print &gt; Disable Header/Footer In Option &amp; Set Margins To None</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(wrapper.firstChild);

        document.getElementById('pos_list_receipt_print').addEventListener('click', function() {
            if (!receiptHtmlCache) {
                return;
            }

            var originalTitle = document.title;
            if (receiptTitleCache) {
                document.title = receiptTitleCache;
            }

            var printSection = document.getElementById('receipt_section');
            printSection.innerHTML = receiptHtmlCache;
            injectRubyLogoIfNeeded(printSection);
            if (typeof __currency_convert_recursively === 'function') {
                __currency_convert_recursively($('#receipt_section'));
            }
            if (typeof __print_receipt === 'function') {
                __print_receipt('receipt_section');
            } else {
                window.print();
            }

            setTimeout(function() {
                document.title = originalTitle;
            }, 1200);
        });

        document.getElementById('pos_list_receipt_email').addEventListener('click', function() {
            if (!receiptLinkCache) {
                if (typeof toastr !== 'undefined') {
                    toastr.warning('No email/notification link available.');
                }
                return;
            }
            window.open(receiptLinkCache, '_blank');
        });
    }

    function setReceiptLoadingState() {
        document.getElementById('pos_list_receipt_body').innerHTML =
            '<div style="text-align:center;padding:34px;"><i class="fa fa-spinner fa-spin fa-2x"></i></div>';
    }

    function setReceiptContent(receipt, whatsappLink) {
        receiptHtmlCache = normalizeReceiptHtml(receipt.html_content || '');
        receiptTitleCache = receipt.print_title || '';
        receiptLinkCache = whatsappLink || receipt.whatsapp_link || '';

        var container = document.getElementById('pos_list_receipt_body');
        container.innerHTML = receiptHtmlCache;
        injectRubyLogoIfNeeded(container);
        if (typeof __currency_convert_recursively === 'function') {
            __currency_convert_recursively($('#pos_list_receipt_body'));
        }
        updateEmailButtonState();
    }

    function extractSellId(href) {
        var m = String(href || '').match(/\/sells\/(\d+)(?:\/|$|\?)/);
        return m ? m[1] : null;
    }

    function openReceiptModalBySellId(sellId) {
        console.log('[POS DEBUG] openReceiptModalBySellId called, sellId=', sellId);
        ensureReceiptModal();
        var modalEl = document.getElementById('pos_list_receipt_modal');
        console.log('[POS DEBUG] modal element exists=', !!modalEl, ' jQuery=', (typeof $ !== 'undefined'));
        receiptHtmlCache = '';
        receiptTitleCache = '';
        receiptLinkCache = '';
        updateEmailButtonState();
        setReceiptLoadingState();
        try {
            $('#pos_list_receipt_modal').modal('show');
            console.log('[POS DEBUG] modal show called OK');
        } catch(ex) {
            console.error('[POS DEBUG] modal show error:', ex);
        }

        var ajaxUrl = '/sells/' + sellId + '/print';
        console.log('[POS DEBUG] AJAX GET', ajaxUrl);
        $.ajax({
            method: 'GET',
            url: ajaxUrl,
            dataType: 'json',
            success: function(res) {
                console.log('[POS DEBUG] AJAX success, res.success=', res && res.success, ' has html=', !!(res && res.receipt && res.receipt.html_content));
                if (res && res.success == 1 && res.receipt && res.receipt.html_content) {
                    setReceiptContent(res.receipt, res.whatsapp_link);
                } else {
                    console.warn('[POS DEBUG] AJAX res not valid, msg=', res && res.msg, ' full=', JSON.stringify(res));
                    $('#pos_list_receipt_modal').modal('hide');
                    if (typeof toastr !== 'undefined') {
                        toastr.error((res && res.msg) ? res.msg : 'ไม่สามารถโหลดใบเสร็จได้');
                    }
                }
            },
            error: function(xhr, status, err) {
                console.error('[POS DEBUG] AJAX error:', status, err, xhr.status, xhr.responseText && xhr.responseText.substring(0, 300));
                $('#pos_list_receipt_modal').modal('hide');
                if (typeof toastr !== 'undefined') {
                    toastr.error('ไม่สามารถโหลดใบเสร็จได้');
                }
            }
        });
    }

    window.showPosReceiptPreviewModal = function(receipt) {
        if (!receipt || !receipt.html_content) {
            return;
        }
        ensureReceiptModal();
        setReceiptContent(receipt, receipt.whatsapp_link || '');
        $('#pos_list_receipt_modal').modal('show');
    };

    document.addEventListener('click', function(e) {
        var tag = e.target ? e.target.tagName : '?';
        var cls = e.target ? (e.target.className || '') : '';
        console.log('[POS DEBUG] capture click: tag=', tag, ' class=', cls, ' id=', e.target && e.target.id);

        var printLink = e.target.closest('a.print-invoice');
        if (printLink && printLink.getAttribute('data-href')) {
            var printId = extractSellId(printLink.getAttribute('data-href'));
            console.log('[POS DEBUG] printLink matched, printId=', printId);
            if (printId) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                openReceiptModalBySellId(printId);
                return;
            }
        }

        var viewLink = e.target.closest('a.btn-modal[data-container=".view_modal"]');
        if (viewLink && viewLink.getAttribute('data-href')) {
            var viewId = extractSellId(viewLink.getAttribute('data-href'));
            console.log('[POS DEBUG] viewLink matched, viewId=', viewId);
            if (viewId) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                openReceiptModalBySellId(viewId);
                return;
            }
        }

        var row = e.target.closest('#sell_table tbody tr[data-href]');
        console.log('[POS DEBUG] row found=', !!row, ' row data-href=', row ? row.getAttribute('data-href') : 'N/A');
        if (!row) {
            return;
        }

        var blockedBy = e.target.closest('a, button, input, select, .btn-group, .dropdown-menu');
        console.log('[POS DEBUG] blocked by=', blockedBy ? blockedBy.tagName + '.' + (blockedBy.className || '') : 'none');
        if (blockedBy) {
            return;
        }

        var rowId = extractSellId(row.getAttribute('data-href'));
        console.log('[POS DEBUG] rowId=', rowId);
        if (rowId) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            openReceiptModalBySellId(rowId);
        }
    }, true);
}());
</script>

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('sale.pos_sale')
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    @component('components.filters', ['title' => __('report.filters')])
        @include('sell.partials.sell_list_filters')
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'sale.list_pos')])
        @can('sell.create')
            @slot('tool')
                <div class="box-tools">
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{action([\App\Http\Controllers\SellPosController::class, 'create'])}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                </div>
            @endslot
        @endcan
        @can('sell.view')
            <input type="hidden" name="is_direct_sale" id="is_direct_sale" value="0">
            @include('sale_pos.partials.sales_table')
        @endcan
    @endcomponent
</section>
<!-- /.content -->
<div class="modal fade payment_modal" tabindex="-1" role="dialog"
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog"
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade register_details_modal" tabindex="-1" role="dialog"
    aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog"
    aria-labelledby="gridSystemModalLabel">
</div>

<!-- This will be printed -->
<section class="invoice print_section" id="receipt_section">
</section>

@stop

@section('javascript')
@include('sale_pos.partials.sale_table_javascript')
<script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection
