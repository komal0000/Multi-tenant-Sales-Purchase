<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register | Sales, Purchase & Ledger System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-800">
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -right-10 top-12 h-44 w-44 rounded-full bg-emerald-200/70 blur-2xl"></div>
            <div class="absolute bottom-10 left-8 h-52 w-52 rounded-full bg-cyan-200/60 blur-2xl"></div>
        </div>

        <div class="relative w-full max-w-5xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="grid lg:grid-cols-2">
                <section class="hidden bg-gradient-to-br from-emerald-600 via-teal-700 to-cyan-600 p-10 text-white lg:block">
                    <p class="text-sm uppercase tracking-[0.2em] text-emerald-100">LedgerApp</p>
                    <h1 class="mt-5 text-3xl font-semibold leading-tight">Create your workspace and start managing business records instantly.</h1>
                    <p class="mt-4 text-sm text-emerald-100">Register once, and your tenant workspace is ready with an owner account.</p>
                </section>
                <section class="p-6 sm:p-8 lg:p-10">
                    <div class="mb-8">
                        <p class="text-sm uppercase tracking-[0.2em] text-emerald-600">Get started</p>
                        <h2 class="mt-2 text-2xl font-semibold text-slate-900">Create your LedgerApp account</h2>
                        <p class="mt-2 text-sm text-slate-500">Use a Nepal mobile number (98XXXXXXXX) for your login username.</p>
                    </div>

                    @if ($errors->any())
                        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="business_name" class="mb-2 block text-sm font-medium text-slate-700">Business Name</label>
                            <input
                                id="business_name"
                                type="text"
                                name="business_name"
                                value="{{ old('business_name') }}"
                                required
                                autofocus
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                placeholder="Your business or organization name"
                            >
                        </div>

                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-slate-700">Your Name</label>
                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                required
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                placeholder="Owner or administrator name"
                            >
                        </div>

                        <div>
                            <label for="phone" class="mb-2 block text-sm font-medium text-slate-700">Phone Number</label>
                            <input
                                id="phone"
                                type="tel"
                                name="phone"
                                value="{{ old('phone') }}"
                                required
                                autocomplete="username"
                                inputmode="numeric"
                                pattern="[0-9]{10}"
                                minlength="10"
                                maxlength="10"
                                oninput="this.value = this.value.replace(/\D/g, '').slice(0, 10)"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                placeholder="98XXXXXXXX"
                            >
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email (optional)</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                placeholder="owner@company.com"
                            >
                        </div>

                        <div>
                            <label for="password" class="mb-2 block text-sm font-medium text-slate-700">Password</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                placeholder="Minimum 8 characters"
                            >
                        </div>

                        <div>
                            <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-700">Confirm Password</label>
                            <input
                                id="password_confirmation"
                                type="password"
                                name="password_confirmation"
                                required
                                autocomplete="new-password"
                                class="w-full rounded-xl border border-slate-300 bg-white px-4 py-1.5 text-sm text-slate-800 outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200"
                                placeholder="Re-enter your password"
                            >
                        </div>

                        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                            Register & Start
                        </button>

                        <p class="text-center text-sm text-slate-600">
                            Already have an account?
                            <a href="{{ route('login') }}" class="font-semibold text-emerald-700 hover:text-emerald-800">Login here</a>
                        </p>
                    </form>
                </section>
            </div>
        </div>
    </div>
</body>
</html>
