<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Jurusan;
use App\Models\RombonganBelajar;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use App\Services\Master\RombelExcelService;
use Illuminate\Http\Request;

class RombelController extends Controller
{
    public function index(Request $r)
    {
        $items = RombonganBelajar::with('jurusan', 'tahunAjaran', 'waliKelas')
            ->when($r->q, fn ($x) => $x->where('nama_rombel', 'like', "%{$r->q}%"))
            ->when($r->ta, fn ($x) => $x->where('tahun_ajaran_id', $r->ta))
            ->orderBy('tingkat')->orderBy('nama_rombel')
            ->paginate(20)->withQueryString();
        return view('datacenter.rombel.index', [
            'items' => $items,
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(),
        ]);
    }

    public function create()
    {
        return view('datacenter.rombel.form', [
            'item' => new RombonganBelajar(),
            'jurusan' => Jurusan::orderBy('nama_jurusan')->get(),
            'guru' => Guru::orderBy('nama_ptk')->get(),
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(),
            'tingkatList' => TingkatKelas::aktif()->orderBy('nomor')->get(),
        ]);
    }

    public function store(Request $r)
    {
        RombonganBelajar::create($this->v($r));
        return redirect()->route('rombel.index')->with('success', 'Rombel ditambahkan.');
    }

    public function edit(RombonganBelajar $rombel)
    {
        return view('datacenter.rombel.form', [
            'item' => $rombel,
            'jurusan' => Jurusan::orderBy('nama_jurusan')->get(),
            'guru' => Guru::orderBy('nama_ptk')->get(),
            'tahunAjaran' => TahunAjaran::orderByDesc('id')->get(),
            'tingkatList' => TingkatKelas::aktif()->orderBy('nomor')->get(),
        ]);
    }

    public function update(Request $r, RombonganBelajar $rombel)
    {
        $rombel->update($this->v($r));
        return redirect()->route('rombel.index')->with('success', 'Rombel diperbarui.');
    }

    public function destroy(RombonganBelajar $rombel)
    {
        $rombel->delete();
        return back()->with('success', 'Rombel dihapus.');
    }

    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:rombongan_belajar,id',
        ]);

        $count = RombonganBelajar::whereIn('id', $data['ids'])->count();
        RombonganBelajar::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} rombel dihapus.");
    }

    protected function v(Request $r): array
    {
        return $r->validate([
            'nama_rombel' => 'required|string|max:50',
            'tingkat' => 'required|integer|between:1,12',
            'jurusan_id' => 'nullable|exists:jurusan,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'wali_kelas_id' => 'nullable|exists:guru,id',
            'kapasitas' => 'nullable|integer|min:1',
        ]);
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.rombel.import');
    }

    public function importStore(Request $r, RombelExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        // File dengan banyak baris bisa melebihi max_execution_time default
        // dan berhenti mendadak (500) di tengah loop.
        set_time_limit(0);

        $result = $svc->import($r->file('file'));

        return redirect()->route('rombel.import.form')
            ->with('success', "Import selesai: {$result->success} sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors);
    }

    public function importTemplate(RombelExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, RombelExcelService $svc)
    {
        $query = RombonganBelajar::with('jurusan', 'tahunAjaran', 'waliKelas')
            ->when($r->q, fn ($x) => $x->where('nama_rombel', 'like', "%{$r->q}%"))
            ->when($r->ta, fn ($x) => $x->where('tahun_ajaran_id', $r->ta));

        return $svc->export($query->orderBy('tingkat')->orderBy('nama_rombel')->get());
    }
}
