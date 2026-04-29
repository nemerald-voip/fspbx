
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8"/>
    <title>{{ isset($title) ? $title . " | " : config('app.name', 'Laravel') }} </title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="{{ config('app.name', 'Laravel') }} Phone System Portal" name="description" />
    <meta content="{{ config('app.name', 'Laravel') }}" name="{{ config('app.name', 'Laravel') }}" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="refresh" content="{{ config('session.lifetime') * 60 }}">

    <link rel="apple-touch-icon" sizes="180x180" href="/storage/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/storage/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/storage/favicon-16x16.png">
    <link rel="manifest" href="/storage/site.webmanifest">
    <link rel="mask-icon" href="/storage/safari-pinned-tab.svg" color="#f08439">
    <link rel="shortcut icon" href="/storage/favicon.ico">
    <meta name="msapplication-TileColor" content="#00aba9">
    <meta name="msapplication-config" content="/storage/browserconfig.xml">
    <meta name="theme-color" content="#ffffff">

    @yield('css')

    @vite(['resources/scss/tailwind.css'])

    @vite(['resources/js/vue.js'])


    @stack('head.end')
    @inertiaHead
</head>


@if (request()->is('login'))

    <body class="m-0 font-nunito text-gray-600 bg-gray-100">
        <div class="max-w-95 mx-auto">

            {{-- @auth --}}

                <div class=" px-3">
                    <div class="content">


                        @yield('content')
                        @inertia

                    </div>

                </div>
            {{-- @else
                @yield('content')

            @endauth --}}

        </div>

    </body>
@endif


@if ( !request()->is('login'))

    <body class="m-0 font-nunito text-gray-600 bg-gray-100">
        <div class="max-w-95 mx-auto">

            {{-- @auth --}}

                <div class=" px-3">
                    <div class="content">


                        @yield('content')
                        @inertia

                    </div>

                </div>
            {{-- @else
                @yield('content')

            @endauth --}}

        </div>

    </body>
@endif

</html>
