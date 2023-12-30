{{-- @extends('layouts/app')

@push('head.end')
    @vite(['resources/js/vue.js'])
    @vite(['resources/scss/tailwind.css'])
    @inertiaHead
@endpush

@php
$hideHorizontalNav = true;
@endphp


@section('content')
    @inertia
@endsection --}}



<!doctype html>
@if (request()->is('contact-center*'))
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout-mode="detached" data-topbar-color="dark"
        data-menu-color="light" data-sidenav-user="true" data-sidenav-size="0px">
@endif

@if (!request()->is('contact-center*'))
    <html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
@endif

<head>
    @include('layouts.shared/title-meta', ['title' => $title ?? null])
    @yield('css')
    @if (request()->is('contact-center*'))
        @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
        @vite(['resources/js/app.js', 'resources/js/hyper-head.js', 'resources/js/hyper-config.js'])
    @endif

    @if (!request()->is('contact-center*'))
        @vite(['resources/scss/tailwind.css'])
    @endif

    @vite(['resources/js/vue.js'])


    @stack('head.end')
    @inertiaHead
</head>


@if (request()->is('contact-center*'))
    <div class="wrapper">

        @auth

            <div class="content-page">
                <div class="content">


                    @include('layouts.shared/horizontal-nav')

                    {{-- @dd(Session::all()); --}}

                    @yield('content')
                    @inertia

                </div>

                @include('layouts.shared/footer')

            </div>
        @else
            @yield('content')
        @endauth



    </div>

    @auth
        @include('layouts.shared/right-sidebar')
        @yield('modal')

    @endauth

    @include('layouts.shared/footer-scripts')
    @vite(['resources/js/hyper-main.js'])
    </body>
@endif

@if (!request()->is('contact-center*'))

    <body class="m-0 font-nunito text-gray-600 bg-gray-100">
        <div class="max-w-95 mx-auto">

            @auth

                <div class=" px-3">
                    <div class="content">


                        @yield('content')
                        @inertia

                    </div>

                </div>
            @else
                @yield('content')

            @endauth

        </div>

    </body>
@endif

</html>
