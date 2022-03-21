<!-- bundle -->
<script src="{{asset('assets/js/vendor.js')}}"></script>
@yield('script')
<script src="{{asset('assets/js/app.min.js')}}"></script>
@yield('script-bottom')

<script>
    $(document).ready(function() {

        // Domain search
        $("#domainSearchInput").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#domainSearchList *").filter(function() {
                $(this).parent('.listgroup').toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });

        //CallerID single page. 
        //uncheck all of the checkboxes, apart from the one checked
        $('input.callerIdCheckbox').on('change', function() {
            var id = $(this).val();
            var checkbox = $(this);
            var url = '{{ route("updateCallerID", ":id") }}';
            url = url.replace(':id', id);
            $.ajax({
                type : "POST",
                url : url,
                checkbox : $(this),
                headers: {
                    'X-CSRF-Token': '{{ csrf_token() }}',
                },
            })
            .done(function(response) { 
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


    });
</script>