<nav class="navbar navbar-expand-lg navbar-light bg-success">
    <div class="container-fluid">
        <a class="navbar-brand text-white" href="/"> <img class="mb-2"
                src="{{ asset('images/icon_pharmacy_white.png') }}" alt="Logo"
                style="height: 30px; margin-right: 10px;">FARMASI</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="text-white nav-link {{ request()->routeIs('pharmacies.dashboard.locations') ? 'active' : '' }}"
                        href="{{ route('pharmacies.dashboard.locations') }}">Lokasi</a>
                </li>
                <li class="nav-item">
                    <a class="text-white nav-link {{ request()->routeIs('pharmacies.dashboard.payers') ? 'active' : '' }}"
                        href="{{ route('pharmacies.dashboard.payers') }}">Penjamin</a>
                </li>
                <li class="nav-item">
                    <a class="text-white nav-link {{ request()->routeIs('pharmacies.dashboard.orders') ? 'active' : '' }}"
                        href="{{ route('pharmacies.dashboard.orders') }}">List Order</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                {{-- <form class="d-flex ms-auto mr-auto" action="" method="GET">
                    <div class="input-group">
                        <input class="form-control" type="search" placeholder="Search" aria-label="Search"
                            name="query">
                        <span class="input-group-text bg-white border-0">
                            <i class="bi bi-search"></i>
                        </span>
                    </div>
                </form> --}}

                {{-- @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Selamat datang, {{ auth()->user()->name }}
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="/dashboard"><i class="bi bi-layout-text-sidebar-reverse"></i>
                                    Dashboard Saya</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <form action="/logout" method="post">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i
                                            class="bi bi-box-arrow-right"></i>Logout</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a href="/login" class="nav-link text-white {{ $active === 'login' ? 'active' : '' }}"><i
                                class="bi bi-box-arrow-in-right p-1"></i>Login</a>
                    </li>
                @endauth --}}

            </ul>
        </div>
    </div>
</nav>
