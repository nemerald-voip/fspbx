<!-- Right Sidebar -->
<div class="end-bar" id="endBar" >

    <div class="rightbar-title">
        <a href="javascript:void(0);" class="end-bar-toggle float-end">
            <i class="dripicons-cross noti-icon"></i>
        </a>
        <h5 class="m-0">Select company</h5>
    </div>

    <div class="rightbar-content h-100" data-simplebar>

        <div class="p-3">

            <div class="input-group flex-nowrap mb-3">
                <input type="text" class="form-control" placeholder="Search..." aria-label="domainSearchInput" id="domainSearchInput" aria-describedby="basic-addon1">
                <span class="input-group-text" id="basic-addon1"> <i class="uil uil-search"></i></span>
            </div>

            @if (Session::get("domains"))
            <div class="list-group" id ="domainSearchList">
                @foreach(session()->get('domains') as $domain)
                    <div class="listgroup">
                        <a href="#" class="list-group-item list-group-item-action
                            @if (Session::get("domain_uuid") === $domain->domain_uuid ) active @endif "
                            onclick="event.preventDefault();
                                document.getElementById('form_input_domain_uuid').value = '{{ $domain->domain_uuid }}';
                                document.getElementById('domain-search-form').submit();">

                            <div class="d-flex w-100 justify-content-between text-break">
                                <h5 class="mb-1">{{ $domain->domain_description }}</h5>
                            </div>
                            <small class="text-muted">{{ $domain->domain_name }}</small>
                        </a>
                    </div>
                @endforeach
            </div>
            @endif
            
            <form id="domain-search-form" action="{{ route('switchDomain') }}" method="POST" style="display: none;">
                @csrf
                <input type="hidden" name="domain_uuid" id="form_input_domain_uuid" value="">
            </form>
            
        </div> <!-- end padding-->

    </div>
</div>

<div class="rightbar-overlay"></div>
<!-- /End-bar -->