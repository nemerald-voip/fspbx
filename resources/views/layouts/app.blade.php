<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('layouts.shared/head')

</head>

<body
    @auth
class="loading" data-layout="topnav"
data-layout-config='{"layoutBoxed":false,"darkMode":false,"showRightSidebarOnStart": false}'
@else
data-layout-config='{"darkMode":false}' @endauth>

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

                    @yield('content')

                </div>
                <!-- content -->

                @include('layouts.shared/footer')

            </div>
        @else
            @yield('content')
        @endauth
        <!-- ============================================================== -->
        <!-- End Page content -->
        <!-- ============================================================== -->


    </div>
    <!-- END wrapper -->

    @auth
        @include('layouts.shared/right-sidebar')
    @endauth

    @include('layouts.shared/footer-script')
</body>

</html>
