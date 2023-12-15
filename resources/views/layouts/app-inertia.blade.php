@extends('layouts/app')

@push('head.end')
    @vite(['resources/js/vue.js'])
    @inertiaHead
@endpush

@section('content')
    @inertia
@endsection