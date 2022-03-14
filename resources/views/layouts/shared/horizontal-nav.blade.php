

<div class="topnav shadow-sm">
    <div class="container-fluid">
        <nav class="navbar navbar-dark navbar-expand-lg topnav-menu">

            <div class="collapse navbar-collapse" id="topnav-menu-content">
                <!-- LOGO -->
                <a href="" class="topnav-logo me-3">
                    <span class="topnav-logo-sm">
                        <img src="{{asset('/themes/default/logo.png')}}" alt="" height="40">
                    </span>
                </a>
                <ul class="navbar-nav">
                    @foreach (Session::get('menu') as $menu)
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle arrow-none" href="@if ($menu->menu_item_link != '') {{ $menu->menu_item_link }} @else # @endif" 
                            id="topnav-dashboards" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="uil-dashboard me-1"></i>{{ $menu->menu_item_title }} <div class="arrow-down"></div>
                            </a>
                            <div class="dropdown-menu" aria-labelledby="topnav-dashboards">
                                @foreach ($menu->child_menu as $child_menu)
                                <a href="{{ $child_menu->menu_item_link }}" class="dropdown-item">{{ $child_menu->menu_item_title }}</a>
                                @endforeach
                            </div>
                        </li>
                    @endforeach

                </ul>
            </div>

            <div class="float-end" id="">
                <ul class="navbar-nav float-end">
                    <li class="nav-item">
                        <a class="nav-link @if (Session::get("domain_select")) end-bar-toggle @endif" href="javascript: void(0);">
                            <i class="uil uil-globe fs-4 me-1"></i>{{ Session::get("domain_name") }}
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
    </div>
</div>