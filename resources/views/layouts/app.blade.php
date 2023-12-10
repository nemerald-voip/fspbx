<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-layout-mode="detached"  data-topbar-color="dark" data-menu-color="light" data-sidenav-user="true" data-sidenav-size="0px">

<head>
    {{-- <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('layouts.shared/head') --}}
    @include('layouts.shared/title-meta', ['title' => $title ?? null])
    @yield('css')
    @include('layouts.shared/head-css', ['mode' => $mode ?? '', 'demo' => $demo ?? ''])
    @vite(['resources/js/hyper-head.js', 'resources/js/hyper-config.js'])
</head>

<body
    {{-- @auth
class="loading" data-layout="topnav"
data-layout-config='{"layoutBoxed":false,"darkMode":false,"showRightSidebarOnStart": false}'
@else
data-layout-config='{"darkMode":false}' @endauth --}}
>

    @if (!empty($page))
        @inertia
    @endif


    <!-- Begin page -->
    <div id="app" class="wrapper">

        @auth

            <!-- ============================================================== -->
            <!-- Start Page Content here -->
            <!-- ============================================================== -->
            <div class="content-page">
                <div class="content">

                    @include('layouts.shared/horizontal-nav')

                    {{-- <div class="container-fluid"> --}}
                        @yield('content')
                    {{-- </div> --}}

                </div>
                <!-- content -->

                @include('layouts.shared/footer')

            </div>
        @else
            {{-- <div class="container-fluid"> --}}
                @yield('content')
            {{-- </div> --}}
        @endauth
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->


    </div>
    <!-- END wrapper -->

    @auth
        @include('layouts.shared/right-sidebar')
        @yield('modal')

    @endauth

    @include('layouts.shared/footer-scripts')
    @vite(['resources/js/hyper-main.js'])
</body>

</html>
