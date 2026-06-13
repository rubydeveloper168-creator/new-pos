<link href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}" rel="stylesheet">

<link rel="stylesheet" href="{{ asset('css/vendor.css?v='.$asset_v) }}">

@if( in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) )
	<link rel="stylesheet" href="{{ asset('css/rtl.css?v='.$asset_v) }}">
@endif

@yield('css')

<!-- app css -->
<link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">

@if(isset($pos_layout) && $pos_layout)
	<style type="text/css">
		.content{
			padding-bottom: 0px !important;
		}
	</style>
@endif
<style type="text/css">
	/*
	* Pattern lock css
	* Pattern direction
	* http://ignitersworld.com/lab/patternLock.html
	*/
	.patt-wrap {
	  z-index: 10;
	}
	.patt-circ.hovered {
	  background-color: #cde2f2;
	  border: none;
	}
	.patt-circ.hovered .patt-dots {
	  display: none;
	}
	.patt-circ.dir {
	  background-image: url("{{asset('/img/pattern-directionicon-arrow.png')}}");
	  background-position: center;
	  background-repeat: no-repeat;
	}
	.patt-circ.e {
	  -webkit-transform: rotate(0);
	  transform: rotate(0);
	}
	.patt-circ.s-e {
	  -webkit-transform: rotate(45deg);
	  transform: rotate(45deg);
	}
	.patt-circ.s {
	  -webkit-transform: rotate(90deg);
	  transform: rotate(90deg);
	}
	.patt-circ.s-w {
	  -webkit-transform: rotate(135deg);
	  transform: rotate(135deg);
	}
	.patt-circ.w {
	  -webkit-transform: rotate(180deg);
	  transform: rotate(180deg);
	}
	.patt-circ.n-w {
	  -webkit-transform: rotate(225deg);
	   transform: rotate(225deg);
	}
	.patt-circ.n {
	  -webkit-transform: rotate(270deg);
	  transform: rotate(270deg);
	}
	.patt-circ.n-e {
	  -webkit-transform: rotate(315deg);
	  transform: rotate(315deg);
	}
</style>

<!-- Global Select2 Dropdown Fix -->
<style type="text/css">
/* Fix Select2 dropdown positioning */
.form-group {
    position: relative;
}

/* Fix Select2 container width */
.select2-container {
    width: 100% !important;
}

.input-group .select2-container {
    flex: 1 1 auto;
    width: auto !important;
}

/* Remove black background from Select2 */
.select2-container--default .select2-selection--single,
.select2-container--default .select2-selection--multiple {
    background-color: #fff !important;
    border: 1px solid #d2d6de !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #444 !important;
    background-color: transparent !important;
}

.select2-dropdown {
    background-color: #fff !important;
    border: 1px solid #d2d6de !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: #fff !important;
    color: #333 !important;
    border: 1px solid #ccc !important;
}

.select2-container--default .select2-results__option {
    background-color: #fff !important;
    color: #333 !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #3c8dbc !important;
    color: #fff !important;
}

.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #ddd !important;
    color: #333 !important;
}
</style>

@if(!empty($__system_settings['additional_css']))
    {!! $__system_settings['additional_css'] !!}
@endif

