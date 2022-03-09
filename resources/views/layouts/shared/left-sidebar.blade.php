<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu">

    <!-- LOGO -->
    <a href="{{route('any', 'index')}}" class="logo text-center logo-light">
        <span class="logo-lg">
            <img src="{{asset('assets/images/logo.png')}}" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="{{asset('assets/images/logo_sm.png')}}" alt="" height="16">
        </span>
    </a>

    <!-- LOGO -->
    <a href="{{route('any', 'index')}}" class="logo text-center logo-dark">
        <span class="logo-lg">
            <img src="{{asset('assets/images/logo-dark.png')}}" alt="" height="16">
        </span>
        <span class="logo-sm">
            <img src="{{asset('assets/images/logo_sm_dark.png')}}" alt="" height="16">
        </span>
    </a>

    <div class="h-100" id="leftside-menu-container" data-simplebar>

        <!--- Sidemenu -->
        <ul class="side-nav">

            <li class="side-nav-title side-nav-item">Navigation</li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarDashboards" aria-expanded="false" aria-controls="sidebarDashboards" class="side-nav-link">
                    <i class="uil-home-alt"></i>
                    <span class="badge bg-success float-end">4</span>
                    <span> Dashboards </span>
                </a>
                <div class="collapse" id="sidebarDashboards">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['dashboard', 'analytics'])}}">Analytics</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['dashboard', 'crm'])}}">CRM</a>
                        </li>
                        <li>
                            <a href="{{route('any', 'index')}}">Ecommerce</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['dashboard', 'projects'])}}">Projects</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-title side-nav-item">Apps</li>

            <li class="side-nav-item">
                <a href="{{route('third', ['apps', 'calendar', 'calendar'])}}" class="side-nav-link">
                    <i class="uil-calender"></i>
                    <span> Calendar </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{route('third', ['apps', 'chat', 'chat'])}}" class="side-nav-link">
                    <i class="uil-comments-alt"></i>
                    <span> Chat </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarEcommerce" aria-expanded="false" aria-controls="sidebarEcommerce" class="side-nav-link">
                    <i class="uil-store"></i>
                    <span> Ecommerce </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarEcommerce">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'products'])}}">Products</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'products-details'])}}">Products Details</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'orders'])}}">Orders</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'orders-details'])}}">Order Details</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'customers'])}}">Customers</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'shopping-cart'])}}">Shopping Cart</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'checkout'])}}">Checkout</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'ecommerce', 'sellers'])}}">Sellers</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarEmail" aria-expanded="false" aria-controls="sidebarEmail" class="side-nav-link">
                    <i class="uil-envelope"></i>
                    <span> Email </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarEmail">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('third', ['apps', 'email', 'inbox'])}}">Inbox</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'email', 'read'])}}">Read Email</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarProjects" aria-expanded="false" aria-controls="sidebarProjects" class="side-nav-link">
                    <i class="uil-briefcase"></i>
                    <span> Projects </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarProjects">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('third', ['apps', 'projects', 'list'])}}">List</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'projects', 'details'])}}">Details</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'projects', 'gantt'])}}">Gantt <span class="badge rounded-pill badge-light-lighten font-10 float-end">New</span></a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'projects', 'add'])}}">Create Project <span class="badge rounded-pill badge-success-lighten font-10 float-end">New</span></a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{route('third', ['apps', 'social', 'feed'])}}" class="side-nav-link">
                    <i class="uil-rss"></i>
                    <span> Social Feed </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarTasks" aria-expanded="false" aria-controls="sidebarTasks" class="side-nav-link">
                    <i class="uil-clipboard-alt"></i>
                    <span> Tasks </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarTasks">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('third', ['apps', 'tasks', 'tasks'])}}">List</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'tasks', 'details'])}}">Details</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['apps', 'kanban'])}}">Kanban Board</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{route('second', ['apps', 'file-manager'])}}" class="side-nav-link">
                    <i class="uil-folder-plus"></i>
                    <span> File Manager </span>
                </a>
            </li>

            <li class="side-nav-title side-nav-item">Custom</li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarPages" aria-expanded="false" aria-controls="sidebarPages" class="side-nav-link">
                    <i class="uil-copy-alt"></i>
                    <span> Pages </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarPages">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('third', ['apps', 'pages', 'profile'])}}">Profile</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'pages', 'profile-2'])}}">Profile 2</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'pages', 'invoice'])}}">Invoice</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'pages', 'faq'])}}">FAQ</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'pages', 'pricing'])}}">Pricing</a>
                        </li>
                        <li>
                            <a href="{{route('third', ['apps', 'pages', 'maintenance'])}}">Maintenance</a>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarPagesAuth" aria-expanded="false" aria-controls="sidebarPagesAuth">
                                <span> Authentication </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarPagesAuth">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{route('second', ['auth', 'login'])}}">Login</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'login-2'])}}">Login 2</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'register'])}}">Register</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'register-2'])}}">Register 2</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'logout'])}}">Logout</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'logout-2'])}}">Logout 2</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'recoverpw'])}}">Recover Password</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'recoverpw-2'])}}">Recover Password 2</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'lock-screen'])}}">Lock Screen</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'lock-screen-2'])}}">Lock Screen 2</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'confirm-mail'])}}">Confirm Mail</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['auth', 'confirm-mail-2'])}}">Confirm Mail 2</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarPagesError" aria-expanded="false" aria-controls="sidebarPagesError">
                                <span> Error </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarPagesError">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{route('second', ['error', '404'])}}">Error 404</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['error', '404-alt'])}}">Error 404-alt</a>
                                    </li>
                                    <li>
                                        <a href="{{route('second', ['error', '500'])}}">Error 500</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li>
                            <a href="{{route('third',['apps', 'pages', 'starter'])}}">Starter Page</a>
                        </li>
                        <li>
                            <a href="{{route('third',['apps', 'pages', 'preloader'])}}">With Preloader</a>
                        </li>
                        <li>
                            <a href="{{route('third',['apps', 'pages', 'timeline'])}}">Timeline</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{route('any', 'landing')}}" target="_blank" class="side-nav-link">
                    <i class="uil-globe"></i>
                    <span class="badge bg-secondary text-light float-end">New</span>
                    <span> Landing </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarLayouts" aria-expanded="false" aria-controls="sidebarLayouts" class="side-nav-link">
                    <i class="uil-window"></i>
                    <span> Layouts </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarLayouts">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['layouts-eg', 'horizontal'])}}">Horizontal</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['layouts-eg', 'detached'])}}">Detached</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-title side-nav-item mt-1">Components</li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarBaseUI" aria-expanded="false" aria-controls="sidebarBaseUI" class="side-nav-link">
                    <i class="uil-box"></i>
                    <span> Base UI </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarBaseUI">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['base-ui','accordions'])}}">Accordions</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','alerts'])}}">Alerts</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','avatars'])}}">Avatars</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','badges'])}}">Badges</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','breadcrumb'])}}">Breadcrumb</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','buttons'])}}">Buttons</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','cards'])}}">Cards</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','carousel'])}}">Carousel</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','dropdowns'])}}">Dropdowns</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','embed-video'])}}">Embed Video</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','grid'])}}">Grid</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','list-group'])}}">List Group</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','modals'])}}">Modals</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','notifications'])}}">Notifications</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','offcanvas'])}}">Offcanvas</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','pagination'])}}">Pagination</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','popovers'])}}">Popovers</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','progress'])}}">Progress</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','ribbons'])}}">Ribbons</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','spinners'])}}">Spinners</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','tabs'])}}">Tabs</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','tooltips'])}}">Tooltips</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['base-ui','typography'])}}">Typography</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarExtendedUI" aria-expanded="false" aria-controls="sidebarExtendedUI" class="side-nav-link">
                    <i class="uil-package"></i>
                    <span> Extended UI </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarExtendedUI">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['extended','dragula'])}}">Dragula</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['extended','range-slider'])}}">Range Slider</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['extended','ratings'])}}">Ratings</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['extended','scrollbar'])}}">Scrollbar</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['extended','scrollspy'])}}">Scrollspy</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{route('any', 'widgets')}}" class="side-nav-link">
                    <i class="uil-layer-group"></i>
                    <span> Widgets </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarIcons" aria-expanded="false" aria-controls="sidebarIcons" class="side-nav-link">
                    <i class="uil-streering"></i>
                    <span> Icons </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarIcons">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['icons','dripicons'])}}">Dripicons</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['icons','mdi'])}}">Material Design</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['icons','unicons'])}}">Unicons</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarForms" aria-expanded="false" aria-controls="sidebarForms" class="side-nav-link">
                    <i class="uil-document-layout-center"></i>
                    <span> Forms </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarForms">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['forms','elements'])}}">Basic Elements</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['forms','advanced'])}}">Form Advanced</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['forms','validation'])}}">Validation</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['forms','wizard'])}}">Wizard</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['forms','fileuploads'])}}">File Uploads</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['forms','editors'])}}">Editors</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarCharts" aria-expanded="false" aria-controls="sidebarCharts" class="side-nav-link">
                    <i class="uil-chart"></i>
                    <span> Charts </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarCharts">
                    <ul class="side-nav-second-level">
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarApexCharts" aria-expanded="false" aria-controls="sidebarApexCharts">
                                <span> Apex Charts </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarApexCharts">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'area'])}}">Area</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'bar'])}}">Bar</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'bubble'])}}">Bubble</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'candlestick'])}}">Candlestick</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'column'])}}">Column</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'heatmap'])}}">Heatmap</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'line'])}}">Line</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'mixed'])}}">Mixed</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'pie'])}}">Pie</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'radar'])}}">Radar</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'radialbar'])}}">RadialBar</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'scatter'])}}">Scatter</a>
                                    </li>
                                    <li>
                                        <a href="{{route('third', ['charts', 'apex', 'sparklines'])}}">Sparklines</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li>
                            <a href="{{route('second', ['charts', 'brite'])}}">Britecharts</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['charts', 'chartjs'])}}">Chartjs</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['charts', 'sparkline'])}}">Sparklines</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarTables" aria-expanded="false" aria-controls="sidebarTables" class="side-nav-link">
                    <i class="uil-table"></i>
                    <span> Tables </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarTables">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['tables', 'basic'])}}">Basic Tables</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['tables', 'datatable'])}}">Data Tables</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarMaps" aria-expanded="false" aria-controls="sidebarMaps" class="side-nav-link">
                    <i class="uil-location-point"></i>
                    <span> Maps </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarMaps">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{route('second', ['maps', 'google'])}}">Google Maps</a>
                        </li>
                        <li>
                            <a href="{{route('second', ['maps', 'vector'])}}">Vector Maps</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarMultiLevel" aria-expanded="false" aria-controls="sidebarMultiLevel" class="side-nav-link">
                    <i class="uil-folder-plus"></i>
                    <span> Multi Level </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarMultiLevel">
                    <ul class="side-nav-second-level">
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarSecondLevel" aria-expanded="false" aria-controls="sidebarSecondLevel">
                                <span> Second Level </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarSecondLevel">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="javascript: void(0);">Item 1</a>
                                    </li>
                                    <li>
                                        <a href="javascript: void(0);">Item 2</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarThirdLevel" aria-expanded="false" aria-controls="sidebarThirdLevel">
                                <span> Third Level </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarThirdLevel">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="javascript: void(0);">Item 1</a>
                                    </li>
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#sidebarFourthLevel" aria-expanded="false" aria-controls="sidebarFourthLevel">
                                            <span> Item 2 </span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="sidebarFourthLevel">
                                            <ul class="side-nav-forth-level">
                                                <li>
                                                    <a href="javascript: void(0);">Item 2.1</a>
                                                </li>
                                                <li>
                                                    <a href="javascript: void(0);">Item 2.2</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>

        <!-- Help Box -->
        <div class="help-box text-white text-center">
            <a href="javascript: void(0);" class="float-end close-btn text-white">
                <i class="mdi mdi-close"></i>
            </a>
            <img src="{{asset('assets/images/help-icon.svg')}}" height="90" alt="Helper Icon Image" />
            <h5 class="mt-3">Unlimited Access</h5>
            <p class="mb-3">Upgrade to plan to get access to unlimited reports</p>
            <a href="javascript: void(0);" class="btn btn-outline-light btn-sm">Upgrade</a>
        </div>
        <!-- end Help Box -->
        <!-- End Sidebar -->

        <div class="clearfix"></div>

    </div>
    <!-- Sidebar -left -->

</div>
<!-- Left Sidebar End -->