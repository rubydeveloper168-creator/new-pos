@php
    $invoiceNo = $sell->invoice_no ?? '';
    $isVT = substr($invoiceNo, 0, 2) === 'VT';
    $isIPAY = substr($invoiceNo, 0, 4) === 'IPAY';
    $isQuotation = ($sell->status == 'draft' && ($sell->sub_status == 'quotation' || $sell->is_quotation == 1));
    $showProductImages = $isQuotation || str_starts_with(strtoupper((string) $invoiceNo), 'QUOTE');

    $docTypeLabel = 'เอกสาร';
    $docTypeEN = 'Document';
    if ($isVT) {
        $docTypeLabel = 'ใบกำกับภาษี';
        $docTypeEN = 'TAX INVOICE';
    } elseif ($isIPAY) {
        $docTypeLabel = 'ใบเสร็จรับเงิน';
        $docTypeEN = 'Billing Receipt';
    } elseif ($isQuotation) {
        $docTypeLabel = 'ใบเสนอราคา';
        $docTypeEN = 'Quotation';
    }

    $relatedIpay = null;
    $relatedVt = null;

    if ($isVT) {
        if (!empty($sell->linked_billing_receive_id)) {
            $relatedIpay = \App\Transaction::find($sell->linked_billing_receive_id);
        }
        if (!$relatedIpay) {
            $relatedIpay = \App\Transaction::where('linked_tax_invoice_id', $sell->id)
                                           ->where('status', 'final')
                                           ->first();
        }
        if (!$relatedIpay) {
            $relatedIpay = \App\Transaction::where('transfer_parent_id', $sell->id)
                                           ->where('status', 'final')
                                           ->where('invoice_no', 'LIKE', 'IPAY%')
                                           ->first();
        }
        if (!$relatedIpay) {
            $paymentRefIpay = $sell->payment_lines
                ->pluck('payment_ref_no')
                ->filter(fn($ref) => !empty($ref) && str_starts_with((string) $ref, 'IPAY'))
                ->first();
            if (!empty($paymentRefIpay)) {
                $relatedIpay = \App\Transaction::where('business_id', $sell->business_id)
                                               ->where('invoice_no', $paymentRefIpay)
                                               ->first();
                if (!$relatedIpay) {
                    $relatedIpay = (object) ['id' => $sell->id, 'invoice_no' => $paymentRefIpay, 'synthetic' => true];
                }
            }
        }
    } elseif ($isIPAY) {
        if (!empty($sell->linked_tax_invoice_id)) {
            $relatedVt = \App\Transaction::find($sell->linked_tax_invoice_id);
        }
        if (!$relatedVt && !empty($sell->transfer_parent_id)) {
            $relatedVt = \App\Transaction::find($sell->transfer_parent_id);
        }
    }

    $received_payment_total = $sell->payment_lines->where('is_return', 0)->sum('amount');
    $has_received_payment = $received_payment_total > 0;

    $billingReceiveDisplayNo = null;
    if ($isIPAY) {
        $billingReceiveDisplayNo = $invoiceNo;
    } elseif ($isVT && !empty($relatedIpay?->invoice_no)) {
        $billingReceiveDisplayNo = $relatedIpay->invoice_no;
    }

    // Totals
    $total_paid = 0;
    foreach ($sell->payment_lines as $pl) {
        $total_paid += $pl->is_return == 1 ? -$pl->amount : $pl->amount;
    }
    $total_remaining = $sell->final_total - $total_paid;

    // Payment status label
    $paymentStatusLabel = '';
    if (!empty($sell->payment_status)) {
        $paymentStatusMap = ['paid' => 'ชำระแล้ว', 'due' => 'ค้างชำระ', 'partial' => 'ชำระบางส่วน', 'overdue' => 'เกินกำหนด'];
        $paymentStatusLabel = $paymentStatusMap[$sell->payment_status] ?? $sell->payment_status;
    }

    // Status label
    $statusLabel = '';
    if ($sell->status == 'draft' && $sell->is_quotation == 1) {
        $statusLabel = 'ใบเสนอราคา';
    } elseif ($sell->status == 'draft' && $sell->sub_status == 'proforma') {
        $statusLabel = 'เรียกเก็บได้';
    } elseif ($sell->status == 'final') {
        $statusLabel = 'ชำระแล้ว';
    } else {
        $statusLabel = $statuses[$sell->status] ?? $sell->status;
    }
@endphp

<style>
.inv-modal-wrap { font-family: 'Sarabun', 'Noto Sans Thai', Arial, sans-serif; font-size: 14px; color: #222; background: #fff; }
.inv-header-bar { background: #fff; border: 1px solid #d9d9d9; border-bottom: none; padding: 14px 16px 8px; text-align: center; }
.inv-header-bar .inv-doc-title { font-size: 31px; font-weight: 700; color: #2b2b2b; line-height: 1.2; margin: 0; }
.inv-header-bar .inv-doc-subline { margin-top: 8px; font-size: 13px; color: #333; display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; }
.inv-parties { display: flex; flex-direction: row; border: 1px solid #d9d9d9; border-top: none; }
.inv-party { flex: 1; padding: 10px 14px; border-right: 1px solid #d9d9d9; }
.inv-party:last-child { border-right: none; }
.inv-party-label { font-weight: 700; font-size: 17px; color: #333; margin-bottom: 6px; line-height: 1.2; }
.inv-party-name { font-weight: 700; font-size: 16px; margin-bottom: 5px; color: #2c2c2c; }
.inv-party-detail { font-size: 14px; color: #333; line-height: 1.55; }
.inv-products-section { border: 1px solid #d9d9d9; border-top: none; }
.inv-section-header { display: none; }
.inv-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.inv-table thead th { background: #3f8fcb; color: #fff; border: 1px solid #2f79b3; padding: 8px 8px; text-align: left; font-weight: 700; font-size: 12px; }
.inv-table thead th.text-right { text-align: right; }
.inv-table thead th.text-center { text-align: center; }
.inv-table tbody td { padding: 8px 8px; border: 1px solid #d9d9d9; vertical-align: top; background: #fff; }
.inv-table tbody td.text-right { text-align: right; }
.inv-table tbody td.text-center { text-align: center; }
.inv-sku { font-size: 11px; color: #666; }
.inv-bottom-row { display: flex; flex-direction: column; border: 1px solid #d9d9d9; border-top: none; }
.inv-notes-col { padding: 12px 14px; border-top: 1px solid #d9d9d9; background: #f7f7f7; }
.inv-totals-col { padding: 0; }
.inv-totals-table { width: 100%; max-width: 320px; font-size: 12px; margin-left: auto; }
.inv-totals-table td { padding: 6px 10px; border: 1px solid #d9d9d9; }
.inv-totals-table .lbl { color: #333; font-weight: 700; }
.inv-totals-table .val { text-align: right; font-weight: 700; color: #222; }
.inv-totals-table .total-row td { font-weight: 700; font-size: 12px; }
.inv-payments-section { border: 1px solid #d9d9d9; border-top: none; padding: 12px 16px; background: #fff; }
.inv-payments-section h5 { font-weight: 700; font-size: 12px; text-transform: uppercase; color: #555; margin: 0 0 8px; }
.inv-activity-section { border: 1px solid #d9d9d9; border-top: none; padding: 10px 14px; background: #f7f7f7; }
.inv-activity-section h5 { font-weight: 700; font-size: 12px; color: #333; margin: 0 0 6px; text-transform: none; }
.status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
.status-vt { background: #fff3cd; color: #856404; }
.status-ipay { background: #d1e7dd; color: #0f5132; }
.status-paid { background: #d1e7dd; color: #0f5132; }
.status-due { background: #f8d7da; color: #842029; }
.status-partial { background: #fff3cd; color: #856404; }
.inv-slip-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.inv-slip-img { width: 120px; height: 90px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; cursor: pointer; }

</style>

<div class="modal-dialog modal-lg no-print" role="document" style="max-width: 920px; width: 92%;">
  <div class="modal-content inv-modal-wrap">

    {{-- Modal Header Bar --}}
    <div class="modal-header" style="padding: 10px 16px; border-bottom: none;">
      <button type="button" class="close no-print" data-dismiss="modal" aria-label="Close">
        <span aria-hidden="true">&times;</span>
      </button>
      <button type="button" class="btn btn-sm btn-default no-print" onclick="printCurrentModal()"
              style="float:right; margin-left:6px; padding:3px 10px; border:1px solid #ccc;"
              title="พิมพ์ / Print to PDF">
        <i class="fa fa-print"></i> พิมพ์
      </button>
      @if($isVT || $isQuotation)
      <a href="{{ route('sells.export-modal-excel', ['id' => $sell->id, 'document_type' => ($isQuotation ? 'quotation' : 'proforma')]) }}"
         class="btn btn-sm btn-success no-print"
         style="float:right; margin-left:6px; padding:3px 10px;"
         title="Export to Excel">
        <i class="fa fa-file-excel-o"></i> Excel
      </a>
      @endif
      <h4 class="modal-title" style="font-size: 14px; font-weight: 700;">
        @if($sell->type == 'sales_order') @lang('restaurant.order_no') @else @lang('sale.invoice_no') @endif:
        <span style="color:#ff3131;">{{ $invoiceNo }}</span>
      </h4>
    </div>

    <div class="modal-body" style="padding: 0;">

      {{-- Invoice Header --}}
      <div class="inv-header-bar">
        <div class="inv-doc-title">{{ $docTypeLabel }}</div>
        <div class="inv-doc-subline">
          <span><strong>เลขที่:</strong> {{ $invoiceNo }}</span>
          <span><strong>วันที่:</strong> {{ @format_datetime($sell->transaction_date) }}</span>
        </div>
      </div>

      {{-- Parties: Customer + Company --}}
      <div class="inv-parties">
        {{-- Customer (ถึง) --}}
        <div class="inv-party">
          <div class="inv-party-label">ถึง: / To:</div>
          @if(!empty($sell->contact->supplier_business_name) && $sell->contact->supplier_business_name !== $sell->contact->name)
            <div class="inv-party-name">{{ $sell->contact->supplier_business_name }}</div>
          @endif
          <div class="inv-party-name">{{ $sell->contact->name }}</div>
          <div class="inv-party-detail">
            @if(!empty($sell->contact->tax_number))
              เลขประจำตัวผู้เสียภาษี: {{ $sell->contact->tax_number }}<br>
            @endif
            @php
              $billingArr = $sell->billing_address(true);
              unset($billingArr['name'], $billingArr['company']);
              $billingStr = implode(', ', array_filter($billingArr));
            @endphp
            @if(!empty($billingStr))
              {!! nl2br(e($billingStr)) !!}
              @if($sell->contact->mobile)<br>โทรศัพท์: {{ $sell->contact->mobile }}@endif
              @if($sell->contact->alternate_number)<br>โทร: {{ $sell->contact->alternate_number }}@endif
              @if($sell->contact->landline)<br>สายตรง: {{ $sell->contact->landline }}@endif
              @if($sell->contact->email)<br>อีเมล: {{ $sell->contact->email }}@endif
            @else
              @if(!empty($sell->contact->address_line_1)){{ $sell->contact->address_line_1 }}<br>@endif
              @if(!empty($sell->contact->address_line_2)){{ $sell->contact->address_line_2 }}<br>@endif
              @if(!empty($sell->contact->city)){{ $sell->contact->city }}, @endif
              @if(!empty($sell->contact->state)){{ $sell->contact->state }}, @endif
              @if(!empty($sell->contact->country)){{ $sell->contact->country }}, @endif
              @if(!empty($sell->contact->zip_code)){{ $sell->contact->zip_code }}@endif
              @if($sell->contact->mobile)<br>โทรศัพท์: {{ $sell->contact->mobile }}@endif
              @if($sell->contact->alternate_number)<br>โทร: {{ $sell->contact->alternate_number }}@endif
              @if($sell->contact->landline)<br>สายตรง: {{ $sell->contact->landline }}@endif
              @if($sell->contact->email)<br>อีเมล: {{ $sell->contact->email }}@endif
            @endif
          </div>
        </div>

        {{-- Company (จาก) --}}
        <div class="inv-party">
          <div class="inv-party-label">จาก: / From:</div>
          <div class="inv-party-name">หจก.รูบี้ช๊อป (สำนักงานใหญ่)</div>
          <div class="inv-party-detail">
            97/60 หมู่บ้านหลักสี่แลนด์ ซอยโกสุมรวมใจ39<br>
            แขวงดอนเมือง เขตดอนเมือง กรุงเทพฯ 10210<br>
            เลขประจำตัวผู้เสียภาษี: 0103555019171<br>
            เบอร์โทรศัพท์: 089-666-7802<br>
            อีเมล: info@rubyshop.co.th
          </div>
        </div>
      </div>

      {{-- Products Section --}}
      <div class="inv-products-section">
        <div class="inv-section-header">สินค้าและบริการ / Products &amp; Services</div>
        <table class="inv-table">
          <thead>
            <tr>
              <th style="width:4%;" class="text-center">item no.</th>
              <th style="width:61%;">คำอธิบาย</th>
              <th style="width:10%;" class="text-center">จำนวน</th>
              <th style="width:12%;" class="text-right">ราคาขายต่อชิ้น</th>
              <th style="width:13%;" class="text-right">ยอดรวม</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sell->sell_lines as $sell_line)
            <tr>
              <td class="text-center">{{ $loop->iteration }}</td>
              <td>
                <div style="display:flex; align-items:flex-start; gap:10px;">
                  @if($showProductImages)
                    @if($sell_line->product && !empty($sell_line->product->image_url))
                      <img src="{{ $sell_line->product->image_url }}"
                           alt="{{ $sell_line->product->name }}"
                           style="width:56px; height:56px; object-fit:cover; border-radius:4px; border:1px solid #e0e0e0; flex-shrink:0;"
                           onerror="this.style.display='none'">
                    @else
                      <div style="width:56px; height:56px; border-radius:4px; border:1px solid #e0e0e0; background:#f5f5f5; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                        <i class="fa fa-image" style="color:#ccc; font-size:18px;"></i>
                      </div>
                    @endif
                  @endif
                  <div>
                    @if($sell_line->product)
                      @if(!empty($sell_line->variations->sub_sku))
                        <span class="inv-sku">{{ $sell_line->variations->sub_sku }}</span> -
                      @endif
                      <strong>{{ $sell_line->product->name }}</strong>
                      @if($sell_line->product->type == 'variable')
                        @if(!empty($sell_line->variations->product_variation->name)) - {{ $sell_line->variations->product_variation->name }} @endif
                        @if(!empty($sell_line->variations->name)) - {{ $sell_line->variations->name }} @endif
                      @endif
                    @else
                      <span class="text-red">Product Label (Missing)</span>
                      @if(!empty($sell_line->variations->sub_sku))
                        <span class="inv-sku"> {{ $sell_line->variations->sub_sku }}</span>
                      @endif
                    @endif
                    @if(!empty($sell_line->sell_line_note))
                      <div style="font-size:11px; color:#666; margin-top:3px;">{{ $sell_line->sell_line_note }}</div>
                    @endif
                  </div>
                </div>
              </td>
              <td class="text-center">
                <span class="display_currency" data-currency_symbol="false" data-is_quantity="true">{{ $sell_line->quantity }}</span>
                @if(!empty($sell_line->sub_unit)) {{ $sell_line->sub_unit->short_name }}
                @elseif(!empty($sell_line->product->unit)) {{ $sell_line->product->unit->short_name }}
                @endif
              </td>
              <td class="text-right">
                <span class="display_currency" data-currency_symbol="true">{{ $sell_line->unit_price_before_discount }}</span>
              </td>
              <td class="text-right">
                <span class="display_currency" data-currency_symbol="true">{{ $sell_line->quantity * $sell_line->unit_price_inc_tax }}</span>
              </td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      {{-- Bottom Row: Notes + Totals --}}
      <div class="inv-bottom-row">
        {{-- Notes --}}
        <div class="inv-notes-col">
          @php
            $rawAdditionalNotes = (string) ($sell->additional_notes ?? '');
            $decodedAdditionalNotes = html_entity_decode($rawAdditionalNotes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $decodedAdditionalNotes = preg_replace('/<\s*br\s*\/?>/i', "\n", $decodedAdditionalNotes);
            $decodedAdditionalNotes = preg_replace('/<\/\s*p\s*>/i', "\n", $decodedAdditionalNotes);
            $cleanAdditionalNotes = trim(strip_tags($decodedAdditionalNotes));
            $cleanAdditionalNotes = preg_replace('/\x{00A0}/u', ' ', $cleanAdditionalNotes);
            $cleanAdditionalNotes = preg_replace("/\n{3,}/", "\n\n", $cleanAdditionalNotes);
            $hasCleanAdditionalNotes = !empty($cleanAdditionalNotes);
          @endphp

          @if(!$hasCleanAdditionalNotes)
            <div style="font-weight:700; font-size:12px; color:#333; margin-bottom:6px;">อื่นๆ:</div>
            <div style="font-size:12px; color:#444; line-height:1.7;">
              รับประกันซ่อมฟรี 1 ปี (ไม่รวมอะไหล่ใหม่)<br>
              มีค่าใช้จ่ายในการ รับ - ส่ง (กรณีส่งซ่อม)<br>
              Service หลังการขาย ส่งสินค้าเข้าศูนย์บริการที่ดอนเมือง
            </div>
          @endif

          @if($hasCleanAdditionalNotes)
          <div style="margin-top:10px;">
            <div style="font-weight:700; font-size:12px; color:#333; margin-bottom:4px;">อื่นๆ:</div>
            <div style="font-size:12px; color:#444; line-height:1.7;">{!! nl2br(e($cleanAdditionalNotes)) !!}</div>
          </div>
          @endif
          @if(!empty($sell->staff_note))
          <div style="margin-top:8px;">
            <div style="font-weight:700; font-size:12px; color:#333; margin-bottom:4px;">บันทึกเจ้าหน้าที่:</div>
            <div style="font-size:12px; color:#444;">{!! nl2br(e($sell->staff_note)) !!}</div>
          </div>
          @endif
        </div>

        {{-- Totals --}}
        <div class="inv-totals-col">
          <table class="inv-totals-table">
            <tr>
              <td class="lbl">ทั้งหมด (THB)</td>
              <td class="val"><span class="display_currency" data-currency_symbol="false">{{ $sell->total_before_tax }}</span></td>
            </tr>
            @if($sell->discount_amount > 0)
            <tr>
              <td class="lbl">ส่วนลด (THB)</td>
              <td class="val" style="color:#c00;">
                -<span class="display_currency" data-currency_symbol="false">{{ $sell->discount_amount }}</span>
                @if($sell->discount_type == 'percentage') ({{ $sell->discount_amount }}%) @endif
              </td>
            </tr>
            @endif
            @php
              $vat_total = 0;
              if (!empty($order_taxes)) {
                  $vat_total = collect($order_taxes)->sum(function ($v) {
                      if (is_numeric($v)) {
                          return (float) $v;
                      }
                      return (float) str_replace(',', '', (string) $v);
                  });
              }
              if ($vat_total <= 0 && !empty($sell->tax_amount)) {
                  $vat_total = (float) $sell->tax_amount;
              }
            @endphp
            @if($vat_total > 0)
            <tr>
              <td class="lbl">ภาษี (THB)</td>
              <td class="val"><span class="display_currency" data-currency_symbol="false">{{ $vat_total }}</span></td>
            </tr>
            @elseif(!empty($order_taxes))
              @foreach($order_taxes as $k => $v)
              <tr>
                <td class="lbl">{{ $k }} (THB)</td>
                <td class="val"><span class="display_currency" data-currency_symbol="false">{{ $v }}</span></td>
              </tr>
              @endforeach
            @endif
            @if($sell->shipping_charges > 0)
            <tr>
              <td class="lbl">ค่าขนส่ง (THB)</td>
              <td class="val"><span class="display_currency" data-currency_symbol="false">{{ $sell->shipping_charges }}</span></td>
            </tr>
            @endif
            <tr class="total-row">
              <td class="lbl">รวมจำนวนเงิน (THB)</td>
              <td class="val"><span class="display_currency" data-currency_symbol="false">{{ $sell->final_total }}</span></td>
            </tr>
            @if($sell->type != 'sales_order')
            <tr>
              <td class="lbl">ชำระเงินแล้ว (THB)</td>
              <td class="val" style="color:#0a6630;"><span class="display_currency" data-currency_symbol="false">{{ $total_paid }}</span></td>
            </tr>
            <tr>
              <td class="lbl">ยอดคงเหลือ (THB)</td>
              <td class="val" style="{{ $total_remaining > 0 ? 'color:#c00;' : 'color:#0a6630;' }}">
                <span class="display_currency" data-currency_symbol="false">{{ $total_remaining }}</span>
              </td>
            </tr>
            @endif
          </table>
        </div>
      </div>

      {{-- Payment Lines (if any) --}}
      @if($sell->type != 'sales_order' && $sell->payment_lines->count() > 0)
      <div class="inv-payments-section">
        <h5>ข้อมูลการชำระเงิน / Payment Info</h5>
        <table class="inv-table">
          <thead>
            <tr>
              <th>#</th>
              <th>วันที่</th>
              <th>เลขอ้างอิง</th>
              <th>จำนวนเงิน</th>
              <th>วิธีชำระ</th>
              <th>หมายเหตุ</th>
            </tr>
          </thead>
          <tbody>
            @foreach($sell->payment_lines as $payment_line)
            <tr>
              <td>{{ $loop->iteration }}</td>
              <td>{{ @format_date($payment_line->paid_on) }}</td>
              <td>{{ $payment_line->payment_ref_no }}</td>
              <td><span class="display_currency" data-currency_symbol="true">{{ $payment_line->amount }}</span>
                @if($payment_line->is_return == 1) <small class="text-muted">(คืนเงิน)</small>@endif
              </td>
              <td>{{ $payment_types[$payment_line->method] ?? $payment_line->method }}</td>
              <td>{{ $payment_line->note ? ucfirst(strip_tags($payment_line->note)) : '--' }}</td>
            </tr>
            @endforeach
          </tbody>
        </table>
        {{-- Payment slip images --}}
        @php $slipImages = $sell->payment_lines->filter(fn($pl) => !empty($pl->document)); @endphp
        @if($slipImages->count() > 0)
        <div class="inv-slip-grid">
          @foreach($slipImages as $payment_line)
            @php
              $ext = strtolower(pathinfo($payment_line->document, PATHINFO_EXTENSION));
              $isImg = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            @endphp
            @if($isImg)
              <img src="{{ $payment_line->document_path }}"
                   class="inv-slip-img"
                   onclick="openImageModal('{{ $payment_line->document_path }}')"
                   title="{{ $payment_line->payment_ref_no }}">
            @else
              <a href="{{ $payment_line->document_path }}" target="_blank" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-accent">
                <i class="fa fa-download"></i> {{ $payment_line->document }}
              </a>
            @endif
          @endforeach
        </div>
        @endif
      </div>
      @endif

      {{-- Activity Log --}}
      <div class="inv-activity-section">
        <h5>บันทึกเจ้าหน้าที่:</h5>
        @includeIf('activity_log.activities', ['activity_type' => 'sell'])
      </div>

    </div>{{-- end modal-body --}}

    {{-- Modal Footer: Action Buttons --}}
    <div class="modal-footer no-print" style="display:flex; flex-wrap:wrap; justify-content:center; gap:8px; padding:12px 16px; background:#f8f8f8; border-top:1px solid #e0e0e0;">

      {{-- Print Tax-Invoice --}}
      @if($isVT)
        <button type="button" class="modal-print-btn"
                data-transaction-id="{{ $sell->id }}"
                data-document-type="proforma"
                style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#28a745; border:none; border-radius:4px; cursor:pointer; line-height:1.4;">
          <span><i class="fa fa-print"></i> ใบกำกับภาษี</span>
          <small style="font-weight:400; opacity:0.9;">({{ $invoiceNo }})</small>
        </button>
      @elseif($isIPAY && $relatedVt)
        <button type="button" class="modal-print-btn"
                data-transaction-id="{{ $relatedVt->id }}"
                data-document-type="proforma"
                style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#28a745; border:none; border-radius:4px; cursor:pointer; line-height:1.4;">
          <span><i class="fa fa-print"></i> ใบกำกับภาษี</span>
          <small style="font-weight:400; opacity:0.9;">({{ $relatedVt->invoice_no }})</small>
        </button>
      @endif

      {{-- Print Billing-Receive --}}
      @if($isIPAY)
        <button type="button" class="modal-print-btn"
                data-transaction-id="{{ $sell->id }}"
                data-document-type="final"
                style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#28a745; border:none; border-radius:4px; cursor:pointer; line-height:1.4;">
          <span><i class="fa fa-print"></i> ใบเสร็จรับเงิน</span>
          <small style="font-weight:400; opacity:0.9;">({{ $invoiceNo }})</small>
        </button>
      @elseif($isVT && $relatedIpay)
        <button type="button" class="modal-print-btn"
                data-transaction-id="{{ $relatedIpay->id }}"
                data-document-type="final"
                style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#28a745; border:none; border-radius:4px; cursor:pointer; line-height:1.4;">
          <span><i class="fa fa-print"></i> ใบเสร็จรับเงิน</span>
          <small style="font-weight:400; opacity:0.9;">({{ $relatedIpay->invoice_no }})</small>
        </button>
      @elseif($isVT && $has_received_payment)
        <button type="button" class="modal-print-btn"
                data-transaction-id="{{ $sell->id }}"
                data-document-type="final"
                style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#28a745; border:none; border-radius:4px; cursor:pointer; line-height:1.4;">
          <span><i class="fa fa-print"></i> ใบเสร็จรับเงิน</span>
          <small style="font-weight:400; opacity:0.9;">({{ $billingReceiveDisplayNo ?? $invoiceNo }})</small>
        </button>
      @elseif($isVT && !$relatedIpay)
        <button type="button" disabled
                style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#999; background:#e9ecef; border:none; border-radius:4px; cursor:not-allowed; line-height:1.4;">
          <span><i class="fa fa-print"></i> ใบเสร็จรับเงิน</span>
          <small style="font-weight:400;">(ยังไม่รับชำระ)</small>
        </button>
      @endif

      {{-- Quotation buttons --}}
      @if($isQuotation)
        <a href="{{ route('quotations.pdfprint.nodejs', ['id' => $sell->id]) }}"
           class="pdf-print-btn"
           target="_blank"
           style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#17a2b8; border:none; border-radius:4px; cursor:pointer; line-height:1.4; text-decoration:none;">
          <span><i class="fa fa-print"></i> ใบเสนอราคา</span>
        </a>
        @if(auth()->user()->can('sell.create') || auth()->user()->can('direct_sell.access'))
          <button type="button"
                  onclick="createTaxInvoice({{ $sell->id }})"
                  style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#ffc107; border:none; border-radius:4px; cursor:pointer; line-height:1.4; color:#212529;">
            <span><i class="fa fa-file-invoice"></i> สร้างใบกำกับภาษี</span>
          </button>
        @endif
      @endif

      {{-- Edit --}}
      @if(auth()->user()->can('sell.update') || auth()->user()->can('direct_sell.access'))
        <a href="{{ action([\App\Http\Controllers\SellController::class, 'edit'], $sell->id) }}"
           style="display:inline-flex; flex-direction:column; align-items:center; padding:7px 14px; font-size:12px; font-weight:600; color:#fff; background:#6c757d; border:none; border-radius:4px; cursor:pointer; line-height:1.4; text-decoration:none;">
          <span><i class="fa fa-edit"></i> @lang('messages.edit')</span>
          <small style="font-weight:400; opacity:0.9;">({{ $invoiceNo }})</small>
        </a>
      @endif

      <button type="button" data-dismiss="modal"
              style="display:inline-flex; align-items:center; padding:7px 18px; font-size:12px; font-weight:600; color:#fff; background:#343a40; border:none; border-radius:4px; cursor:pointer;">
        @lang('messages.close')
      </button>
    </div>

  </div>
</div>

<!-- Full Image Preview Modal -->
<div id="imagePreviewModal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.9); overflow:auto;">
  <span onclick="closeImageModal()" style="position:absolute; top:20px; right:35px; color:#fff; font-size:40px; font-weight:bold; cursor:pointer; z-index:10001;">&times;</span>
  <img id="imagePreviewImg" style="margin:auto; display:block; max-width:95%; max-height:95%; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); border-radius:4px; box-shadow:0 0 20px rgba(0,0,0,0.5);">
</div>

<script type="text/javascript">
  $(document).ready(function(){
    __currency_convert_recursively($('div.modal-lg'));
  });

  function printCurrentModal() {
    // On /pos page: show receipt preview modal instead of direct print
    var pathname = window.location.pathname || '';
    var isPosPage = pathname.split('/').filter(function(s){ return s !== ''; }).indexOf('pos') !== -1;

    if (isPosPage) {
      var printUrl = '{{ route('sell.printInvoice', $sell->id) }}';
      var $btn = $('.view_modal').find('button[onclick="printCurrentModal()"]');
      var origHtml = $btn.html();
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

      $.ajax({
        method: 'GET',
        url: printUrl,
        dataType: 'json',
        success: function(result) {
          $btn.prop('disabled', false).html(origHtml);
          if (result.success == 1 && result.receipt && result.receipt.html_content) {
            // Close view_modal first
            $('.view_modal').modal('hide');
            // Use the existing pos_list_print_preview_modal if available
            if (typeof window.showPosReceiptPreviewModal === 'function') {
              window.showPosReceiptPreviewModal(result.receipt);
            } else if ($('#pos_list_print_preview_modal').length) {
              var html = result.receipt.html_content;
              var bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
              var styles = html.match(/<style[\s\S]*?<\/style>/gi);
              var normalized = (styles ? styles.join('') : '') + (bodyMatch ? bodyMatch[1] : html);
              $('#pos_list_print_preview_body').html(normalized);
              $('#pos_list_print_preview_modal').modal('show');
            } else {
              // Fallback: direct print
              document.getElementById('receipt_section') && (document.getElementById('receipt_section').innerHTML = result.receipt.html_content);
              window.print();
            }
          } else {
            alert(result.msg || 'ไม่สามารถโหลดใบเสร็จได้');
          }
        },
        error: function() {
          $btn.prop('disabled', false).html(origHtml);
          alert('ไม่สามารถโหลดใบเสร็จได้');
        }
      });
      return;
    }

    // Other pages (/sells/summary-sales, /sells/quotations): direct print the modal
    var content = document.querySelector('.view_modal .modal-content');
    if (!content) { alert('ไม่พบเนื้อหา'); return; }

    var printDiv = document.createElement('div');
    printDiv.id = 'modal-print-container';

    var styleEl = document.querySelector('.view_modal style');
    if (styleEl) {
      printDiv.appendChild(styleEl.cloneNode(true));
    }
    printDiv.appendChild(content.cloneNode(true));
    document.body.appendChild(printDiv);

    document.body.classList.add('modal-printing');
    setTimeout(function() {
      window.print();
      document.body.classList.remove('modal-printing');
      document.body.removeChild(printDiv);
    }, 150);
  }

  function openImageModal(src) {
    document.getElementById('imagePreviewImg').src = src;
    document.getElementById('imagePreviewModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
  }
  function closeImageModal() {
    document.getElementById('imagePreviewModal').style.display = 'none';
    document.body.style.overflow = 'auto';
  }
  document.getElementById('imagePreviewModal').addEventListener('click', function(e) {
    if (e.target === this) closeImageModal();
  });
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImageModal();
  });
</script>
