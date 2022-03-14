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
            {{-- <div>{{ Session::get('domains') }} </div> --}}

            {{-- <select class="form-control select2" id="domainSelector" data-toggle="select2">
                <option>Select</option>
                @foreach(session()->get('domains') as $domain)
                    <option value="{{ $domain->domain_uuid }}"><strong>{{ $domain->domain_description }} ({{ $domain->domain_name }})</strong></option>
                    {{-- <option value="{{ $domain->domain_uuid }}">{{ $domain->domain_name }}</></option> --}}
                    {{-- <optgroup label="{{ $domain->domain_description }}">
                        <option value="{{ $domain->domain_uuid }}">{{ $domain->domain_name }}</option>
                    </optgroup>
                @endforeach

            </select> --}}

            <div class="input-group flex-nowrap mb-3">
                <input type="text" class="form-control" placeholder="Search..." aria-label="domainSearchInput" id="domainSearchInput" aria-describedby="basic-addon1">
                <span class="input-group-text" id="basic-addon1"> <i class="uil uil-search"></i></span>
            </div>

            <div class="list-group" id ="domainSearchList">
                @foreach(session()->get('domains') as $domain)
                    <div class="listgroup">
                        <a href="#" class="list-group-item list-group-item-action
                            @if (Session::get("domain_uuid") === $domain->domain_uuid ) active @endif ">
                            <div class="d-flex w-100 justify-content-between text-break">
                                <h5 class="mb-1">{{ $domain->domain_description }}</h5>
                            </div>
                            <small class="text-muted">{{ $domain->domain_name }}</small>
                        </a>
                    </div>
                @endforeach
            </div>
            
        </div> <!-- end padding-->

    </div>
</div>

<div class="rightbar-overlay"></div>
<!-- /End-bar -->