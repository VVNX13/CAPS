<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('owner.dashboard');
        }

        return view('owner.login');
    }

    /**
     * Authenticate with the account username.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'username' => ['required', 'string'],
                'password' => ['required', 'string'],
            ], [
                'username.required' => 'Username dan password wajib diisi',
                'password.required' => 'Username dan password wajib diisi',
            ]);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->validator->errors()->first(), 422, $e->errors());
        }

        if (! Auth::attempt([
            'username' => $credentials['username'],
            'password' => $credentials['password'],
        ])) {
            return ApiResponse::error('Username atau password salah');
        }

        $request->session()->regenerate();

        $user = $request->user();

        return ApiResponse::success([
            'redirect' => route('owner.dashboard'),
            'role' => $user->role,
            'name' => $user->name,
        ], 'Login berhasil');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
