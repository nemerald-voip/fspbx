@extends('layouts.horizontal', ["page_title"=> "Edit Extension"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Edit Extension</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <div class="row">
                        <div class="col-sm-2 mb-2 mb-sm-0">
                            <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                <a class="nav-link active show" id="v-pills-home-tab" data-bs-toggle="pill" href="#v-pills-home" role="tab" aria-controls="v-pills-home"
                                    aria-selected="true">
                                    <i class="mdi mdi-home-variant d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Basic Information</span>
                                </a>
                                <a class="nav-link" id="v-pills-callerid-tab" data-bs-toggle="pill" href="#v-pills-callerid" role="tab" aria-controls="v-pills-callerid"
                                    aria-selected="false">
                                    <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Caller ID</span>
                                </a>
                                <a class="nav-link" id="v-pills-profile-tab" data-bs-toggle="pill" href="#v-pills-profile" role="tab" aria-controls="v-pills-profile"
                                    aria-selected="false">
                                    <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Devices</span>
                                </a>
                                <a class="nav-link" id="v-pills-settings-tab" data-bs-toggle="pill" href="#v-pills-settings" role="tab" aria-controls="v-pills-settings"
                                    aria-selected="false">
                                    <i class="mdi mdi-settings-outline d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Settings</span>
                                </a>
                            </div>
                        </div> <!-- end col-->
                    
                        <div class="col-sm-10">
                            <div class="tab-content" id="v-pills-tabContent">
                                <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab">
                                    <!-- Basic Info Content-->
                                    <div class="tab-pane show active" id="basic-information">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Basic information</h4>

                                                <p class="text-muted mb-4">Provide basic information about the user or extension</p>

                                                <form>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="billing-first-name" class="form-label">First Name <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="text" placeholder="Enter first name" id="billing-first-name" />
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="billing-last-name" class="form-label">Last Name</label>
                                                                <input class="form-control" type="text" placeholder="Enter last name" id="billing-last-name" />
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="billing-phone" class="form-label">Extension number <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="text" placeholder="xxxx" id="billing-phone" />
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="billing-email-address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="email" placeholder="Enter email" id="billing-email-address" />
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="users-select" class="form-label">Users</label>
                                                                <!-- Multiple Select -->
                                                                <select class="select2 form-control select2-multiple" data-toggle="select2" multiple="multiple" data-placeholder="Choose ..."
                                                                    id="users-select">
                                                                        <option value="AK">Alaska</option>
                                                                        <option value="HI">Hawaii</option>
                                                                        <option value="CA">California</option>
                                                                        <option value="NV">Nevada</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Display contact in the company's dial by name directory </label>
                                                                <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                                    data-bs-content="This user will appear in the company's dial by name directory">
                                                                    <i class="dripicons-information"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="checkbox" id="directory-name-switch" checked data-switch="primary"/>
                                                                <label for="directory-name-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label  class="form-label">Announce extension in the the dial by name directory </label>
                                                                <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                                    data-bs-content="Announce user's extension when calling the dial by name directory">
                                                                    <i class="dripicons-information"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="checkbox" id="directory-extension-switch" checked data-switch="primary"/>
                                                                <label for="directory-extension-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="billing-town-city" class="form-label">Town / City</label>
                                                                <input class="form-control" type="text" placeholder="Enter your city name" id="billing-town-city" />
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="billing-state" class="form-label">State</label>
                                                                <input class="form-control" type="text" placeholder="Enter your state" id="billing-state" />
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="billing-zip-postal" class="form-label">Zip / Postal Code</label>
                                                                <input class="form-control" type="text" placeholder="Enter your zip code" id="billing-zip-postal" />
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <div class="mb-3">
                                                                <label class="form-label">Country</label>
                                                                <select data-toggle="select2" title="Country">
                                                                    <option value="0">Select Country</option>
                                                                    <option value="AF">Afghanistan</option>
                                                                    <option value="AL">Albania</option>
                                                                    <option value="DZ">Algeria</option>
                                                                    <option value="AS">American Samoa</option>
                                                                    <option value="AD">Andorra</option>
                                                                    <option value="AO">Angola</option>
                                                                    <option value="AI">Anguilla</option>
                                                                    <option value="AQ">Antarctica</option>
                                                                    <option value="AR">Argentina</option>
                                                                    <option value="AM">Armenia</option>
                                                                    <option value="AW">Aruba</option>
                                                                    <option value="AU">Australia</option>
                                                                    <option value="AT">Austria</option>
                                                                    <option value="AZ">Azerbaijan</option>
                                                                    <option value="BS">Bahamas</option>
                                                                    <option value="DK">Denmark</option>
                        
                
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-12">
                                                            <div class="mb-3">
                                                                <div class="form-check">
                                                                    <input type="checkbox" class="form-check-input" id="customCheck2">
                                                                    <label class="form-check-label" for="customCheck2">Ship to different address ?</label>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3 mt-3">
                                                                <label for="example-textarea" class="form-label">Order Notes:</label>
                                                                <textarea class="form-control" id="example-textarea" rows="3" placeholder="Write some note.."></textarea>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row mt-4">
                                                        <div class="col-sm-6">
                                                            <a href="" class="btn text-muted d-none d-sm-inline-block btn-link fw-semibold">
                                                                <i class="mdi mdi-arrow-left"></i> Back to Shopping Cart </a>
                                                        </div> <!-- end col -->
                                                        <div class="col-sm-6">
                                                            <div class="text-sm-end">
                                                                <a href="" class="btn btn-danger">
                                                                    <i class="mdi mdi-truck-fast me-1"></i> Proceed to Shipping </a>
                                                            </div>
                                                        </div> <!-- end col -->
                                                    </div> <!-- end row -->
                                                </form>
                                            </div>
                                            {{-- <div class="col-lg-4">
                                                <div class="border p-3 mt-4 mt-lg-0 rounded">
                                                    <h4 class="header-title mb-3">Order Summary</h4>

                                                    <div class="table-responsive">
                                                        <table class="table table-centered mb-0">
                                                            <tbody>
                                                                <tr>
                                                                    <td>
                                                                        <img src="{{asset('assets/images/products/product-1.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                                        <p class="m-0 d-inline-block align-middle">
                                                                            <a href="" class="text-body fw-semibold">Amazing Modern Chair</a>
                                                                            <br>
                                                                            <small>5 x $148.66</small>
                                                                        </p>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        $743.30
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        <img src="{{asset('assets/images/products/product-2.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                                        <p class="m-0 d-inline-block align-middle">
                                                                            <a href="" class="text-body fw-semibold">Designer Awesome Chair</a>
                                                                            <br>
                                                                            <small>2 x $99.00</small>
                                                                        </p>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        $198.00
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <td>
                                                                        <img src="{{asset('assets/images/products/product-3.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                                        <p class="m-0 d-inline-block align-middle">
                                                                            <a href="" class="text-body fw-semibold">Biblio Plastic Armchair</a>
                                                                            <br>
                                                                            <small>1 x $129.99</small>
                                                                        </p>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        $129.99
                                                                    </td>
                                                                </tr>
                                                                <tr class="text-end">
                                                                    <td>
                                                                        <h6 class="m-0">Sub Total:</h6>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        $1071.29
                                                                    </td>
                                                                </tr>
                                                                <tr class="text-end">
                                                                    <td>
                                                                        <h6 class="m-0">Shipping:</h6>
                                                                    </td>
                                                                    <td class="text-end">
                                                                        FREE
                                                                    </td>
                                                                </tr>
                                                                <tr class="text-end">
                                                                    <td>
                                                                        <h5 class="m-0">Total:</h5>
                                                                    </td>
                                                                    <td class="text-end fw-semibold">
                                                                        $1071.29
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <!-- end table-responsive -->
                                                </div> <!-- end .border-->

                                            </div> <!-- end col --> --}}
                                        </div> <!-- end row-->
                                    </div>
                                    <!-- End Billing Information Content-->
                                </div>
                                <!-- Caller ID Content-->
                                <div class="tab-pane fade" id="v-pills-callerid" role="tabpanel" aria-labelledby="v-pills-callerid-tab">
                                    <div class="tab-pane show active" id="basic-information">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">External Caller ID</h4>

                                                <p class="text-muted mb-3">Define the External Caller ID that will be displayed on the recipeint's device when dialing outside the company.</p>

                                                <form>
                                                    
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Phone Number</label>
                                                                <select data-toggle="select2" title="Country">
                                                                    <option value="">Main Company Number</option>
                                                                    <option value="AF">Afghanistan</option>
                                                                    <option value="AL">Albania</option>
                                                                    <option value="DZ">Algeria</option>
                                                                    <option value="AS">American Samoa</option>                
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                   
                                                </form>
                                            </div>
                                        
                                            <div class="col-lg-12">
                                                <h4 class="mt-4">Internal Caller ID</h4>

                                                <p class="text-muted mb-3">Define the Internal Caller ID that will be displayed on the recipeint's device when dialing inside the company.</p>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="billing-first-name" class="form-label">First Name</label>
                                                            <input class="form-control" type="text" placeholder="Enter first name" disabled id="billing-first-name" />
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="billing-last-name" class="form-label">Last Name</label>
                                                            <input class="form-control" type="text" placeholder="Enter last name" disabled id="billing-last-name" />
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="billing-extension" class="form-label">Extension number</label>
                                                            <input class="form-control" type="text" placeholder="xxxx"  disabled id="billing-extension" />
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                            </div>

                                            <div class="col-lg-12">
                                                <h4 class="mt-4">Emergency Caller ID</h4>

                                                <p class="text-muted mb-3">Define the Emergency Caller ID that will be displayed when dialing emergency services.</p>

                                                <form>
                                                    
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Phone Number</label>
                                                                <select data-toggle="select2" title="Country">
                                                                    <option value="">Main Company Number</option>
                                                                    <option value="AF">Afghanistan</option>
                                                                    <option value="AL">Albania</option>
                                                                    <option value="DZ">Algeria</option>
                                                                    <option value="AS">American Samoa</option>                
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                   
                                                </form>
                                            </div>
                                           
                                        </div> <!-- end row-->
                                    </div>
                                    <!-- End Caller ID Content-->
                                </div>
                                <div class="tab-pane fade" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab">
                                    <p class="mb-0">...</p>
                                </div>
                                <div class="tab-pane fade" id="v-pills-settings" role="tabpanel" aria-labelledby="v-pills-settings-tab">
                                    <p class="mb-0">...</p>
                                </div>
                            </div> <!-- end tab-content-->
                        </div> <!-- end col-->
                    </div>
                    <!-- end row-->

                    <!-- Checkout Steps -->
                    <ul class="nav nav-pills bg-nav-pills nav-justified mb-3">
                        <li class="nav-item">
                            <a href="#billing-information" data-bs-toggle="tab" aria-expanded="false" class="nav-link rounded-0 active">
                                <i class="mdi mdi-account-circle font-18"></i>
                                <span class="d-none d-lg-block">Basic Info</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#shipping-information" data-bs-toggle="tab" aria-expanded="true" class="nav-link rounded-0">
                                <i class="mdi mdi-truck-fast font-18"></i>
                                <span class="d-none d-lg-block">Shipping Info</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#payment-information" data-bs-toggle="tab" aria-expanded="false" class="nav-link rounded-0">
                                <i class="mdi mdi-cash-multiple font-18"></i>
                                <span class="d-none d-lg-block">Payment Info</span>
                            </a>
                        </li>
                    </ul>

                    <!-- Steps Information -->
                    <div class="tab-content">

                        <!-- Basic Info Content-->
                        <div class="tab-pane show active" id="basic-information">
                            <div class="row">
                                <div class="col-lg-12">
                                    <h4 class="mt-2">Basic information</h4>

                                    <p class="text-muted mb-4">Provide basic information about the user or extension</p>

                                    <form>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="billing-first-name" class="form-label">First Name</label>
                                                    <input class="form-control" type="text" placeholder="Enter your first name" id="billing-first-name" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="billing-last-name" class="form-label">Last Name</label>
                                                    <input class="form-control" type="text" placeholder="Enter your last name" id="billing-last-name" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="billing-email-address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                    <input class="form-control" type="email" placeholder="Enter your email" id="billing-email-address" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="billing-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                                    <input class="form-control" type="text" placeholder="(xx) xxx xxxx xxx" id="billing-phone" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label for="billing-address" class="form-label">Address</label>
                                                    <input class="form-control" type="text" placeholder="Enter full address" id="billing-address">
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="billing-town-city" class="form-label">Town / City</label>
                                                    <input class="form-control" type="text" placeholder="Enter your city name" id="billing-town-city" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="billing-state" class="form-label">State</label>
                                                    <input class="form-control" type="text" placeholder="Enter your state" id="billing-state" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="billing-zip-postal" class="form-label">Zip / Postal Code</label>
                                                    <input class="form-control" type="text" placeholder="Enter your zip code" id="billing-zip-postal" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Country</label>
                                                    <select data-toggle="select2" title="Country">
                                                        <option value="0">Select Country</option>
                                                        <option value="AF">Afghanistan</option>
                                                        <option value="AL">Albania</option>
                                                        <option value="DZ">Algeria</option>
                                                        <option value="AS">American Samoa</option>
                                                        <option value="AD">Andorra</option>
                                                        <option value="AO">Angola</option>
                                                        <option value="AI">Anguilla</option>
                                                        <option value="AQ">Antarctica</option>
                                                        <option value="AR">Argentina</option>
                                                        <option value="AM">Armenia</option>
                                                        <option value="AW">Aruba</option>
                                                        <option value="AU">Australia</option>
                                                        <option value="AT">Austria</option>
                                                        <option value="AZ">Azerbaijan</option>
                                                        <option value="BS">Bahamas</option>
                                                        <option value="DK">Denmark</option>
               
     
                                                    </select>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input" id="customCheck2">
                                                        <label class="form-check-label" for="customCheck2">Ship to different address ?</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3 mt-3">
                                                    <label for="example-textarea" class="form-label">Order Notes:</label>
                                                    <textarea class="form-control" id="example-textarea" rows="3" placeholder="Write some note.."></textarea>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <div class="row mt-4">
                                            <div class="col-sm-6">
                                                <a href="" class="btn text-muted d-none d-sm-inline-block btn-link fw-semibold">
                                                    <i class="mdi mdi-arrow-left"></i> Back to Shopping Cart </a>
                                            </div> <!-- end col -->
                                            <div class="col-sm-6">
                                                <div class="text-sm-end">
                                                    <a href="" class="btn btn-danger">
                                                        <i class="mdi mdi-truck-fast me-1"></i> Proceed to Shipping </a>
                                                </div>
                                            </div> <!-- end col -->
                                        </div> <!-- end row -->
                                    </form>
                                </div>
                                {{-- <div class="col-lg-4">
                                    <div class="border p-3 mt-4 mt-lg-0 rounded">
                                        <h4 class="header-title mb-3">Order Summary</h4>

                                        <div class="table-responsive">
                                            <table class="table table-centered mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-1.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Amazing Modern Chair</a>
                                                                <br>
                                                                <small>5 x $148.66</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $743.30
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-2.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Designer Awesome Chair</a>
                                                                <br>
                                                                <small>2 x $99.00</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $198.00
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-3.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Biblio Plastic Armchair</a>
                                                                <br>
                                                                <small>1 x $129.99</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $129.99
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h6 class="m-0">Sub Total:</h6>
                                                        </td>
                                                        <td class="text-end">
                                                            $1071.29
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h6 class="m-0">Shipping:</h6>
                                                        </td>
                                                        <td class="text-end">
                                                            FREE
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h5 class="m-0">Total:</h5>
                                                        </td>
                                                        <td class="text-end fw-semibold">
                                                            $1071.29
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- end table-responsive -->
                                    </div> <!-- end .border-->

                                </div> <!-- end col --> --}}
                            </div> <!-- end row-->
                        </div>
                        <!-- End Billing Information Content-->

                        <!-- Shipping Content-->
                        <div class="tab-pane" id="shipping-information">
                            <div class="row">
                                <div class="col-lg-8">
                                    <h4 class="mt-2">Saved Address</h4>

                                    <p class="text-muted mb-3">Fill the form below in order to
                                        send you the order's invoice.</p>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="border p-3 rounded mb-3 mb-md-0">
                                                <address class="mb-0 address-lg">
                                                    <div class="form-check">
                                                        <input type="radio" id="customRadio1" name="customRadio" class="form-check-input" checked>
                                                        <label class="form-check-label font-16 fw-bold" for="customRadio1">Home</label>
                                                    </div>
                                                    <br />
                                                    <span class="fw-semibold">Stanley Jones</span> <br />
                                                    795 Folsom Ave, Suite 600<br>
                                                    San Francisco, CA 94107<br>
                                                    <abbr title="Phone">P:</abbr> (123) 456-7890 <br>
                                                </address>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border p-3 rounded">
                                                <address class="mb-0 address-lg">
                                                    <div class="form-check">
                                                        <input type="radio" id="customRadio2" name="customRadio" class="form-check-input">
                                                        <label class="form-check-label font-16 fw-bold" for="customRadio2">Office</label>
                                                    </div>
                                                    <br />
                                                    <span class="fw-semibold">Stanley Jones</span> <br />
                                                    795 Folsom Ave, Suite 600<br>
                                                    San Francisco, CA 94107<br>
                                                    <abbr title="Phone">P:</abbr> (123) 456-7890 <br>
                                                </address>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end row-->

                                    <h4 class="mt-4">Add New Address</h4>

                                    <p class="text-muted mb-4">Fill the form below so we can
                                        send you the order's invoice.</p>

                                    <form>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="new-adr-first-name" class="form-label">First Name</label>
                                                    <input class="form-control" type="text" placeholder="Enter your first name" id="new-adr-first-name" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="new-adr-last-name" class="form-label">Last Name</label>
                                                    <input class="form-control" type="text" placeholder="Enter your last name" id="new-adr-last-name" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="new-adr-email-address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                    <input class="form-control" type="email" placeholder="Enter your email" id="new-adr-email-address" />
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="new-adr-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                                    <input class="form-control" type="text" placeholder="(xx) xxx xxxx xxx" id="new-adr-phone" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label for="new-adr-address" class="form-label">Address</label>
                                                    <input class="form-control" type="text" placeholder="Enter full address" id="new-adr-address">
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="new-adr-town-city" class="form-label">Town / City</label>
                                                    <input class="form-control" type="text" placeholder="Enter your city name" id="new-adr-town-city" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="new-adr-state" class="form-label">State</label>
                                                    <input class="form-control" type="text" placeholder="Enter your state" id="new-adr-state" />
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="mb-3">
                                                    <label for="new-adr-zip-postal" class="form-label">Zip / Postal Code</label>
                                                    <input class="form-control" type="text" placeholder="Enter your zip code" id="new-adr-zip-postal" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-12">
                                                <div class="mb-3">
                                                    <label class="form-label">Country</label>
                                                    <select data-toggle="select2" title="Country">
                                                        <option value="0">Select Country</option>
                                                        <option value="AF">Afghanistan</option>
                                                        <option value="AL">Albania</option>
                                                        <option value="DZ">Algeria</option>
                                                        <option value="AS">American Samoa</option>
                                                        <option value="AD">Andorra</option>
                                                        <option value="AO">Angola</option>
                                                        <option value="AI">Anguilla</option>
                                                        <option value="AQ">Antarctica</option>
                                                        <option value="AR">Argentina</option>
                                                        <option value="AM">Armenia</option>
                                                        <option value="AW">Aruba</option>
                                                        <option value="AU">Australia</option>
                                                        <option value="AT">Austria</option>
                                                        <option value="AZ">Azerbaijan</option>
                                                        <option value="BS">Bahamas</option>
                                                        <option value="BH">Bahrain</option>
                                                        <option value="BD">Bangladesh</option>
                                                        <option value="BB">Barbados</option>
                                                        <option value="BY">Belarus</option>
                                                        <option value="BE">Belgium</option>
                                                        <option value="BZ">Belize</option>
                                                        <option value="BJ">Benin</option>
                                                        <option value="BM">Bermuda</option>
                                                        <option value="BT">Bhutan</option>
                                                        <option value="BO">Bolivia</option>
                                                        <option value="BW">Botswana</option>
                                                        <option value="BV">Bouvet Island</option>
                                                        <option value="BR">Brazil</option>
                                                        <option value="BN">Brunei Darussalam</option>
                                                        <option value="BG">Bulgaria</option>
                                                        <option value="BF">Burkina Faso</option>
                                                        <option value="BI">Burundi</option>
                                                        <option value="KH">Cambodia</option>
                                                        <option value="CM">Cameroon</option>
                                                        <option value="CA">Canada</option>
                                                        <option value="CV">Cape Verde</option>
                                                        <option value="KY">Cayman Islands</option>
                                                        <option value="CF">Central African Republic</option>
                                                        <option value="TD">Chad</option>
                                                        <option value="CL">Chile</option>
                                                        <option value="CN">China</option>
                                                        <option value="CX">Christmas Island</option>
                                                        <option value="CC">Cocos (Keeling) Islands</option>
                                                        <option value="CO">Colombia</option>
                                                        <option value="KM">Comoros</option>
                                                        <option value="CG">Congo</option>
                                                        <option value="CK">Cook Islands</option>
                                                        <option value="CR">Costa Rica</option>
                                                        <option value="CI">Cote d'Ivoire</option>
                                                        <option value="HR">Croatia (Hrvatska)</option>
                                                        <option value="CU">Cuba</option>
                                                        <option value="CY">Cyprus</option>
                                                        <option value="CZ">Czech Republic</option>
                                                        <option value="DK">Denmark</option>
                                                        <option value="DJ">Djibouti</option>
                                                        <option value="DM">Dominica</option>
                                                        <option value="DO">Dominican Republic</option>
                                                        <option value="EC">Ecuador</option>
                                                        <option value="EG">Egypt</option>
                                                        <option value="SV">El Salvador</option>
                                                        <option value="GQ">Equatorial Guinea</option>
                                                        <option value="ER">Eritrea</option>
                                                        <option value="EE">Estonia</option>
                                                        <option value="ET">Ethiopia</option>
                                                        <option value="FK">Falkland Islands (Malvinas)</option>
                                                        <option value="FO">Faroe Islands</option>
                                                        <option value="FJ">Fiji</option>
                                                        <option value="FI">Finland</option>
                                                        <option value="FR">France</option>
                                                        <option value="GF">French Guiana</option>
                                                        <option value="PF">French Polynesia</option>
                                                        <option value="GA">Gabon</option>
                                                        <option value="GM">Gambia</option>
                                                        <option value="GE">Georgia</option>
                                                        <option value="DE">Germany</option>
                                                        <option value="GH">Ghana</option>
                                                        <option value="GI">Gibraltar</option>
                                                        <option value="GR">Greece</option>
                                                        <option value="GL">Greenland</option>
                                                        <option value="GD">Grenada</option>
                                                        <option value="GP">Guadeloupe</option>
                                                        <option value="GU">Guam</option>
                                                        <option value="GT">Guatemala</option>
                                                        <option value="GN">Guinea</option>
                                                        <option value="GW">Guinea-Bissau</option>
                                                        <option value="GY">Guyana</option>
                                                        <option value="HT">Haiti</option>
                                                        <option value="HN">Honduras</option>
                                                        <option value="HK">Hong Kong</option>
                                                        <option value="HU">Hungary</option>
                                                        <option value="IS">Iceland</option>
                                                        <option value="IN">India</option>
                                                        <option value="ID">Indonesia</option>
                                                        <option value="IQ">Iraq</option>
                                                        <option value="IE">Ireland</option>
                                                        <option value="IL">Israel</option>
                                                        <option value="IT">Italy</option>
                                                        <option value="JM">Jamaica</option>
                                                        <option value="JP">Japan</option>
                                                        <option value="JO">Jordan</option>
                                                        <option value="KZ">Kazakhstan</option>
                                                        <option value="KE">Kenya</option>
                                                        <option value="KI">Kiribati</option>
                                                        <option value="KR">Korea, Republic of</option>
                                                        <option value="KW">Kuwait</option>
                                                        <option value="KG">Kyrgyzstan</option>
                                                        <option value="LV">Latvia</option>
                                                        <option value="LB">Lebanon</option>
                                                        <option value="LS">Lesotho</option>
                                                        <option value="LR">Liberia</option>
                                                        <option value="LY">Libyan Arab Jamahiriya</option>
                                                        <option value="LI">Liechtenstein</option>
                                                        <option value="LT">Lithuania</option>
                                                        <option value="LU">Luxembourg</option>
                                                        <option value="MO">Macau</option>
                                                        <option value="MG">Madagascar</option>
                                                        <option value="MW">Malawi</option>
                                                        <option value="MY">Malaysia</option>
                                                        <option value="MV">Maldives</option>
                                                        <option value="ML">Mali</option>
                                                        <option value="MT">Malta</option>
                                                        <option value="MH">Marshall Islands</option>
                                                        <option value="MQ">Martinique</option>
                                                        <option value="MR">Mauritania</option>
                                                        <option value="MU">Mauritius</option>
                                                        <option value="YT">Mayotte</option>
                                                        <option value="MX">Mexico</option>
                                                        <option value="MD">Moldova, Republic of</option>
                                                        <option value="MC">Monaco</option>
                                                        <option value="MN">Mongolia</option>
                                                        <option value="MS">Montserrat</option>
                                                        <option value="MA">Morocco</option>
                                                        <option value="MZ">Mozambique</option>
                                                        <option value="MM">Myanmar</option>
                                                        <option value="NA">Namibia</option>
                                                        <option value="NR">Nauru</option>
                                                        <option value="NP">Nepal</option>
                                                        <option value="NL">Netherlands</option>
                                                        <option value="AN">Netherlands Antilles</option>
                                                        <option value="NC">New Caledonia</option>
                                                        <option value="NZ">New Zealand</option>
                                                        <option value="NI">Nicaragua</option>
                                                        <option value="NE">Niger</option>
                                                        <option value="NG">Nigeria</option>
                                                        <option value="NU">Niue</option>
                                                        <option value="NF">Norfolk Island</option>
                                                        <option value="MP">Northern Mariana Islands</option>
                                                        <option value="NO">Norway</option>
                                                        <option value="OM">Oman</option>
                                                        <option value="PW">Palau</option>
                                                        <option value="PA">Panama</option>
                                                        <option value="PG">Papua New Guinea</option>
                                                        <option value="PY">Paraguay</option>
                                                        <option value="PE">Peru</option>
                                                        <option value="PH">Philippines</option>
                                                        <option value="PN">Pitcairn</option>
                                                        <option value="PL">Poland</option>
                                                        <option value="PT">Portugal</option>
                                                        <option value="PR">Puerto Rico</option>
                                                        <option value="QA">Qatar</option>
                                                        <option value="RE">Reunion</option>
                                                        <option value="RO">Romania</option>
                                                        <option value="RU">Russian Federation</option>
                                                        <option value="RW">Rwanda</option>
                                                        <option value="KN">Saint Kitts and Nevis</option>
                                                        <option value="LC">Saint LUCIA</option>
                                                        <option value="WS">Samoa</option>
                                                        <option value="SM">San Marino</option>
                                                        <option value="ST">Sao Tome and Principe</option>
                                                        <option value="SA">Saudi Arabia</option>
                                                        <option value="SN">Senegal</option>
                                                        <option value="SC">Seychelles</option>
                                                        <option value="SL">Sierra Leone</option>
                                                        <option value="SG">Singapore</option>
                                                        <option value="SK">Slovakia (Slovak Republic)</option>
                                                        <option value="SI">Slovenia</option>
                                                        <option value="SB">Solomon Islands</option>
                                                        <option value="SO">Somalia</option>
                                                        <option value="ZA">South Africa</option>
                                                        <option value="ES">Spain</option>
                                                        <option value="LK">Sri Lanka</option>
                                                        <option value="SH">St. Helena</option>
                                                        <option value="PM">St. Pierre and Miquelon</option>
                                                        <option value="SD">Sudan</option>
                                                        <option value="SR">Suriname</option>
                                                        <option value="SZ">Swaziland</option>
                                                        <option value="SE">Sweden</option>
                                                        <option value="CH">Switzerland</option>
                                                        <option value="SY">Syrian Arab Republic</option>
                                                        <option value="TW">Taiwan, Province of China</option>
                                                        <option value="TJ">Tajikistan</option>
                                                        <option value="TZ">Tanzania, United Republic of</option>
                                                        <option value="TH">Thailand</option>
                                                        <option value="TG">Togo</option>
                                                        <option value="TK">Tokelau</option>
                                                        <option value="TO">Tonga</option>
                                                        <option value="TT">Trinidad and Tobago</option>
                                                        <option value="TN">Tunisia</option>
                                                        <option value="TR">Turkey</option>
                                                        <option value="TM">Turkmenistan</option>
                                                        <option value="TC">Turks and Caicos Islands</option>
                                                        <option value="TV">Tuvalu</option>
                                                        <option value="UG">Uganda</option>
                                                        <option value="UA">Ukraine</option>
                                                        <option value="AE">United Arab Emirates</option>
                                                        <option value="GB">United Kingdom</option>
                                                        <option value="US">United States</option>
                                                        <option value="UY">Uruguay</option>
                                                        <option value="UZ">Uzbekistan</option>
                                                        <option value="VU">Vanuatu</option>
                                                        <option value="VE">Venezuela</option>
                                                        <option value="VN">Viet Nam</option>
                                                        <option value="VG">Virgin Islands (British)</option>
                                                        <option value="VI">Virgin Islands (U.S.)</option>
                                                        <option value="WF">Wallis and Futuna Islands</option>
                                                        <option value="EH">Western Sahara</option>
                                                        <option value="YE">Yemen</option>
                                                        <option value="ZM">Zambia</option>
                                                        <option value="ZW">Zimbabwe</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <h4 class="mt-4">Shipping Method</h4>

                                        <p class="text-muted mb-3">Fill the form below in order to
                                            send you the order's invoice.</p>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="border p-3 rounded mb-3 mb-md-0">
                                                    <div class="form-check">
                                                        <input type="radio" id="shippingMethodRadio1" name="shippingOptions" class="form-check-input" checked>
                                                        <label class="form-check-label font-16 fw-bold" for="shippingMethodRadio1">Standard Delivery - FREE</label>
                                                    </div>
                                                    <p class="mb-0 ps-3 pt-1">Estimated 5-7 days shipping (Duties and tax may be due upon delivery)</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="border p-3 rounded">
                                                    <div class="form-check">
                                                        <input type="radio" id="shippingMethodRadio2" name="shippingOptions" class="form-check-input">
                                                        <label class="form-check-label font-16 fw-bold" for="shippingMethodRadio2">Fast Delivery - $25</label>
                                                    </div>
                                                    <p class="mb-0 ps-3 pt-1">Estimated 1-2 days shipping (Duties and tax may be due upon delivery)</p>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- end row-->

                                        <div class="row mt-4">
                                            <div class="col-sm-6">
                                                <a href="" class="btn text-muted d-none d-sm-inline-block btn-link fw-semibold">
                                                    <i class="mdi mdi-arrow-left"></i> Back to Shopping Cart </a>
                                            </div> <!-- end col -->
                                            <div class="col-sm-6">
                                                <div class="text-sm-end">
                                                    <a href="" class="btn btn-danger">
                                                        <i class="mdi mdi-cash-multiple me-1"></i> Continue to Payment </a>
                                                </div>
                                            </div> <!-- end col -->
                                        </div> <!-- end row -->
                                    </form>
                                </div>
                                <div class="col-lg-4">
                                    <div class="border p-3 mt-4 mt-lg-0 rounded">
                                        <h4 class="header-title mb-3">Order Summary</h4>

                                        <div class="table-responsive">
                                            <table class="table table-centered mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-1.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Amazing Modern Chair</a>
                                                                <br>
                                                                <small>5 x $148.66</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $743.30
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-2.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Designer Awesome Chair</a>
                                                                <br>
                                                                <small>2 x $99.00</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $198.00
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-3.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Biblio Plastic Armchair</a>
                                                                <br>
                                                                <small>1 x $129.99</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $129.99
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h6 class="m-0">Sub Total:</h6>
                                                        </td>
                                                        <td class="text-end">
                                                            $1071.29
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h6 class="m-0">Shipping:</h6>
                                                        </td>
                                                        <td class="text-end">
                                                            FREE
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h5 class="m-0">Total:</h5>
                                                        </td>
                                                        <td class="text-end fw-semibold">
                                                            $1071.29
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- end table-responsive -->
                                    </div> <!-- end .border-->

                                </div> <!-- end col -->
                            </div> <!-- end row-->
                        </div>
                        <!-- End Shipping Information Content-->

                        <!-- Payment Content-->
                        <div class="tab-pane" id="payment-information">
                            <div class="row">

                                <div class="col-lg-8">
                                    <h4 class="mt-2">Payment Selection</h4>

                                    <p class="text-muted mb-4">Fill the form below in order to
                                        send you the order's invoice.</p>

                                    <!-- Pay with Paypal box-->
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-sm-8">
                                                <div class="form-check">
                                                    <input type="radio" id="BillingOptRadio2" name="billingOptions" class="form-check-input">
                                                    <label class="form-check-label font-16 fw-bold" for="BillingOptRadio2">Pay with Paypal</label>
                                                </div>
                                                <p class="mb-0 ps-3 pt-1">You will be redirected to PayPal website to complete your purchase securely.</p>
                                            </div>
                                            <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                                                <img src="{{asset('assets/images/payments/paypal.png')}}" height="25" alt="paypal-img">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end Pay with Paypal box-->

                                    <!-- Credit/Debit Card box-->
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-sm-8">
                                                <div class="form-check">
                                                    <input type="radio" id="BillingOptRadio1" name="billingOptions" class="form-check-input" checked>
                                                    <label class="form-check-label font-16 fw-bold" for="BillingOptRadio1">Credit / Debit Card</label>
                                                </div>
                                                <p class="mb-0 ps-3 pt-1">Safe money transfer using your bank account. We support Mastercard, Visa, Discover and Stripe.</p>
                                            </div>
                                            <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                                                <img src="{{asset('assets/images/payments/master.png')}}" height="24" alt="master-card-img">
                                                <img src="{{asset('assets/images/payments/discover.png')}}" height="24" alt="discover-card-img">
                                                <img src="{{asset('assets/images/payments/visa.png')}}" height="24" alt="visa-card-img">
                                                <img src="{{asset('assets/images/payments/stripe.png')}}" height="24" alt="stripe-card-img">
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row mt-4">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label for="card-number" class="form-label">Card Number</label>
                                                    <input type="text" id="card-number" class="form-control" data-toggle="input-mask" data-mask-format="0000 0000 0000 0000" placeholder="4242 4242 4242 4242">
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="card-name-on" class="form-label">Name on card</label>
                                                    <input type="text" id="card-name-on" class="form-control" placeholder="Master Shreyu">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-3">
                                                    <label for="card-expiry-date" class="form-label">Expiry date</label>
                                                    <input type="text" id="card-expiry-date" class="form-control" data-toggle="input-mask" data-mask-format="00/00" placeholder="MM/YY">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="mb-3">
                                                    <label for="card-cvv" class="form-label">CVV code</label>
                                                    <input type="text" id="card-cvv" class="form-control" data-toggle="input-mask" data-mask-format="000" placeholder="012">
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                    </div>
                                    <!-- end Credit/Debit Card box-->

                                    <!-- Pay with Payoneer box-->
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-sm-8">
                                                <div class="form-check">
                                                    <input type="radio" id="BillingOptRadio3" name="billingOptions" class="form-check-input">
                                                    <label class="form-check-label font-16 fw-bold" for="BillingOptRadio3">Pay with Payoneer</label>
                                                </div>
                                                <p class="mb-0 ps-3 pt-1">You will be redirected to Payoneer website to complete your purchase securely.</p>
                                            </div>
                                            <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                                                <img src="{{asset('assets/images/payments/payoneer.png')}}" height="30" alt="paypal-img">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end Pay with Payoneer box-->

                                    <!-- Cash on Delivery box-->
                                    <div class="border p-3 mb-3 rounded">
                                        <div class="row">
                                            <div class="col-sm-8">
                                                <div class="form-check">
                                                    <input type="radio" id="BillingOptRadio4" name="billingOptions" class="form-check-input">
                                                    <label class="form-check-label font-16 fw-bold" for="BillingOptRadio4">Cash on Delivery</label>
                                                </div>
                                                <p class="mb-0 ps-3 pt-1">Pay with cash when your order is delivered.</p>
                                            </div>
                                            <div class="col-sm-4 text-sm-end mt-3 mt-sm-0">
                                                <img src="{{asset('assets/images/payments/cod.png')}}" height="22" alt="paypal-img">
                                            </div>
                                        </div>
                                    </div>
                                    <!-- end Cash on Delivery box-->

                                    <div class="row mt-4">
                                        <div class="col-sm-6">
                                            <a href="" class="btn text-muted d-none d-sm-inline-block btn-link fw-semibold">
                                                <i class="mdi mdi-arrow-left"></i> Back to Shopping Cart </a>
                                        </div> <!-- end col -->
                                        <div class="col-sm-6">
                                            <div class="text-sm-end">
                                                <a href="" class="btn btn-danger">
                                                    <i class="mdi mdi-cash-multiple me-1"></i> Complete Order </a>
                                            </div>
                                        </div> <!-- end col -->
                                    </div> <!-- end row-->

                                </div> <!-- end col -->

                                <div class="col-lg-4">
                                    <div class="border p-3 mt-4 mt-lg-0 rounded">
                                        <h4 class="header-title mb-3">Order Summary</h4>

                                        <div class="table-responsive">
                                            <table class="table table-centered mb-0">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-1.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Amazing Modern Chair</a>
                                                                <br>
                                                                <small>5 x $148.66</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $743.30
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-2.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Designer Awesome Chair</a>
                                                                <br>
                                                                <small>2 x $99.00</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $198.00
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>
                                                            <img src="{{asset('assets/images/products/product-3.jpg')}}" alt="contact-img" title="contact-img" class="rounded me-2" height="48" />
                                                            <p class="m-0 d-inline-block align-middle">
                                                                <a href="" class="text-body fw-semibold">Biblio Plastic Armchair</a>
                                                                <br>
                                                                <small>1 x $129.99</small>
                                                            </p>
                                                        </td>
                                                        <td class="text-end">
                                                            $129.99
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h6 class="m-0">Sub Total:</h6>
                                                        </td>
                                                        <td class="text-end">
                                                            $1071.29
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h6 class="m-0">Shipping:</h6>
                                                        </td>
                                                        <td class="text-end">
                                                            FREE
                                                        </td>
                                                    </tr>
                                                    <tr class="text-end">
                                                        <td>
                                                            <h5 class="m-0">Total:</h5>
                                                        </td>
                                                        <td class="text-end fw-semibold">
                                                            $1071.29
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <!-- end table-responsive -->
                                    </div> <!-- end .border-->

                                </div> <!-- end col -->
                            </div> <!-- end row-->
                        </div>
                        <!-- End Payment Information Content-->

                    </div> <!-- end tab content-->

                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row-->

</div> <!-- container -->
@endsection