@extends('layouts.horizontal', ["page_title"=> "Edit Extension"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('extensions.index') }}">Extensions</a></li>
                        @if($extension->exists)
                            <li class="breadcrumb-item active">Edit Extension</li>
                        @else
                            <li class="breadcrumb-item active">Create Extension</li>
                        @endif
                    </ol>
                </div>
                @if($extension->exists)
                    <h4 class="page-title">Edit Extension ({{ $extension->extension }})</h4>
                @else
                    <h4 class="page-title">Create Extension</h4>
                @endif
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @php
                        if ($extension->exists) {
                            $actionUrl = route('extensions.update', $extension);
                        } else {
                            $actionUrl = route('extensions.store');
                        }
                    @endphp
                    <form method="POST" id="extensionForm" action="{{$actionUrl}}" class="form">
                        @if ($extension->exists)
                            @method('put')
                        @endif
                        @csrf
                    <div class="row">
                        <div class="col-sm-2 mb-2 mb-sm-0">
                            <div class="nav flex-column nav-pills" id="extensionNavPills" role="tablist" aria-orientation="vertical">
                                <a class="nav-link active show" id="v-pills-home-tab" data-bs-toggle="pill" href="#v-pills-home" role="tab" aria-controls="v-pills-home"
                                    aria-selected="true">
                                    <i class="mdi mdi-home-variant d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Basic Information
                                        <span class="float-end text-end
                                            directory_first_name_err_badge
                                            directory_last_name_err_badge
                                            extension_err_badge
                                            voicemail_mail_to_err_badge
                                            users_err_badge
                                            directory_visible_err_badge
                                            directory_exten_visible_err_badge
                                            enabled_err_badge
                                            description_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
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
                                        <span class="float-end text-end
                                            outbound_caller_id_number_err_badge
                                            emergency_caller_id_number_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                </a>

                                @if (userCheckPermission('voicemail_option_edit') && $extension->exists)
                                <a class="nav-link" id="v-pills-voicemail-tab" data-bs-toggle="pill" href="#v-pills-voicemail" role="tab" aria-controls="v-pills-voicemail"
                                    aria-selected="false">
                                    <i class="mdi mdi-account-circle d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Voicemail
                                        <span class="float-end text-end
                                            voicemail_enabled_err_badge
                                            voicemail_password_err_badge
                                            voicemail_transcription_enabled_err_badge
                                            voicemail_local_after_email_err_badge
                                            voicemail_description_err_badge
                                            voicemail_tutorial_err_badge
                                            voicemail_destinations_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                </a>
                                @endif

                                <a class="nav-link" id="v-pills-device-tab" data-bs-toggle="pill" href="#v-pills-device" role="tab" aria-controls="v-pills-device"
                                   aria-selected="false">
                                    <i class="mdi mdi-devices-circle d-md-none d-inline-block"></i>
                                    <span class="d-inline-block">Devices</span>
                                </a>

                                <a class="nav-link" id="v-pills-settings-tab" data-bs-toggle="pill" href="#v-pills-settings" role="tab" aria-controls="v-pills-settings"
                                   aria-selected="false">
                                    <i class="mdi mdi-settings-outline d-md-none d-block"></i>
                                    <span class="d-none d-md-block">Settings
                                        <span class="float-end text-end
                                            domain_uuid_err_badge
                                            user_context_err_badge
                                            max_registrations_err_badge
                                            limit_max_err_badge
                                            limit_destination_err_badge
                                            toll_allow_err_badge
                                            call_group_err_badge
                                            call_screen_enabled_err_badge
                                            user_record_err_badge
                                            auth_acl_err_badge
                                            sip_force_contact_err_badge
                                            sip_force_expires_err_badge
                                            mwi_account_err_badge
                                            sip_bypass_media_err_badge
                                            absolute_codec_string_err_badge
                                            force_ping_err_badge
                                            dial_string_err_badge
                                            hold_music_err_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                </a>

                            </div>
                        </div> <!-- end col-->

                            <div class="col-sm-10">

                                <div class="tab-content">
                                    <div class="text-sm-end" id="action-buttons">
                                        <a href="{{ route('extensions.index') }}" class="btn btn-light me-2">Cancel</a>
                                        <button class="btn btn-success" type="submit" id="submitFormButton"><i class="uil uil-down-arrow me-2"></i> Save </button>
                                        {{-- <button class="btn btn-success" type="submit">Save</button> --}}
                                    </div>
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
                                                                <div class="text-danger directory_first_name_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="directory_last_name" class="form-label">Last Name</label>
                                                                <input class="form-control" type="text" placeholder="Enter last name" id="directory_last_name"
                                                                    name="directory_last_name" value="{{ $extension->directory_last_name }}"/>
                                                                <div class="text-danger directory_last_name_err error_message"></div>
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
                                                                <div class="text-danger error-text extension_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @endif
                                                        @if (userCheckPermission('voicemail_edit'))
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="voicemail_mail_to" class="form-label">Email Address </label>
                                                                <input class="form-control" type="email" placeholder="Enter email" id="voicemail_mail_to"
                                                                    name="voicemail_mail_to" value="{{ $extension->voicemail->voicemail_mail_to ?? '' }}"/>
                                                                <div class="text-danger error-text voicemail_mail_to_err error_message"></div>
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
                                                                                @if(isset($extension_users) && $extension_users->contains($domain_user))
                                                                                    selected
                                                                                @endif>
                                                                                @if (isset($domain_user->user_adv_fields->first_name) || isset($domain_user->user_adv_fields->last_name))
                                                                                    @if ($domain_user->user_adv_fields->first_name)
                                                                                        {{ $domain_user->user_adv_fields->first_name }}
                                                                                    @endif
                                                                                    @if ($domain_user->user_adv_fields->last_name)
                                                                                        {{ $domain_user->user_adv_fields->last_name }}
                                                                                    @endif
                                                                                @elseif ($domain_user->description)
                                                                                    {{ $domain_user->description }}
                                                                                @else
                                                                                    {{ $domain_user->username }}
                                                                                @endif
                                                                            </option>
                                                                        @endforeach
                                                                </select>
                                                                <div class="text-danger users_err error_message"></div>
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
                                                    @else
                                                        <input type="hidden" name="directory_visible" value="true">
                                                        <input type="hidden" name="directory_exten_visible" value="true">
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
                                                    @else
                                                        <input type="hidden" name="enabled" value="{{ $extension->enabled }}">
                                                    @endif

                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="description" class="form-label">Description</label>
                                                                <input class="form-control" type="text" placeholder="" id="description" name="description"
                                                                    value="{{ $extension->description }}"/>
                                                                    <div class="text-danger description_err error_message"></div>
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
                                                                            <option value="{{ phone($destination->destination_number, "US")->formatE164() }}"
                                                                                @if (($extension->outbound_caller_id_number &&
                                                                                    phone($extension->outbound_caller_id_number, "US")->formatE164() == phone($destination->destination_number, "US")->formatE164()))
                                                                                    selected
                                                                                @endif>
                                                                                {{ phone($destination->destination_number,"US",$national_phone_number_format) }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                </div>
                                                @else
                                                    <input type="hidden" name="outbound_caller_id_number" value="">
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

                                                @if (userCheckPermission('emergency_caller_id_number'))
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
                                                                            <option value="{{ phone($destination->destination_number, "US")->formatE164() }}"
                                                                                @if ($extension->emergency_caller_id_number &&
                                                                                    (phone($extension->emergency_caller_id_number, "US")->formatE164() == phone($destination->destination_number, "US")->formatE164()))
                                                                                    selected
                                                                                @endif>
                                                                                {{ phone($destination->destination_number,"US",$national_phone_number_format) }}
                                                                            </option>
                                                                        @endforeach
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                </div>
                                                @else
                                                    <input type="hidden" name="emergency_caller_id_number" value="">
                                                @endif

                                            </div> <!-- end row-->
                                        <!-- End Caller ID Content-->
                                    </div>
                                    @if (userCheckPermission('voicemail_option_edit') && $extension->exists)
                                    <div class="tab-pane fade" id="v-pills-voicemail" role="tabpanel" aria-labelledby="v-pills-voicemail-tab">
                                        <!-- Voicemail Content-->
                                        <div class="tab-pane show active">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mt-2">Voicemail settings</h4>

                                                    <p class="text-muted mb-4">Voicemail settings allow you to update your voicemail access PIN, personalize, maintain and update your voicemail greeting to inform your friends, customers, or colleagues of your status.</p>

                                                    <input type="hidden" id="voicemail_id" name="voicemail_id"
                                                        data-uuid="{{ $extension->voicemail->voicemail_uuid ?? ''}}"
                                                        data-extensionuuid="{{ $extension->extension_uuid ?? ''}}"
                                                        value="{{ $extension->extension }}">

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
                                                                @if ($extension->voicemail->exists && $extension->voicemail->voicemail_enabled == "true") checked @endif
                                                                data-switch="primary"/>
                                                                <label for="voicemail_enabled" data-on-label="On" data-off-label="Off"></label>
                                                                <div class="text-danger voicemail_enabled_err error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    @if ($extension->voicemail->exists)

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
                                                            <div class="text-danger call_timeout_err error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->


                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label for="voicemail_password" class="form-label">Set voicemail PIN <span class="text-danger">*</span></label>
                                                                <div class="input-group input-group-merge">
                                                                    <input type="password" id="voicemail_password" class="form-control" placeholder="xxxx"
                                                                    value="{{ $extension->voicemail->voicemail_password ?? ''}}" name="voicemail_password">
                                                                    <div class="input-group-text" data-password="false">
                                                                        <span class="password-eye"></span>
                                                                    </div>
                                                                </div>
                                                                <div class="text-danger voicemail_password_err error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Notification type</label>
                                                                <select data-toggle="select2" title="Notification Type" name="voicemail_file">
                                                                    <option value="attach" @if (isset($extension->voicemail) && $extension->voicemail->voicemail_file == "attach") selected @endif>
                                                                        Email with audio file attachment
                                                                    </option>
                                                                    <option value="link" @if (isset($extension->voicemail) && $extension->voicemail->voicemail_file == "link") selected @endif>
                                                                        Email with download link
                                                                    </option>
                                                                </select>
                                                                <div class="text-danger voicemail_file_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="vm-email-address" class="form-label">Email Address</span></label>
                                                                <input class="form-control" type="email" disabled placeholder="Enter email" id="vm-email-address"
                                                                value="{{ $extension->voicemail->voicemail_mail_to ?? ''}}"/>
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
                                                                @if (isset($extension->voicemail) && $extension->voicemail->voicemail_local_after_email == "false") checked @endif />
                                                                <label for="voicemail_local_after_email" data-on-label="On" data-off-label="Off"></label>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    @endif


                                                    <div class="row mb-4">
                                                        <div class="col-lg-6">
                                                            <h4 class="mt-2">Unavailable greeting</h4>

                                                            <p class="text-muted mb-2">This plays when you do not pick up the phone.</p>
                                                            <p class="text-black-50 mb-1">Play the default, upload or record a new message.</p>

                                                            <audio id="voicemail_unavailable_audio_file"
                                                                @if ($vm_unavailable_file_exists)
                                                                src="{{ route('getVoicemailGreeting', ['voicemail' => $extension->voicemail->voicemail_uuid,'filename' => 'greeting_1.wav'] ) }}"
                                                                @endif >
                                                            </audio>
                                                            <p class="text-muted mb-1">File name: <span id='voicemailUnavailableFilename'>
                                                                <strong>
                                                                    @if ($vm_unavailable_file_exists) greeting_1.wav
                                                                    @else generic greeting
                                                                    @endif
                                                                </strong></span></p>
                                                            <button type="button" class="btn btn-light" id="voicemail_unavailable_play_button"
                                                                @if (!$vm_unavailable_file_exists) disabled @endif
                                                                title="Play"><i class="uil uil-play"></i>
                                                            </button>

                                                            <button type="button" class="btn btn-light" id="voicemail_unavailable_pause_button" title="Pause"><i class="uil uil-pause"></i> </button>

                                                            <button id="voicemail_unavailable_upload_file_button" data-url="{{ route("uploadVoicemailGreeting", $extension->voicemail->voicemail_uuid) }}" type="button" class="btn btn-light" title="Upload">
                                                                <span id="voicemail_unavailable_upload_file_button_icon" ><i class="uil uil-export"></i> </span>
                                                                <span id="voicemail_unavailable_upload_file_button_spinner" hidden class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            </button>
                                                            <input id="voicemail_unavailable_upload_file" type="file" hidden/>

                                                            <a href="{{ route('downloadVoicemailGreeting', [
                                                                'voicemail' => $extension->voicemail->voicemail_uuid,
                                                                'filename' => 'greeting_1.wav'
                                                                ] ) }}">
                                                                    <button id="voicemail_unavailable_download_file_button" type="button" class="btn btn-light" title="Download"
                                                                    @if (!$vm_unavailable_file_exists) disabled @endif>
                                                                    <i class="uil uil-down-arrow"></i>
                                                                </button>
                                                            </a>

                                                            <button id="voicemail_unavailable_delete_file_button" type="button" class="btn btn-light" title="Delete"
                                                                data-url="{{ route('deleteVoicemailGreeting', ['voicemail' => $extension->voicemail->voicemail_uuid,'filename' => 'greeting_1.wav'] ) }}"
                                                                @if (!$vm_unavailable_file_exists) disabled @endif>
                                                                <span id="voicemail_unavailable_delete_file_button_icon" ><i class="uil uil-trash-alt"></i> </span>
                                                                <span id="voicemail_unavailable_delete_file_button_spinner" hidden class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            </button>


                                                            <div class="text-danger" id="voicemailUnvaialableGreetingError"></div>

                                                        </div>

                                                        <div class="col-lg-6">
                                                            <h4 class="mt-2">Name greeting</h4>

                                                            <p class="text-muted mb-2">This plays to identify your extension in the company's dial by name directory.</p>
                                                            <p class="text-black-50 mb-1">Play the default, upload or record a new message.</p>
                                                            <audio id="voicemail_name_audio_file"
                                                                @if ($vm_name_file_exists)
                                                                src="{{ route('getVoicemailGreeting', ['voicemail' => $extension->voicemail->voicemail_uuid,'filename' => 'recorded_name.wav'] ) }}"
                                                                @endif >
                                                            </audio>
                                                            <p class="text-muted mb-1">File name: <span id='voicemailNameFilename'>
                                                                <strong>
                                                                    @if ($vm_name_file_exists) recorded_name.wav
                                                                    @else generic greeting
                                                                    @endif
                                                                </strong></span></p>
                                                            <button type="button" class="btn btn-light" id="voicemail_name_play_button"
                                                                @if (!$vm_name_file_exists) disabled @endif
                                                                title="Play"><i class="uil uil-play"></i>
                                                            </button>

                                                            <button type="button" class="btn btn-light" id="voicemail_name_pause_button" title="Pause"><i class="uil uil-pause"></i> </button>

                                                            <button id="voicemail_name_upload_file_button" data-url="{{ route("uploadVoicemailGreeting", $extension->voicemail->voicemail_uuid) }}" type="button" class="btn btn-light" title="Upload">
                                                                <span id="voicemail_name_upload_file_button_icon" ><i class="uil uil-export"></i> </span>
                                                                <span id="voicemail_name_upload_file_button_spinner" hidden class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            </button>
                                                            <input id="voicemail_name_upload_file" type="file" hidden data-url="{{ route("uploadVoicemailGreeting", $extension->voicemail->voicemail_uuid) }}"/>

                                                            <a href="{{ route('downloadVoicemailGreeting', [
                                                                'voicemail' => $extension->voicemail->voicemail_uuid,
                                                                'filename' => 'recorded_name.wav'
                                                                ] ) }}">
                                                                    <button id="voicemail_name_download_file_button" type="button" class="btn btn-light" title="Download"
                                                                    @if (!$vm_name_file_exists) disabled @endif>
                                                                    <i class="uil uil-down-arrow"></i>
                                                                </button>
                                                            </a>

                                                            <button id="voicemail_name_delete_file_button" type="button" class="btn btn-light" title="Delete"
                                                                data-url="{{ route('deleteVoicemailGreeting', ['voicemail' => $extension->voicemail->voicemail_uuid,'filename' => 'recorded_name.wav'] ) }}"
                                                                @if (!$vm_name_file_exists) disabled @endif>
                                                                <span id="voicemail_name_delete_file_button_icon" ><i class="uil uil-trash-alt"></i> </span>
                                                                <span id="voicemail_name_delete_file_button_spinner" hidden class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                                            </button>

                                                            <div class="text-danger" id="voicemailNameGreetingError"></div>

                                                        </div>

                                                    </div> <!-- end row-->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="voicemail_alternate_greet_id" class="form-label">Alternative greet ID</label>
                                                                <input class="form-control" type="text" placeholder="" id="voicemail_alternate_greet_id" name="voicemail_alternate_greet_id"/>
                                                                <span class="help-block"><small>An alternative greet id used in the default greeting.</small></span>
                                                                <div class="text-danger voicemail_alternate_greet_id_err error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="voicemail_description" class="form-label">Description</label>
                                                                <input class="form-control" type="text" placeholder="" id="voicemail_description" name="voicemail_description"
                                                                value="{{ $extension->voicemail->voicemail_description }}"/>
                                                                <div class="text-danger voicemail_description_err error_message"></div>
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
                                                                <div class="text-danger voicemail_tutorial_err error_message"></div>
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
                                                                            @if (isset($domain_voicemail->extension->directory_first_name) ||
                                                                                isset($domain_voicemail->extension->directory_last_name))
                                                                                    {{ $domain_voicemail->extension->directory_first_name ?? ""}}

                                                                                    {{ $domain_voicemail->extension->directory_last_name ?? ""}}
                                                                                (ext {{ $domain_voicemail->voicemail_id }})
                                                                            @elseif ($domain_voicemail->voicemail_description)
                                                                                {{ $domain_voicemail->voicemail_description }} (ext {{ $domain_voicemail->voicemail_id }})
                                                                            @else
                                                                                Voicemail (ext {{ $domain_voicemail->voicemail_id }})
                                                                            @endif
                                                                        </option>
                                                                    @endforeach
                                                            </select>
                                                            <div class="text-danger voicemail_destinations_err error_message"></div>
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
                                                    @endif
                                                </div> <!-- end row-->
                                            </div>
                                        </div>
                                        <!-- End Voicemail Content-->
                                    </div>
                                    @else
                                        <input type="hidden" name="call_timeout" value="25">
                                    @endif

                                    <div class="tab-pane fade" id="v-pills-settings" role="tabpanel" aria-labelledby="v-pills-settings-tab">
                                        <!-- Settings Content-->
                                        <div class="tab-pane show active">
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mt-2 mb-3">Settings</h4>

                                                    <div class="row">
                                                        @if (userCheckPermission('extension_domain'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Domain</label>
                                                                <select data-toggle="select2" title="Domain" id="domain_uuid" name="domain_uuid">
                                                                    @foreach (Session::get("domains") as $domain))
                                                                    <option value="{{ $domain->domain_uuid }}"
                                                                        @if($domain->domain_uuid == $extension->domain_uuid)
                                                                        selected
                                                                        @endif>
                                                                        {{ $domain->domain_name }}
                                                                    </option>
                                                                    @endforeach
                                                                </select>
                                                                <div class="text-danger domain_uuid_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @else
                                                            <input type="hidden" name="domain_uuid" value="{{ Session::get('domain_uuid') }}">
                                                        @endif

                                                        @if (userCheckPermission('extension_user_context'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="context" class="form-label">Context <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="text" placeholder="" id="user_context"
                                                                    name="user_context" value="{{ $extension->user_context}}"/>
                                                                <div class="text-danger user_context_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @else
                                                            <input type="hidden" name="user_context" value="{{ Session::get('domain_name') }}">
                                                        @endif
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        @if (userCheckPermission('number_alias'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="number-alias" class="form-label">Number Alias</label>
                                                                <input class="form-control" type="text" placeholder="" id="number_alias"
                                                                name="number_alias" value="{{ $extension->number_alias}}"/>
                                                                <span class="help-block"><small>If the extension is numeric then number alias is optional.</small></span>
                                                                <div class="text-danger number_alias_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @endif

                                                        @if (userCheckPermission('extension_accountcode'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="accountcode" class="form-label">Account Code</label>
                                                                <input class="form-control" type="text" placeholder="" id="accountcode"
                                                                    name="accountcode" value="{{ $extension->accountcode}}"/>
                                                                <span class="help-block"><small>Enter the account code here.</small></span>
                                                                <div class="text-danger accountcode_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @else
                                                            <input type="hidden" name="accountcode" value="{{ Session::get('domain_name') }}">
                                                        @endif
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        @if (userCheckPermission('extension_max_registrations'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="max_registrations" class="form-label">Total allowed registrations</label>
                                                                <input class="form-control" type="text" placeholder="" id="max_registrations"
                                                                    name="max_registrations"  value="{{ $extension->max_registrations}}"/>
                                                                <span class="help-block"><small>Enter the maximum registration allowed for this user</small></span>
                                                                <div class="text-danger error-text max_registrations_err error_message"></div>
                                                            </div>

                                                        </div>
                                                        @endif

                                                        @if (userCheckPermission('extension_toll'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="toll_allow" class="form-label">Toll Allow</label>
                                                                <input class="form-control" type="text" placeholder="" id="toll_allow"
                                                                    name="toll_allow" value="{{ $extension->toll_allow}}"/>
                                                                <span class="help-block"><small>Enter the toll allow value here. (Examples: domestic,international,local)</small></span>
                                                                <div class="text-danger toll_allow_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div> <!-- end row -->

                                                    @if (userCheckPermission('extension_limit'))
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="limit_destination" class="form-label">Limit Destination</label>
                                                                <input class="form-control" type="text" placeholder="" id="limit_destination"
                                                                    name="limit_destination" value="{{ $extension->limit_destination}}"/>
                                                                <span class="help-block"><small>Enter the destination to send the calls when the max number of outgoing calls has been reached.</small></span>
                                                                <div class="text-danger limit_destination_err error_message"></div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="limit_max" class="form-label">Total allowed outbound calls</label>
                                                                <input class="form-control" type="text" placeholder="" id="limit_max"
                                                                    name="limit_max" value="{{ $extension->limit_max}}"/>
                                                                <span class="help-block"><small>Enter the max number of outgoing calls for this user.</small></span>
                                                                <div class="text-danger limit_max_err error_message"></div>
                                                            </div>
                                                        </div>

                                                    </div> <!-- end row -->
                                                    @else
                                                        <input type="hidden" name="limit_destination" value="!USER_BUSY">
                                                        <input type="hidden" name="limit_max" value="5">
                                                    @endif


                                                    <div class="row">
                                                        @if (userCheckPermission('extension_call_group'))
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label for="call_group" class="form-label">Call Group</label>
                                                                <input class="form-control" type="text" placeholder="" id="call_group"
                                                                    name="call_group" value="{{ $extension->call_group}}"/>
                                                                <span class="help-block"><small>Enter the user call group here. Groups available by default: sales, support, billing.</small></span>
                                                                <div class="text-danger call_group_err error_message"></div>
                                                            </div>
                                                        </div>
                                                        @endif
                                                    </div> <!-- end row -->

                                                    @if (userCheckPermission('extension_call_screen'))
                                                    <div class="row">
                                                        <div class="col-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Enable call screening</label>
                                                                <a href="#"  data-bs-toggle="popover" data-bs-placement="top" data-bs-trigger="focus"
                                                                    data-bs-content="You can use Call Screen to find out who’s calling and why before you pick up a call. ">
                                                                    <i class="dripicons-information"></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                        <div class="col-2">
                                                            <div class="mb-3 text-sm-end">
                                                                <input type="hidden" name="call_screen_enabled" value="false">
                                                                <input type="checkbox" id="call_screen_enabled" name="call_screen_enabled"
                                                                @if ($extension->call_screen_enabled == "true") checked @endif
                                                                data-switch="primary"/>
                                                                <label for="call_screen_enabled" data-on-label="On" data-off-label="Off"></label>
                                                                <div class="text-danger call_screen_enabled_err error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    @endif

                                                    @if (userCheckPermission('extension_user_record'))
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Call recording</label>
                                                                <select data-toggle="select2" title="Call recording" name="user_record">
                                                                    <option value="">Disabled</option>
                                                                    <option value="all"
                                                                        @if ($extension->user_record == 'all')
                                                                        selected
                                                                        @endif>
                                                                        All
                                                                    </option>
                                                                    <option value="local"
                                                                        @if ($extension->user_record == 'local')
                                                                        selected
                                                                        @endif>
                                                                        Local
                                                                    </option>
                                                                    <option value="inbound"
                                                                        @if ($extension->user_record == 'inbound')
                                                                        selected
                                                                        @endif>
                                                                        Inbound
                                                                    </option>
                                                                    <option value="outbound"
                                                                        @if ($extension->user_record == 'outbound')
                                                                        selected
                                                                        @endif>
                                                                        Outbound
                                                                    </option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    @endif

                                                    @if (userCheckPermission('extension_hold_music'))
                                                    <div class="row">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Select custom music on hold</label>
                                                                <select data-toggle="select2" title="Select custom music on hold" name="hold_music">
                                                                    <option value="">Not selected</option>
                                                                    @if (!$moh->isEmpty())
                                                                    <optgroup label="Music on Hold">
                                                                        @foreach ($moh as $music)
                                                                        <option value="local_stream://{{ $music->music_on_hold_name }}"
                                                                            @if("local_stream://" . $music->music_on_hold_name == $extension->hold_music)
                                                                            selected
                                                                            @endif>
                                                                            {{ $music->music_on_hold_name }}
                                                                        </option>
                                                                        @endforeach
                                                                    </optgroup>
                                                                    @endif

                                                                    @if (!$recordings->isEmpty())
                                                                    <optgroup label="Recordings">
                                                                        @foreach ($recordings as $recording)
                                                                        <option value="{{ getDefaultSetting('switch','recordings'). "/" . Session::get('domain_name') . "/" . $recording->recording_filename }}"
                                                                            @if(getDefaultSetting('switch','recordings'). "/" . Session::get('domain_name') . "/" . $recording->recording_filename == $extension->hold_music)
                                                                            selected
                                                                            @endif>
                                                                            {{ $recording->recording_name }}
                                                                        </option>
                                                                        @endforeach
                                                                    </optgroup>
                                                                    @endif
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                                    @endif

                                                    @if (userCheckPermission('extension_advanced'))
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="auth_acl" class="form-label">Auth ACL</label>
                                                                    <input class="form-control" type="text" placeholder="" id="auth_acl"
                                                                        name="auth_acl" value="{{ $extension->auth_acl}}"/>
                                                                    <span class="help-block"><small>Enter the Auth ACL here.</small></span>
                                                                    <div class="text-danger auth_acl_err error_message"></div>
                                                                </div>
                                                            </div>
                                                            @if (userCheckPermission('extension_cidr'))
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="cidr" class="form-label">CIDR</label>
                                                                    <input class="form-control" type="text" placeholder="" id="cidr"
                                                                        name="cidr" value="{{ $extension->cidr}}"/>
                                                                    <span class="help-block"><small>Enter allowed address/ranges in CIDR notation (comma separated).</small></span>
                                                                    <div class="text-danger cidr_err error_message"></div>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        </div> <!-- end row -->

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">SIP Force Contact</label>
                                                                    <select data-toggle="select2" title="SIP Force Contact" name="sip_force_contact">
                                                                        <option value="">Disabled</option>
                                                                        <option value="NDLB-connectile-dysfunction"
                                                                            @if ($extension->sip_force_contact == 'NDLB-connectile-dysfunction')
                                                                            selected
                                                                            @endif>
                                                                            Rewrite Contact IP and Port
                                                                        </option>
                                                                        <option value="NDLB-connectile-dysfunction-2.0"
                                                                            @if ($extension->sip_force_contact == 'NDLB-connectile-dysfunction-2.0')
                                                                            selected
                                                                            @endif>
                                                                            Rewrite Contact IP and Port 2.0
                                                                        </option>
                                                                        <option value="NDLB-tls-connectile-dysfunction"
                                                                            @if ($extension->sip_force_contact == 'NDLB-tls-connectile-dysfunction')
                                                                            selected
                                                                            @endif>
                                                                            Rewrite TLS Contact Port
                                                                        </option>
                                                                    </select>
                                                                    <div class="text-danger sip_force_contact_err error_message"></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="sip_force_expires" class="form-label">SIP Force Expires</label>
                                                                    <input class="form-control" type="text" placeholder="" id="sip_force_expires"
                                                                        name="sip_force_expires" value="{{ $extension->sip_force_expires}}"/>
                                                                    <span class="help-block"><small>To prevent stale registrations SIP Force expires can override the client expire.</small></span>
                                                                    <div class="text-danger sip_force_expires_err error_message"></div>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="mwi_account" class="form-label">Monitor MWI Account</label>
                                                                    <input class="form-control" type="text" placeholder="" id="mwi_account"
                                                                        name="mwi_account" value="{{ $extension->mwi_account}}"/>
                                                                    <span class="help-block"><small>MWI Account with user@domain of the voicemail to monitor.</small></span>
                                                                    <div class="text-danger mwi_account_err error_message"></div>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label class="form-label">SIP Bypass Media</label>
                                                                    <select data-toggle="select2" title="SIP Bypass Media" name="sip_bypass_media">
                                                                        <option value="">Disabled</option>
                                                                        <option value="bypass-media"
                                                                            @if ($extension->sip_bypass_media == 'bypass-media')
                                                                            selected
                                                                            @endif>
                                                                            Bypass Media
                                                                        </option>
                                                                        <option value="bypass-media-after-bridge"
                                                                            @if ($extension->sip_bypass_media == 'bypass-media-after-bridge')
                                                                            selected
                                                                            @endif>
                                                                            Bypass Media After Bridge
                                                                        </option>
                                                                        <option value="proxy-media"
                                                                            @if ($extension->sip_bypass_media == 'proxy-media')
                                                                            selected
                                                                            @endif>
                                                                            Proxy Media
                                                                        </option>
                                                                    </select>
                                                                    <div class="text-danger sip_bypass_media_err error_message"></div>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->

                                                        @if (userCheckPermission('extension_absolute_codec_string'))
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="absolute_codec_string" class="form-label">Absolute Codec String</label>
                                                                    <input class="form-control" type="text" placeholder="" id="absolute_codec_string"
                                                                        name="absolute_codec_string" value="{{ $extension->absolute_codec_string}}"/>
                                                                    <span class="help-block"><small>Absolute Codec String for the extension</small></span>
                                                                    <div class="text-danger absolute_codec_string_err error_message"></div>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

                                                        @if (userCheckPermission('extension_force_ping'))
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
                                                                    <input type="hidden" name="force_ping" value="false">
                                                                    <input type="checkbox" id="force_ping" name="force_ping"
                                                                    @if ($extension->force_ping == "true") checked @endif
                                                                    data-switch="primary"/>
                                                                    <label for="force_ping" data-on-label="On" data-off-label="Off"></label>
                                                                    <div class="text-danger force_ping_err error_message"></div>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif

                                                        @if (userCheckPermission('extension_dial_string'))
                                                        <div class="row">
                                                            <div class="col-6">
                                                                <div class="mb-3">
                                                                    <label for="dial_string" class="form-label">Dial String</label>
                                                                    <input class="form-control" type="text" placeholder="" id="dial_string"
                                                                        name="dial_string" value="{{ $extension->dial_string}}"/>
                                                                    <span class="help-block"><small>Location of the endpoint.</small></span>
                                                                    <div class="text-danger dial_string_err error_message"></div>
                                                                </div>
                                                            </div>
                                                        </div> <!-- end row -->
                                                        @endif
                                                    @endif

                                                </div>

                                            </div> <!-- end row-->

                                        </div>
                                        <!-- End Settings Content-->
                                    </div>

                                    @if ($extension->exists)
                                        <div class="tab-pane fade" id="v-pills-device" role="tabpanel" aria-labelledby="v-pills-device-tab">
                                            <!-- Voicemail Content-->
                                            <div class="row">
                                                <div class="col-lg-12">
                                                    <h4 class="mt-2">Attached Devices</h4>
                                                    <div class="card" id="extensionForm" action="" >
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-3">
                                                                    <div class="form-group">
                                                                        <label class="col-form-label">Line Number</label>
                                                                        <input type="text" name="line_number" id="line_number" class="form-control" />
                                                                        <div class="error text-danger"></div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group">
                                                                        <label class="col-form-label">Select Device</label>
                                                                        <div class="input-group mb-3">
                                                                            <select name="device_uuid" class="form-select" id="device-select">
                                                                                @foreach($devices as $device)
                                                                                    <option value="{{$device->device_uuid}}">{{$device->device_mac_address}}</option>
                                                                                @endforeach
                                                                            </select>
                                                                            <button class="btn btn-primary" type="button" id="add-new-device" data-bs-toggle="modal" data-bs-target="#createDeviceModal">Create</button>
                                                                        </div>
                                                                        <div class="error text-danger" id="device_uuid_error"></div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-3">
                                                                    <div class="form-group mt-4">
                                                                        <button class="btn btn-info assign-device-btn" type="button"> Assign</button>
                                                                        <div class="error text-danger"></div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <table class="table table-bordered">
                                                        <tr>
                                                            <td>Line</td>
                                                            <td>MAC Address</td>
                                                            <td>Template</td>
                                                            <td>Actions</td>
                                                        </tr>
                                                        @foreach($extension->devices as $device)
                                                            <tr>
                                                                <td>{{$device->pivot->line_number}}</td>
                                                                <td>{{$device->device_mac_address}}</td>
                                                                <td>{{$device->device_template}}</td>
                                                                <td>
                                                                    <div id="tooltip-container-actions">
                                                                        <a class="action-icon" data-bs-toggle="modal" data-bs-target="#deleteModal" data-href="{{route('extensions.unassign-device', [$extension->extension_uuid, $device->pivot->device_line_uuid ])}}">
                                                                            <i class="mdi mdi-delete" data-bs-container="#tooltip-container-actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Delete"></i>
                                                                        </a>
                                                                    </div>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                </div> <!-- end row-->
                                            </div>
                                        </div>
                                    @endif
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

{{-- Modal --}}
<div class="modal " id="loader" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            {{-- <div class="modal-header">
                <h4 class="modal-title" id="myCenterModalLabel">Center modal</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
            </div> --}}
            <div class="modal-body text-center">
                <div class="spinner-grow text-secondary" role="status"></div>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="text-center">
                    <i class="uil uil-times-circle h1 text-danger"></i>
                    <h3 class="mt-3">Are you sure?</h3>
                    <p class="mt-3">Do you really want to delete this? This process cannot be undone.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary confirm-delete-btn" data-href="">Delete</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="createDeviceModal" tabindex="-1" aria-labelledby="createDeviceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createDeviceModalLabel">Create New Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{route('devices.store')}}">
                    @csrf
                    <div class="mb-3">
                        <label class="col-form-label">Mac Address</label>
                        <input type="text" class="form-control" id="device_mac_address" name="device_mac_address" placeholder="Enter the MAC address">
                        <div class="error text-danger" id="device_mac_address_error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="col-form-label">Label</label>
                        <input type="text" class="form-control" id="device_label" name="device_label" placeholder="Enter the Device Label">
                        <div class="error text-danger" id="device_label_error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="col-form-label">Vendor</label>
                        <input type="text" class="form-control" id="device_vendor" name="device_vendor" placeholder="Enter the Device Vendor">
                        <div class="error text-danger" id="device_vendor_error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="col-form-label">Template</label>
                        <input type="text" class="form-control" id="device_template" name="device_template" placeholder="Enter the Device Template">
                        @php $templateDir = public_path('resources/templates/provision'); @endphp
                        <select name="device_uuid" class="form-select" id="device-select">
                            @foreach($vendors as $vendor)
                                <optgroup label='{{$vendor->name}}'>
                                    @if (is_dir($templateDir.'/'.$vendor->name)) {
                                        @php $templates = scandir($templateDir.'/'.$vendor->name); @endphp
                                        @foreach($templates as $dir) {
                                            @if ($dir != "." && $dir != ".." && $dir[0] != '.' && is_dir($templateDir.'/'.$vendor->name.'/'.$dir))
                                                <option value='{{$vendor->name."/".$dir}}'>{{$vendor->name."/".$dir}}</option>
                                            @endif
                                        @endforeach
                                    @endif
                                </optgroup>
                            @endforeach
                        </select>
                        <div class="error text-danger" id="device_template_error"></div>
                    </div>
                    <div class="mb-3">
                        <label class="col-form-label">Description</label>
                        <textarea class="form-control" id="device_description" name="device_description" placeholder="Enter the Description"></textarea>
                        <div class="error text-danger" id="device_description_error"></div>
                    </div>
                    <div class="mb-3">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary save-device-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection


@push('scripts')
    <style>
        .input-group > .select2-container {
            width: auto !important;
            flex: 1 1 auto;
        }
    </style>
<script>
    $(document).ready(function() {

        $('a[data-bs-toggle="pill"]').on('show.bs.tab', function(e) {
            localStorage.setItem('activeTab', $(e.target).attr('href'));
        });

        var activeTab = localStorage.getItem('activeTab');
        if(activeTab){
            $('#extensionNavPills a[href="' + activeTab + '"]').tab('show');
        }

        $('#submitFormButton').on('click', function(e) {
            e.preventDefault();
            $('.loading').show();

            //Reset error messages
            $('.error_message').text("");

            var url = $('#extensionForm').attr('action');

            $.ajax({
                type : "POST",
                url : url,
                cache: false,
                data : $('#extensionForm').serialize(),
            })
            .done(function(response) {
                // console.log(response);
                // $('.loading').hide();

                if (response.error){
                    $('.loading').hide();
                    printErrorMsg(response.error);

                } else {
                    $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                    if(response.redirect_url){
                        window.location=response.redirect_url;
                    } else {
                        $('.loading').hide();
                    }

                }
            })
            .fail(function (jqXHR, testStatus, error) {
                // console.log(error);
                $('.loading').hide();
                printErrorMsg(error);
            });
        });

        if($('#extensionNavPills #v-pills-device-tab').hasClass('active')) {
            $('#action-buttons').hide();
        }

        $('#extensionNavPills .nav-link').on('click', function(e) {
            e.preventDefault();
            if($(this).attr('id') == 'v-pills-device-tab') {
                $('#action-buttons').hide();
            } else {
                $('#action-buttons').show();
            }
        });

        $(document).on('click', '.save-device-btn', function(e){
            e.preventDefault();

            var btn = $(this);
            var form = btn.closest('form');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                beforeSend: function() {
                    //Reset error messages
                    form.find('.error').text('');

                    $('.error_message').text("");
                    $('.btn').attr('disabled', true);
                    $('.loading').show();
                },
                complete: function (xhr,status) {
                    $('.btn').attr('disabled', false);
                    $('.loading').hide();
                },
                success: function(result) {
                    form[0].reset();
                    $('#createDeviceModal').modal('hide');
                    $('#device-select').append(
                        $('<option></option>').val(result.device.device_uuid).html(result.device.device_mac_address)
                    );
                    $.NotificationApp.send("Success",result.message,"top-right","#10c469","success");
                },
                error: function(error) {
                    if(error.status == 422){
                        if(error.responseJSON.errors) {
                            $.each( error.responseJSON.errors, function( key, value ) {
                                if (value != '') {
                                    form.find('#'+key+'_error').text(value);
                                    printErrorMsg(value);
                                }
                            });
                        } else {
                            printErrorMsg(error.responseJSON.message);
                        }
                    } else {
                        printErrorMsg(error.responseJSON.message);
                    }
                }
            });
        });

        $('#device-select').select2({
            sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
        });

        $(document).on('click', '.assign-device-btn', function(e){
            e.preventDefault();

            var btn = $(this);
            var data = {
                'line_number' : btn.closest('.card').find('#line_number').val(),
                'device_uuid' : btn.closest('.card').find('#device-select').val(),
                '_token' : $('meta[name="csrf-token"]').attr('content')
            }

            $.ajax({
                url: "{{route('extensions.assign-device', [$extension->extension_uuid])}}",
                type: 'POST',
                data: data,
                dataType: 'json',
                beforeSend: function() {
                    //Reset error messages
                    btn.closest('.card').find('.error').text('');

                    $('.error_message').text("");
                    $('.btn').attr('disabled', true);
                    $('.loading').show();
                },
                complete: function (xhr,status) {
                    $('.btn').attr('disabled', false);
                    $('.loading').hide();
                },
                success: function(result) {
                    if(result.status == 'success') {
                        $.NotificationApp.send("Success",result.message,"top-right","#10c469","success");
                        location.reload();
                    } else {
                        $.NotificationApp.send("Warning",result.message,"top-right","#ebb42a","error");
                    }
                },
                error: function(error) {
                    if(error.status == 422){
                        if(error.responseJSON.errors) {
                            $.each( error.responseJSON.errors, function( key, value ) {
                                if (value != '') {
                                    btn.closest('.card').find('#'+key+'_error').text(value);
                                    printErrorMsg(value);
                                }
                            });
                        } else {
                            printErrorMsg(error.responseJSON.message);
                        }
                    } else {
                        printErrorMsg(error.responseJSON.message);
                    }
                }
            });
        })

        $('#deleteModal').on('shown.bs.modal', function (event) {
            var btn = $(event.relatedTarget)
            var modal = $(this);
            let action = btn.data('href');
            let table = btn.data('table');
            modal.find('.modal-body input').val(action)
            modal.find('.confirm-delete-btn').attr('data-href', action);
            modal.find('.confirm-delete-btn').attr('data-href', action);
        });

        $(document).off('click', '.confirm-delete-btn').on('click', '.confirm-delete-btn', function () {
            var btn = $(this);

            var token = $("meta[name='csrf-token']").attr("content");
            $.ajax({
                url: btn.attr('data-href'),
                type: 'DELETE',
                dataType: 'json',
                data:{'_token' : token},
                beforeSend: function() {
                    $('.btn').attr('disabled', true);
                },
                complete: function (xhr,status) {
                    $('.btn').attr('disabled', false);
                },
                success: function(result) {
                    $.NotificationApp.send("Success",result.message,"top-right","#10c469","success");
                    $('#deleteModal').modal('hide');
                    location.reload();
                },
                error(error) {
                    printErrorMsg(error.responseJSON.message);
                }
            });
        });

        //Extension Page
        // Copy email to voicmemail_email
        $('#voicemail-email-address').change(function() {
            $('#vm-email-address').val($(this).val());
        });

        //Extension Page
        // Copy first name to caller ID first name
        $('#directory_first_name').change(function() {
            $('#callerid-first-name').val($(this).val());
        });

        //Extension Page
        // Copy last name to caller ID last name
        $('#directory_last_name').change(function() {
            $('#callerid-last-name').val($(this).val());
        });

        //Extension Page
        // Copy extension to caller ID extension
        $('#extension').change(function() {
            $('#effective_caller_id_number').val($(this).val());
        });

        // Extension Page
        // Sort Select2 for users
        $('#users-select').select2({
            sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
        });

        // Extension Page
        // Sort Select2 for voicemail destinations
        $('#additional-destinations-select').select2({
            sorter: data => data.sort((a, b) => a.text.localeCompare(b.text)),
        });


        // Upload voicemail unavailable file
        $('#voicemail_unavailable_upload_file_button').on('click', function() {
            $('#voicemail_unavailable_upload_file').trigger('click');
        });

        $('#voicemail_unavailable_upload_file').on('change', function(e) {
            e.preventDefault();

            var formData = new FormData();
            formData.append('voicemail_unavailable_upload_file', $(this)[0].files[0]);
            formData.append('greeting_type', 'unavailable');

            // Add spinner
            $("#voicemail_unavailable_upload_file_button_icon").hide();
            $("#voicemail_unavailable_upload_file_button_spinner").attr("hidden", false);

            var url = $('#voicemail_unavailable_upload_file_button').data('url');

            $.ajax({
                type : "POST",
                url : url,
                data : formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#voicemail_unavailable_upload_file_button_icon").show();
                $("#voicemail_unavailable_upload_file_button_spinner").attr("hidden", true);

                //Enable play button
                $("#voicemail_unavailable_play_button").attr("disabled", false);
                //Enable download button
                $("#voicemail_unavailable_download_file_button").attr("disabled", false);
                //Enable delete button
                $("#voicemail_unavailable_delete_file_button").attr("disabled", false);

                @if($extension->exists && $extension->voicemail->exists)
                //Update audio file
                $("#voicemail_unavailable_audio_file").attr("src",
                    "{{ route('getVoicemailGreeting', ['voicemail' => $extension->voicemail->voicemail_uuid,'filename' => 'greeting_1.wav'] ) }}"
                );
                @endif

                $("#voicemail_unavailable_audio_file")[0].pause();
                $("#voicemail_unavailable_audio_file")[0].load();

                $("#voicemailUnavailableFilename").html('<strong>' + response.filename + '</strong>');

                if (response.error){
                    $.NotificationApp.send("Warning","There was a error uploading this greeting","top-right","#ff5b5b","error")
                    $("#voicemailUnvaialableGreetingError").text(response.message);
                } else {
                    $.NotificationApp.send("Success","The greeeting has been uploaded successfully","top-right","#10c469","success")
                }
            })
            .fail(function (response){
                //
            });
        });


        // Upload voicemail name file
        $('#voicemail_name_upload_file_button').on('click', function() {
            $('#voicemail_name_upload_file').trigger('click');
        });

        $('#voicemail_name_upload_file').on('change', function(e) {
            e.preventDefault();

            var formData = new FormData();
            formData.append('voicemail_name_upload_file', $(this)[0].files[0]);
            formData.append('greeting_type', 'name');

            // Add spinner
            $("#voicemail_name_upload_file_button_icon").hide();
            $("#voicemail_name_upload_file_button_spinner").attr("hidden", false);

            var url = $('#voicemail_name_upload_file').data('url');

            $.ajax({
                type : "POST",
                url : url,
                data : formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#voicemail_name_upload_file_button_icon").show();
                $("#voicemail_name_upload_file_button_spinner").attr("hidden", true);

                //Enable play button
                $("#voicemail_name_play_button").attr("disabled", false);
                //Enable download button
                $("#voicemail_name_download_file_button").attr("disabled", false);
                //Enable delete button
                $("#voicemail_name_delete_file_button").attr("disabled", false);

                @if($extension->exists && $extension->voicemail->exists)
                //Update audio file
                $("#voicemail_name_audio_file").attr("src",
                    "{{ route('getVoicemailGreeting', ['voicemail' => $extension->voicemail->voicemail_uuid,'filename' => 'recorded_name.wav'] ) }}"
                );
                @endif

                $("#voicemail_name_audio_file")[0].pause();
                $("#voicemail_name_audio_file")[0].load();

                $("#voicemailNameFilename").html('<strong>' + response.filename + '</strong>');

                if (response.error){
                    $.NotificationApp.send("Warning","There was a error uploading this greeting","top-right","#ff5b5b","error")
                    $("#voicemailNameGreetingError").text(response.message);
                } else {
                    $.NotificationApp.send("Success","The greeeting has been uploaded successfully","top-right","#10c469","success")
                }
            })
            .fail(function (response){
                //
            });
        });


        // Delete unavailable voicemail file
        $('#voicemail_unavailable_delete_file_button').on('click', function(e) {
            e.preventDefault();

            var url = $('#voicemail_unavailable_delete_file_button').data('url');

            // Add spinner
            $("#voicemail_unavailable_delete_file_button_icon").hide();
            $("#voicemail_unavailable_delete_file_button_spinner").attr("hidden", false);

            $.ajax({
                type : "GET",
                url : url,
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#voicemail_unavailable_delete_file_button_icon").show();
                $("#voicemail_unavailable_delete_file_button_spinner").attr("hidden", true);

                //Disable play button
                $("#voicemail_unavailable_play_button").attr("disabled", true);
                //Disable download button
                $("#voicemail_unavailable_download_file_button").attr("disabled", true);
                //Disable delete button
                $("#voicemail_unavailable_delete_file_button").attr("disabled", true);

                $("#voicemailUnavailableFilename").html('<strong>generic greeeting</strong>');

                if (response.error){
                    $.NotificationApp.send("Warning","There was a error deleting this greeting","top-right","#ff5b5b","error")
                    $("#voicemailGreetingError").text(response.message);
                } else {
                    $.NotificationApp.send("Success","The greeeting has been deleted successfully","top-right","#10c469","success")
                }
            })
            .fail(function (response){
                //
            });
        });

        // Delete name voicemail file
        $('#voicemail_name_delete_file_button').on('click', function(e) {
            e.preventDefault();

            var url = $('#voicemail_name_delete_file_button').data('url');

            // Add spinner
            $("#voicemail_name_delete_file_button_icon").hide();
            $("#voicemail_name_delete_file_button_spinner").attr("hidden", false);

            $.ajax({
                type : "GET",
                url : url,
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#voicemail_name_delete_file_button_icon").show();
                $("#voicemail_name_delete_file_button_spinner").attr("hidden", true);

                //Disable play button
                $("#voicemail_name_play_button").attr("disabled", true);
                //Disable download button
                $("#voicemail_name_download_file_button").attr("disabled", true);
                //Disable delete button
                $("#voicemail_name_delete_file_button").attr("disabled", true);

                $("#voicemailNameFilename").html('<strong>generic greeeting</strong>');

                if (response.error){
                    $.NotificationApp.send("Warning","There was a error deleting this greeting","top-right","#ff5b5b","error")
                    $("#voicemailGreetingError").text(response.message);
                } else {
                    $.NotificationApp.send("Success","The greeeting has been deleted successfully","top-right","#10c469","success")
                }
            })
            .fail(function (response){
                //
            });
        });

        // hide pause button
        $('#voicemail_unavailable_pause_button').hide();
        $('#voicemail_name_pause_button').hide();

        // Play unavailable audio file
        $('#voicemail_unavailable_play_button').click(function(){
            var audioElement = document.getElementById('voicemail_unavailable_audio_file');
            $(this).hide();
            $('#voicemail_unavailable_pause_button').show();
            audioElement.play();
            audioElement.addEventListener('ended', function() {
                $('#voicemail_unavailable_pause_button').hide();
                $('#voicemail_unavailable_play_button').show();
            });
        });

         // Pause unavailable audio file
         $('#voicemail_unavailable_pause_button').click(function(){
            var audioElement = document.getElementById('voicemail_unavailable_audio_file');
            $(this).hide();
            $('#voicemail_unavailable_play_button').show();
            audioElement.pause();
        });

        // Play name audio file
        $('#voicemail_name_play_button').click(function(){
            var audioElement = document.getElementById('voicemail_name_audio_file');
            $(this).hide();
            $('#voicemail_name_pause_button').show();
            audioElement.play();
            audioElement.addEventListener('ended', function() {
                $('#voicemail_name_pause_button').hide();
                $('#voicemail_name_play_button').show();
            });
        });

         // Pause name audio file
         $('#voicemail_name_pause_button').click(function(){
            var audioElement = document.getElementById('voicemail_name_audio_file');
            $(this).hide();
            $('#voicemail_name_play_button').show();
            audioElement.pause();
        });


        $('#voicemail_enabled').change(function() {
            if(this.checked == true){
                //check if voicemail already exists. If not create it
                if($('#voicemail_id').data('uuid') == ""){
                    //Create voicemail box
                    $('.loading').show();

                    var url = '{{ route("voicemails.store") }}';

                    $.ajax({
                        type: 'POST',
                        url: url,
                        cache: false,
                        data: {
                            extension: $('#voicemail_id').data('extensionuuid'),
                            voicemail_id: $('#voicemail_id').val(),
                            voicemail_password: $('#voicemail_id').val(),
                            voicemail_enabled: "true",
                            voicemail_transcription_enabled: "true",
                            voicemail_attach_file: "true",
                            voicemail_file: "attach",
                            voicemail_local_after_email: "true",
                        },
                    })
                    .done(function(response) {
                        //console.log(response);
                        $('#settingModal').modal('hide');
                        //$('.loading').hide();

                        if (response.error){
                            printErrorMsg(response.error);

                        } else {
                            $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                            setTimeout(function (){
                                    window.location.reload();
                                }, 1000);
                        }
                    })
                    .fail(function (response){
                        $('#settingModal').modal('hide');
                        $('.loading').hide();
                        printErrorMsg(response.error);
                    });
                }
            }

        });

    });

</script>
@endpush
