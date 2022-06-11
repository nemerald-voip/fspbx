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
                                <a class="nav-link" id="v-pills-voicemail-tab" data-bs-toggle="pill" href="#v-pills-voicemail" role="tab" aria-controls="v-pills-voicemail"
                                    aria-selected="false">
                                    <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Voicemail</span>
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
                                    <div class="row">
                                        <div class="col-lg-12">
                                            <h4 class="mt-2">Basic information</h4>

                                            <p class="text-muted mb-4">Provide basic information about the user or extension</p>

                                            <form>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="first-name" class="form-label">First Name <span class="text-danger">*</span></label>
                                                            <input class="form-control" type="text" placeholder="Enter first name" id="first-name" value="{{ $extension->directory_first_name }}"/>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="last-name" class="form-label">Last Name</label>
                                                            <input class="form-control" type="text" placeholder="Enter last name" id="last-name" value="{{ $extension->directory_last_name }}"/>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                                <div class="row">
                                                    @if (userCheckPermission('extension_extension'))
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="extension" class="form-label">Extension number <span class="text-danger">*</span></label>
                                                            <input class="form-control" type="text" placeholder="xxxx" id="extension" value="{{ $extension->extension }}"/>
                                                        </div>
                                                    </div>
                                                    @endif
                                                    @if (userCheckPermission('voicemail_edit'))
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="voicemail-email-address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                            <input class="form-control" type="email" placeholder="Enter email" id="voicemail-email-address" value="{{ $extension->voicemail()->voicemail_mail_to }}"/>
                                                        </div>
                                                    </div>
                                                    @endif
                                                </div> <!-- end row -->
                                                @if (userCheckPermission('extension_user_edit'))
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
                                                @endif
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
                                                    <div class="col-4">
                                                        <div class="mb-3">
                                                            <label  class="form-label">Enabled </label>
                                                            <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                                data-bs-content="Set the status of the extension.">
                                                                <i class="dripicons-information"></i>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <div class="col-2">
                                                        <div class="mb-3 text-sm-end">
                                                            <input type="checkbox" id="enabled-switch" checked data-switch="primary"/>
                                                            <label for="enabled-switch" data-on-label="On" data-off-label="Off"></label>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="mb-3">
                                                            <label for="description" class="form-label">Description</label>
                                                            <input class="form-control" type="text" placeholder="" id="description" />
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->


                                            </form>
                                        </div>

                                    </div> <!-- end row-->

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
                                <div class="tab-pane fade" id="v-pills-voicemail" role="tabpanel" aria-labelledby="v-pills-voicemail-tab">
                                    <!-- Voicemail Content-->
                                    <div class="tab-pane show active">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Voicemail settings</h4>

                                                <p class="text-muted mb-4">Voicemail settings allow you to update your voicemail access PIN, personalize, maintain and update your voicemail greeting to inform your friends, customers, or colleagues of your status.</p>

                                                <form>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Voicemail enabled </label>
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
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">If no answer, send to voicemail after</label>
                                                                <select data-toggle="select2" title="If no answer, send to voicemail after">
                                                                    <option value="">6 Rings - 30 Sec</option>
                                                                    <option value="AF">6 Rings - 30 Sec</option>
                                                                    <option value="AL">6 Rings - 30 Sec</option>             
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="voicmeail-pin" class="form-label">Set voicemail PIN <span class="text-danger">*</span></label>
                                                                <div class="input-group input-group-merge">
                                                                    <input type="password" id="password" class="form-control" placeholder="xxxx">
                                                                    <div class="input-group-text" data-password="false">
                                                                        <span class="password-eye"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Notification type</label>
                                                                <select data-toggle="select2" title="Notification Type">
                                                                    <option value="">Email with audio file attachment</option>
                                                                    <option value="AF">Email with download link</option>
                                                                    <option value="AL">Email with login link</option>             
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="billing-email-address" class="form-label">Email Address <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="email" placeholder="Enter email" id="billing-email-address" />
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Enable voicemail transcription </label>
                                                                <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                                    data-bs-content="Send a text trancsript. Accuracy may vary based on call quality, accents, vocabulary, etc. ">
                                                                    <i class="dripicons-information"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="checkbox" id="transcription-switch" checked data-switch="primary"/>
                                                                <label for="transcription-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Delete voicemail after sending email </label>
                                                                <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                                    data-bs-content="Enables email-only voicemail. Disables storing of voicemail messages for this mailbox in the cloud.">
                                                                    <i class="dripicons-information"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="checkbox" id="directory-name-switch" data-switch="primary"/>
                                                                <label for="directory-name-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->


                                                </form>
                                            </div>

                                        </div> <!-- end row-->
                                        <div class="row mb-4">
                                            <div class="col-lg-6">
                                                <h4 class="mt-2">Unavailable greeting</h4>

                                                <p class="text-muted mb-2">This plays when you do not pick up the phone.</p>
                                                <p class="text-black-50 mb-1">Play the default, upload or record a new message.</p>
                                                <p class="text-muted mb-1">File name: <strong>unavailable.wav</strong></p>
                                                
                                                <button type="button" class="btn btn-light"><i class="uil uil-play"></i> </button>
                                                <button type="button" class="btn btn-light"><i class="uil uil-export"></i> </button>
                                                <button type="button" class="btn btn-light"><i class="uil uil-down-arrow"></i> </button>
                                                <button type="button" class="btn btn-light"><i class="uil uil-trash-alt"></i> </button>
                                            </div>
                                            <div class="col-lg-6">
                                                <h4 class="mt-2">Name greeting</h4>

                                                <p class="text-muted mb-2">This plays to identify your extension in the company's dial by name directory.</p>
                                                <p class="text-black-50 mb-1">Play the default, upload or record a new message.</p>
                                                <p class="text-muted mb-1">File name: <strong>name.wav</strong></p>
                                                
                                                <button type="button" class="btn btn-light"><i class="uil uil-play"></i> </button>
                                                <button type="button" class="btn btn-light"><i class="uil uil-export"></i> </button>
                                                <button type="button" class="btn btn-light"><i class="uil uil-down-arrow"></i> </button>
                                                <button type="button" class="btn btn-light"><i class="uil uil-trash-alt"></i> </button>


                                            </div>

                                        </div> <!-- end row-->

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="alternative-greeting" class="form-label">Alternative greet ID</label>
                                                    <input class="form-control" type="email" placeholder="" id="alternative-greeting" />
                                                    <span class="help-block"><small>An alternative greet id used in the default greeting.</small></span>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="voicemail-description" class="form-label">Description</label>
                                                    <input class="form-control" type="email" placeholder="" id="voicemail-description" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->
                                        
                                        <div class="row">
                                            <div class="col-4">
                                                <div class="mb-3">
                                                    <label class="form-label">Play voicemail tutorial </label>
                                                    <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                        data-bs-content="Play the voicemail tutorial after the next voicemail login.">
                                                        <i class="dripicons-information"></i>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="mb-3 text-sm-end">
                                                    <input type="checkbox" id="voicemail-tutorial-switch" data-switch="primary"/>
                                                    <label for="voicemail-tutorial-switch" data-on-label="On" data-off-label="Off"></label>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <div class="row">
                                            <div class="col-6">
                                                <div class="mb-3">
                                                    <label for="additional-destinations-select" class="form-label">Forward voicemail messages to additional destinations.</label>
                                                    <!-- Multiple Select -->
                                                    <select class="select2 form-control select2-multiple" data-toggle="select2" multiple="multiple" data-placeholder="Choose ..."
                                                        id="additional-destinations-select">
                                                            <option value="AK">Alaska</option>
                                                            <option value="HI">Hawaii</option>
                                                            <option value="CA">California</option>
                                                            <option value="NV">Nevada</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                        <h4 class="mt-2">Exiting voicemail options</h4>

                                        <div class="row">
                                            <div class="col-1">
                                                <div class="mb-3">
                                                    <label for="voicemail-option" class="form-label">Option</label>
                                                    <input class="form-control" type="email" placeholder="" id="voicemail-option" />
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="mb-3">
                                                    <label class="form-label">Destination type</label>
                                                    <select data-toggle="select2" title="Destination Type">
                                                        <option value=""></option>
                                                        <option value="AF"></option>
                                                        <option value="AL"></option>             
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <div class="mb-3">
                                                    <label for="voicemail-option-destination" class="form-label">Destination</label>
                                                    <input class="form-control" type="text" placeholder="" id="voicemail-option-destination" />
                                                </div>
                                            </div>
                                            <div class="col-1">
                                                <div class="mb-3">
                                                    <label class="form-label">Order</label>
                                                    <select data-toggle="select2" title="Order">
                                                        <option value=""></option>
                                                        <option value="AF"></option>
                                                        <option value="AL"></option>             
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="mb-3">
                                                    <label for="voicemail-option-description" class="form-label">Description</label>
                                                    <input class="form-control" type="email" placeholder="" id="voicemail-option-description" />
                                                </div>
                                            </div>
                                        </div> <!-- end row -->

                                    </div>
                                    <!-- End Voicemail Content-->
                                </div>
                                <div class="tab-pane fade" id="v-pills-profile" role="tabpanel" aria-labelledby="v-pills-profile-tab">
                                    <p class="mb-0">...</p>
                                </div>
                                <div class="tab-pane fade" id="v-pills-settings" role="tabpanel" aria-labelledby="v-pills-settings-tab">
                                    <!-- Settings Content-->
                                    <div class="tab-pane show active">
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2 mb-3">Settings</h4>

                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label class="form-label">Domain</label>
                                                            <select data-toggle="select2" title="Domain">
                                                                <option value=""></option>
                                                                <option value="AF"></option>
                                                                <option value="AL"></option>             
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="context" class="form-label">Context <span class="text-danger">*</span></label>
                                                            <input class="form-control" type="text" placeholder="" id="context" />
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="number-alias" class="form-label">Number Alias</label>
                                                            <input class="form-control" type="text" placeholder="" id="number-alias" />
                                                            <span class="help-block"><small>If the extension is numeric then number alias is optional.</small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="account-code" class="form-label">Account Code</label>
                                                            <input class="form-control" type="text" placeholder="" id="account-code" />
                                                            <span class="help-block"><small>Enter the account code here.</small></span>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="max-registrations" class="form-label">Total allowed registrations</label>
                                                            <input class="form-control" type="text" placeholder="" id="max-registrations" />
                                                            <span class="help-block"><small>Enter the maximum registration allowed for this user</small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="max-calls" class="form-label">Total allowed outbound calls</label>
                                                            <input class="form-control" type="text" placeholder="" id="max-calls" />
                                                            <span class="help-block"><small>Enter the max number of outgoing calls for this user.</small></span>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="limit-destination" class="form-label">Limit Destination</label>
                                                            <input class="form-control" type="text" placeholder="" id="limit-destination" />
                                                            <span class="help-block"><small>Enter the destination to send the calls when the max number of outgoing calls has been reached.</small></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="toll-allow" class="form-label">Toll Allow</label>
                                                            <input class="form-control" type="text" placeholder="" id="toll-allow" />
                                                            <span class="help-block"><small>Enter the toll allow value here. (Examples: domestic,international,local)</small></span>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="call-group" class="form-label">Call Group</label>
                                                            <input class="form-control" type="text" placeholder="" id="call-group" />
                                                            <span class="help-block"><small>Enter the user call group here. Groups available by default: sales, support, billing.</small></span>
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <form>
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Enable call screening</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="checkbox" id="call-screening-switch" data-switch="primary"/>
                                                                <label for="call-screening-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Call recording</label>
                                                                <select data-toggle="select2" title="Call recording">
                                                                    <option value="">Disabled</option>
                                                                    <option value="AF">All</option>
                                                                    <option value="AL">Local</option>
                                                                    <option value="AL">Inbound</option>   
                                                                    <option value="AL">Outbound</option>          
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Select custom music on hold</label>
                                                                <select data-toggle="select2" title="Select custom music on hold">
                                                                    <option value="">Disabled</option>
                                                                    <option value="AF">All</option>
                                                                    <option value="AL">Local</option>
                                                                    <option value="AL">Inbound</option>   
                                                                    <option value="AL">Outbound</option>          
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="auth-acl" class="form-label">Auth ACL</label>
                                                                <input class="form-control" type="text" placeholder="" id="auth-acl" />
                                                                <span class="help-block"><small>Enter the Auth ACL here.</small></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="cidr" class="form-label">CIDR</label>
                                                                <input class="form-control" type="text" placeholder="" id="cidr" />
                                                                <span class="help-block"><small>Enter allowed address/ranges in CIDR notation (comma separated).</small></span>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">SIP Force Contact</label>
                                                                <select data-toggle="select2" title="SIP Force Contact">
                                                                    <option value="">Rewrite Contact IP and Port</option>
                                                                    <option value="AF">Rewrite Contact IP and Port 2.0</option>
                                                                    <option value="AL">Rewrite TLS Contact Port</option>             
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="sip-force-expires" class="form-label">SIP Force Expires</label>
                                                                <input class="form-control" type="email" placeholder="Enter email" id="sip-force-expires" />
                                                                <span class="help-block"><small>To prevent stale registrations SIP Force expires can override the client expire.</small></span>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="monitor-wmi-account" class="form-label">Monitor MWI Account</label>
                                                                <input class="form-control" type="text" placeholder="" id="monitor-wmi-account" />
                                                                <span class="help-block"><small>MWI Account with user@domain of the voicemail to monitor.</small></span>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">SIP Bypass Media</label>
                                                                <select data-toggle="select2" title="SIP Bypass Media">
                                                                    <option value="">Bypass Media</option>
                                                                    <option value="AF">Bypass Media After Bridge</option>
                                                                    <option value="AL">Proxy Media</option>             
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="absolute-codec-string" class="form-label">Absolute Codec String</label>
                                                                <input class="form-control" type="text" placeholder="" id="absolute-codec-string" />
                                                                <span class="help-block"><small>Absolute Codec String for the extension</small></span>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Force ping </label>
                                                                <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus" 
                                                                    data-bs-content="Use OPTIONS to detect if extension is reachable">
                                                                    <i class="dripicons-information"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="checkbox" id="force-ping-switch" data-switch="primary"/>
                                                                <label for="force-ping-switch" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="dial-string" class="form-label">Dial String</label>
                                                                <input class="form-control" type="text" placeholder="" id="dial-string" />
                                                                <span class="help-block"><small>Location of the endpoint.</small></span>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->



                                                </form>
                                            </div>

                                        </div> <!-- end row-->

                                    </div>
                                    <!-- End Settings Content-->
                                </div>
                            </div> <!-- end tab-content-->
                        </div> <!-- end col-->
                    </div>
                    <!-- end row-->

                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row-->

</div> <!-- container -->
@endsection