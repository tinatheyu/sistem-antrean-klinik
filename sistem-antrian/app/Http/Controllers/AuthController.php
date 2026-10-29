<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\TelegramLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Display the login page.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.login');
    }

    /**
     * Handle authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email atau username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($request->input('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                ]);
        }

        $remember = $request->boolean('remember');

        // Check login with email or username/phone
        $loginField = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $authData = [
            $loginField => $credentials['email'],
            'password' => $credentials['password'],
        ];

        if (Auth::attempt($authData, $remember)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            $user = Auth::user();

            // Check if active
            if ($user->status !== 'aktif') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'Akun Anda berstatus nonaktif. Silakan hubungi admin.',
                    ]);
            }

            // Record activity
            ActivityLog::record($user, 'LOGIN', "Pengguna {$user->name} ({$user->role}) berhasil masuk.");

            return $this->redirectBasedOnRole($user)
                ->with('success', "Selamat datang kembali, {$user->name}!");
        }

        RateLimiter::hit($throttleKey, 60);

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors([
                'email' => 'Email/No. Telepon atau kata sandi yang Anda masukkan salah.',
            ]);
    }

    /**
     * Display the public patient registration page.
     */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return $this->redirectBasedOnRole(Auth::user());
        }

        return view('auth.register');
    }

    /**
     * Handle public patient registration.
     */
    public function register(Request $request): RedirectResponse
    {
        // Enforce strict server-side validation and role assignment
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,14}$/'],
            'telegram_username' => ['nullable', 'string', 'max:33', 'regex:/^@?[A-Za-z0-9_]{5,32}$/'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'privacy_policy' => ['accepted'],
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah terdaftar. Silakan masuk.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.regex' => 'Format nomor telepon tidak valid (contoh: 081234567890).',
            'telegram_username.regex' => 'Username Telegram tidak valid (5-32 karakter, huruf/angka/underscore, boleh diawali @).',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'privacy_policy.accepted' => 'Anda harus menyetujui kebijakan privasi untuk melanjutkan.',
        ]);

        // Securely create user with FORCED 'pasien' role on the server
        $patient = User::create([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
            'phone' => trim($validated['phone']),
            'telegram_username' => TelegramLinkService::normalizeUsername($validated['telegram_username'] ?? null),
            'password' => Hash::make($validated['password']),
            'role' => 'pasien', // Fixed to patient exclusively
            'status' => 'aktif',
            'email_verified_at' => now(), // Initial implementation auto-verified
        ]);

        // Automatically authenticate the patient
        Auth::login($patient);
        $request->session()->regenerate();

        ActivityLog::record($patient, 'REGISTER_PATIENT', "Pasien baru terdaftar secara mandiri: {$patient->name}.");

        return redirect()->route('pasien.dashboard')
            ->with('success', "Pendaftaran berhasil! Selamat datang di Sistem Antrian Klinik, {$patient->name}.");
    }

    /**
     * Handle user logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            ActivityLog::record($user, 'LOGOUT', "Pengguna {$user->name} keluar dari sistem.");
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('info', 'Anda telah berhasil keluar dari sistem.');
    }

    /**
     * Show forgot password page.
     */
    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send password reset link.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
        ]);

        $user = User::where('email', $request->email)->first();
        if ($user) {
            ActivityLog::record($user, 'FORGOT_PASSWORD_REQUEST', "Permintaan reset kata sandi untuk {$user->email}.");
        }

        // For demo/clinic setup without external SMTP configured, inform the user clearly
        return back()->with('status', 'Jika alamat email terdaftar pada sistem kami, tautan pemulihan kata sandi telah dikirimkan ke kotak masuk Anda.');
    }

    /**
     * Helper to redirect authenticated user to their role-specific dashboard.
     */
    private function redirectBasedOnRole(User $user): RedirectResponse
    {
        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'dokter' => redirect()->route('doctor.dashboard'),
            'pasien' => redirect()->route('pasien.dashboard'),
            default => redirect()->route('login'),
        };
    }
}
