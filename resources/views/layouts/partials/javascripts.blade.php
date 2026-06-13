<script type="text/javascript">
    base_path = "{{ url('/') }}";
    //used for push notification
    APP = {};
    APP.PUSHER_APP_KEY = '{{ config('broadcasting.connections.pusher.key') }}';
    APP.PUSHER_APP_CLUSTER = '{{ config('broadcasting.connections.pusher.options.cluster') }}';
    APP.INVOICE_SCHEME_SEPARATOR = '{{ config('constants.invoice_scheme_separator') }}';
    //variable from app service provider
    APP.PUSHER_ENABLED = '{{ $__is_pusher_enabled }}';
    @auth
    @php
        $user = Auth::user();
    @endphp
    APP.USER_ID = "{{ $user->id }}";
    @else
        APP.USER_ID = '';
    @endauth
</script>

<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js?v=$asset_v"></script>
<script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js?v=$asset_v"></script>
<![endif]-->

<script src="{{ asset('js/vendor.js?v=' . $asset_v) }}"></script>

@if (file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script src="{{ asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v) }}">
    </script>
@else
    <script src="{{ asset('js/lang/en.js?v=' . $asset_v) }}"></script>
@endif
@php
    $business_date_format = session('business.date_format', config('constants.default_date_format'));
    $datepicker_date_format = str_replace('d', 'dd', $business_date_format);
    $datepicker_date_format = str_replace('m', 'mm', $datepicker_date_format);
    $datepicker_date_format = str_replace('Y', 'yyyy', $datepicker_date_format);

    $moment_date_format = str_replace('d', 'DD', $business_date_format);
    $moment_date_format = str_replace('m', 'MM', $moment_date_format);
    $moment_date_format = str_replace('Y', 'YYYY', $moment_date_format);

    $business_time_format = session('business.time_format');
    $moment_time_format = 'HH:mm';
    if ($business_time_format == 12) {
        $moment_time_format = 'hh:mm A';
    }

    $common_settings = !empty(session('business.common_settings')) ? session('business.common_settings') : [];

    $default_datatable_page_entries = !empty($common_settings['default_datatable_page_entries'])
        ? $common_settings['default_datatable_page_entries']
        : 25;
@endphp

<script>
    Dropzone.autoDiscover = false;
    moment.tz.setDefault('{{ Session::get('business.time_zone') }}');
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        @if (config('app.debug') == false)
            $.fn.dataTable.ext.errMode = 'throw';
        @endif
    });

    var financial_year = {
        start: moment('{{ Session::get('financial_year.start') }}'),
        end: moment('{{ Session::get('financial_year.end') }}'),
    }
    @if (file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
        //Default setting for select2
        $.fn.select2.defaults.set("language", "{{ session()->get('user.language', config('app.locale')) }}");
    @endif

    var datepicker_date_format = "{{ $datepicker_date_format }}";
    var moment_date_format = "{{ $moment_date_format }}";
    var moment_time_format = "{{ $moment_time_format }}";

    var app_locale = "{{ session()->get('user.language', config('app.locale')) }}";

    var non_utf8_languages = [
        @foreach (config('constants.non_utf8_languages') as $const)
            "{{ $const }}",
        @endforeach
    ];

    var __default_datatable_page_entries = "{{ $default_datatable_page_entries }}";

    var __new_notification_count_interval = "{{ config('constants.new_notification_count_interval', 60) }}000";
</script>

@if (file_exists(public_path('js/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script src="{{ asset('js/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v) }}">
    </script>
@else
    <script src="{{ asset('js/lang/en.js?v=' . $asset_v) }}"></script>
@endif

<script src="{{ asset('js/functions.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/common.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/app.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/help-tour.js?v=' . $asset_v) }}"></script>
<script src="{{ asset('js/documents_and_note.js?v=' . $asset_v) }}"></script>

<!-- TODO -->
@if (file_exists(public_path('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js')))
    <script
        src="{{ asset('AdminLTE/plugins/select2/lang/' . session()->get('user.language', config('app.locale')) . '.js?v=' . $asset_v) }}">
    </script>
@endif
@php
    $validation_lang_file = 'messages_' . session()->get('user.language', config('app.locale')) . '.js';
@endphp
@if (file_exists(public_path() . '/js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file))
    <script src="{{ asset('js/jquery-validation-1.16.0/src/localization/' . $validation_lang_file . '?v=' . $asset_v) }}">
    </script>
@endif

@if (!empty($__system_settings['additional_js']))
    {!! $__system_settings['additional_js'] !!}
@endif
@yield('javascript')

@if (Module::has('Essentials'))
    @includeIf('essentials::layouts.partials.footer_part')
@endif

<script type="text/javascript">
    $(document).ready(function() {
        var locale = "{{ session()->get('user.language', config('app.locale')) }}";
        var isRTL =
            @if (in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')))
                true;
            @else
                false;
            @endif

        $('#calendar').fullCalendar('option', {
            locale: locale,
            isRTL: isRTL
        });
        // side bar toggle  
        $(".drop_down").click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $chiled = $(this).next(".chiled");
            var svgElement = $(this).find(".svg");
            $(".chiled").not($chiled).slideUp();
            $chiled.slideToggle(function() {
                $(".svg").each(function() {
                    var $currentSvgElement = $(this);
                    if ($currentSvgElement.closest(".drop_down").next(".chiled").is(
                            ":visible")) {
                        // If the corresponding menu is visible, set the arrow pointing upwards
                        $currentSvgElement.html(
                            '<path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6 9l6 6l6 -6" />'
                        );
                    } else {
                        // Otherwise, set the arrow pointing downwards
                        $currentSvgElement.html(
                            '<path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 6l-6 6l6 6" />'
                        );
                    }
                });
            });
        });

        // Handle nested dropdown toggles
        $(document).on('click', '.nested-drop-down', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var $nestedDropdown = $(this);
            var $nestedContent = $nestedDropdown.siblings('.nested-dropdown-content');
            var $nestedSvg = $nestedDropdown.find('.nested-svg');
            
            // Toggle nested dropdown
            if ($nestedContent.is(':visible')) {
                $nestedContent.slideUp(200);
                $nestedDropdown.removeClass('tw-text-primary-700 tw-bg-gray-50');
                $nestedDropdown.addClass('hover:tw-bg-gray-200');
                // Update arrow direction with rotation
                $nestedSvg.removeClass('tw-rotate-180').html('<path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 6l-6 6l6 6" />');
            } else {
                // Close other nested dropdowns in the same parent
                $nestedDropdown.closest('.chiled').find('.nested-dropdown-content:visible').slideUp(200);
                $nestedDropdown.closest('.chiled').find('.nested-drop-down').removeClass('tw-text-primary-700 tw-bg-gray-50');
                $nestedDropdown.closest('.chiled').find('.nested-drop-down').addClass('hover:tw-bg-gray-200');
                $nestedDropdown.closest('.chiled').find('.nested-svg').removeClass('tw-rotate-180').html('<path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 6l-6 6l6 6" />');
                
                // Open this nested dropdown
                $nestedContent.slideDown(200);
                $nestedDropdown.addClass('tw-text-primary-700 tw-bg-gray-50');
                $nestedDropdown.removeClass('hover:tw-bg-gray-200');
                // Update arrow direction with rotation
                $nestedSvg.addClass('tw-rotate-180').html('<path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6 9l6 6l6 -6" />');
            }
        });

        // Keep active nested dropdowns open on page load
        $('.nested-dropdown-content').each(function() {
            var $nestedContent = $(this);
            var $nestedDropdown = $nestedContent.siblings('.nested-drop-down');
            
            if ($nestedContent.find('.tw-text-primary-700').length > 0 || $nestedContent.find('.tw-bg-primary-50').length > 0) {
                $nestedContent.show();
                $nestedDropdown.addClass('tw-text-primary-700 tw-bg-gray-50');
                $nestedDropdown.removeClass('hover:tw-bg-gray-200');
                $nestedDropdown.find('.nested-svg').addClass('tw-rotate-180').html('<path stroke="none" d="M0 0h24v24H0z" fill="none" /><path d="M6 9l6 6l6 -6" />');
            }
        });

        // Add hover effects for nested dropdowns
        $(document).on('mouseenter', '.nested-drop-down:not(.tw-bg-gray-50)', function() {
            $(this).addClass('tw-bg-gray-200');
        }).on('mouseleave', '.nested-drop-down:not(.tw-bg-gray-50)', function() {
            $(this).removeClass('tw-bg-gray-200');
        });

        // Prevent dropdown closing when clicking inside the nested content
        $(document).on('click', '.nested-dropdown-content', function(e) {
            e.stopPropagation();
        });

        $('.small-view-button').on('click', function() {
            $('.side-bar').addClass('small-view-side-active');
            $('.overlay').fadeIn('slow');
        });

        $('.overlay').on('click', function() {
            $('.overlay').fadeOut('slow');
            $('.side-bar').removeClass('small-view-side-active');
        });

        $(window).on('resize', function() {
            if ($(window).width() >= 992) {
                $('.overlay').fadeOut('slow');
                $('.side-bar').removeClass('small-view-side-active');
            }

            if($('.side-bar').hasClass('small-view-side-active')){
                $('.overlay').fadeIn('slow');
            }
        });

        $('.side-bar-collapse').click(function() {
            $('.side-bar').toggle('slow');
        });

        $('.dt-buttons.btn-group').find('a.btn').removeClass('btn-default');
        $('.dt-buttons.btn-group').find('a.btn').removeClass('btn');

    });

    // Global Select2 Dropdown Fix - Auto-initialize all select2 with proper settings
    $(document).ready(function() {
        // Function to fix a select2 dropdown
        function fixSelect2Dropdown($select) {
            if (!$select.length || $select.data('select2-fixed')) return;

            var $formGroup = $select.closest('.form-group');
            var $modal = $select.closest('.modal');
            var dropdownParent = $modal.length ? $modal : ($formGroup.length ? $formGroup : $(document.body));

            // Mark as fixed to prevent double initialization
            $select.data('select2-fixed', true);

            // Destroy existing select2 if exists
            if ($select.data('select2')) {
                try {
                    $select.select2('destroy');
                } catch(e) {}
            }

            // Initialize with proper settings
            $select.select2({
                dropdownParent: dropdownParent,
                width: '100%',
                dropdownAutoWidth: false
            });

            // Fix dropdown width on open
            $select.on('select2:open', function() {
                setTimeout(function() {
                    var $container = $select.next('.select2-container');
                    if ($container.length) {
                        var containerWidth = $container.outerWidth();
                        dropdownParent.find('.select2-dropdown').last().css({
                            'width': containerWidth + 'px',
                            'min-width': containerWidth + 'px',
                            'max-width': containerWidth + 'px'
                        });
                    }
                }, 0);
            });
        }

        // Fix all existing select2 elements (that have class select2 but NOT ajax-based ones)
        setTimeout(function() {
            $('select.select2').each(function() {
                var $select = $(this);
                // Skip if already has select2 initialized with ajax (like customer search)
                if ($select.data('select2') && $select.data('select2').options && $select.data('select2').options.options && $select.data('select2').options.options.ajax) {
                    // For ajax select2, just fix the open event
                    var $formGroup = $select.closest('.form-group');
                    $select.off('select2:open.fix').on('select2:open.fix', function() {
                        setTimeout(function() {
                            var $container = $select.next('.select2-container');
                            if ($container.length) {
                                var containerWidth = $container.outerWidth();
                                $('.select2-dropdown').last().css({
                                    'width': containerWidth + 'px',
                                    'min-width': containerWidth + 'px',
                                    'max-width': containerWidth + 'px'
                                });
                            }
                        }, 0);
                    });
                } else {
                    fixSelect2Dropdown($select);
                }
            });
        }, 500);

        // Fix select2 in modals when they open
        $(document).on('shown.bs.modal', '.modal', function() {
            var $modal = $(this);
            setTimeout(function() {
                $modal.find('select.select2').each(function() {
                    var $select = $(this);
                    $select.data('select2-fixed', false); // Reset flag for modal
                    fixSelect2Dropdown($select);
                });
            }, 100);
        });
    });
</script>


