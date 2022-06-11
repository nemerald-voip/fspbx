<!DOCTYPE html>
<html lang="en">
    <head>
        @include('layouts.shared/head')
        

    </head>

    <body class="loading" data-layout="topnav" data-layout-config='{"layoutBoxed":false,"darkMode":false,"showRightSidebarOnStart": false}' >
        <!-- Begin page -->
        <div class="wrapper">

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

            <!-- ============================================================== -->
            <!-- End Page content -->
            <!-- ============================================================== -->


        </div>
        <!-- END wrapper -->

        @include('layouts.shared/right-sidebar')

        @include('layouts.shared/footer-script')
    </body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.4/jquery.validate.min.js" integrity="sha512-FOhq9HThdn7ltbK8abmGn60A/EMtEzIzv1rvuh+DqzJtSGq8BRdEN0U+j0iKEIffiw/yEtVuladk6rsG4X6Uqg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.4/additional-methods.min.js" integrity="sha512-XJiEiB5jruAcBaVcXyaXtApKjtNie4aCBZ5nnFDIEFrhGIAvitoqQD6xd9ayp5mLODaCeaXfqQMeVs1ZfhKjRQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</html>