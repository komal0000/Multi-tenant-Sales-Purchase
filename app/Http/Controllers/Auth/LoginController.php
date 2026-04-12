<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(private readonly Factory $auth)
    {
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $guard = $this->auth->guard();

        $credentials = $request->validated();
        $credentials['phone'] = (int) $credentials['phone'];

        $remember = $request->boolean('remember');

        if (! $guard->attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('phone', 'remember'))
                ->withErrors([
                    'phone' => 'The provided credentials do not match our records.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->auth->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
