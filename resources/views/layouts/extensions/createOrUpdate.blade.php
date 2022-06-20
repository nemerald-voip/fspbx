@extends('layouts.horizontal', ["page_title"=> "Edit Extension"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Edit Extension ({{ $extension->extension }})</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    @if ($extension->exists)
                        <form method="POST" action="{{ route('extensions.update',$extension) }}">
                        @method('put')
                    @else
                        <form method="POST" action="{{ route('extensions.create') }}">
                    @endif
                    @csrf
                        <div class="row">
                            <div class="col-sm-2 mb-2 mb-sm-0">
                                <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                    <a class="nav-link active show" id="v-pills-home-tab" data-bs-toggle="pill" href="#v-pills-home" role="tab" aria-controls="v-pills-home"
                                        aria-selected="true">
                                        <i class="mdi mdi-home-variant d-md-none d-block"></i>
                                        <span class="d-none d-md-block">Basic Information
                                            @if( $errors->has('directory_first_name') || 
                                                $errors->has('directory_last_name') || 
                                                $errors->has('extension') || 
                                                $errors->has('voicemail_mail_to') ||
                                                $errors->has('users') ||
                                                $errors->has('directory_visible') ||
                                                $errors->has('directory_exten_visible') ||
                                                $errors->has('enabled') ||
                                                $errors->has('description'))
                                                <span class="float-end text-end"><span class="badge badge-danger-lighten">error</span></span>
                                            @endif    
                                        </span>
                                    </a>
                                    <a class="nav-link" id="v-pills-callerid-tab" data-bs-toggle="pill" href="#v-pills-callerid" role="tab" aria-controls="v-pills-callerid"
                                        aria-selected="false">
                                        <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                        <span class="d-none d-md-block">Caller ID
                                            @if( $errors->has('outbound_caller_id_number') ||
                                                $errors->has('emergency_caller_id_number'))
                                                <span class="float-end text-end"><span class="badge badge-danger-lighten">error</span></span>
                                            @endif
                                        </span>
                                    </a>

                                    @if (userCheckPermission('voicemail_option_edit'))
                                    <a class="nav-link" id="v-pills-voicemail-tab" data-bs-toggle="pill" href="#v-pills-voicemail" role="tab" aria-controls="v-pills-voicemail"
                                        aria-selected="false">
                                        <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                        <span class="d-none d-md-block">Voicemail
                                            @if( $errors->has('voicemail_enabled') || 
                                                $errors->has('call_timeout') || 
                                                $errors->has('voicemail_password') || 
                                                $errors->has('voicemail_file') ||
                                                $errors->has('voicemail_transcription_enabled') ||
                                                $errors->has('voicemail_local_after_email') ||
                                                $errors->has('voicemail_description') ||
                                                $errors->has('voicemail_tutorial'))
                                                <span class="float-end text-end"><span class="badge badge-danger-lighten">error</span></span>
                                            @endif                                        
                                        </span>
                                    </a>
                                    @endif

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
                                <button class="btn btn-primary" type="submit"><i class="uil uil-down-arrow"></i> Save </button>
                            </div> <!-- end col-->

                                <div class="col-sm-10">
                                    <div class="tab-content" id="v-pills-tabContent">
                                        <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab">
                                            <!-- Basic Info Content-->
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mt-2">Basic information</h4>

                                                    <p class="text-muted mb-4">Provide basic information about the user or extension</p>

                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="directory_first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                                                    <input class="form-control" type="text" placeholder="Enter first name" id="directory_first_name"
                                                                        name="directory_first_name" value="{{ $extension->directory_first_name }}"/>
                                                                    @error('directory_first_name')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="directory_last_name" class="form-label">Last Name</label>
                                                                    <input class="form-control" type="text" placeholder="Enter last name" id="directory_last_name"
                                                                        name="directory_last_name" value="{{ $extension->directory_last_name }}"/>
                                                                    @error('directory_last_name')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        <div class="row">
                                                            @if (userCheckPermission('extension_extension'))
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="extension" class="form-label">Extension number <span class="text-danger">*</span></label>
                                                                    <input class="form-control" type="text" placeholder="xxxx" id="extension"
                                                                        name="extension" value="{{ $extension->extension }}"/>
                                                                    @error('extension')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                            @endif
                                                            @if (userCheckPermission('voicemail_edit'))
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="voicemail-email-address" class="form-label">Email Address </label>
                                                                    <input class="form-control" type="email" placeholder="Enter email" id="voicemail-email-address"
                                                                        name="voicemail_mail_to" value="{{ $extension->voicemail->voicemail_mail_to }}"/>
                                                                    @error('voicemail_mail_to')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div> <!-- end row -->

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="users-select" class="form-label">Users</label>
                                                                    <!-- Multiple Select -->
                                                                    <select class="select2 form-control select2-multiple" data-toggle="select2" multiple="multiple" data-placeholder="Choose ..."
                                                                        id="users-select" @if (!userCheckPermission('extension_user_edit')) disabled @endif name="users[]">
                                                                            @foreach ($domain_users as $domain_user)
                                                                                <option value="{{ $domain_user->user_uuid }}"
                                                                                    @if($extension_users->contains($domain_user))
                                                                                        selected
                                                                                    @endif>{{ $domain_user->username }}</option>
                                                                            @endforeach
                                                                    </select>
                                                                    @error('users')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        @if (userCheckPermission('extension_directory'))
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
                                                                    <input type="hidden" name="directory_visible" value="false">
                                                                    <input type="checkbox" id="directory_visible" name="directory_visible"
                                                                    @if ($extension->directory_visible == "true") checked @endif
                                                                    data-switch="primary"/>
                                                                    <label for="directory_visible" data-on-label="On" data-off-label="Off"></label>
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
                                                                    <input type="hidden" name="directory_exten_visible" value="false">
                                                                    <input type="checkbox" id="directory_exten_visible" name="directory_exten_visible"
                                                                    @if ($extension->directory_exten_visible == "true") checked @endif
                                                                    data-switch="primary"/>
                                                                    <label for="directory_exten_visible" data-on-label="On" data-off-label="Off"></label>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

                                                        @if (userCheckPermission('extension_enabled'))
                                                        <div class="row">
                                                            <div class="col-4">
                                                                <div class="mb-3">
                                                                    <label  class="form-label">Enabled </label>
                                                                    <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus"
                                                                        data-bs-content="This prevents devices from registering using this extension">
                                                                        <i class="dripicons-information"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                            <div class="col-2">
                                                                <div class="mb-3 text-sm-end">
                                                                    <input type="hidden" name="enabled" value="false">
                                                                    <input type="checkbox" id="enabled-switch" name="enabled"
                                                                    @if ($extension->enabled == "true") checked @endif
                                                                    data-switch="primary"/>
                                                                    <label for="enabled-switch" data-on-label="On" data-off-label="Off"></label>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="description" class="form-label">Description</label>
                                                                    <input class="form-control" type="text" placeholder="" id="description" name="description"
                                                                        value="{{ $extension->description }}"/>
                                                                    @error('description')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                </div>

                                            </div> <!-- end row-->

                                        </div>
                                        <!-- Caller ID Content-->
                                        <div class="tab-pane fade" id="v-pills-callerid" role="tabpanel" aria-labelledby="v-pills-callerid-tab">
                                                <div class="row">
                                                    @if (userCheckPermission('outbound_caller_id_number'))
                                                    <div class="col-lg-12">
                                                        <h4 class="mt-2">External Caller ID</h4>

                                                        <p class="text-muted mb-3">Define the External Caller ID that will be displayed on the recipeint's device when dialing outside the company.</p>

                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Phone Number</label>
                                                                        <select data-toggle="select2" title="Outbound Caller ID" name="outbound_caller_id_number">
                                                                            <option value="">Main Company Number</option>
                                                                            @foreach ($destinations as $destination)
                                                                                <option value="1{{ $destination->destination_number }}"
                                                                                    @if (strpos(' '.$extension->outbound_caller_id_number,$destination->destination_number)) selected @endif>
                                                                                    {{ phone($destination->destination_number,"US",$national_phone_number_format) }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div> <!-- end row -->
                                                    </div>
                                                    @endif

                                                    @if (userCheckPermission('effective_caller_id_name') || userCheckPermission('effective_caller_id_number'))
                                                    <div class="col-lg-12">
                                                        <h4 class="mt-4">Internal Caller ID</h4>

                                                        <p class="text-muted mb-3">Define the Internal Caller ID that will be displayed on the recipeint's device when dialing inside the company.</p>

                                                        @if (userCheckPermission('effective_caller_id_name'))
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="callerid-first-name" class="form-label">First Name</label>
                                                                    <input class="form-control" type="text" placeholder="Enter first name" disabled 
                                                                        id="callerid-first-name" value="{{ $extension->directory_first_name }}" />
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="callerid-last-name" class="form-label">Last Name</label>
                                                                    <input class="form-control" type="text" placeholder="Enter last name" disabled 
                                                                        id="callerid-last-name" value="{{ $extension->directory_last_name }}" />
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

                                                        @if (userCheckPermission('effective_caller_id_number'))
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="effective_caller_id_number" class="form-label">Extension number</label>
                                                                    <input class="form-control" type="text" placeholder="xxxx"  disabled id="effective_caller_id_number" 
                                                                    name="effective_caller_id_number" value="{{ $extension->effective_caller_id_number }}"/>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif
                                                    </div>
                                                    @endif

                                                    <div class="col-lg-12">
                                                        <h4 class="mt-4">Emergency Caller ID</h4>

                                                        <p class="text-muted mb-3">Define the Emergency Caller ID that will be displayed when dialing emergency services.</p>

                                                            <div class="row">
                                                                <div class="col-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Phone Number</label>
                                                                        <select data-toggle="select2" title="Emergency Caller ID" name="emergency_caller_id_number">
                                                                            <option value="">Main Company Number</option>
                                                                            @foreach ($destinations as $destination)
                                                                                <option value="1{{ $destination->destination_number }}"
                                                                                    @if (strpos(' '.$extension->emergency_caller_id_number,$destination->destination_number)) selected @endif>
                                                                                    {{ phone($destination->destination_number,"US",$national_phone_number_format) }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div> <!-- end row -->

                                                    </div>

                                                </div> <!-- end row-->
                                            <!-- End Caller ID Content-->
                                        </div>
                                        @if (userCheckPermission('voicemail_option_edit'))
                                        <div class="tab-pane fade" id="v-pills-voicemail" role="tabpanel" aria-labelledby="v-pills-voicemail-tab">
                                            <!-- Voicemail Content-->
                                            <div class="tab-pane show active">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <h4 class="mt-2">Voicemail settings</h4>

                                                        <p class="text-muted mb-4">Voicemail settings allow you to update your voicemail access PIN, personalize, maintain and update your voicemail greeting to inform your friends, customers, or colleagues of your status.</p>

                                                        <input type="hidden" name="voicemail_id" value="{{ $extension->extension }}">

                                                        <div class="row">
                                                            <div class="col-4">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Voicemail enabled </label>
                                                                </div>
                                                            </div>
                                                            <div class="col-2">
                                                                <div class="mb-3 text-sm-end">
                                                                    <input type="hidden" name="voicemail_enabled" value="false">
                                                                    <input type="checkbox" id="voicemail_enabled" name="voicemail_enabled"
                                                                    @if ($extension->voicemail->voicemail_enabled == "true") checked @endif
                                                                    data-switch="primary"/>
                                                                    <label for="voicemail_enabled" data-on-label="On" data-off-label="Off"></label>
                                                                    @error('voicemail_enabled')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">If no answer, send to voicemail after</label>
                                                                    <select data-toggle="select2" title="If no answer, send to voicemail after" name="call_timeout">
                                                                        @for ($i = 1; $i < 21; $i++)
                                                                            <option value="{{ $i * 5 }}" @if ($extension->call_timeout == $i*5) selected @endif>
                                                                                {{ $i }} @if ($i >1 ) Rings @else Ring @endif - {{ $i * 5 }} Sec
                                                                            </option>
                                                                        @endfor
                                                                    </select>
                                                                @error('call_timeout')
                                                                    <div class="text-danger">{{ $message }}</div>
                                                                @enderror
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <div class="mb-3">
                                                                    <label for="voicemail_password" class="form-label">Set voicemail PIN <span class="text-danger">*</span></label>
                                                                    <div class="input-group input-group-merge">
                                                                        <input type="password" id="voicemail_password" class="form-control" placeholder="xxxx"
                                                                        value="{{ $extension->voicemail->voicemail_password }}" name="voicemail_password">
                                                                        <div class="input-group-text" data-password="false">
                                                                            <span class="password-eye"></span>
                                                                        </div>
                                                                    </div>
                                                                    @error('voicemail_password')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Notification type</label>
                                                                    <select data-toggle="select2" title="Notification Type" name="voicemail_file">
                                                                        <option value="attach" @if ($extension->voicemail->voicemail_file == "attach") selected @endif>
                                                                            Email with audio file attachment
                                                                        </option>
                                                                        <option value="link" @if ($extension->voicemail->voicemail_file == "link") selected @endif>
                                                                            Email with download link
                                                                        </option>
                                                                    </select>
                                                                    @error('voicemail_file')
                                                                        <div class="text-danger">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="vm-email-address" class="form-label">Email Address</span></label>
                                                                    <input class="form-control" type="email" disabled placeholder="Enter email" id="vm-email-address" 
                                                                    value="{{ $extension->voicemail->voicemail_mail_to }}"/>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        @if (userCheckPermission('voicemail_transcription_edit'))
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
                                                                    <input type="hidden" name="voicemail_transcription_enabled" value="false">
                                                                    <input type="checkbox" id="voicemail_transcription_enabled" data-switch="primary" name="voicemail_transcription_enabled"
                                                                    @if ($extension->voicemail->voicemail_transcription_enabled == "true") checked @endif />
                                                                    <label for="voicemail_transcription_enabled" data-on-label="On" data-off-label="Off"></label>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

                                                        @if (userCheckPermission('voicemail_local_after_email'))
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
                                                                    <input type="hidden" name="voicemail_local_after_email" value="false">
                                                                    <input type="checkbox" id="voicemail_local_after_email" data-switch="primary" name="voicemail_local_after_email"
                                                                    @if ($extension->voicemail->voicemail_local_after_email == "false") checked @endif />
                                                                    <label for="voicemail_local_after_email" data-on-label="On" data-off-label="Off"></label>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

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
                                                            <label for="voicemail_alternate_greet_id" class="form-label">Alternative greet ID</label>
                                                            <input class="form-control" type="text" placeholder="" id="voicemail_alternate_greet_id" name="voicemail_alternate_greet_id"/>
                                                            <span class="help-block"><small>An alternative greet id used in the default greeting.</small></span>
                                                            @error('voicemail_alternate_greet_id')
                                                                <div class="text-danger">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="voicemail_description" class="form-label">Description</label>
                                                            <input class="form-control" type="text" placeholder="" id="voicemail_description" name="voicemail_description"
                                                            value="{{ $extension->voicemail->voicemail_description }}"/>
                                                            @error('voicemail_description')
                                                                <div class="text-danger">{{ $message }}</div>
                                                            @enderror
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
                                                            <input type="hidden" name="voicemail_tutorial" value="false">
                                                            <input type="checkbox" id="voicemail_tutorial" data-switch="primary" name="voicemail_tutorial"
                                                            @if ($extension->voicemail->voicemail_tutorial == "true") checked @endif />
                                                            <label for="voicemail_tutorial" data-on-label="On" data-off-label="Off"></label>
                                                            @error('voicemail_tutorial')
                                                                <div class="text-danger">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->

                                                @if (userCheckPermission('voicemail_forward'))
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="mb-3">
                                                            <label for="additional-destinations-select" class="form-label">Forward voicemail messages to additional destinations.</label>
                                                            <!-- Multiple Select -->
                                                            <select class="select2 form-control select2-multiple" data-toggle="select2" multiple="multiple" data-placeholder="Choose ..."
                                                            id="additional-destinations-select" name="voicemail_destinations[]">
                                                                @foreach ($domain_voicemails as $domain_voicemail)
                                                                    <option value="{{ $domain_voicemail->voicemail_uuid }}"
                                                                        @if($extension->voicemail->forward_destinations()->contains($domain_voicemail))
                                                                            selected
                                                                        @endif>
                                                                        @if ($domain_voicemail->extension->directory_first_name || $domain_voicemail->extension->directory_last_name)
                                                                            @if ($domain_voicemail->extension->directory_first_name)
                                                                                {{ $domain_voicemail->extension->directory_first_name }} 
                                                                            @endif
                                                                            @if ($domain_voicemail->extension->directory_last_name)
                                                                                {{ $domain_voicemail->extension->directory_last_name }} 
                                                                            @endif
                                                                            (ext {{ $domain_voicemail->voicemail_id }})
                                                                        @elseif ($domain_voicemail->voicemail_description)
                                                                            {{ $domain_voicemail->voicemail_description }} (ext {{ $domain_voicemail->voicemail_id }}) 
                                                                        @else
                                                                            Voicemail (ext {{ $domain_voicemail->voicemail_id }})
                                                                        @endif
                                                                    </option>
                                                                @endforeach
                                                        </select>
                                                        @error('voicemail_destinations')
                                                            <div class="text-danger">{{ $message }}</div>
                                                        @enderror
                                                        </div>
                                                    </div>
                                                </div> <!-- end row -->
                                                @endif

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
                                        @endif
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
                    </form>

                </div> <!-- end card-body-->
            </div> <!-- end card-->
        </div> <!-- end col -->
    </div>
    <!-- end row-->

</div> <!-- container -->
@endsection