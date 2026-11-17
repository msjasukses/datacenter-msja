<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Sekolah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Pengaturan Aplikasi — satu halaman ber-tab (Identitas Sekolah, Logo Sekolah,
 * TTD Kepala Sekolah, Halaman Login, Identitas Aplikasi) menggantikan menu Profil Sekolah &
 * Halaman Login yang tadinya terpisah di sidebar.
 *
 * Identitas Sekolah, Logo, dan Background Halaman Login yang diatur di sini
 * adalah SUMBER TUNGGAL yang dibaca langsung oleh CBT lewat koneksi database
 * mysql_datacenter (lihat App\Providers\AppServiceProvider::datacenterBranding()
 * di project CBT) — tanpa HTTP API antar-app. CBT tidak lagi punya form untuk
 * itu — hanya tampilan salinan read-only.
 */
class PengaturanController extends Controller
{
    public function index()
    {
        $sekolah = Sekolah::first() ?? new Sekolah();

        return view('datacenter.pengaturan.index', [
            'sekolah' => $sekolah,
            'app' => [
                'login_bg' => AppSetting::get('login_bg'),
                'login_title' => AppSetting::get('login_title', 'Selamat datang di Data Center Sekolah.'),
                'login_subtitle' => AppSetting::get('login_subtitle', 'Kelola data sekolah, guru, siswa, dan kelas dalam satu sumber data terpusat.'),
                'app_name' => AppSetting::get('app_name', config('app.name')),
                'app_tagline' => AppSetting::get('app_tagline', 'Sistem Informasi Data Induk Sekolah'),
                'theme_color' => AppSetting::get('theme_color', '#0d9488'),
                'footer_text' => AppSetting::get('footer_text'),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $sekolahData = $request->validate([
            'npsn' => 'nullable|string|max:20',
            'nama_sekolah' => 'nullable|string|max:255',
            'jenjang' => 'nullable|string|max:20',
            'alamat' => 'nullable|string|max:255',
            'kelurahan' => 'nullable|string|max:100',
            'kecamatan' => 'nullable|string|max:100',
            'kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'telepon' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'website' => 'nullable|string|max:100',
            'kepala_sekolah' => 'nullable|string|max:255',
            'nip_kepala_sekolah' => 'nullable|string|max:30',
        ]);

        $request->validate([
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            'ttd_kepala_sekolah' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'login_bg' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:5120',
            'app_name' => 'nullable|string|max:255',
            'app_tagline' => 'nullable|string|max:255',
            'theme_color' => 'nullable|string|max:20',
            'footer_text' => 'nullable|string|max:255',
            'login_title' => 'nullable|string|max:255',
            'login_subtitle' => 'nullable|string|max:500',
        ]);

        // ---- Identitas Sekolah + Logo (sumber tunggal branding) ----
        $sekolah = Sekolah::first();

        if ($request->hasFile('logo')) {
            if ($sekolah && $sekolah->logo) {
                Storage::disk('public')->delete($sekolah->logo);
            }
            $sekolahData['logo'] = $request->file('logo')->store('sekolah', 'public');
        } elseif ($request->boolean('remove_logo')) {
            if ($sekolah && $sekolah->logo) {
                Storage::disk('public')->delete($sekolah->logo);
            }
            $sekolahData['logo'] = null;
        }

        // ---- Tanda tangan kepala sekolah ----
        if ($request->hasFile('ttd_kepala_sekolah')) {
            if ($sekolah && $sekolah->ttd_kepala_sekolah) {
                Storage::disk('public')->delete($sekolah->ttd_kepala_sekolah);
            }
            $sekolahData['ttd_kepala_sekolah'] = $request->file('ttd_kepala_sekolah')->store('sekolah/ttd', 'public');
        } elseif ($request->boolean('remove_ttd_kepala_sekolah')) {
            if ($sekolah && $sekolah->ttd_kepala_sekolah) {
                Storage::disk('public')->delete($sekolah->ttd_kepala_sekolah);
            }
            $sekolahData['ttd_kepala_sekolah'] = null;
        }

        if ($sekolah) {
            $sekolah->update($sekolahData);
        } else {
            Sekolah::create($sekolahData);
        }

        // ---- Background Halaman Login ----
        if ($request->hasFile('login_bg')) {
            $old = AppSetting::get('login_bg');
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $path = $request->file('login_bg')->store('settings', 'public');
            AppSetting::set('login_bg', $path, 'file', 'tampilan', 'Background Halaman Login');
        } elseif ($request->boolean('remove_login_bg')) {
            $old = AppSetting::get('login_bg');
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            AppSetting::set('login_bg', null, 'file', 'tampilan', 'Background Halaman Login');
        }

        // ---- Identitas Aplikasi + judul/sub-judul halaman login ----
        $textKeys = [
            'login_title' => 'text',
            'login_subtitle' => 'text',
            'app_name' => 'text',
            'app_tagline' => 'text',
            'footer_text' => 'text',
            'theme_color' => 'color',
        ];
        foreach ($textKeys as $key => $type) {
            $val = $request->input($key);
            if ($val !== null) {
                AppSetting::set($key, $val, $type, 'tampilan');
            }
        }

        AppSetting::flush();

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }
}
