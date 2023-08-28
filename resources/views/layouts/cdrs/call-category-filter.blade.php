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

        <div class="mb-3">
            <label class="form-label">Date Range</label>
            <input type="text" class="form-control date" id="dateRangeFilter" data-toggle="date-picker" data-cancel-class="btn-warning">
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

        $('#dateRangeFilter').daterangepicker({
            timePicker: false,
            startDate: moment(),
            endDate: moment(),
            locale: {
                format: 'MM/DD/YY'
            }
        }).on('apply.daterangepicker', function(e) {
            var location = window.location.protocol +"//" + window.location.host + window.location.pathname;
            location += '?page=1&' + $('#filterForm').serialize();
            window.location.href = location;
        });
    });

</script>
@endpush