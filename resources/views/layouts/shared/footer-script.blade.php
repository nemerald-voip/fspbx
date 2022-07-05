<!-- bundle -->
<script src="{{asset('assets/js/vendor.js')}}"></script>
@yield('script')
<script src="{{asset('assets/js/app.min.js')}}"></script>
@yield('script-bottom')
@stack('scripts')

<script>

    $(function() {
        // Set the csrf token, and set dataType: 'json' for all ajax requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json'
        });
    });

    // This function receives IDs and URL for items to be deleted and passes them to the Confirm Delete Modal
    function confirmDeleteAction(url,setting_id=''){

        dataObj = new Object();
        dataObj.url = url;

        if(setting_id==''){
            setting_id=[];
            $('.action_checkbox').each(function(key,val){
                if($(this).is(':checked')){
                    setting_id.push($(this).val());
                }
            });
        }
        dataObj.setting_id = setting_id;
        $('#confirmDeleteModal').data(dataObj).modal('show');
                // deleteSetting(setting_id);
    }

    //This function sends AJAX request to delete selected items from list pages
    function performConfirmedDeleteAction(){
        var setting_id = $("#confirmDeleteModal").data("setting_id");
        $('#confirmDeleteModal').modal('hide');
        //$('.loading').show();

        //Check if we received an array with multiple IDs
        if (Array.isArray(setting_id)) {
            setting_id.forEach( function (item){
                var url = $("#confirmDeleteModal").data("url");
                url = url.replace(':id', item );
                $.ajax({
                    type: 'DELETE',
                    url: url,
                    cache: false,
                })
                .done(function(response) {
                    // console.log(response);
                    //$('.loading').hide();

                    if (response.error){
                        $.NotificationApp.send("Warning",response.message,"top-right","#ff5b5b","error");

                    } else {
                        $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                        $("#id" + item).fadeOut("slow");
                    }
                })
                .fail(function (response){
                    $('.loading').hide();
                    $.NotificationApp.send("Warning",response,"top-right","#ff5b5b","error");
                });
            });

        } else {
            var url = $("#confirmDeleteModal").data("url");
            url = url.replace(':id', setting_id );
            $.ajax({
                    type: 'POST',
                    url: url,
                    cache: false,
                    data: {
                        '_method': 'DELETE',
                    }
            })
            .done(function(response) {
                //$('.loading').hide();

                if (response.error){
                    $.NotificationApp.send("Warning",response.message,"top-right","#ff5b5b","error");

                } else {
                    $.NotificationApp.send("Success",response.message,"top-right","#10c469","success");
                    $("#id" + setting_id).fadeOut("slow");
                    //$(this).closest('tr').fadeOut("fast");
                }
            })
            .fail(function (response){
                $('.loading').hide();
                $.NotificationApp.send("Warning",response,"top-right","#ff5b5b","error");
            });
        }

        // setTimeout(function (){
        //         window.location.reload();
        //         }, 2000);
    }

    // Check if all action checkboxes on the page are checked
    function checkIfAllCheckboxesChecked(){
        var checked=true;
        $('.action_checkbox').each(function(key,val){
            if(!$(this).is(':checked')){
                checked=false;
            }
        });
        return checked;
    }

    //Check if at least one action checkbox is checked
    function checkIfAnyCheckboxesChecked(){
        var has=false;
        $('.action_checkbox').each(function(key,val){
        if($(this).is(':checked')){
            has=true;
        }});
        return has;
    }

    // Add errors to the page and send alert
    function printErrorMsg (msg) {
        var error_message = "<ul>"
        $.each( msg, function( key, value ) {
            //console.log(key);
            $('.'+key+'_err').text(value);
            $('.'+key+'_err_badge').attr("hidden", false);
            error_message = error_message + '<li>'+value+'</li>';
        });
        error_message = error_message + "</ul>";
        $.NotificationApp.send("Warning",error_message,"top-right","#ff5b5b","error")

    }

    $(document).ready(function() {

        // Domain search
        $("#domainSearchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#domainSearchList *").filter(function() {
                $(this).parent('.listgroup').toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        // Action checkboxes on list pages
        $('#selectallCheckbox').on('change',function(){
            if($(this).is(':checked')){
                $('.action_checkbox').prop('checked',true);
            } else {
                $('.action_checkbox').prop('checked',false);
            }
            if (checkIfAnyCheckboxesChecked()){
                $('#deleteMultipleActionButton').removeClass('disabled');
            } else {
                $('#deleteMultipleActionButton').addClass('disabled');
            }
        });

        $('.action_checkbox').on('change',function(){
            if(!$(this).is(':checked')){
                $('#selectallCheckbox').prop('checked',false);
            } else {
                if(checkIfAllCheckboxesChecked()){
                    $('#selectallCheckbox').prop('checked',true);
                }
            }
            if (checkIfAnyCheckboxesChecked()){
                $('#deleteMultipleActionButton').removeClass('disabled');
            } else {
                $('#deleteMultipleActionButton').addClass('disabled');
            }
        });

        //CallerID single page. 
        //uncheck all of the checkboxes, apart from the one checked
        $('input.callerIdCheckbox').on('change', function() {
            var id = $(this).val();
            var checkbox = $(this);
            var url = '{{ route("updateCallerID") }}';
            var extension_uuid = '{{ $extension->extension_uuid ?? ''}}'

            var formData = new FormData();
            formData.append('extension_uuid', extension_uuid);
            formData.append('destination_uuid', id); 

            $.ajax({
                type : "POST",
                url : url,
                data: formData,
                processData: false,
                contentType: false,
                checkbox : $(this),
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) { 
                console.log(response);
                if (response.error){
                    checkbox.prop('checked', false);
                } else {
                    $('input.callerIdCheckbox').not(checkbox).prop('checked', false);
                }
            })
            .fail(function (response){
                //
            });

        });

        // App Provisioning page
        // Change Provision button status to enabled when at least one organization is selected
        $('input.appCompanyCheckbox').on('change', function() {
            //Uncheck other checkboxes
            $('input.appCompanyCheckbox').not($(this)).prop('checked', false);

            //Toggle the status of Provision button
            var checkBoxes = $('input.appCompanyCheckbox');
            $('#appProvisionButton').toggleClass('disabled', checkBoxes.filter(':checked').length < 1);

            //Prefill the form in the modal with selected values
            $('#organization_name').val($.trim($(this).closest("tr").find('td:eq(1)').text()));
            $('#organization_domain').val($.trim($(this).closest("tr").find('td:eq(1)').text()).toLowerCase().replace(/ /g,'').replace(/[^\w-]+/g,''));
            $('#organization_uuid').val($.trim($(this).val()));
            $('#connection_domain').val($.trim($(this).closest("tr").find('td:eq(2)').text()));
            $('#connection_name').val($.trim($(this).closest("tr").find('td:eq(1)').text()));
            $('#connection_organization_uuid').val($.trim($(this).val()));
        });

        // App Provisioning page
        // Provision new organization
        $('#createOrganizationForm').on('submit', function(e) {
            e.preventDefault();
            //Change button to spinner
            $("#appProvisionNextButton").html('');
            $("#appProvisionNextButton").append('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
            $("#appProvisionNextButton").prop( "disabled", true );

            //Hide error message
            $("#appOrganizationError").find("ul").html('');
            $("#appOrganizationError").css('display','none');

            var url = '{{ route("appsCreateOrganization") }}';
 
            $.ajax({
                type : "POST",
                url : url,
                data: $(this).serialize(),
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#appProvisionNextButton").html('');
                $("#appProvisionNextButton").append('Next');
                $("#appProvisionNextButton").prop( "disabled", false );

                if (response.error){
                    $("#appOrganizationError").find("ul").html('');
                    $("#appOrganizationError").css('display','block');
                    $("#appOrganizationError").find("ul").append('<li>'+response.message+'</li>');
                    
                 } else {
                    //Switch to the next tab
                    $('a[href*="connection-b2"] span').trigger("click");
                    // Assign Org ID to a hidden input
                    $("#org_id").val(response.org_id);
                }
            })
            .fail(function (response){
                //
            });
        });


        // App Provisioning page
        // Provision new Connection
        $('#createConnectionForm').on('submit', function(e) {
            e.preventDefault();
            //Change button to spinner
            $("#appConnectionNextButton").html('');
            $("#appConnectionNextButton").append('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Loading...');
            $("#appConnectionNextButton").prop( "disabled", true );

            //Hide error message
            $("#appConnectionError").find("ul").html('');
            $("#appConnectionError").css('display','none');

            var url = '{{ route("appsCreateConnection") }}';

            $.ajax({
                type : "POST",
                url : url,
                data: $(this).serialize(),
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) {
                // remove the spinner and change button to default
                $("#appConnectionNextButton").html('');
                $("#appConnectionNextButton").append('Next');
                $("#appConnectionNextButton").prop( "disabled", false );

                if (response.error){
                    $("#appConnectionError").find("ul").html('');
                    $("#appConnectionError").css('display','block');
                    $("#appConnectionError").find("ul").append('<li>'+response.message+'</li>');
                    
                 } else {
                    //Switch to the next tab
                    $('a[href*="result-b2"] span').trigger("click");

                }
            })
            .fail(function (response){
                //
            });
        });


    });
</script>