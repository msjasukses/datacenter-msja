<?php

use App\Http\Controllers\AccountStatusController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Datacenter\GuruController;
use App\Http\Controllers\Datacenter\GuruMapelController;
use App\Http\Controllers\Datacenter\JurusanController;
use App\Http\Controllers\Datacenter\MataPelajaranController;
use App\Http\Controllers\Datacenter\PengaturanController;
use App\Http\Controllers\Datacenter\PeriodikalController;
use App\Http\Controllers\Datacenter\RombelController;
use App\Http\Controllers\Datacenter\SiswaController;
use App\Http\Controllers\Datacenter\TahunAjaranController;
use App\Http\Controllers\Datacenter\TingkatKelasController;
use App\Http\Controllers\LogLoginController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\ProfilController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth('admin')->check() ? 'dashboard' : 'login');
});

// Halaman status akun (selalu accessible, tanpa auth)
Route::get('/account/suspended', [AccountStatusController::class, 'suspended'])->name('account.suspended');
Route::get('/account/locked',    [AccountStatusController::class, 'locked'])->name('account.locked');
Route::get('/account/inactive',  [AccountStatusController::class, 'inactive'])->name('account.inactive');

Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:30,1')
        ->name('login.post');
});

// Endpoint refresh CSRF token — dipakai login form di mobile sebelum submit
// untuk menghindari 419 saat halaman lama di-cache browser.
Route::get('/csrf-refresh', function () {
    return response()->json(['token' => csrf_token()])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
})->name('csrf.refresh');

Route::middleware([
    'auth:admin',
    'accountstatus',   // cek account_status & locked_until
    'otp',             // paksa verifikasi OTP jika otp_enabled
])->group(function () {

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/otp', [OtpController::class, 'show'])->name('otp.show');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
    Route::post('/otp/verify', [OtpController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('otp.verify');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profil', [ProfilController::class, 'index'])->name('profil.index');
    Route::put('/profil/password', [ProfilController::class, 'updatePassword'])->name('profil.password');

    Route::middleware(['role:admin', 'rbac'])->group(function () {
        Route::get('/pengaturan', [PengaturanController::class, 'index'])->name('pengaturan.index');
        Route::put('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');

        Route::resource('pengguna', PenggunaController::class)->except('show')
            ->parameters(['pengguna' => 'pengguna']);

        Route::resource('tahun-ajaran', TahunAjaranController::class)
            ->except('show')->parameters(['tahun-ajaran' => 'tahunAjaran']);
        Route::resource('jurusan', JurusanController::class)->except('show');
        Route::resource('mapel', MataPelajaranController::class)->except('show')
            ->parameters(['mapel' => 'mapel']);
        Route::get('/rombel/import',          [RombelController::class, 'importForm'])->name('rombel.import.form');
        Route::post('/rombel/import',         [RombelController::class, 'importStore'])->name('rombel.import.store');
        Route::get('/rombel/import-template', [RombelController::class, 'importTemplate'])->name('rombel.import.template');
        Route::get('/rombel/export/excel',    [RombelController::class, 'exportExcel'])->name('rombel.export.excel');
        Route::resource('rombel', RombelController::class)->except('show')
            ->parameters(['rombel' => 'rombel']);

        Route::resource('tingkat-kelas', TingkatKelasController::class)
            ->except('show')->parameters(['tingkat-kelas' => 'tingkatKelas']);

        Route::get('/guru/import',          [GuruController::class, 'importForm'])->name('guru.import.form');
        Route::post('/guru/import',         [GuruController::class, 'importStore'])->name('guru.import.store');
        Route::get('/guru/import-template', [GuruController::class, 'importTemplate'])->name('guru.import.template');
        Route::get('/guru/export/excel',    [GuruController::class, 'exportExcel'])->name('guru.export.excel');
        Route::delete('/guru/bulk-destroy', [GuruController::class, 'bulkDestroy'])->name('guru.bulk-destroy');
        Route::resource('guru', GuruController::class)->except('show');
        Route::post('/guru/{guru}/unlock', [GuruController::class, 'unlock'])->name('guru.unlock');

        Route::get('/guru-mapel/import',          [GuruMapelController::class, 'importForm'])->name('guru-mapel.import.form');
        Route::post('/guru-mapel/import',         [GuruMapelController::class, 'importStore'])->name('guru-mapel.import.store');
        Route::get('/guru-mapel/import-template', [GuruMapelController::class, 'importTemplate'])->name('guru-mapel.import.template');
        Route::get('/guru-mapel/export/excel',    [GuruMapelController::class, 'exportExcel'])->name('guru-mapel.export.excel');
        Route::delete('/guru-mapel/bulk-destroy', [GuruMapelController::class, 'bulkDestroy'])->name('guru-mapel.bulk-destroy');
        Route::resource('guru-mapel', GuruMapelController::class)
            ->except('show')->parameters(['guru-mapel' => 'guruMapel']);

        Route::get('/siswa/import',          [SiswaController::class, 'importForm'])->name('siswa.import.form');
        Route::post('/siswa/import',         [SiswaController::class, 'importStore'])->name('siswa.import.store');
        Route::get('/siswa/import-template', [SiswaController::class, 'importTemplate'])->name('siswa.import.template');
        Route::get('/siswa/export/excel',    [SiswaController::class, 'exportExcel'])->name('siswa.export.excel');
        Route::delete('/siswa/bulk-destroy', [SiswaController::class, 'bulkDestroy'])->name('siswa.bulk-destroy');
        Route::resource('siswa', SiswaController::class)->except('show');
        Route::post('/siswa/{siswa}/unlock', [SiswaController::class, 'unlock'])->name('siswa.unlock');

        Route::prefix('periodikal')->name('periodikal.')->group(function () {
            Route::get('/semua',               [PeriodikalController::class, 'semuaForm'])->name('semua.form');
            Route::post('/semua',              [PeriodikalController::class, 'semuaProses'])->name('semua.proses');
            Route::post('/duplikasi-struktur', [PeriodikalController::class, 'duplikasiStruktur'])->name('duplikasi-struktur');

            Route::get('/per-rombel',  [PeriodikalController::class, 'perRombelForm'])->name('per-rombel.form');
            Route::post('/per-rombel', [PeriodikalController::class, 'perRombelProses'])->name('per-rombel.proses');

            Route::get('/koreksi',                          [PeriodikalController::class, 'koreksiIndex'])->name('koreksi.index');
            Route::get('/koreksi/{riwayatPeriodikal}/edit',  [PeriodikalController::class, 'koreksiEdit'])->name('koreksi.edit');
            Route::put('/koreksi/{riwayatPeriodikal}',       [PeriodikalController::class, 'koreksiUpdate'])->name('koreksi.update');
            Route::delete('/koreksi/{riwayatPeriodikal}',    [PeriodikalController::class, 'koreksiUndo'])->name('koreksi.undo');
        });
    });

    Route::middleware('admin')->group(function () {
        Route::get('/log-login', [LogLoginController::class, 'index'])->name('log-login.index');
    });
});
