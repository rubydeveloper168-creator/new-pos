@extends('layouts.app')
@section('title', 'ตรวจสอบประกัน')

@section('content')
    <style>
        .warranty-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .warranty-legend .label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-weight: 600;
        }

        .warranty-legend .dot {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            display: inline-block;
        }

        .warranty-note {
            color: #6b7280;
            font-size: 12px;
            margin-top: 6px;
        }

        .warranty-product-image-col {
            width: 72px;
        }

        .warranty-summary-card {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px 16px;
            background: #fff;
            margin-bottom: 14px;
        }

        .warranty-summary-card h4 {
            margin: 0 0 4px 0;
            font-weight: 700;
        }

        .warranty-summary-card p {
            margin: 0;
            color: #6b7280;
        }
    </style>

    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">ตรวจสอบประกัน</h1>
        <p class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">
            ติดตามสถานะประกัน อายุสินค้าที่ขายไปแล้ว และการแจ้งเตือนเข้ารับบริการรอบ 3 เดือน / 6 เดือน
        </p>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => 'ตรวจสอบประกัน'])
            <div class="warranty-summary-card">
                <h4>เงื่อนไขการรับประกัน</h4>
                <p>สินค้าสามารถเว้นสถานะประกันว่างได้ ระบบจะนับประกัน 365 วันจากวันที่ออกบิล และสามารถเปิดการแจ้งเตือนรอบเข้ารับบริการ 3 เดือน / 6 เดือนแยกตามสินค้าได้</p>
                <div style="margin-top: 12px;">
                    <a href="{{ route('warranty-check.calendar') }}" class="tw-dw-btn tw-dw-btn-primary tw-text-white">
                        <i class="fa fa-calendar"></i> เปิดปฏิทินนัดเข้ารับบริการ
                    </a>
                </div>
            </div>

            <div class="warranty-legend">
                <span class="label" style="background:#dcfce7;color:#166534;"><span class="dot" style="background:#16a34a;"></span>มีประกัน</span>
                <span class="label" style="background:#e5e7eb;color:#374151;"><span class="dot" style="background:#6b7280;"></span>ยังไม่ตั้งค่า</span>
                <span class="label" style="background:#fef3c7;color:#92400e;"><span class="dot" style="background:#f59e0b;"></span>ถึงรอบบริการ / แจ้งแล้ว</span>
                <span class="label" style="background:#dcfce7;color:#166534;"><span class="dot" style="background:#16a34a;"></span>เข้ารับบริการแล้ว</span>
                <span class="label" style="background:#fee2e2;color:#991b1b;"><span class="dot" style="background:#dc2626;"></span>หมดประกัน</span>
            </div>

            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#warranty_products_tab" data-toggle="tab" aria-expanded="true">
                            <i class="fa fa-cubes" aria-hidden="true"></i> ตั้งค่าสินค้า
                        </a>
                    </li>
                    <li>
                        <a href="#warranty_services_tab" data-toggle="tab" aria-expanded="false">
                            <i class="fa fa-wrench" aria-hidden="true"></i> สถานะสินค้าที่ขายไปแล้ว
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane active" id="warranty_products_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="warranty_product_table">
                                <thead>
                                    <tr>
                                        <th class="warranty-product-image-col">รูปภาพ</th>
                                        <th>สินค้า</th>
                                        <th>SKU</th>
                                        <th>สถานะประกัน</th>
                                        <th>รอบเข้ารับบริการ</th>
                                        <th>อัปเดตล่าสุด</th>
                                        <th>@lang('messages.action')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="warranty-note">
                            กดแก้ไขที่รายการสินค้าเพื่อกำหนดสถานะประกันแบบเว้นว่างได้ และเปิดการแจ้งเตือนรอบเข้ารับบริการ 3 เดือน หรือ 6 เดือน
                        </div>
                    </div>

                    <div class="tab-pane" id="warranty_services_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped" id="warranty_service_table">
                                <thead>
                                    <tr>
                                        <th>เลขที่บิล</th>
                                        <th>ลูกค้า</th>
                                        <th>สินค้า</th>
                                        <th>วันที่บิล</th>
                                        <th>วันรับประกัน</th>
                                        <th>สถานะประกัน</th>
                                        <th>สถานะรอบ 3 เดือน</th>
                                        <th>สถานะรอบ 6 เดือน</th>
                                        <th>@lang('messages.action')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="warranty-note">
                            เมื่ออายุบิลเกิน 365 วัน สินค้าจะถือว่าหมดประกัน และระบบจะแสดงรายการที่ถึงรอบเข้ารับบริการ 3 เดือน / 6 เดือนเพื่อให้ติดตามลูกค้าได้ง่ายขึ้น
                        </div>
                    </div>
                </div>
            </div>
        @endcomponent
    </section>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            var productTable = $('#warranty_product_table').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                ajax: "{{ route('warranty-check.products') }}",
                columnDefs: [{
                    targets: [0, 3, 4, 6],
                    orderable: false,
                    searchable: false
                }, {
                    targets: [5],
                    searchable: false
                }],
                columns: [{
                        data: 'product_image',
                        name: 'product_image'
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'sku',
                        name: 'sku'
                    },
                    {
                        data: 'warranty_status',
                        name: 'warranty_check_status'
                    },
                    {
                        data: 'cycle_configuration',
                        name: 'cycle_configuration'
                    },
                    {
                        data: 'updated_at',
                        name: 'updated_at'
                    },
                    {
                        data: 'action',
                        name: 'action'
                    }
                ]
            });

            var serviceTable = $('#warranty_service_table').DataTable({
                processing: true,
                serverSide: true,
                scrollX: true,
                ajax: "{{ route('warranty-check.sold-products') }}",
                columnDefs: [{
                    targets: [4, 5, 6, 7, 8],
                    orderable: false,
                    searchable: false
                }, {
                    targets: [3],
                    searchable: false
                }],
                columns: [{
                        data: 'invoice_no',
                        name: 'invoice_no'
                    },
                    {
                        data: 'customer_name',
                        name: 'customer_name'
                    },
                    {
                        data: 'product_name',
                        name: 'product_name'
                    },
                    {
                        data: 'transaction_date',
                        name: 'transaction_date'
                    },
                    {
                        data: 'warranty_day_count',
                        name: 'warranty_day_count'
                    },
                    {
                        data: 'warranty_status',
                        name: 'warranty_status'
                    },
                    {
                        data: 'cycle_3_status',
                        name: 'cycle_3_status'
                    },
                    {
                        data: 'cycle_6_status',
                        name: 'cycle_6_status'
                    },
                    {
                        data: 'action',
                        name: 'action'
                    }
                ]
            });

            $(document).on('submit', '#warranty_product_form, #warranty_service_form', function(e) {
                e.preventDefault();

                var form = $(this);
                form.find('button[type="submit"]').prop('disabled', true);

                $.ajax({
                    method: form.attr('method'),
                    url: form.attr('action'),
                    dataType: 'json',
                    data: form.serialize(),
                    success: function(result) {
                        if (result.success) {
                            $('div.view_modal').modal('hide');
                            toastr.success(result.msg);
                            productTable.ajax.reload(null, false);
                            serviceTable.ajax.reload(null, false);
                        } else {
                            toastr.error(result.msg || 'ไม่สามารถบันทึกข้อมูลได้');
                        }
                    },
                    error: function() {
                        toastr.error('ไม่สามารถบันทึกข้อมูลได้');
                    },
                    complete: function() {
                        form.find('button[type="submit"]').prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
