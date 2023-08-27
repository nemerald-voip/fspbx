<div class="dropdown">
    <button class="btn btn-light dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown"
        aria-haspopup="true" aria-expanded="false">
        Filter
    </button>
    <div class="dropdown-menu " aria-labelledby="dropdownMenuButton">
            {{-- <div class="card">
                <div class="card-body"> --}}
                    {{-- <form class="px-4 py-3" wire:submit.prevent="applyFilters">
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
                    </form> --}}

                    <form class="p-3">
                        <div class="mb-3">
                            <label for="exampleDropdownFormEmail1" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="exampleDropdownFormEmail1" placeholder="email@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="exampleDropdownFormPassword1" class="form-label">Password</label>
                            <input type="password" class="form-control" id="exampleDropdownFormPassword1" placeholder="Password">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="dropdownCheck">
                                <label class="form-check-label" for="dropdownCheck">
                                    Remember me
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Sign in</button>
                    </form>
                {{-- </div>
            </div> --}}
    </div>
</div>
