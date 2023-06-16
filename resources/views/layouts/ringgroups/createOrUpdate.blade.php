@extends('layouts.horizontal', ["page_title"=> "Edit Ring Group"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('ring-groups.index') }}">Ring Groups</a></li>
                        @if($ringGroup->exists)
                            <li class="breadcrumb-item active">Edit Ring Group</li>
                        @else
                            <li class="breadcrumb-item active">Create Ring Group</li>
                        @endif
                    </ol>
                </div>
                @if($ringGroup->exists)
                    <h4 class="page-title">Edit Ring Group ({{ $ringGroup->ring_group_name }})</h4>
                @else
                    <h4 class="page-title">Create Ring Group</h4>
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
                        if ($ringGroup->exists) {
                            $actionUrl = route('ring-groups.update', $ringGroup);
                        } else {
                            $actionUrl = route('ring-groups.store');
                        }
                    @endphp
                    <form method="POST" id="ringGroupForm" action="{{$actionUrl}}" class="form">
                        @if ($ringGroup->exists)
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
                                            ring_group_extension_badge
                                            " hidden><span class="badge badge-danger-lighten">error</span></span>
                                    </span>
                                </a>
                            </div>
                        </div> <!-- end col-->

                            <div class="col-sm-10">

                                <div class="tab-content">
                                    <div class="text-sm-end" id="action-buttons">
                                        <a href="{{ route('ring-groups.index') }}" class="btn btn-light me-2">Cancel</a>
                                        <button class="btn btn-success" type="submit" id="submitFormButton"><i class="uil uil-down-arrow me-2"></i> Save </button>
                                        {{-- <button class="btn btn-success" type="submit">Save</button> --}}
                                    </div>
                                    <div class="tab-pane fade active show" id="v-pills-home" role="tabpanel" aria-labelledby="v-pills-home-tab">
                                        <!-- Basic Info Content-->
                                        <div class="row">
                                            <div class="col-lg-12">
                                                <h4 class="mt-2">Basic information</h4>

                                                <p class="text-muted mb-4">Provide basic information about the ring group</p>

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_extension" class="form-label">Ring Group number <span class="text-danger">*</span></label>
                                                                <input class="form-control" type="text" placeholder="xxx" id="ring_group_extension"
                                                                       name="ring_group_extension" value="{{ $ringGroup->ring_group_extension }}"/>
                                                                <div id="ring_group_extension_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_greeting" class="form-label">Greeting</label>
                                                                <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                                                                        id="ring_group_greeting" name="ring_group_greeting">
                                                                    <option value=""></option>
                                                                </select>
                                                                <div id="ring_group_greeting_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->

                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_strategy" class="form-label">Strategy</label>
                                                                <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..." id="ring_group_strategy" name="ring_group_strategy">
                                                                    <option value="simultaneous" @if($ringGroup->ring_group_strategy == 'simultaneous') selected="selected" @endif>Simultaneous</option>
                                                                    <option value="sequence" @if($ringGroup->ring_group_strategy == 'sequence') selected="selected" @endif>Sequence</option>
                                                                    <option value="random" @if($ringGroup->ring_group_strategy == 'random') selected="selected" @endif>Random</option>
                                                                    <option value="enterprise" @if($ringGroup->ring_group_strategy == 'enterprise') selected="selected" @endif>Enterprise</option>
                                                                    <option value="rollover" @if($ringGroup->ring_group_strategy == 'rollover') selected="selected" @endif>Rollover</option>
                                                                </select>
                                                                <div id="ring_group_strategy_err" class="text-danger text-error error_message"></div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label for="ring_group_ringback" class="form-label">Ring Back</label>
                                                                <select class="select2 form-control" data-toggle="select2" data-placeholder="Choose ..."
                                                                        id="ring_group_ringback" name="ring_group_ringback">
                                                                    <option value=""></option>
                                                                </select>
                                                                <div id="ring_group_ringback_err" class="text-danger error-text error_message"></div>
                                                            </div>
                                                        </div>
                                                    </div> <!-- end row -->
                                            </div>

                                        </div> <!-- end row-->

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

@push('scripts')

<script>
    $(document).ready(function() {
        const form = $('#ringGroupForm');
        $('#submitFormButton').on('click', function(e) {
            e.preventDefault();
            $('.loading').show();

            //Reset error messages
            $('.error_message').text("");

            var url = form.attr('action');

            $.ajax({
                type : "POST",
                url : url,
                cache: false,
                data : form.serialize(),
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
                    $('.loading').hide();
                    $.NotificationApp.send("Success",result.message,"top-right","#10c469","success");
                    //window.location.href = "{{ route('ring-groups.index')}}";
                },
                error: function(error) {
                    $('.loading').hide();
                    $('.btn').attr('disabled', false);
                    if(error.status == 422){
                        if(error.responseJSON.errors) {
                            $.each( error.responseJSON.errors, function( key, value ) {
                                if (value != '') {
                                    form.find('#'+key+'_err').text(value);
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
            })
        });
    });
</script>
@endpush
