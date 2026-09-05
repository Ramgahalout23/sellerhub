<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Selling Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
</head>
<body class="h-full font-sans antialiased bg-gray-50 flex items-center justify-center">
    <div class="w-full max-w-md px-6">
        <div class="text-center mb-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-lg shadow-brand-200 mb-4">
                <i class="bi bi-shop text-xl"></i>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Forgot password?</h2>
            <p class="mt-1.5 text-sm text-gray-500">Enter your email to receive a reset link</p>
        </div>
        @if(session('status'))<div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"><i class="bi bi-check-circle-fill text-emerald-500"></i>{{ session('status') }}</div>@endif
        <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email address</label>
                    <input type="email" name="email" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none @error('email') border-rose-300 @enderror" value="{{ old('email') }}" required autofocus>
                    @error('email')<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-200 hover:bg-brand-700 transition-all">Send Reset Link</button>
            </form>
        </div>
        <p class="mt-6 text-center"><a href="{{ route('login') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700"><i class="bi bi-arrow-left me-1"></i>Back to login</a></p>
    </div>
</body>
</html>
