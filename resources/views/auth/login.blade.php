<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Sales, Purchase & Ledger System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-12 top-10 h-44 w-44 rounded-full bg-indigo-200/70 blur-2xl"></div>
            <div class="absolute bottom-8 right-8 h-52 w-52 rounded-full bg-cyan-200/60 blur-2xl"></div>
        </div>

        <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="grid lg:grid-cols-2">
                <section class="hidden bg-gradient-to-br from-indigo-600 via-indigo-700 to-cyan-600 p-10 text-white lg:block">
                    <p class="text-sm uppercase tracking-[0.2em] text-indigo-100">LedgerApp</p>
                    <h1 class="mt-5 text-3xl font-semibold leading-tight">Sales, Purchase, and Ledger control in one workspace.</h1>
                </section>
                <section class="p-6 sm:p-8 lg:p-10">
                    <div class="mb-8">
                        <p class="text-sm uppercase tracking-[0.2em] text-indigo-600">Welcome back</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">Sign in to LedgerApp</h2>
                        <p class="mt-2 text-sm text-slate-500">Use your phone number username to access the system.</p>
                    </div>

                    @if ($errors->any())
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                        @csrf
                        <div>
                            <label for="phone" class="mb-2 block text-sm font-medium text-slate-700">Phone Number</label>
                            <input
                                id="phone"
                                type="tel"
                                name="phone"
                                value="{{ old('phone') }}"
                                required
                                autofocus
                                autocomplete="username"
                                inputmode="numeric"
                                pattern="[0-9]{10}"
                                minlength="10"
                                maxlength="10"
                                oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                placeholder="98XXXXXXXX"
                            >
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200"
                                placeholder="Enter your password"
                            >
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }} class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Remember me
                        </label>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-300">
                            Login
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</body>
</html>
