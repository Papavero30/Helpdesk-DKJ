<?php

use App\Livewire\Admin\ActivityLog;
use App\Livewire\Admin\AllTickets;
use App\Livewire\Admin\Report;
use App\Livewire\Alerts;
use App\Livewire\Dashboard;
use App\Livewire\Manager\AllTickets as ManagerAllTickets;
use App\Livewire\Manager\MasterData;
use App\Livewire\Manager\Oversight;
use App\Livewire\Manager\People;
use App\Livewire\MyTickets;
use App\Livewire\TicketDetail;
use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// === Auth ===
Route::get('/login', fn () => view('auth.login'))->middleware('guest')->name('login');

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    $karyawan = Karyawan::query()
        ->whereRaw('LOWER(email) = ?', [strtolower($credentials['email'])])
        ->first();

    $user = $karyawan?->user;

    if ($user && Hash::check($credentials['password'], $user->password)) {
        Auth::login($user, true);

        if ($user->status_akun !== 'active') {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Your account is inactive. Please contact an administrator.',
            ])->onlyInput('email');
        }

        $user->update(['terakhir_masuk' => now()]);
        $request->session()->regenerate();

        return redirect('/');
    }

    return back()->withErrors([
        'email' => 'Incorrect email or password.',
    ])->onlyInput('email');
})->middleware('guest');

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->middleware('auth');

Route::get('/health', function () {
    $dbOk = true;
    try {
        DB::select('select 1');
    } catch (Throwable) {
        $dbOk = false;
    }

    return response()->json([
        'app' => 'ok',
        'db' => $dbOk,
        'queue_pending' => $dbOk ? DB::table('jobs')->count() : null,
        'queue_failed' => $dbOk ? DB::table('failed_jobs')->count() : null,
        'scheduler_last_beat' => Cache::get('scheduler-heartbeat'),
    ], $dbOk ? 200 : 503);
})->name('health');

// === Authenticated Pages ===
Route::middleware('auth')->group(function () {
    // Both roles
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/ticket/{tiket}', TicketDetail::class)->name('ticket.detail');
    Route::get('/alerts', Alerts::class)->name('alerts');

    // Karyawan only
    Route::redirect('/new-ticket', '/my-tickets')->name('new-ticket');
    Route::get('/my-tickets', MyTickets::class)->name('my-tickets');

    // Admin only
    Route::redirect('/admin/panel', '/')->name('admin.panel');
    Route::get('/admin/all-tickets', AllTickets::class)->name('admin.all-tickets');
    Route::get('/admin/activity-log', ActivityLog::class)->name('admin.activity-log');
    Route::get('/admin/report', Report::class)->name('admin.report');

    Route::get('/manager/master-data', MasterData::class)->name('manager.master-data');
    Route::get('/manager/people', People::class)->name('manager.people');
    Route::get('/manager/oversight', Oversight::class)->name('manager.oversight');
    Route::get('/manager/all-tickets', ManagerAllTickets::class)->name('manager.all-tickets');
    Route::get('/admin/master-data', MasterData::class)->name('admin.master-data');
    Route::get('/admin/people', People::class)->name('admin.people');
});
