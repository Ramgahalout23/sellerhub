<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Selling Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .gradient-emerald {
            background:
                radial-gradient(ellipse at 20% 50%, rgba(16, 185, 129, 0.2) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(52, 211, 153, 0.12) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(4, 120, 87, 0.6) 0%, transparent 50%),
                linear-gradient(135deg, #022c22 0%, #064e3b 40%, #065f46 100%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .input-group { border: 2px solid #e5e7eb; background: #fafafa; transition: all 0.2s ease; border-radius: 1rem; }
        .input-group:focus-within { border-color: #047857; background: #fff; box-shadow: 0 0 0 3px rgba(4, 120, 87, 0.1); }
        .emerald-line { background: linear-gradient(90deg, transparent, #047857, transparent); }
        .btn-emerald {
            background: linear-gradient(135deg, #047857 0%, #065f46 100%);
            box-shadow: 0 4px 20px rgba(4, 120, 87, 0.35);
            transition: all 0.3s ease;
        }
        .btn-emerald:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            box-shadow: 0 8px 30px rgba(4, 120, 87, 0.5);
            transform: translateY(-1px);
        }
        .btn-emerald:active { transform: translateY(0); }
        .dot-pattern {
            background-image: radial-gradient(circle at 1px 1px, rgba(4, 120, 87, 0.06) 1px, transparent 0);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="h-full font-sans antialiased">
    <div class="flex min-h-screen">

        <!-- Left Panel — Brand -->
        <div class="hidden lg:flex lg:w-[55%] relative overflow-hidden gradient-emerald">
            <!-- Animated background -->
            <div class="absolute inset-0">
                <div class="absolute top-28 left-16 h-80 w-80 rounded-full bg-brand-400/[0.08] blur-3xl animate-pulse-slow"></div>
                <div class="absolute bottom-20 right-20 h-[500px] w-[500px] rounded-full bg-brand-300/[0.05] blur-3xl animate-pulse-slow" style="animation-delay: 2s;"></div>
                <div class="absolute top-1/2 left-1/3 h-64 w-64 rounded-full bg-white/[0.02] blur-2xl animate-float"></div>
            </div>

            <!-- Top accent line -->
            <div class="absolute top-0 left-0 w-full h-px emerald-line opacity-40"></div>

            <div class="relative z-10 flex flex-col justify-center px-16 xl:px-20 text-white">
                <!-- Logo -->
                <div class="flex items-center gap-3 mb-14 animate-slide-in" style="animation-delay: 0.1s;">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-brand-400/30 bg-brand-400/15 backdrop-blur-md">
                        <i class="bi bi-shop-window text-brand-300 text-xl"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight text-white">Selling Hub</span>
                </div>

                <!-- Heading -->
                <div class="animate-fade-in" style="animation-delay: 0.2s;">
                    <h1 class="text-4xl xl:text-5xl font-extrabold leading-[1.1] tracking-tight mb-5">
                        Run your<br>
                        <span class="text-brand-300">e-commerce</span><br>
                        like a pro
                    </h1>
                    <p class="text-brand-200/50 text-lg max-w-md leading-relaxed">
                        Track inventory, manage suppliers, monitor orders, and grow your profits — all from a single dashboard.
                    </p>
                </div>

                <!-- Emerald separator -->
                <div class="mt-10 h-px w-20 emerald-line opacity-50 animate-fade-in" style="animation-delay: 0.35s;"></div>

                <!-- Feature cards -->
                <div class="mt-8 space-y-3 animate-fade-in" style="animation-delay: 0.4s;">
                    @php
                    $features = [
                        ['icon' => 'bi-diagram-3', 'label' => 'Multi-platform tracking', 'desc' => 'Meesho, Amazon & more'],
                        ['icon' => 'bi-graph-up-arrow', 'label' => 'Real-time P&L analytics', 'desc' => 'Know your margins instantly'],
                        ['icon' => 'bi-truck', 'label' => 'Supplier management', 'desc' => 'Orders, payments & batch tracking'],
                        ['icon' => 'bi-box-seam', 'label' => 'Inventory & stock alerts', 'desc' => 'Never run out of bestsellers'],
                    ];
                    @endphp
                    @foreach($features as $i => $f)
                    <div class="glass-card flex items-center gap-4 rounded-2xl px-5 py-3.5 transition-all duration-300 hover:bg-white/[0.08] hover:border-brand-400/20 cursor-default" style="animation-delay: {{ 0.5 + $i * 0.1 }}s;">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-400/15 border border-brand-400/10 flex-shrink-0">
                            <i class="bi {{ $f['icon'] }} text-brand-300 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white">{{ $f['label'] }}</p>
                            <p class="text-xs text-white/30">{{ $f['desc'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Stats -->
                <div class="mt-12 flex items-center gap-8 animate-fade-in" style="animation-delay: 0.9s;">
                    @php
                    $stats = [
                        ['value' => '50K+', 'label' => 'Orders tracked'],
                        ['value' => '99.9%', 'label' => 'Uptime'],
                        ['value' => '24/7', 'label' => 'Support'],
                    ];
                    @endphp
                    @foreach($stats as $s)
                    <div>
                        <p class="text-2xl font-extrabold text-brand-300">{{ $s['value'] }}</p>
                        <p class="text-xs text-white/25 mt-0.5">{{ $s['label'] }}</p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Right Panel — Login Form -->
        <div class="flex flex-1 items-center justify-center px-6 py-12 bg-white relative">
            <!-- Subtle dot pattern -->
            <div class="absolute inset-0 dot-pattern"></div>

            <div class="w-full max-w-[400px] relative z-10">
                <!-- Mobile brand -->
                <div class="flex items-center gap-2.5 mb-10 lg:hidden animate-fade-in">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-brand-200 bg-brand-50">
                        <i class="bi bi-shop-window text-brand-600 text-lg"></i>
                    </div>
                    <span class="text-xl font-bold text-gray-900 tracking-tight">Selling Hub</span>
                </div>

                <!-- Welcome text -->
                <div class="animate-fade-in" style="animation-delay: 0.15s;">
                    <h2 class="text-2xl font-extrabold text-gray-900 tracking-tight">Welcome back</h2>
                    <p class="mt-1.5 text-sm text-gray-400">Enter your credentials to access your dashboard</p>
                </div>

                <!-- Emerald accent line -->
                <div class="mt-5 h-0.5 w-12 rounded-full bg-brand-500/40 animate-fade-in" style="animation-delay: 0.2s;"></div>

                @if(session('status'))
                <div class="mt-5 flex items-center gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-700 animate-fade-in">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 flex-shrink-0">
                        <i class="bi bi-check-circle-fill text-emerald-500"></i>
                    </div>
                    <span class="font-medium">{{ session('status') }}</span>
                </div>
                @endif

                @if(session('error'))
                <div class="mt-5 flex items-center gap-3 rounded-2xl border border-rose-200/80 bg-rose-50 px-4 py-3.5 text-sm text-rose-700 animate-fade-in">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 flex-shrink-0">
                        <i class="bi bi-exclamation-triangle-fill text-rose-500"></i>
                    </div>
                    <span class="font-medium">{{ session('error') }}</span>
                </div>
                @endif

                <!-- Login Form -->
                <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5">
                    @csrf

                    <!-- Email -->
                    <div class="animate-fade-in" style="animation-delay: 0.2s;">
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email address</label>
                        <div class="input-group relative flex items-center @error('email') border-rose-300 @enderror">
                            <div class="absolute left-4 text-gray-400 pointer-events-none">
                                <i class="bi bi-envelope text-base"></i>
                            </div>
                            <input type="email" id="email" name="email"
                                   class="w-full bg-transparent py-3.5 pl-12 pr-4 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none rounded-2xl"
                                   value="{{ old('email') }}" required autofocus placeholder="you@company.com">
                        </div>
                        @error('email')
                            <p class="mt-2 text-xs font-medium text-rose-600 flex items-center gap-1.5">
                                <i class="bi bi-info-circle"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Password -->
                    <div class="animate-fade-in" style="animation-delay: 0.3s;">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <div class="input-group relative flex items-center @error('password') border-rose-300 @enderror">
                            <div class="absolute left-4 text-gray-400 pointer-events-none">
                                <i class="bi bi-lock text-base"></i>
                            </div>
                            <input type="password" id="password" name="password"
                                   class="w-full bg-transparent py-3.5 pl-12 pr-12 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none rounded-2xl"
                                   required placeholder="Enter your password">
                            <button type="button" onclick="togglePassword()" class="absolute right-3.5 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 transition-all">
                                <i id="eye-open" class="bi bi-eye text-base"></i>
                                <i id="eye-closed" class="bi bi-eye-slash text-base hidden"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="mt-2 text-xs font-medium text-rose-600 flex items-center gap-1.5">
                                <i class="bi bi-info-circle"></i> {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- Remember & Forgot -->
                    <div class="flex items-center justify-between animate-fade-in" style="animation-delay: 0.35s;">
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input type="checkbox" name="remember"
                                   class="h-4 w-4 rounded-lg border-gray-300 focus:ring-2 focus:ring-offset-0 transition-all" style="accent-color: #047857;">
                            <span class="text-sm text-gray-500 group-hover:text-gray-700 transition-colors">Remember me</span>
                        </label>
                        <a href="{{ route('password.request') }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                            Forgot password?
                        </a>
                    </div>

                    <!-- Submit -->
                    <div class="animate-fade-in pt-1" style="animation-delay: 0.4s;">
                        <button type="submit"
                                class="w-full group relative flex items-center justify-center gap-2.5 rounded-2xl btn-emerald px-4 py-3.5 text-sm font-bold text-white tracking-wide focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:translate-y-0">
                            <i class="bi bi-box-arrow-in-right text-base transition-transform group-hover:translate-x-0.5"></i>
                            Sign in to Dashboard
                        </button>
                    </div>
                </form>

                <!-- Footer -->
                <div class="mt-10 text-center animate-fade-in" style="animation-delay: 0.5s;">
                    <div class="flex items-center justify-center gap-3 mb-3">
                        <div class="h-px w-8 bg-brand-200/40"></div>
                        <span class="text-[10px] font-semibold tracking-[0.2em] text-gray-300 uppercase">Selling Hub</span>
                        <div class="h-px w-8 bg-brand-200/40"></div>
                    </div>
                    <p class="text-xs text-gray-400">
                        E-commerce Inventory Management
                    </p>
                    <div class="mt-3 flex items-center justify-center gap-4 text-[11px] text-gray-300">
                        <span>© {{ date('Y') }} All rights reserved</span>
                        <span class="text-brand-300/30">•</span>
                        <a href="#" class="hover:text-brand-500 transition-colors">Privacy</a>
                        <span class="text-brand-300/30">•</span>
                        <a href="#" class="hover:text-brand-500 transition-colors">Terms</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const pw = document.getElementById('password');
            const eyeOpen = document.getElementById('eye-open');
            const eyeClosed = document.getElementById('eye-closed');
            if (pw.type === 'password') {
                pw.type = 'text';
                eyeOpen.classList.add('hidden');
                eyeClosed.classList.remove('hidden');
            } else {
                pw.type = 'password';
                eyeOpen.classList.remove('hidden');
                eyeClosed.classList.add('hidden');
            }
        }
    </script>
</body>
</html>
