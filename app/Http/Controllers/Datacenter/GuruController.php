<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Services\Master\GuruExcelService;
use Illuminate\Http\Request;

class GuruController extends Controller
{
    public function index(Request $r)
    {
        $items = Guru::when($r->q, function ($x) use ($r) {
                $x->where('nama_ptk', 'like', "%{$r->q}%")
                  ->orWhere('nip', 'like', "%{$r->q}%")
                  ->orWhere('nuptk', 'like', "%{$r->q}%");
            })->with('mapel')->orderBy('nama_ptk')->paginate(20)->withQueryString();
        return view('datacenter.guru.index', compact('items'));
    }

    public function create()
    {
        return view('datacenter.guru.form', ['item' => new Guru(), 'mapelList' => $this->mapelList()]);
    }

    public function store(Request $r)
    {
        $data = $this->v($r);
        $data['password'] = $data['password'] ?? 'password'; // password default untuk guru baru
        Guru::create($data);
        return redirect()->route('guru.index')->with('success', 'Data guru ditambahkan.');
    }

    public function edit(Guru $guru)
    {
        return view('datacenter.guru.form', ['item' => $guru, 'mapelList' => $this->mapelList()]);
    }

    /** Daftar mapel aktif untuk pilihan "Guru Mata Pelajaran" di form. */
    protected function mapelList()
    {
        return \App\Models\MataPelajaran::where('is_aktif', true)
            ->orderBy('nama_mapel')->pluck('nama_mapel', 'id');
    }

    public function update(Request $r, Guru $guru)
    {
        $data = $this->v($r, $guru->id);
        if (empty($data['password'])) unset($data['password']);
        $guru->update($data);
        return redirect()->route('guru.index')->with('success', 'Data guru diperbarui.');
    }

    public function destroy(Guru $guru)
    {
        $guru->delete();
        return back()->with('success', 'Data guru dihapus.');
    }

    /** Hapus banyak guru sekaligus (dipilih lewat checkbox di daftar). */
    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:guru,id',
        ]);

        $count = Guru::whereIn('id', $data['ids'])->count();
        Guru::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} data guru dihapus.");
    }

    /**
     * Buka kunci akun guru yang sedang terkunci (5x salah login berturut-turut).
     * Reset failed_login_count & locked_until supaya guru bisa login lagi
     * saat itu juga, tanpa perlu menunggu 15 menit habis.
     */
    public function unlock(Guru $guru)
    {
        $guru->update(['locked_until' => null, 'failed_login_count' => 0]);
        return back()->with('success', "Akun {$guru->nama_ptk} berhasil dibuka. Guru bisa login lagi sekarang.");
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.guru.import');
    }

    public function importStore(Request $r, GuruExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        // File dengan banyak baris (hash password per guru baru) bisa melebihi
        // max_execution_time default dan berhenti mendadak (500) di tengah loop.
        set_time_limit(0);

        $result = $svc->import($r->file('file'));

        return redirect()->route('guru.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors);
    }

    public function importTemplate(GuruExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, GuruExcelService $svc)
    {
        $query = Guru::query();
        if ($r->q) {
            $query->where('nama_ptk', 'like', "%{$r->q}%")
                  ->orWhere('nip', 'like', "%{$r->q}%");
        }
        return $svc->export($query->orderBy('nama_ptk')->get());
    }

    protected function v(Request $r, $id = null): array
    {
        return $r->validate([
            'nip' => 'required|string|max:30|unique:guru,nip,'.$id,
            'nuptk' => 'nullable|string|max:30',
            'nama_ptk' => 'required|string|max:255',
            'email' => 'nullable|email|max:100',
            'nomor_hp' => 'nullable|string|max:20',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tempat_lahir' => 'nullable|string|max:100',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:255',
            'jabatan' => 'nullable|string|max:100',
            'status_kepegawaian' => 'nullable|string|max:50',
            'mata_pelajaran_id' => 'nullable|integer|exists:mata_pelajaran,id',
            'password' => 'nullable|string|min:6',
            'is_aktif' => 'nullable|boolean',
        ]) + ['is_aktif' => $r->boolean('is_aktif', true)];
    }
}
