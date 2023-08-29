<div>
    <button class="btn btn-link mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse"
        aria-expanded="false" aria-controls="filterCollapse">
        Filters
        @if ($count = $component->getFilterBadgeCount())
            <span class="badge bg-info">
                {{ $count }}
            </span>
        @endif
        <i class="mdi mdi-chevron-down d-none d-sm-inline-block align-middle"></i>
    </button>
    <div class="collapse" id="filterCollapse">
        <div class="row mb-3">
            <div class="col-md-4 col-lg-3 col-sm-6">
                <label class="form-label">Date Range</label>
                <input type="text" class="form-control date" id="dateRangeFilter" data-toggle="date-picker"
                    data-cancel-class="btn-warning">
            </div>
        </div>



        {{-- <div class="card card-body"> --}}
        <div class="dropdown">
            <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton"
                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">

            </button>
            <div class="dropdown-menu " aria-labelledby="dropdownMenuButton">
                <form class="px-4 py-3" wire:submit.prevent="applyFilters">
                    <div class="form-group">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="allCheck">
                        <label class="form-check-label" for="allCheck">All</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="personalCheck">
                        <label class="form-check-label" for="personalCheck">Personal</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="professionalCheck">
                        <label class="form-check-label" for="professionalCheck">Professional</label>
                    </div>


                    <div class="row mt-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-link btn-warning"
                                wire:click="clearCategories">Clear</button>

                        </div>
                        <div class="col-6 text-end">
                            <button type="submit" class="btn btn-primary">Apply</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>
        {{-- </div> --}}
    </div>
</div>


@push('scripts')
    <script>
        $(document).ready(function() {

            // Get the current browser URL
            const urlString = window.location.href;
            // Create a URL object
            const url = new URL(urlString);

            // Extract specific query parameters
            const dateFrom = url.searchParams.get("table[filters][date_from]");
            const dateTo = url.searchParams.get("table[filters][date_to]");

            // Split the date string by "-"
            const dateFromParts = dateFrom.split("-");
            // Reformat the date
            const formattedDateFrom = `${dateFromParts[1]}/${dateFromParts[2]}/${dateFromParts[0].slice(-2)}`;

            // Split the date string by "-"
            const dateToParts = dateFrom.split("-");
            // Reformat the date
            const formattedDateTo = `${dateToParts[1]}/${dateToParts[2]}/${dateToParts[0].slice(-2)}`;


            $('#dateRangeFilter').daterangepicker({
                timePicker: false,
                startDate: formattedDateFrom,
                endDate: formattedDateTo,
                locale: {
                    format: 'MM/DD/YY'
                }
            }).on('apply.daterangepicker', function(e, picker) {
                @this.setDateRange(picker.startDate.format('YYYY-MM-DD'), picker.endDate.format('YYYY-MM-DD'))

                console.log(picker.startDate.format('YYYY-MM-DD'));

            });
        });
    </script>
@endpush
