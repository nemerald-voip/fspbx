<meta charset="utf-8" />
<title>{{ $page_title }} | {{ config('app.name', 'Laravel') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="{{ config('app.name', 'Laravel') }} Phone System Portal" name="description" />
<meta content="{{ config('app.name', 'Laravel') }}" name="{{ config('app.name', 'Laravel') }}" />

<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">


<!--  If your homepage contains a login form, or a modal with login, then when the session ends (by default, after 2 hours) 
    then the csrf token is no longer valid and the user sees a page expired warning after they have filled out their login details.
We can work around this with a simple addition to the <head> of the main layout template. -->
<meta http-equiv="refresh" content="{{ config('session.lifetime') * 60 }}">

<!-- App favicon -->
<link rel="shortcut icon" href="{{asset('/assets/images/favicon.png')}}">

@yield('css')

<!-- App css -->
<link href="{{asset('assets/css/icons.min.css')}}" rel="stylesheet" type="text/css" />

<link href="{{asset('assets/libs/admin-resources/admin-resources.min.css')}}" rel="stylesheet" type="text/css">

<link href="{{asset('assets/css/app-modern.min.css')}}" rel="stylesheet" type="text/css" id="light-style" />
<link href="{{asset('assets/css/app-modern-dark.min.css')}}" rel="stylesheet" type="text/css" id="dark-style" />

<!-- Scripts -->
{{-- <script src="{{ asset('js/app.js') }}" defer></script> --}}

<!-- Fonts -->
<link rel="dns-prefetch" href="//fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css?family=Nunito" rel="stylesheet">

<!-- Styles -->
{{-- <link href="{{ asset('css/app.css') }}" rel="stylesheet"> --}}

{{-- This style is added to fix a bug with modals shifting the page --}}
<style>
.modal-open {
    padding-right: 0px!important;
    padding-left: 0px!important;
}
</style>