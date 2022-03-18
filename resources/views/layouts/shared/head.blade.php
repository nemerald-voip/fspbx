<meta charset="utf-8" />
<title>{{ $page_title }} | Nemerald</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta content="Nemerald Phone System Portal" name="description" />
<meta content="Nemerald" name="author" />

<!-- CSRF Token -->
<meta name="csrf-token" content="{{ csrf_token() }}">

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