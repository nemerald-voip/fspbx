@extends('layouts.horizontal', ["page_title"=> "Apps"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">App Provisioning Status</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-xl-8">
                            <form class="row gy-2 gx-2 align-items-center justify-content-xl-start justify-content-between">
                                <div class="col-auto">
                                    <label for="inputPassword2" class="visually-hidden">Search</label>
                                    <input type="search" class="form-control" id="inputPassword2" placeholder="Search...">
                                </div>
                                <div class="col-auto">
                                    <div class="d-flex align-items-center">
                                        <label for="status-select" class="me-2">Status</label>
                                        <select class="form-select" id="status-select">
                                            <option selected>Choose...</option>
                                            <option value="1">Paid</option>
                                            <option value="2">Awaiting Authorization</option>
                                            <option value="3">Payment failed</option>
                                            <option value="4">Cash On Delivery</option>
                                            <option value="5">Fulfilled</option>
                                            <option value="6">Unfulfilled</option>
                                        </select>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="col-xl-4">
                            <div class="text-xl-end mt-xl-0 mt-2">
                                <button type="button" class="btn btn-success mb-2 me-2 disabled" id="appProvisionButton"
                                    data-bs-toggle="modal" data-bs-target="#app-provision-modal">Provision</button>
                                {{-- <button type="button" class="btn btn-light mb-2">Export</button> --}}
                            </div>
                        </div><!-- end col-->
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 20px;">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="customCheck1">
                                            <label class="form-check-label" for="customCheck1">&nbsp;</label>
                                        </div>
                                    </th>
                                    <th>Company</th>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th>BLFs</th>
                                    <th>SMS</th>
                                    <th style="width: 125px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $i = 1;
                                @endphp
                                @foreach ($companies as $company)
                                    <tr>
                                        <td>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input appCompanyCheckbox" id="@php print 'companyCheck'.$i; @endphp" 
                                                    value="{{ $company['domain_uuid'] }}">
                                                <label class="form-check-label" for="@php print 'companyCheck'.$i; @endphp">&nbsp;</label>
                                            </div>
                                        </td>
                                        <td><a href="" class="text-body fw-bold">{{ $company['name'] }}</a> </td>
                                        <td>
                                            {{ $company['domain'] }} 
                                        </td>
                                        <td>
                                            @if ($company['status']) 
                                                <h5><span class="badge bg-success"></i>Provisioned</span></h5>
                                            @else 
                                                <h5><span class="badge bg-warning">Inactive</span></h5>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">Coming Soon...</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">Coming Soon...</small>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-eye"></i></a>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-square-edit-outline"></i></a>
                                            <a href="javascript:void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                        </td>
                                    </tr>
                                    @php 
                                        $i++;
                                    @endphp
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row -->

</div> <!-- container -->

    <!-- Provision modal-->
    <div id="app-provision-modal" class="modal fade" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
    
                <div class="modal-body">

                    <ul class="nav nav-tabs nav-justified nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#organization-b2" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                <i class="mdi mdi-home-variant d-md-none d-block"></i>
                                <span class="d-none d-md-block">Organization</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#connection-b2" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                <span class="d-none d-md-block">Connection</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#result-b2" data-bs-toggle="tab" aria-expanded="false" class="nav-link">
                                <i class="mdi mdi-settings-outline d-md-none d-block"></i>
                                <span class="d-none d-md-block">Finish</span>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane show active" id="organization-b2">
                            <form class="ps-3 pe-3" action="" id="createOrganizationForm">
    
                                <div class="mb-3">
                                    <label for="organization_name" class="form-label">Organization Name</label>
                                    <input class="form-control" type="text" id="organization_name" name="organization_name" required="" placeholder="">
                                </div>
            
                                <div class="row">
                                    <div class="col-7">
                                        <div class="mb-3">        
                                            <label for="organization_domain" class="form-label">Unique Organization Domain</label>
                                            <input class="form-control" type="text" id="organization_domain" name="organization_domain" required="" placeholder="">
                                        </div>
                                    </div>
        
                                    <div class="col-5">
                                        <div class="mb-3">
                                            <label for="organization_region" class="form-label">Region</label>
                                            <select class="form-select mb-3" id="organization_region" name="organization_region">
                                                <option value="1">US East</option>
                                                <option value="2" selected>US West</option>
                                                <option value="3">Europe (Frankfurt)</option>
                                                <option value="4">Asia Pacific (Singapore)</option>
                                                <option value="5">Europe (London)</option>
                                            </select> 
        
                                        </div>
                                    </div>
                                    <input type="hidden" id="organization_uuid" name="organization_uuid">
                                </div>
            
                                <div class="alert alert-danger" id="appOrganizationError" style="display:none">
                                    <ul></ul>
                                </div>
            
                                <div class="mb-3 text-center">
                                    <button class="btn btn-primary" id="appProvisionNextButton" type="submit">Next</button>
                                </div>
            
                            </form>
                        </div>


                        <div class="tab-pane" id="connection-b2">
                            <form class="ps-3 pe-3" action="" id="createConnectionForm">
            
                                <div class="row">
                                    <div class="col-7">
                                        <div class="mb-3">        
                                            <label for="connection_name" class="form-label">Connection Name</label>
                                            <input class="form-control" type="text" id="connection_name" name="connection_name" required="" placeholder="">
                                            <span class="help-block"><small>Enter a name for this connection</small></span>
                                        </div>
                                    </div>
        
                                    <div class="col-5">
                                        <div class="mb-3">
                                            <label for="connection_protocol" class="form-label">Protocol</label>
                                            <select class="form-select mb-3" id="connection_protocol" name="connection_protocol">
                                                <option value="sip">SIP (UDP)</option>
                                                <option value="tcp">SIP (TCP)</option>
                                                <option value="sips" selected>SIPS (TLS/SRTP)</option>
                                            </select> 
        
                                        </div>
                                    </div>
                                    <input type="hidden" id="org_id" name="org_id">
                                    <input type="hidden" id="connection_organization_uuid" name="connection_organization_uuid">
                                </div>

                                <div class="row">
                                    <div class="col-8">
                                        <div class="mb-3">        
                                            <label for="connection_domain" class="form-label">Domain Name or IP Address</label>
                                            <input class="form-control" type="text" id="connection_domain" name="connection_domain" required="" placeholder="">
                                            <span class="help-block"><small>e.g. pbx.example.com or 192.168.1.101</small></span>
                                        </div>
                                    </div>
        
                                    <div class="col-4">
                                        <div class="mb-3">
                                            <label for="connection_port" class="form-label">Port</label>
                                            <input class="form-control" type="text" id="connection_port" name="connection_port" 
                                            value="{{ $conn_params['connection_port'] }}" required="" placeholder="">
                                            <span class="help-block"><small>SIP Port</small></span>
        
                                        </div>
                                    </div>
                                </div>


                                <div class="accordion mb-3" id="accordionExample">
                                    <div class="card mb-0">
                                        <div class="card-header" id="headingOne">
                                            <h5 class="m-0">
                                                <a class="custom-accordion-title d-block pt-2 pb-2"
                                                    data-bs-toggle="collapse" href="#collapseOne"
                                                    aria-expanded="false" aria-controls="collapseOne">
                                                    Outbound Proxy
                                                </a>
                                            </h5>
                                        </div>
                                
                                        <div id="collapseOne" class="collapse"
                                            aria-labelledby="headingOne" data-bs-parent="#accordionExample">
                                            <div class="card-body">
                                                <div class="mb-3">        
                                                    <label for="connection_proxy_address" class="form-label">Address</label>
                                                    <input class="form-control" type="text" id="connection_proxy_address" name="connection_proxy_address" 
                                                        value="{{ $conn_params['outbound_proxy'] ?? ''}}" placeholder="">
                                                    <span class="help-block"><small>e.g. pbx.example.com:5070</small></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-0">
                                        <div class="card-header" id="headingTwo">
                                            <h5 class="m-0">
                                                <a class="custom-accordion-title collapsed d-block pt-2 pb-2"
                                                    data-bs-toggle="collapse" href="#collapseTwo"
                                                    aria-expanded="false" aria-controls="collapseTwo">
                                                    Miscellaneous
                                                </a>
                                            </h5>
                                        </div>
                                        <div id="collapseTwo" class="collapse" aria-labelledby="headingTwo"
                                            data-bs-parent="#accordionExample">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-4">
                                                        <div class="mb-3">        
                                                            <label for="connection_ttl" class="form-label">Registration TTL</label>
                                                            <input class="form-control" type="text" id="connection_ttl" name="connection_ttl" value="300" required="" placeholder="">
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="row">                        
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <div class="form-check mb-2">
                                                                <input type="checkbox" class="form-check-input" id="connection_private_list" name="connection_private_list">
                                                                <label class="form-check-label" for="connection_private_list">Private user list</label>
                                                            </div>
                        
                                                        </div>
                                                    </div>
                                                </div>


                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-0">
                                        <div class="card-header" id="headingThree">
                                            <h5 class="m-0">
                                                <a class="custom-accordion-title collapsed d-block pt-2 pb-2"
                                                    data-bs-toggle="collapse" href="#collapseThree"
                                                    aria-expanded="false" aria-controls="collapseThree">
                                                    Security
                                                </a>
                                            </h5>
                                        </div>
                                        <div id="collapseThree" class="collapse"
                                            aria-labelledby="headingThree" data-bs-parent="#accordionExample">
                                            <div class="card-body">
                                                ...
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card mb-0">
                                        <div class="card-header" id="headingFour">
                                            <h5 class="m-0">
                                                <a class="custom-accordion-title collapsed d-block pt-2 pb-2"
                                                    data-bs-toggle="collapse" href="#collapseFour"
                                                    aria-expanded="false" aria-controls="collapseFour">
                                                    Audio Codecs
                                                </a>
                                            </h5>
                                        </div>
                                        <div id="collapseFour" class="collapse"
                                            aria-labelledby="headingFour" data-bs-parent="#accordionExample">
                                            <div class="card-body">
                                                <div class="mb-3">
                                                    <div class="form-check mb-2">
                                                        <input type="checkbox" class="form-check-input" id="connection_codec_u711" name="connection_codec_u711" checked>
                                                        <label class="form-check-label" for="connection_codec_u711">G.711 uLaw</label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="form-check mb-2">
                                                        <input type="checkbox" class="form-check-input" id="connection_codec_a711" name="connection_codec_a711" checked>
                                                        <label class="form-check-label" for="connection_codec_a711">G.711 aLaw</label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="form-check mb-2">
                                                        <input type="checkbox" class="form-check-input" id="connection_codec_729" name="connection_codec_729">
                                                        <label class="form-check-label" for="connection_codec_729">G.729</label>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <div class="form-check mb-2">
                                                        <input type="checkbox" class="form-check-input" id="connection_codec_opus" name="connection_codec_opus">
                                                        <label class="form-check-label" for="connection_codec_opus">OPUS</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>


            
                                <div class="alert alert-danger" id="appConnectionError" style="display:none">
                                    <ul></ul>
                                </div>
            
                                <div class="mb-3 text-center">
                                    <button class="btn btn-primary" id="appConnectionNextButton" type="submit">Next</button>
                                </div>
            
                            </form>
                        </div>
                        <div class="tab-pane" id="result-b2">
                            <div class="row">
                                <div class="col-12">
                                    <div class="text-center">
                                        <h2 class="mt-0"><i class="mdi mdi-check-all"></i></h2>
                                        <h3 class="mt-0">Success !</h3>

                                        <p class="w-75 mb-2 mx-auto">New organization is provisiomed and ready to be used.</p>

                                    </div>
                                </div> <!-- end col -->
                            </div>
                        </div>
                    </div>
                    
    
                </div>
            </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
    </div><!-- /.modal -->

@endsection