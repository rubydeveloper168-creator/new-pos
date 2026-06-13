<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"
    dir="{{ in_array(session()->get('user.language', config('app.locale')), config('constants.langs_rtl')) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ config('app.name') }}</title>

    @include('layouts.partials.css')
    @include('layouts.partials.extracss')

    @yield('css')
</head>
<body class="hold-transition skin-blue-light">
    <div id="scrollable-container">
        @yield('content')
    </div>

    @include('layouts.partials.javascripts')
    @yield('javascript')
</body>
</html>
