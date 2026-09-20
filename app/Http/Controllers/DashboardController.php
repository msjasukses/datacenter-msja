<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\Sekolah;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'siswa'   => Siswa::count(),
            'guru'    => Guru::count(),
            'mapel'   => MataPelajaran::count(),
            'jurusan' => Jurusan::count(),
            'rombel'  => RombonganBelajar::count(),
        ];

        $taAktif = TahunAjaran::where('is_aktif', true)->first();

        // Sebaran siswa per rombel di tahun ajaran aktif. Rombel yang belum
        // terisi tetap ditampilkan (jumlah 0) supaya kelihatan mana yang
        // datanya belum masuk.
        $rombelAktif = $taAktif
            ? RombonganBelajar::where('tahun_ajaran_id', $taAktif->id)
                ->with('waliKelas:id,nama_ptk')
                ->withCount('siswaRombel as jumlah_siswa')
                ->orderBy('tingkat')->orderBy('nama_rombel')->get()
            : collect();

        // Siswa yang belum punya penempatan rombel di tahun ajaran aktif.
        $siswaBelumDitempatkan = $taAktif
            ? Siswa::whereDoesntHave('siswaRombel', fn ($q) => $q->where('tahun_ajaran_id', $taAktif->id))->count()
            : 0;

        return view('dashboard.admin', [
            'stats' => $stats,
            'sekolah' => Sekolah::first(),
            'tahunAjaranAktif' => $taAktif,
            'rombelAktif' => $rombelAktif,
            'siswaBelumDitempatkan' => $siswaBelumDitempatkan,
        ]);
    }
}
