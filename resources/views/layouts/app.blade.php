<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="theme-color" content="#ffffff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>@yield('title', 'Selling Hub')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Jost:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
</head>
<body class="h-full bg-gray-50/50 font-sans antialiased">
    <div class="flex h-full min-h-screen">

        <!-- ═══════════════════════════════════════════
             SIDEBAR — Desktop only (lg+)
             ═══════════════════════════════════════════ -->
        <aside class="hidden lg:flex fixed inset-y-0 left-0 z-50 w-64 flex-col border-r border-gray-200 bg-white">
            <!-- Brand -->
            <div class="flex h-16 items-center gap-2.5 border-b border-gray-100 px-5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-lg shadow-brand-200">
                    <i class="bi bi-shop text-lg"></i>
                </div>
                <div>
                    <span class="text-sm font-bold text-gray-900 tracking-tight">Selling Hub</span>
                    <p class="text-[10px] text-gray-400 font-medium -mt-0.5">Inventory & Orders</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
                <a class="sidebar-link {{ request()->is('/') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="bi bi-grid-1x2 text-base w-5 text-center"></i> Dashboard
                </a>
                <div class="sidebar-section">Inventory</div>
                <a class="sidebar-link {{ request()->is('products*') ? 'active' : '' }}" href="{{ route('products.index') }}">
                    <i class="bi bi-box-seam text-base w-5 text-center"></i> Products
                </a>
                <a class="sidebar-link {{ request()->is('suppliers*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}">
                    <i class="bi bi-truck text-base w-5 text-center"></i> Suppliers
                </a>
                <a class="sidebar-link {{ request()->is('batch-orders*') ? 'active' : '' }}" href="{{ route('batch-orders.index') }}">
                    <i class="bi bi-cart-plus text-base w-5 text-center"></i> Batch Orders
                </a>
                <a class="sidebar-link {{ request()->is('platforms*') ? 'active' : '' }}" href="{{ route('platforms.index') }}">
                    <i class="bi bi-globe text-base w-5 text-center"></i> Platforms
                </a>
                <div class="sidebar-section">Sales</div>
                <a class="sidebar-link {{ request()->is('orders*') ? 'active' : '' }}" href="{{ route('orders.index') }}">
                    <i class="bi bi-receipt text-base w-5 text-center"></i> Orders
                </a>
                <div class="sidebar-section">Finance</div>
                <a class="sidebar-link {{ request()->is('accounting*') ? 'active' : '' }}" href="{{ route('accounting.index') }}">
                    <i class="bi bi-wallet2 text-base w-5 text-center"></i> Accounting
                </a>
                <div class="sidebar-section">Insights</div>
                <a class="sidebar-link {{ request()->is('insights*') ? 'active' : '' }}" href="{{ route('insights.index') }}">
                    <i class="bi bi-bar-chart-line text-base w-5 text-center"></i> Insights
                </a>
                <div class="sidebar-section">System</div>
                <a class="sidebar-link {{ request()->is('alerts*') ? 'active' : '' }}" href="{{ route('alerts.index') }}">
                    <i class="bi bi-bell text-base w-5 text-center"></i> Alerts
                    @php $unreadCount = \App\Models\Alert::unread()->count(); @endphp
                    @if($unreadCount > 0)
                        <span class="ml-auto inline-flex items-center justify-center h-5 min-w-[20px] px-1.5 rounded-full bg-rose-500 text-[10px] font-bold text-white">{{ $unreadCount }}</span>
                    @endif
                </a>
            </nav>

            <!-- User -->
            <div class="border-t border-gray-100 p-3">
                <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 hover:bg-gray-50 transition-colors">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-bold text-white shadow-sm">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ Auth::user()->name }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ Auth::user()->email }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-rose-500 transition-colors p-1" title="Logout">
                            <i class="bi bi-box-arrow-right text-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- ═══════════════════════════════════════════
             MOBILE SIDEBAR — Slide-in overlay
             ═══════════════════════════════════════════ -->
        <div id="sidebarOverlay" class="sidebar-overlay lg:hidden" onclick="toggleMobileSidebar()"></div>
        <aside id="sidebarMobile" class="sidebar-mobile lg:hidden flex flex-col border-r border-gray-200 bg-white">
            <div class="flex h-16 items-center justify-between border-b border-gray-100 px-5">
                <div class="flex items-center gap-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-lg shadow-brand-200">
                        <i class="bi bi-shop text-lg"></i>
                    </div>
                    <span class="text-sm font-bold text-gray-900">Selling Hub</span>
                </div>
                <button onclick="toggleMobileSidebar()" class="touch-target flex items-center justify-center rounded-xl hover:bg-gray-100">
                    <i class="bi bi-x-lg text-gray-500 text-xl"></i>
                </button>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-0.5">
                <a class="sidebar-link {{ request()->is('/') ? 'active' : '' }}" href="{{ route('dashboard') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-grid-1x2 text-base w-5 text-center"></i> Dashboard
                </a>
                <div class="sidebar-section">Inventory</div>
                <a class="sidebar-link {{ request()->is('products*') ? 'active' : '' }}" href="{{ route('products.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-box-seam text-base w-5 text-center"></i> Products
                </a>
                <a class="sidebar-link {{ request()->is('suppliers*') ? 'active' : '' }}" href="{{ route('suppliers.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-truck text-base w-5 text-center"></i> Suppliers
                </a>
                <a class="sidebar-link {{ request()->is('batch-orders*') ? 'active' : '' }}" href="{{ route('batch-orders.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-cart-plus text-base w-5 text-center"></i> Batch Orders
                </a>
                <a class="sidebar-link {{ request()->is('platforms*') ? 'active' : '' }}" href="{{ route('platforms.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-globe text-base w-5 text-center"></i> Platforms
                </a>
                <div class="sidebar-section">Sales</div>
                <a class="sidebar-link {{ request()->is('orders*') ? 'active' : '' }}" href="{{ route('orders.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-receipt text-base w-5 text-center"></i> Orders
                </a>
                <div class="sidebar-section">Finance</div>
                <a class="sidebar-link {{ request()->is('accounting*') ? 'active' : '' }}" href="{{ route('accounting.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-wallet2 text-base w-5 text-center"></i> Accounting
                </a>
                <div class="sidebar-section">Insights</div>
                <a class="sidebar-link {{ request()->is('insights*') ? 'active' : '' }}" href="{{ route('insights.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-bar-chart-line text-base w-5 text-center"></i> Insights
                </a>
                <div class="sidebar-section">System</div>
                <a class="sidebar-link {{ request()->is('alerts*') ? 'active' : '' }}" href="{{ route('alerts.index') }}" onclick="toggleMobileSidebar()">
                    <i class="bi bi-bell text-base w-5 text-center"></i> Alerts
                </a>
            </nav>
            <div class="border-t border-gray-100 p-3">
                <div class="flex items-center gap-3 rounded-xl px-3 py-2.5">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-400 to-brand-600 text-sm font-bold text-white shadow-sm">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ Auth::user()->name }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ Auth::user()->email }}</p>
                    </div>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="touch-target text-gray-400 hover:text-rose-500 p-2" title="Logout">
                            <i class="bi bi-box-arrow-right text-lg"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 min-w-0 lg:pl-64 pb-20 lg:pb-0">
            <!-- Top Bar -->
            <header class="sticky top-0 z-[60] flex h-14 lg:h-16 shrink-0 items-center justify-between border-b border-gray-200 bg-white/80 backdrop-blur-xl px-4 lg:px-6">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <!-- Mobile hamburger -->
                    <button onclick="toggleMobileSidebar()" class="touch-target flex lg:hidden items-center justify-center rounded-xl hover:bg-gray-100 -ml-1">
                        <i class="bi bi-list text-2xl text-gray-700"></i>
                    </button>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-base lg:text-lg font-bold text-gray-900 truncate">@yield('title', 'Dashboard')</h1>
                        @hasSection('subtitle')
                            <p class="text-[11px] lg:text-xs text-gray-500 -mt-0.5 truncate">@yield('subtitle')</p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 lg:gap-3 shrink-0 ml-2 min-w-0 max-w-[65%] sm:max-w-[50%] lg:max-w-none overflow-x-auto">
                    @yield('actions')
                </div>
            </header>

            <!-- Page Content -->
            <div class="p-4 lg:p-6 fade-in">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="mb-4 flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="alert">
                        <i class="bi bi-check-circle-fill text-emerald-500 shrink-0"></i>
                        <span class="flex-1 min-w-0">{{ session('success') }}</span>
                        <button onclick="this.parentElement.remove()" class="touch-target flex items-center justify-center text-emerald-500 hover:text-emerald-700 shrink-0">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                @endif
                @if(session('error'))
                    <div class="mb-4 flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500 shrink-0"></i>
                        <span class="flex-1 min-w-0">{{ session('error') }}</span>
                        <button onclick="this.parentElement.remove()" class="touch-target flex items-center justify-center text-rose-500 hover:text-rose-700 shrink-0">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- ═══════════════════════════════════════════
         BOTTOM NAV — Mobile only (5 items max)
         ═══════════════════════════════════════════ -->
    <nav class="fixed bottom-0 inset-x-0 z-50 lg:hidden bg-white border-t border-gray-200 pb-safe" style="box-shadow: 0 -2px 10px rgba(0,0,0,0.06);">
        <div class="grid grid-cols-5 h-16">
            <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->is('/') ? 'active' : '' }} flex flex-col items-center justify-center gap-1 text-gray-500 hover:text-brand-600 transition-colors">
                <div class="nav-icon flex h-8 items-center justify-center rounded-xl px-3 transition-colors">
                    <i class="bi bi-grid-1x2 text-lg"></i>
                </div>
                <span class="text-[10px] font-medium">Home</span>
            </a>
            <a href="{{ route('products.index') }}" class="bottom-nav-item {{ request()->is('products*') ? 'active' : '' }} flex flex-col items-center justify-center gap-1 text-gray-500 hover:text-brand-600 transition-colors">
                <div class="nav-icon flex h-8 items-center justify-center rounded-xl px-3 transition-colors">
                    <i class="bi bi-box-seam text-lg"></i>
                </div>
                <span class="text-[10px] font-medium">Products</span>
            </a>
            <a href="{{ route('orders.index') }}" class="bottom-nav-item {{ request()->is('orders*') ? 'active' : '' }} flex flex-col items-center justify-center gap-1 text-gray-500 hover:text-brand-600 transition-colors">
                <div class="nav-icon flex h-8 items-center justify-center rounded-xl px-3 transition-colors">
                    <i class="bi bi-receipt text-lg"></i>
                </div>
                <span class="text-[10px] font-medium">Orders</span>
            </a>
            <a href="{{ route('suppliers.index') }}" class="bottom-nav-item {{ request()->is('suppliers*') ? 'active' : '' }} flex flex-col items-center justify-center gap-1 text-gray-500 hover:text-brand-600 transition-colors">
                <div class="nav-icon flex h-8 items-center justify-center rounded-xl px-3 transition-colors">
                    <i class="bi bi-truck text-lg"></i>
                </div>
                <span class="text-[10px] font-medium">Suppliers</span>
            </a>
            <a href="{{ route('accounting.index') }}" class="bottom-nav-item {{ request()->is('accounting*') ? 'active' : '' }} flex flex-col items-center justify-center gap-1 text-gray-500 hover:text-brand-600 transition-colors">
                <div class="nav-icon flex h-8 items-center justify-center rounded-xl px-3 transition-colors">
                    <i class="bi bi-wallet2 text-lg"></i>
                </div>
                <span class="text-[10px] font-medium">Accounting</span>
            </a>
        </div>
    </nav>

    <!-- Mobile sidebar toggle script -->
    <script>
        (function () {
            var sidebar = document.getElementById('sidebarMobile');
            var overlay = document.getElementById('sidebarOverlay');

            window.toggleMobileSidebar = function () {
                var open = !sidebar.classList.contains('active');
                sidebar.classList.toggle('active', open);
                overlay.classList.toggle('active', open);
                document.body.classList.toggle('sidebar-open', open);
                if (open) {
                    var first = sidebar.querySelector('a, button');
                    if (first) first.focus();
                }
            };

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                    window.toggleMobileSidebar();
                }
            });
        })();
    </script>

    @stack('scripts')
    @yield('scripts')
</body>
</html>
