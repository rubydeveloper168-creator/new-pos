@extends('layouts.app')
@section('title', 'ปฏิทินนัดเข้ารับบริการ')

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">ปฏิทินนัดเข้ารับบริการ</h1>
    <p class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">
        ดูวันที่ครบกำหนดเข้ารับบริการสำหรับรอบ 3 เดือน และ 6 เดือน
    </p>
</section>

<section class="content">
    <div class="row">
        <div class="col-sm-3">
            <div class="box box-solid">
                <div class="box-body">
                    <div class="form-group">
                        <a href="{{ route('warranty-check.index') }}" class="tw-dw-btn tw-dw-btn-outline tw-w-full">
                            <i class="fa fa-arrow-left"></i> กลับไปหน้าตรวจสอบประกัน
                        </a>
                    </div>

                    @foreach($calendar_event_types as $key => $value)
                        <div class="form-group">
                            <label>
                                {!! Form::checkbox('events', $key, true, ['class' => 'input-icheck warranty-calendar-check']) !!}
                                <span style="color: {{ $value['color'] }}">{{ $value['label'] }}</span>
                            </label>
                        </div>
                    @endforeach

                    <hr>

                    <p class="help-block">
                        รายการรอบ 3 เดือนที่ยังรอดำเนินการจะแสดงเป็นสีเทา รายการรอบ 6 เดือนที่ยังรอดำเนินการจะแสดงเป็นสีแดง ถ้าแจ้งลูกค้าแล้วจะเป็นสีเหลือง และถ้าเข้ารับบริการแล้วจะเป็นสีเขียว
                    </p>

                    <hr>

                    <div class="form-group">
                        {!! Form::label('warranty_calendar_year', 'ภาพรวมรายปี') !!}
                        {!! Form::select('warranty_calendar_year', $availableYears, $selectedYear, ['class' => 'form-control', 'id' => 'warranty_calendar_year']) !!}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-9">
            <div class="box box-solid">
                <div class="box-body">
                    <div id="warranty_service_calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">มุมมองรายปี: {{ $selectedYear }}</h3>
                </div>
                <div class="box-body">
                    <style>
                        .warranty-year-grid {
                            display: grid;
                            grid-template-columns: repeat(3, minmax(0, 1fr));
                            gap: 16px;
                        }

                        .warranty-year-month {
                            border: 1px solid #e5e7eb;
                            border-radius: 10px;
                            background: #fff;
                            overflow: hidden;
                        }

                        .warranty-year-month-head {
                            padding: 10px 12px;
                            background: #f8fafc;
                            border-bottom: 1px solid #e5e7eb;
                            font-weight: 700;
                        }

                        .warranty-year-month-body {
                            padding: 10px 12px;
                            min-height: 160px;
                        }

                        .warranty-year-event {
                            padding: 8px 10px;
                            border-radius: 8px;
                            margin-bottom: 8px;
                            color: #1f2937;
                            background: #f9fafb;
                            border-left: 4px solid #d1d5db;
                        }

                        .warranty-year-event.is-3m {
                            border-left-color: #6b7280;
                            background: #f3f4f6;
                        }

                        .warranty-year-event.is-6m {
                            border-left-color: #dc2626;
                            background: #fef2f2;
                        }

                        .warranty-year-event.is-notified {
                            border-left-color: #f59e0b;
                            background: #fffbeb;
                        }

                        .warranty-year-event.is-completed {
                            border-left-color: #16a34a;
                            background: #f0fdf4;
                        }

                        .warranty-year-event-date {
                            font-weight: 700;
                            font-size: 12px;
                        }

                        .warranty-year-event-title {
                            font-weight: 600;
                            margin-top: 2px;
                        }

                        .warranty-year-empty {
                            color: #9ca3af;
                            font-style: italic;
                        }

                        @media (max-width: 991px) {
                            .warranty-year-grid {
                                grid-template-columns: repeat(2, minmax(0, 1fr));
                            }
                        }

                        @media (max-width: 767px) {
                            .warranty-year-grid {
                                grid-template-columns: 1fr;
                            }
                        }
                    </style>

                    <div class="warranty-year-grid">
                        @foreach($yearOverview as $monthNumber => $monthData)
                            <div class="warranty-year-month">
                                <div class="warranty-year-month-head">{{ $monthData['label'] }}</div>
                                <div class="warranty-year-month-body">
                                    @forelse($monthData['events'] as $event)
                                        <div class="warranty-year-event {{ $event['event_class'] ?? (str_contains($event['title'], '3M') ? 'is-3m' : 'is-6m') }}">
                                            <div class="warranty-year-event-date">{{ \Carbon\Carbon::parse($event['start'])->format('d M Y') }}</div>
                                            <div class="warranty-year-event-title">{!! $event['title_html'] !!}</div>
                                            <div style="margin-top: 8px;">
                                                    <a href="#" class="btn-modal btn btn-xs btn-default"
                                                   data-href="{{ $event['edit_url'] }}"
                                                   data-container=".view_modal">
                                                    อัปเดตสถานะบริการ
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="warranty-year-empty">ไม่มีรายการที่ถึงกำหนดเข้ารับบริการ</div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('#warranty_service_calendar').fullCalendar({
            header: {
                left: 'prev,next,today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay,listWeek,listYear'
            },
            contentHeight: 'auto',
            eventLimit: 2,
            eventSources: [
                {
                    url: "{{ route('warranty-check.calendar') }}",
                    type: 'get',
                    data: function() {
                        var payload = {
                            events: getWarrantyCalendarEvents()
                        };

                        console.log('[WarrantyCalendar] fetching events', payload);

                        return payload;
                    }
                }
            ],
            eventRender: function(event, element) {
                console.log('[WarrantyCalendar] render event', {
                    id: event.id,
                    title: event.title,
                    start: event.start ? event.start.format ? event.start.format('YYYY-MM-DD') : event.start : null,
                    status: event.status,
                    backgroundColor: event.backgroundColor,
                    borderColor: event.borderColor,
                    eventClass: event.event_class,
                    description: event.description
                });

                if (event.title_html) {
                    element.find('.fc-title').html(event.title_html);
                }

                if (event.description) {
                    element.attr('title', event.description);
                }
            },
            eventClick: function(event, jsEvent, view) {
                console.log('[WarrantyCalendar] event clicked', {
                    id: event.id,
                    edit_url: event.edit_url,
                    status: event.status,
                    backgroundColor: event.backgroundColor
                });

                if (!event.edit_url) {
                    return true;
                }

                jsEvent.preventDefault();

                $.ajax({
                    url: event.edit_url,
                    success: function(response) {
                        console.log('[WarrantyCalendar] modal loaded for event', {
                            id: event.id,
                            edit_url: event.edit_url
                        });
                        $('div.view_modal').html(response).modal('show');
                    },
                    error: function() {
                        console.error('[WarrantyCalendar] failed to load modal', {
                            id: event.id,
                            edit_url: event.edit_url
                        });
                        toastr.error('ไม่สามารถเปิดฟอร์มอัปเดตสถานะบริการได้');
                    }
                });

                return false;
            }
        });
    });

    $(document).on('ifChanged', '.warranty-calendar-check', function() {
        reloadWarrantyCalendar();
    });

    $(document).on('change', '#warranty_calendar_year', function() {
        var year = $(this).val();
        window.location = "{{ route('warranty-check.calendar') }}" + '?year=' + year;
    });

    $(document).on('submit', '#warranty_service_form', function(e) {
        e.preventDefault();

        var form = $(this);
        form.find('button[type="submit"]').prop('disabled', true);

        console.log('[WarrantyCalendar] submitting service update', {
            action: form.attr('action'),
            method: form.attr('method'),
            payload: form.serializeArray()
        });

        $.ajax({
            method: form.attr('method'),
            url: form.attr('action'),
            dataType: 'json',
            data: form.serialize(),
            success: function(result) {
                console.log('[WarrantyCalendar] update response', result);

                if (result.success) {
                    $('div.view_modal').modal('hide');
                    showWarrantyToast(result.msg || 'อัปเดตสถานะบริการเรียบร้อยแล้ว', 'success');
                    reloadWarrantyCalendar();
                } else {
                    showWarrantyToast(result.msg || 'ไม่สามารถบันทึกข้อมูลได้', 'error');
                }
            },
            error: function() {
                console.error('[WarrantyCalendar] update request failed');
                showWarrantyToast('ไม่สามารถบันทึกข้อมูลได้', 'error');
            },
            complete: function() {
                console.log('[WarrantyCalendar] update request completed');
                form.find('button[type="submit"]').prop('disabled', false);
            }
        });
    });

    function getWarrantyCalendarEvents() {
        var events = [];

        $.each($("input[name='events']:checked"), function() {
            events.push($(this).val());
        });

        return events;
    }

    function reloadWarrantyCalendar() {
        console.log('[WarrantyCalendar] refetch events');
        $('#warranty_service_calendar').fullCalendar('refetchEvents');
    }

    function showWarrantyToast(message, type) {
        if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type === 'success' ? 'success' : 'error',
                title: message,
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            });
            return;
        }

        if (typeof toastr !== 'undefined') {
            if (type === 'success') {
                toastr.success(message);
            } else {
                toastr.error(message);
            }
            return;
        }

        alert(message);
    }
</script>
@endsection
