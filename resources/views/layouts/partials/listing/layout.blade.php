@extends('layouts.horizontal', ["page_title" => $pageTitle])

@section('content')
    <div class="container-fluid">
        <div class="row">
            @include('layouts.partials.subheader', ['title' => $pageTitle])
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row mb-2">
                            <div class="col-xl-8">
                                @yield('searchbar')
                            </div>
                            <div class="col-xl-4">
                                <div class="text-xl-end mt-xl-0 mt-2">
                                    @yield('actionbar')
                                </div>
                            </div>
                        </div>
                        <div class="row mb-2">
                            @yield('pagination')
                        </div>
                        <div class="table-responsive">
                            <table class="table table-centered mb-0">
                                <thead class="table-light">@yield('table-head')</thead>
                                <tbody>@yield('table-body')</tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
