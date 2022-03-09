@extends('layouts.horizontal', ["page_title"=> "Devices"])

@section('content')
<!-- Start Content-->
<div class="container-fluid">

    <!-- start page title -->
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Hyper</a></li>
                        <li class="breadcrumb-item"><a href="javascript: void(0);">Tables</a></li>
                        <li class="breadcrumb-item active">Basic Tables</li>
                    </ol>
                </div>
                <h4 class="page-title">Basic Tables</h4>
            </div>
        </div>
    </div>
    <!-- end page title -->

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Basic example</h4>
                    <p class="text-muted font-14">
                        For basic styling—light padding and only horizontal dividers—add the base class <code>.table</code> to any <code>&lt;table&gt;</code>.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#basic-example-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#basic-example-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="basic-example-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone Number</th>
                                            <th>Date of Birth</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Risa D. Pearson</td>
                                            <td>336-508-2157</td>
                                            <td>July 24, 1950</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch1" checked data-switch="success" />
                                                    <label for="switch1" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Ann C. Thompson</td>
                                            <td>646-473-2057</td>
                                            <td>January 25, 1959</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch2" checked data-switch="success" />
                                                    <label for="switch2" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paul J. Friend</td>
                                            <td>281-308-0793</td>
                                            <td>September 1, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch3" data-switch="success" />
                                                    <label for="switch3" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Linda G. Smith</td>
                                            <td>606-253-1207</td>
                                            <td>May 3, 1962</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch4" data-switch="success" />
                                                    <label for="switch4" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="basic-example-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-centered mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Name&lt;/th&gt;
                                                                    &lt;th&gt;Phone Number&lt;/th&gt;
                                                                    &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                    &lt;th&gt;Active?&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                    &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch1&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch1&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Ann C. Thompson&lt;/td&gt;
                                                                    &lt;td&gt;646-473-2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch2&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch2&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Paul J. Friend&lt;/td&gt;
                                                                    &lt;td&gt;281-308-0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch3&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch3&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Linda G. Smith&lt;/td&gt;
                                                                    &lt;td&gt;606-253-1207&lt;/td&gt;
                                                                    &lt;td&gt;May 3, 1962&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch4&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch4&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Inverse table</h4>
                    <p class="text-muted font-14">
                        You can also invert the colors—with light text on dark backgrounds—with <code>.table-dark</code>.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#inverse-table-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#inverse-table-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="inverse-table-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-dark mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone Number</th>
                                            <th>Date of Birth</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Risa D. Pearson</td>
                                            <td>336-508-2157</td>
                                            <td>July 24, 1950</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch6" data-switch="success" />
                                                    <label for="switch6" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Ann C. Thompson</td>
                                            <td>646-473-2057</td>
                                            <td>January 25, 1959</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch7" checked data-switch="success" />
                                                    <label for="switch7" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paul J. Friend</td>
                                            <td>281-308-0793</td>
                                            <td>September 1, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch8" data-switch="success" />
                                                    <label for="switch8" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Sean C. Nguyen</td>
                                            <td>269-714-6825</td>
                                            <td>February 5, 1994</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch10" checked data-switch="success" />
                                                    <label for="switch10" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="inverse-table-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-dark mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Name&lt;/th&gt;
                                                                    &lt;th&gt;Phone Number&lt;/th&gt;
                                                                    &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                    &lt;th&gt;Active?&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                    &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch6&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch6&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Ann C. Thompson&lt;/td&gt;
                                                                    &lt;td&gt;646-473-2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch7&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch7&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Paul J. Friend&lt;/td&gt;
                                                                    &lt;td&gt;281-308-0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch8&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch8&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Sean C. Nguyen&lt;/td&gt;
                                                                    &lt;td&gt;269-714-6825&lt;/td&gt;
                                                                    &lt;td&gt;February 5, 1994&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch10&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch10&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Striped rows</h4>
                    <p class="text-muted font-14">
                        Use <code>.table-striped</code> to add zebra-striping to any table row
                        within the <code>&lt;tbody&gt;</code>.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#striped-rows-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#striped-rows-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="striped-rows-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-striped table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Account No.</th>
                                            <th>Balance</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-2.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Risa D. Pearson
                                            </td>
                                            <td>AC336 508 2157</td>
                                            <td>July 24, 1950</td>
                                            <td class="table-action">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-pencil"></i></a>
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-3.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Ann C. Thompson
                                            </td>
                                            <td>SB646 473 2057</td>
                                            <td>January 25, 1959</td>
                                            <td class="table-action">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-pencil"></i></a>
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-4.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Paul J. Friend
                                            </td>
                                            <td>DL281 308 0793</td>
                                            <td>September 1, 1939</td>
                                            <td class="table-action">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-pencil"></i></a>
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-5.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Sean C. Nguyen
                                            </td>
                                            <td>CA269 714 6825</td>
                                            <td>February 5, 1994</td>
                                            <td class="table-action">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-pencil"></i></a>
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="striped-rows-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-striped table-centered mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;User&lt;/th&gt;
                                                                    &lt;th&gt;Account No.&lt;/th&gt;
                                                                    &lt;th&gt;Balance&lt;/th&gt;
                                                                    &lt;th&gt;Action&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-2.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Risa D. Pearson
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;AC336 508 2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-pencil&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-3.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Ann C. Thompson
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;SB646 473 2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-pencil&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-4.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Paul J. Friend
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;DL281 308 0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-pencil&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-5.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Sean C. Nguyen
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;CA269 714 6825&lt;/td&gt;
                                                                    &lt;td&gt;February 5, 1994&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-pencil&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Table head options</h4>
                    <p class="text-muted font-14">
                        Use one of two modifier classes to make <code>&lt;thead&gt;</code>s appear light or dark gray.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#table-head-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#table-head-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="table-head-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-centered mb-0">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Product</th>
                                            <th>Courier</th>
                                            <th>Process</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ASOS Ridley High Waist</td>
                                            <td>FedEx</td>
                                            <td>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar progress-lg bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td><i class="mdi mdi-circle text-success"></i> Delivered</td>
                                        </tr>
                                        <tr>
                                            <td>Marco Lightweight Shirt</td>
                                            <td>DHL</td>
                                            <td>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar progress-lg bg-warning" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td><i class="mdi mdi-circle text-warning"></i> Shipped</td>
                                        </tr>
                                        <tr>
                                            <td>Half Sleeve Shirt</td>
                                            <td>Bright</td>
                                            <td>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar progress-lg bg-info" role="progressbar" style="width: 25%" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td><i class="mdi mdi-circle text-info"></i> Order Received</td>
                                        </tr>
                                        <tr>
                                            <td>Lightweight Jacket</td>
                                            <td>FedEx</td>
                                            <td>
                                                <div class="progress progress-sm">
                                                    <div class="progress-bar progress-lg bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            </td>
                                            <td><i class="mdi mdi-circle text-success"></i> Delivered</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="table-head-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-centered mb-0&quot;&gt;
                                                            &lt;thead class=&quot;thead-dark&quot;&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Product&lt;/th&gt;
                                                                    &lt;th&gt;Courier&lt;/th&gt;
                                                                    &lt;th&gt;Process&lt;/th&gt;
                                                                    &lt;th&gt;Status&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;ASOS Ridley High Waist&lt;/td&gt;
                                                                    &lt;td&gt;FedEx&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;div class=&quot;progress progress-sm&quot;&gt;
                                                                            &lt;div class=&quot;progress-bar progress-lg bg-success&quot; role=&quot;progressbar&quot; style=&quot;width: 100%&quot; aria-valuenow=&quot;100&quot; aria-valuemin=&quot;0&quot; aria-valuemax=&quot;100&quot;&gt;&lt;/div&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;&lt;i class=&quot;mdi mdi-circle text-success&quot;&gt;&lt;/i&gt; Delivered&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Marco Lightweight Shirt&lt;/td&gt;
                                                                    &lt;td&gt;DHL&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;div class=&quot;progress progress-sm&quot;&gt;
                                                                            &lt;div class=&quot;progress-bar progress-lg bg-warning&quot; role=&quot;progressbar&quot; style=&quot;width: 50%&quot; aria-valuenow=&quot;50&quot; aria-valuemin=&quot;0&quot; aria-valuemax=&quot;100&quot;&gt;&lt;/div&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;&lt;i class=&quot;mdi mdi-circle text-warning&quot;&gt;&lt;/i&gt; Shipped&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Half Sleeve Shirt&lt;/td&gt;
                                                                    &lt;td&gt;Bright&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;div class=&quot;progress progress-sm&quot;&gt;
                                                                            &lt;div class=&quot;progress-bar progress-lg bg-info&quot; role=&quot;progressbar&quot; style=&quot;width: 25%&quot; aria-valuenow=&quot;25&quot; aria-valuemin=&quot;0&quot; aria-valuemax=&quot;100&quot;&gt;&lt;/div&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;&lt;i class=&quot;mdi mdi-circle text-info&quot;&gt;&lt;/i&gt; Order Received&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Lightweight Jacket&lt;/td&gt;
                                                                    &lt;td&gt;FedEx&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;div class=&quot;progress progress-sm&quot;&gt;
                                                                            &lt;div class=&quot;progress-bar progress-lg bg-success&quot; role=&quot;progressbar&quot; style=&quot;width: 100%&quot; aria-valuenow=&quot;100&quot; aria-valuemin=&quot;0&quot; aria-valuemax=&quot;100&quot;&gt;&lt;/div&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;&lt;i class=&quot;mdi mdi-circle text-success&quot;&gt;&lt;/i&gt; Delivered&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->


    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Hoverable rows</h4>
                    <p class="text-muted font-14">
                        Add <code>.table-hover</code> to enable a hover state on table rows within a <code>&lt;tbody&gt;</code>.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#hoverable-rows-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#hoverable-rows-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="hoverable-rows-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-hover table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ASOS Ridley High Waist</td>
                                            <td>$79.49</td>
                                            <td><span class="badge bg-primary">82 Pcs</span></td>
                                            <td>$6,518.18</td>
                                        </tr>
                                        <tr>
                                            <td>Marco Lightweight Shirt</td>
                                            <td>$128.50</td>
                                            <td><span class="badge bg-primary">37 Pcs</span></td>
                                            <td>$4,754.50</td>
                                        </tr>
                                        <tr>
                                            <td>Half Sleeve Shirt</td>
                                            <td>$39.99</td>
                                            <td><span class="badge bg-primary">64 Pcs</span></td>
                                            <td>$2,559.36</td>
                                        </tr>
                                        <tr>
                                            <td>Lightweight Jacket</td>
                                            <td>$20.00</td>
                                            <td><span class="badge bg-primary">184 Pcs</span></td>
                                            <td>$3,680.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="hoverable-rows-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-hover table-centered mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Product&lt;/th&gt;
                                                                    &lt;th&gt;Price&lt;/th&gt;
                                                                    &lt;th&gt;Quantity&lt;/th&gt;
                                                                    &lt;th&gt;Amount&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;ASOS Ridley High Waist&lt;/td&gt;
                                                                    &lt;td&gt;$79.49&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;82 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$6,518.18&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Marco Lightweight Shirt&lt;/td&gt;
                                                                    &lt;td&gt;$128.50&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;37 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$4,754.50&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Half Sleeve Shirt&lt;/td&gt;
                                                                    &lt;td&gt;$39.99&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;64 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$2,559.36&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Lightweight Jacket&lt;/td&gt;
                                                                    &lt;td&gt;$20.00&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;184 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$3,680.00&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Small table</h4>
                    <p class="text-muted font-14">
                        Add <code>.table-sm</code> to make tables more compact by cutting cell padding in half.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#small-table-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#small-table-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="small-table-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-sm table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Quantity</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>ASOS Ridley High Waist</td>
                                            <td>$79.49</td>
                                            <td><span class="badge bg-primary">82 Pcs</span></td>
                                            <td>$6,518.18</td>
                                        </tr>
                                        <tr>
                                            <td>Marco Lightweight Shirt</td>
                                            <td>$128.50</td>
                                            <td><span class="badge bg-primary">37 Pcs</span></td>
                                            <td>$4,754.50</td>
                                        </tr>
                                        <tr>
                                            <td>Half Sleeve Shirt</td>
                                            <td>$39.99</td>
                                            <td><span class="badge bg-primary">64 Pcs</span></td>
                                            <td>$2,559.36</td>
                                        </tr>
                                        <tr>
                                            <td>Lightweight Jacket</td>
                                            <td>$20.00</td>
                                            <td><span class="badge bg-primary">184 Pcs</span></td>
                                            <td>$3,680.00</td>
                                        </tr>
                                        <tr>
                                            <td>Marco Shoes</td>
                                            <td>$28.49</td>
                                            <td><span class="badge bg-primary">69 Pcs</span></td>
                                            <td>$1,965.81</td>
                                        </tr>
                                        <tr>
                                            <td>ASOS Ridley High Waist</td>
                                            <td>$79.49</td>
                                            <td><span class="badge bg-primary">82 Pcs</span></td>
                                            <td>$6,518.18</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="small-table-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-sm table-centered mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Product&lt;/th&gt;
                                                                    &lt;th&gt;Price&lt;/th&gt;
                                                                    &lt;th&gt;Quantity&lt;/th&gt;
                                                                    &lt;th&gt;Amount&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;ASOS Ridley High Waist&lt;/td&gt;
                                                                    &lt;td&gt;$79.49&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;82 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$6,518.18&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Marco Lightweight Shirt&lt;/td&gt;
                                                                    &lt;td&gt;$128.50&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;37 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$4,754.50&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Half Sleeve Shirt&lt;/td&gt;
                                                                    &lt;td&gt;$39.99&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;64 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$2,559.36&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Lightweight Jacket&lt;/td&gt;
                                                                    &lt;td&gt;$20.00&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;184 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$3,680.00&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Marco Shoes&lt;/td&gt;
                                                                    &lt;td&gt;$28.49&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;69 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$1,965.81&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;ASOS Ridley High Waist&lt;/td&gt;
                                                                    &lt;td&gt;$79.49&lt;/td&gt;
                                                                    &lt;td&gt;&lt;span class=&quot;badge bg-primary&quot;&gt;82 Pcs&lt;/span&gt;&lt;/td&gt;
                                                                    &lt;td&gt;$6,518.18&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Bordered table</h4>
                    <p class="text-muted font-14">
                        Add <code>.table-bordered</code> for borders on all sides of the table and cells.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#bordered-table-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#bordered-table-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="bordered-table-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-bordered table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Account No.</th>
                                            <th>Balance</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-6.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Risa D. Pearson
                                            </td>
                                            <td>AC336 508 2157</td>
                                            <td>July 24, 1950</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-7.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Ann C. Thompson
                                            </td>
                                            <td>SB646 473 2057</td>
                                            <td>January 25, 1959</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-8.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Paul J. Friend
                                            </td>
                                            <td>DL281 308 0793</td>
                                            <td>September 1, 1939</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-9.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Sean C. Nguyen
                                            </td>
                                            <td>CA269 714 6825</td>
                                            <td>February 5, 1994</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="bordered-table-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-bordered table-centered mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;User&lt;/th&gt;
                                                                    &lt;th&gt;Account No.&lt;/th&gt;
                                                                    &lt;th&gt;Balance&lt;/th&gt;
                                                                    &lt;th class=&quot;text-center&quot;&gt;Action&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-6.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Risa D. Pearson
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;AC336 508 2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-7.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Ann C. Thompson
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;SB646 473 2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-8.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Paul J. Friend
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;DL281 308 0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-9.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Sean C. Nguyen
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;CA269 714 6825&lt;/td&gt;
                                                                    &lt;td&gt;February 5, 1994&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Bordered color table</h4>
                    <p class="text-muted font-14">
                        Add <code>.table-bordered</code> & <code>.border-primary</code> can be added to change colors.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#bordered-color-table-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#bordered-color-table-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="bordered-color-table-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-bordered border-primary table-centered mb-0">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Account No.</th>
                                            <th>Balance</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-6.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Risa D. Pearson
                                            </td>
                                            <td>AC336 508 2157</td>
                                            <td>July 24, 1950</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-7.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Ann C. Thompson
                                            </td>
                                            <td>SB646 473 2057</td>
                                            <td>January 25, 1959</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-8.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Paul J. Friend
                                            </td>
                                            <td>DL281 308 0793</td>
                                            <td>September 1, 1939</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="table-user">
                                                <img src="{{asset('assets/images/users/avatar-9.jpg')}}" alt="table-user" class="me-2 rounded-circle" />
                                                Sean C. Nguyen
                                            </td>
                                            <td>CA269 714 6825</td>
                                            <td>February 5, 1994</td>
                                            <td class="table-action text-center">
                                                <a href="javascript: void(0);" class="action-icon"> <i class="mdi mdi-delete"></i></a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="bordered-color-table-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-bordered border-primary table-centered mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;User&lt;/th&gt;
                                                                    &lt;th&gt;Account No.&lt;/th&gt;
                                                                    &lt;th&gt;Balance&lt;/th&gt;
                                                                    &lt;th class=&quot;text-center&quot;&gt;Action&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-6.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Risa D. Pearson
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;AC336 508 2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-7.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Ann C. Thompson
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;SB646 473 2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-8.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Paul J. Friend
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;DL281 308 0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td class=&quot;table-user&quot;&gt;
                                                                        &lt;img src=&quot;assets/images/users/avatar-9.jpg&quot; alt=&quot;table-user&quot; class=&quot;me-2 rounded-circle&quot; /&gt;
                                                                        Sean C. Nguyen
                                                                    &lt;/td&gt;
                                                                    &lt;td&gt;CA269 714 6825&lt;/td&gt;
                                                                    &lt;td&gt;February 5, 1994&lt;/td&gt;
                                                                    &lt;td class=&quot;table-action text-center&quot;&gt;
                                                                        &lt;a href=&quot;javascript: void(0);&quot; class=&quot;action-icon&quot;&gt; &lt;i class=&quot;mdi mdi-delete&quot;&gt;&lt;/i&gt;&lt;/a&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->


    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Always responsive</h4>
                    <p class="text-muted font-14">
                        Across every breakpoint, use
                        <code>.table-responsive</code> for horizontally scrolling tables. Use
                        <code>.table-responsive{-sm|-md|-lg|-xl}</code> as needed to create responsive tables up to a particular breakpoint. From that breakpoint and up, the table will behave
                        normally and not scroll horizontally.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#responsive-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#responsive-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="responsive-preview">
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">#</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                            <th scope="col">Heading</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <th scope="row">1</th>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                        </tr>
                                        <tr>
                                            <th scope="row">2</th>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                        </tr>
                                        <tr>
                                            <th scope="row">3</th>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                            <td>Cell</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="responsive-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;#&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                    &lt;th scope=&quot;col&quot;&gt;Heading&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th scope=&quot;row&quot;&gt;1&lt;/th&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th scope=&quot;row&quot;&gt;2&lt;/th&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th scope=&quot;row&quot;&gt;3&lt;/th&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                    &lt;td&gt;Cell&lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->


    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Basic Borderless Example</h4>
                    <p class="text-muted font-14">
                        Add <code>.table-borderless</code> for a table without borders.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#basic-borderless-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#basic-borderless-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="basic-borderless-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-centered table-borderless mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone Number</th>
                                            <th>Date of Birth</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Risa D. Pearson</td>
                                            <td>336-508-2157</td>
                                            <td>July 24, 1950</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch21" checked data-switch="success" />
                                                    <label for="switch21" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Ann C. Thompson</td>
                                            <td>646-473-2057</td>
                                            <td>January 25, 1959</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch22" checked data-switch="success" />
                                                    <label for="switch22" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paul J. Friend</td>
                                            <td>281-308-0793</td>
                                            <td>September 1, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch23" data-switch="success" />
                                                    <label for="switch23" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Linda G. Smith</td>
                                            <td>606-253-1207</td>
                                            <td>May 3, 1962</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch24" data-switch="success" />
                                                    <label for="switch24" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="basic-borderless-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-centered table-borderless mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Name&lt;/th&gt;
                                                                    &lt;th&gt;Phone Number&lt;/th&gt;
                                                                    &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                    &lt;th&gt;Active?&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                    &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch21&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch21&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Ann C. Thompson&lt;/td&gt;
                                                                    &lt;td&gt;646-473-2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch22&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch22&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Paul J. Friend&lt;/td&gt;
                                                                    &lt;td&gt;281-308-0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch23&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch23&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Linda G. Smith&lt;/td&gt;
                                                                    &lt;td&gt;606-253-1207&lt;/td&gt;
                                                                    &lt;td&gt;May 3, 1962&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch24&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch24&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">

                    <h4 class="header-title">Inverse Borderless table</h4>
                    <p class="text-muted font-14">
                        Add <code>.table-borderless</code> for a table without borders.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#inverse-borderless-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#inverse-borderless-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="inverse-borderless-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-dark table-borderless mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone Number</th>
                                            <th>Date of Birth</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Risa D. Pearson</td>
                                            <td>336-508-2157</td>
                                            <td>July 24, 1950</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch25" data-switch="success" />
                                                    <label for="switch25" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Ann C. Thompson</td>
                                            <td>646-473-2057</td>
                                            <td>January 25, 1959</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch26" checked data-switch="success" />
                                                    <label for="switch26" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paul J. Friend</td>
                                            <td>281-308-0793</td>
                                            <td>September 1, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch27" data-switch="success" />
                                                    <label for="switch27" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Sean C. Nguyen</td>
                                            <td>269-714-6825</td>
                                            <td>February 5, 1994</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch28" checked data-switch="success" />
                                                    <label for="switch28" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div> <!-- end table-responsive-->
                        </div> <!-- end preview-->

                        <div class="tab-pane" id="inverse-borderless-code">
                            <pre class="mb-0">
                                                    <span class="html escape">
                                                        &lt;table class=&quot;table table-dark table-borderless mb-0&quot;&gt;
                                                            &lt;thead&gt;
                                                                &lt;tr&gt;
                                                                    &lt;th&gt;Name&lt;/th&gt;
                                                                    &lt;th&gt;Phone Number&lt;/th&gt;
                                                                    &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                    &lt;th&gt;Active?&lt;/th&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/thead&gt;
                                                            &lt;tbody&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                    &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                    &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch25&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch25&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Ann C. Thompson&lt;/td&gt;
                                                                    &lt;td&gt;646-473-2057&lt;/td&gt;
                                                                    &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch26&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch26&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Paul J. Friend&lt;/td&gt;
                                                                    &lt;td&gt;281-308-0793&lt;/td&gt;
                                                                    &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch27&quot; data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch27&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                                &lt;tr&gt;
                                                                    &lt;td&gt;Sean C. Nguyen&lt;/td&gt;
                                                                    &lt;td&gt;269-714-6825&lt;/td&gt;
                                                                    &lt;td&gt;February 5, 1994&lt;/td&gt;
                                                                    &lt;td&gt;
                                                                        &lt;!-- Switch--&gt;
                                                                        &lt;div&gt;
                                                                            &lt;input type=&quot;checkbox&quot; id=&quot;switch28&quot; checked data-switch=&quot;success&quot;/&gt;
                                                                            &lt;label for=&quot;switch28&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                        &lt;/div&gt;
                                                                    &lt;/td&gt;
                                                                &lt;/tr&gt;
                                                            &lt;/tbody&gt;
                                                        &lt;/table&gt;
                                                    </span>
                                                </pre> <!-- end highlight-->
                        </div> <!-- end preview code-->
                    </div> <!-- end tab-content-->

                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div>
    <!-- end row-->

    <div class="row">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Active tables</h4>
                    <p class="text-muted font-14">
                        Highlight a table row or cell by adding a <code>.table-active</code> class.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#active-tables-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#active-tables-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="active-tables-preview">
                            <div class="table-responsive-sm">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone Number</th>
                                            <th>Date of Birth</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="table-active">
                                            <td>Risa D. Pearson</td>
                                            <td>336-508-2157</td>
                                            <td>July 24, 1950</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch29" checked data-switch="success" />
                                                    <label for="switch29" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Ann C. Thompson</td>
                                            <td>646-473-2057</td>
                                            <td>January 25, 1959</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch30" checked data-switch="success" />
                                                    <label for="switch30" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paul J. Friend</td>
                                            <td>281-308-0793</td>
                                            <td>September 1, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch31" data-switch="success" />
                                                    <label for="switch31" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td scope="row">Linda G. Smith</td>
                                            <td colspan="2" class="table-active">606-253-1207</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch32" data-switch="success" />
                                                    <label for="switch32" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Paul J. Friend</td>
                                            <td>281-308-0793</td>
                                            <td>September 1, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch33" checked data-switch="success" />
                                                    <label for="switch33" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div><!-- end table-responsive-->
                        </div>

                        <div class="tab-pane" id="active-tables-code">
                            <div class="table-responsive-sm">
                                <pre class="mb-0">
                                                        <span class="html escape">
                                                            &lt;table class=&quot;table mb-0&quot;&gt;
                                                                &lt;thead&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;th&gt;Name&lt;/th&gt;
                                                                        &lt;th&gt;Phone Number&lt;/th&gt;
                                                                        &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                        &lt;th&gt;Active?&lt;/th&gt;
                                                                    &lt;/tr&gt;
                                                                &lt;/thead&gt;
                                                                &lt;tbody&gt;
                                                                    &lt;tr class=&quot;table-active&quot;&gt;
                                                                        &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                        &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                        &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch29&quot; checked data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch29&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td&gt;Ann C. Thompson&lt;/td&gt;
                                                                        &lt;td&gt;646-473-2057&lt;/td&gt;
                                                                        &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch30&quot; checked data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch30&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td&gt;Paul J. Friend&lt;/td&gt;
                                                                        &lt;td&gt;281-308-0793&lt;/td&gt;
                                                                        &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch31&quot; data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch31&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td scope=&quot;row&quot;&gt;Linda G. Smith&lt;/td&gt;
                                                                        &lt;td colspan=&quot;2&quot; class=&quot;table-active&quot;&gt;606-253-1207&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch32&quot; data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch32&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td&gt;Paul J. Friend&lt;/td&gt;
                                                                        &lt;td&gt;281-308-0793&lt;/td&gt;
                                                                        &lt;td&gt;September 1, 1939&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch33&quot; checked data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch33&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                &lt;/tbody&gt;
                                                            &lt;/table&gt;
                                                        </span>
                                                    </pre>
                            </div><!-- end table-responsive-->
                        </div>
                    </div><!-- end tab-content-->
                </div><!-- end card body-->
            </div><!-- end card -->
        </div><!-- end col -->

        <div class="col-xl-6">
            <div class="card">
                <div class="card-body">
                    <h4 class="header-title">Nesting</h4>
                    <p class="text-muted font-14">
                        Border styles, active styles, and table variants are not inherited by nested tables.
                    </p>

                    <ul class="nav nav-tabs nav-bordered mb-3">
                        <li class="nav-item">
                            <a href="#Nesting-tables-preview" data-bs-toggle="tab" aria-expanded="false" class="nav-link active">
                                Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#Nesting-tables-code" data-bs-toggle="tab" aria-expanded="true" class="nav-link">
                                Code
                            </a>
                        </li>
                    </ul> <!-- end nav-->
                    <div class="tab-content">
                        <div class="tab-pane show active" id="Nesting-tables-preview">
                            <div class="table-responsive-sm">
                                <table class="table table-striped mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Phone Number</th>
                                            <th>Date of Birth</th>
                                            <th>Active?</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Risa D. Pearson</td>
                                            <td>336-508-2157</td>
                                            <td>July 24, 1950</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch34" checked data-switch="success" />
                                                    <label for="switch34" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="4">
                                                <table class="table mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th>Name</th>
                                                            <th>Phone Number</th>
                                                            <th>Date of Birth</th>
                                                            <th>Active?</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td>Risa D. Pearson</td>
                                                            <td>336-508-2157</td>
                                                            <td>July 24, 1950</td>
                                                            <td>
                                                                <!-- Switch-->
                                                                <div>
                                                                    <input type="checkbox" id="switch35" checked data-switch="success" />
                                                                    <label for="switch35" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td>Ann C. Thompson</td>
                                                            <td>646-473-2057</td>
                                                            <td>January 25, 1959</td>
                                                            <td>
                                                                <!-- Switch-->
                                                                <div>
                                                                    <input type="checkbox" id="switch36" data-switch="success" />
                                                                    <label for="switch36" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>Linda G. Smith</td>
                                            <td>606-253-1207</td>
                                            <td>September 2, 1939</td>
                                            <td>
                                                <!-- Switch-->
                                                <div>
                                                    <input type="checkbox" id="switch37" data-switch="success" />
                                                    <label for="switch37" data-on-label="Yes" data-off-label="No" class="mb-0 d-block"></label>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane" id="Nesting-tables-code">
                            <div class="table-responsive-sm">
                                <pre class="mb-0">
                                                        <span class="html escape">
                                                            &lt;table class=&quot;table table-striped mb-0&quot;&gt;
                                                                &lt;thead&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;th&gt;Name&lt;/th&gt;
                                                                        &lt;th&gt;Phone Number&lt;/th&gt;
                                                                        &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                        &lt;th&gt;Active?&lt;/th&gt;
                                                                    &lt;/tr&gt;
                                                                &lt;/thead&gt;
                                                                &lt;tbody&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                        &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                        &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch34&quot; checked data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch34&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td colspan=&quot;4&quot;&gt;
                                                                            &lt;table class=&quot;table mb-0&quot;&gt;
                                                                                &lt;thead&gt;
                                                                                    &lt;tr&gt;
                                                                                        &lt;th&gt;Name&lt;/th&gt;
                                                                                        &lt;th&gt;Phone Number&lt;/th&gt;
                                                                                        &lt;th&gt;Date of Birth&lt;/th&gt;
                                                                                        &lt;th&gt;Active?&lt;/th&gt;
                                                                                    &lt;/tr&gt;
                                                                                &lt;/thead&gt;
                                                                                &lt;tbody&gt;
                                                                                    &lt;tr&gt;
                                                                                        &lt;td&gt;Risa D. Pearson&lt;/td&gt;
                                                                                        &lt;td&gt;336-508-2157&lt;/td&gt;
                                                                                        &lt;td&gt;July 24, 1950&lt;/td&gt;
                                                                                        &lt;td&gt;
                                                                                            &lt;!-- Switch--&gt;
                                                                                            &lt;div&gt;
                                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch35&quot; checked data-switch=&quot;success&quot; /&gt;
                                                                                                &lt;label for=&quot;switch35&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot;
                                                                                                    class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                                            &lt;/div&gt;
                                                                                        &lt;/td&gt;
                                                                                    &lt;/tr&gt;
                                                                                    &lt;tr&gt;
                                                                                        &lt;td&gt;Ann C. Thompson&lt;/td&gt;
                                                                                        &lt;td&gt;646-473-2057&lt;/td&gt;
                                                                                        &lt;td&gt;January 25, 1959&lt;/td&gt;
                                                                                        &lt;td&gt;
                                                                                            &lt;!-- Switch--&gt;
                                                                                            &lt;div&gt;
                                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch36&quot; data-switch=&quot;success&quot; /&gt;
                                                                                                &lt;label for=&quot;switch36&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot;
                                                                                                    class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                                            &lt;/div&gt;
                                                                                        &lt;/td&gt;
                                                                                    &lt;/tr&gt;
                                                                                &lt;/tbody&gt;
                                                                            &lt;/table&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                    &lt;tr&gt;
                                                                        &lt;td&gt;Linda G. Smith&lt;/td&gt;
                                                                        &lt;td&gt;606-253-1207&lt;/td&gt;
                                                                        &lt;td&gt;September 2, 1939&lt;/td&gt;
                                                                        &lt;td&gt;
                                                                            &lt;!-- Switch--&gt;
                                                                            &lt;div&gt;
                                                                                &lt;input type=&quot;checkbox&quot; id=&quot;switch37&quot; data-switch=&quot;success&quot; /&gt;
                                                                                &lt;label for=&quot;switch37&quot; data-on-label=&quot;Yes&quot; data-off-label=&quot;No&quot; class=&quot;mb-0 d-block&quot;&gt;&lt;/label&gt;
                                                                            &lt;/div&gt;
                                                                        &lt;/td&gt;
                                                                    &lt;/tr&gt;
                                                                &lt;/tbody&gt;
                                                            &lt;/table&gt;
                                                        </span>
                                                    </pre>
                            </div>
                        </div>
                    </div>
                </div><!-- end card body-->
            </div><!-- end card -->
        </div><!-- end col -->
    </div><!-- end row -->

</div> <!-- container -->
@endsection