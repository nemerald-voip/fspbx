@extends('layouts.horizontal', ["page_title"=> "New Fax"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="{{ route('faxes.index') }}">Fax</a></li>
                            <li class="breadcrumb-item active">New Fax</li>
                    </ol>
                </div>
                    <h4 class="page-title">New Fax</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-12">
            <div class="card mt-3">
                <div class="card-body">
                    
                   

                    <div class="tab-content">
                            
                        <!-- Body Content-->
                            <div class="row">
                                <div class="col-lg-12">

                                <form method="POST" id="new_fax_form" action="{{ route('faxes.store') }}">
                                    @csrf
                                        <div class="row">
                                            <div class="col-md-12">
                                                <div class="mb-3">
                                                    <label for="fax_name" class="form-label">Header <span class="text-danger">*</span></label>
                                                    <input class="form-control"  type="text" value="" 
                                                        placeholder="Enter Header" id="" name="" />
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>


                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_name" class="form-label">From <span class="text-danger">*</span></label>
                                                    <input class="form-control"  type="text" value="" 
                                                        placeholder="From" id="" name="" />
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>


                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_name" class="form-label">To <span class="text-danger">*</span></label>
                                                    <input class="form-control"  type="text" value="" 
                                                        placeholder="To" id="" name="" />
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>

                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_name" class="form-label">Subject <span class="text-danger">*</span></label>
                                                    <input class="form-control"  type="text" value="" 
                                                        placeholder="Enter Subject" id="" name="" />
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_number" class="form-label">Fax Number <span class="text-danger">*</span></label>
                                                    
                                                    <select data-toggle="select2" title="Outbound Caller ID" name="fax_caller_id_number">
                                                        <option value="">Main Company Number</option>
                                                        @foreach ($destinations as $destination)
                                                            <option value="{{ phone($destination->destination_number, "US")->formatE164() }}"
                                                                {{ phone($destination->destination_number,"US",$national_phone_number_format) }}
                                                            </option>
                                                        @endforeach
                                                    </select>  
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>


                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_name" class="form-label">Resolution <span class="text-danger">*</span></label>
                                                    <select data-toggle="select2" title="Outbound Caller ID" name="fax_caller_id_number">
                                                        <option value="normal">Normal</option>
                                                        <option value="fine">Fine</option>
                                                        <option value="superfine">Superfine</option>
                                                    </select>
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>

                                            
                                            
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_name" class="form-label">Page Size <span class="text-danger">*</span></label>
                                                    <select data-toggle="select2" title="Outbound Caller ID" name="fax_caller_id_number">
                                                        <option value="letter">Letter</option>
                                                        <option value="legal">Legal</option>
                                                        <option value="a4">A4</option>
                                                    </select>
                                                    <div class="text-danger error_message fax_name_err"></div>
                                                </div>
                                            </div>

                                            
                                        </div> <!-- end row -->


                                        

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_description" class="form-label">Message </label>
                                                    <textarea class="form-control" type="text" placeholder="" id="fax_description" name="fax_description"
                                                        /></textarea>
                                                    <div class="text-danger error_message fax_description_err"></div>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="fax_description" class="form-label">Footer </label>
                                                    <textarea class="form-control" type="text" placeholder="" id="fax_description" name="fax_description"
                                                        /></textarea>
                                                    <div class="text-danger error_message fax_description_err"></div>
                                                </div>
                                            </div>
                                        </div>
                                      
                            
                                            <div class="row mt-4">
                                                <div class="col-sm-12">
                                                    <div class="text-sm-end">
                                                    <input type="hidden" name="fax_uuid" value="{{ $id}}">
                                                      <a href="{{ Route('faxes.index') }}" class="btn btn-light">Close</a>
                                                       <button id="submitFormButton" class="btn btn-danger" type="submit">Save </button>
                                                    </div>
                                                </div> <!-- end col -->
                                            </div>
    

                                    </form>
                                </div>
                            </div> <!-- end row-->

                    </div>




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
    });

    
    $('#submitFormButton').on('click', function(e) {
        e.preventDefault();
        $('.loading').show();
        
        //Reset error messages
        $('.error_message').text("");

        $.ajax({
            type : "POST",
            url: $('#fax_form').attr('action'),
            cache: false,
            data: $("#fax_form").serialize(),
        })
        .done(function(response) {
                console.log(response);
                $('.loading').hide();

                if (response.error){
                    printErrorMsg(response.error);

                } else {
                    $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                    setTimeout(function (){
                        window.location.href = response.redirect_url;
                    }, 1000);

                }
            })
        .fail(function (response){
            $('.loading').hide();
            printErrorMsg(response.responseText);
        });

    })

</script>
@endpush