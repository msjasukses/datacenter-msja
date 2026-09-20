<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use App\Services\Master\SiswaExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiswaController extends Controller
{
    public function index(Request $r)
    {
        // Urutkan berdasarkan kelas (rombel) tahun ajaran aktif terlebih dahulu —
        // tingkat terkecil dulu (7 sebelum 8), lalu nama rombel (7-1 sebelum 7-2),
        // siswa tanpa rombel di tahun ajaran aktif ditaruh paling akhir — baru
        // nama siswa alfabetis di dalam kelas yang sama.
        $items = Siswa::query()
            ->select('siswa.*')
            ->leftJoin('siswa_rombel', function ($join) {
                $join->on('siswa_rombel.siswa_id', '=', 'siswa.id')
                     ->whereIn('siswa_rombel.tahun_ajaran_id', function ($q) {
                         $q->select('id')->from('tahun_ajaran')->where('is_aktif', true);
                     });
            })
            ->leftJoin('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
            ->with(['rombelSekarang.rombel'])
            ->when($r->q, function ($x) use ($r) {
                $x->where('siswa.nama_siswa', 'like', "%{$r->q}%")
                  ->orWhere('siswa.nisn', 'like', "%{$r->q}%")
                  ->orWhere('siswa.nis', 'like', "%{$r->q}%");
            })
            ->when($r->rombel, fn ($x) => $x->where('rombongan_belajar.id', $r->rombel))
            ->orderByRaw('rombongan_belajar.tingkat is null')
            ->orderBy('rombongan_belajar.tingkat')
            ->orderBy('rombongan_belajar.nama_rombel')
            ->orderBy('siswa.nama_siswa')
            ->paginate(25)->withQueryString();

        // Daftar kelas utk dropdown filter — hanya rombel tahun ajaran aktif,
        // sesuai kelas yang benar-benar dipakai utk mengurutkan/menyaring di atas.
        $rombelList = RombonganBelajar::whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true))
            ->orderBy('tingkat')->orderBy('nama_rombel')->get();

        return view('datacenter.siswa.index', compact('items', 'rombelList'));
    }

    public function create()
    {
        return view('datacenter.siswa.form', [
            'item' => new Siswa(),
            'rombel' => RombonganBelajar::with('tahunAjaran')->get(),
        ]);
    }

    public function store(Request $r)
    {
        DB::transaction(function () use ($r) {
            $data = $this->v($r);
            $data['password'] = $data['password'] ?? '12345678'; //password default untuk siswa baru
            $siswa = Siswa::create($data);
            $this->syncRombel($r, $siswa);
        });
        return redirect()->route('siswa.index')->with('success', 'Data siswa ditambahkan.');
    }

    public function edit(Siswa $siswa)
    {
        $siswa->load('rombelSekarang');
        return view('datacenter.siswa.form', [
            'item' => $siswa,
            'rombel' => RombonganBelajar::with('tahunAjaran')->get(),
        ]);
    }

    public function update(Request $r, Siswa $siswa)
    {
        DB::transaction(function () use ($r, $siswa) {
            $data = $this->v($r, $siswa->id);
            if (empty($data['password'])) unset($data['password']);
            $siswa->update($data);
            $this->syncRombel($r, $siswa);
        });
        return redirect()->route('siswa.index')->with('success', 'Data siswa diperbarui.');
    }

    public function destroy(Siswa $siswa)
    {
        $siswa->delete();
        return back()->with('success', 'Data siswa dihapus.');
    }

    /** Hapus banyak siswa sekaligus (dipilih lewat checkbox di daftar). */
    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:siswa,id',
        ]);

        $count = Siswa::whereIn('id', $data['ids'])->count();
        Siswa::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} data siswa dihapus.");
    }

    /**
     * Buka kunci akun siswa yang sedang terkunci (5x salah login berturut-turut).
     * Reset failed_login_count & locked_until supaya siswa bisa login lagi
     * saat itu juga, tanpa perlu menunggu 15 menit habis.
     */
    public function unlock(Siswa $siswa)
    {
        $siswa->update(['locked_until' => null, 'failed_login_count' => 0]);
        return back()->with('success', "Akun {$siswa->nama_siswa} berhasil dibuka. Siswa bisa login lagi sekarang.");
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.siswa.import');
    }

    public function importStore(Request $r, SiswaExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        // File dengan ratusan+ baris (hash password per siswa baru) bisa melebihi
        // max_execution_time default (30 detik) dan berhenti mendadak (500) di
        // tengah loop meski sebagian baris sudah ter-commit. Hilangkan batas waktu
        // khusus untuk request import ini saja.
        set_time_limit(0);

        $result = $svc->import($r->file('file'));

        return redirect()->route('siswa.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors)
            ->with('importNotes', $result->notes);
    }

    public function importTemplate(SiswaExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, SiswaExcelService $svc)
    {
        $query = Siswa::with('rombelSekarang.rombel');
        if ($r->q) {
            $query->where(function ($q) use ($r) {
                $q->where('nama_siswa', 'like', "%{$r->q}%")
                  ->orWhere('nisn', 'like', "%{$r->q}%")
                  ->orWhere('nis', 'like', "%{$r->q}%");
            });
        }
        return $svc->export($query->orderBy('nama_siswa')->get());
    }

    protected function syncRombel(Request $r, Siswa $siswa): void
    {
        if ($rombelId = $r->input('rombongan_belajar_id')) {
            $rombel = RombonganBelajar::findOrFail($rombelId);
            SiswaRombel::updateOrCreate(
                ['siswa_id' => $siswa->id, 'tahun_ajaran_id' => $rombel->tahun_ajaran_id],
                ['rombongan_belajar_id' => $rombel->id]
            );
        }
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'nisn' => 'required|string|max:20|unique:siswa,nisn,'.$id,
            'nis' => 'nullable|string|max:20',
            'nama_siswa' => 'required|string|max:255',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:30',
            'alamat' => 'nullable|string',
            'nomor_hp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
            'nama_ayah' => 'nullable|string|max:255',
            'nama_ibu' => 'nullable|string|max:255',
            'nomor_hp_ortu' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:6',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
