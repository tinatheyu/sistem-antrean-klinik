<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'))
                ->with('error', 'Silakan masuk terlebih dahulu untuk mengakses halaman ini.');
        }

        $user = Auth::user();

        // Check if user's account is active
        if ($user->status !== 'aktif') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Akun Anda dinonaktifkan. Silakan hubungi administrator.');
        }

        if (! in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akses ditolak: Anda tidak memiliki izin untuk halaman ini.',
                ], 403);
            }

            // Redirect user to their designated dashboard
            $targetRoute = match ($user->role) {
                'admin' => 'admin.dashboard',
                'dokter' => 'doctor.dashboard',
                'pasien' => 'pasien.dashboard',
                default => 'login',
            };

            return redirect()->route($targetRoute)
                ->with('error', 'Akses Ditolak (403): Anda tidak memiliki izin untuk membuka halaman tersebut.');
        }

        return $next($request);
    }
}
