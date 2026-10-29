<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisplayController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Sistem Antrian Terpusat Tiga Peran
|--------------------------------------------------------------------------
*/

// Root redirect
Route::get('/', function () {
    if (Auth::check()) {
        return match (Auth::user()?->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'dokter' => redirect()->route('doctor.dashboard'),
            'pasien' => redirect()->route('pasien.dashboard'),
            default => redirect()->route('login'),
        };
    }

    return redirect()->route('login');
});

// Authentication Routes (Public)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Dedicated public patient registration
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
});

// Logout (Authenticated)
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Public Display & QR Code Verification Routes
Route::get('/tiket/{token}', [DisplayController::class, 'verifyTicket'])->name('ticket.verify');
Route::get('/display', [DisplayController::class, 'monitor'])->name('display.monitor');
Route::get('/api/display', [DisplayController::class, 'monitorApi'])->name('display.api');

// Webhook Bot Telegram (dilindungi secret token, tanpa autentikasi sesi pengguna)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

/*
|--------------------------------------------------------------------------
| 1. Admin Routes (/admin/*)
| Protected by: auth, role:admin, prevent-back-history
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin', 'prevent-back-history'])->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');

    // Manage Patients
    Route::get('/patients', [AdminController::class, 'patients'])->name('patients');
    Route::post('/patients/{user}/toggle', [AdminController::class, 'togglePatientStatus'])->name('patients.toggle');

    // Register Walk-in Patients
    Route::get('/walkin', [AdminController::class, 'showWalkInForm'])->name('walkin');
    Route::post('/walkin', [AdminController::class, 'storeWalkIn'])->name('walkin.store');

    // Centralized Queue Management
    Route::get('/queues', [AdminController::class, 'queues'])->name('queues');
    Route::post('/queues/{queue}/status', [AdminController::class, 'updateQueueStatus'])->name('queues.status');
    Route::post('/queues/{queue}/verify-priority', [AdminController::class, 'verifyPriority'])->name('queues.verify-priority');

    // Doctor Practice Schedules
    Route::get('/schedules', [AdminController::class, 'schedules'])->name('schedules');
    Route::post('/schedules', [AdminController::class, 'storeSchedule'])->name('schedules.store');
    Route::post('/schedules/{schedule}/toggle', [AdminController::class, 'toggleSchedule'])->name('schedules.toggle');
    Route::delete('/schedules/{schedule}', [AdminController::class, 'deleteSchedule'])->name('schedules.destroy');

    // Authorized Doctor Management
    Route::get('/doctors', [AdminController::class, 'doctors'])->name('doctors');
    Route::post('/doctors', [AdminController::class, 'storeDoctor'])->name('doctors.store');
    Route::post('/doctors/{doctor}/toggle', [AdminController::class, 'toggleDoctorStatus'])->name('doctors.toggle');

    // QR Codes and Barcode Records
    Route::get('/qrcodes', [AdminController::class, 'qrcodes'])->name('qrcodes');

    // Reports and Data Export
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');

    // Application Settings
    Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');

    // Activity Logs
    Route::get('/logs', [AdminController::class, 'logs'])->name('logs');
});

/*
|--------------------------------------------------------------------------
| 2. Doctor Routes (/doctor/*)
| Protected by: auth, role:dokter, prevent-back-history
|--------------------------------------------------------------------------
*/
Route::prefix('doctor')->name('doctor.')->middleware(['auth', 'role:dokter', 'prevent-back-history'])->group(function () {
    Route::get('/dashboard', [DoctorController::class, 'dashboard'])->name('dashboard');

    // Queue actions
    Route::post('/call-next', [DoctorController::class, 'callNext'])->name('call-next');
    Route::post('/recall/{queue}', [DoctorController::class, 'recall'])->name('recall');
    Route::post('/start/{queue}', [DoctorController::class, 'startExam'])->name('start');
    Route::post('/complete/{queue}', [DoctorController::class, 'completeExam'])->name('complete');
    Route::post('/skip/{queue}', [DoctorController::class, 'skipPatient'])->name('skip');

    // Service History
    Route::get('/history', [DoctorController::class, 'serviceHistory'])->name('history');

    // Live Queue API
    Route::get('/api/live', [DoctorController::class, 'liveQueueApi'])->name('live.api');
});

/*
|--------------------------------------------------------------------------
| 3. Patient Routes (/pasien/*)
| Protected by: auth, role:pasien, prevent-back-history
|--------------------------------------------------------------------------
*/
Route::prefix('pasien')->name('pasien.')->middleware(['auth', 'role:pasien', 'prevent-back-history'])->group(function () {
    Route::get('/dashboard', [PatientController::class, 'dashboard'])->name('dashboard');

    // Queue Registration
    Route::post('/register-queue', [PatientController::class, 'registerQueue'])->name('register-queue');

    // Digital Queue Ticket (Protected by authorization check in controller)
    Route::get('/ticket/{token}', [PatientController::class, 'showTicket'])->name('ticket');
    Route::get('/ticket/{token}/api', [PatientController::class, 'ticketStatusApi'])->name('ticket.api');

    // Personal Queue History
    Route::get('/history', [PatientController::class, 'history'])->name('history');

    // Profile Management
    Route::get('/profile', [PatientController::class, 'profile'])->name('profile');
    Route::post('/profile', [PatientController::class, 'updateProfile'])->name('profile.update');

    // Penghubungan akun Telegram (token unik, sekali pakai)
    Route::post('/telegram/refresh', [PatientController::class, 'refreshTelegramLink'])->name('telegram.refresh');
    Route::post('/telegram/unlink', [PatientController::class, 'unlinkTelegram'])->name('telegram.unlink');
});
