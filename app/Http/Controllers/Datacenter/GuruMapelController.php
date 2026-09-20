<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use App\Services\Master\GuruMapelExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuruMapelController extends Controller
{
    public function index(Request $r)
    {
        $items = GuruMapel::with('guru', 'mapel', 'rombel.jurusan', 'tahunAjaran')
            ->when($r->guru, fn ($q) => $q->where('guru_id', $r->guru))
            ->when($r->mapel, fn ($q) => $q->where('mata_pelajaran_id', $r->mapel))
            ->when($r->ta, fn ($q) => $q->where('tahun_ajaran_id', $r->ta))
            ->orderByDesc('id')->paginate(25)->withQueryString();

        return view('datacenter.guru-mapel.index', [
            'items' => $items,
            'guruList' => Guru::orderBy('nama_ptk')->get(),
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
            'taList' => TahunAjaran::orderByDesc('id')->get(),
        ]);
    }

    public function create()
    {
        return view('datacenter.guru-mapel.form', [
            'item' => new GuruMapel(),
            'guruList' => Guru::orderBy('nama_ptk')->get(),
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
            'tingkatList' => TingkatKelas::aktif()->orderBy('nomor')->get(),
            'rombelList' => RombonganBelajar::with('tahunAjaran')->orderBy('nama_rombel')->get(),
            'taList' => TahunAjaran::orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'guru_id' => 'required|exists:guru,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'rombongan_belajar_id' => 'required|array|min:1',
            'rombongan_belajar_id.*' => 'exists:rombongan_belajar,id',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['rombongan_belajar_id'] as $rombelId) {
                GuruMapel::firstOrCreate([
                    'guru_id' => $data['guru_id'],
                    'mata_pelajaran_id' => $data['mata_pelajaran_id'],
                    'rombongan_belajar_id' => $rombelId,
                    'tahun_ajaran_id' => $data['tahun_ajaran_id'],
                ]);
            }
        });

        return redirect()->route('guru-mapel.index')
            ->with('success', count($data['rombongan_belajar_id']).' Data tersimpan.');
    }

    public function edit(GuruMapel $guruMapel)
    {
        return view('datacenter.guru-mapel.form', [
            'item' => $guruMapel,
            'guruList' => Guru::orderBy('nama_ptk')->get(),
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
            'tingkatList' => TingkatKelas::aktif()->orderBy('nomor')->get(),
            'rombelList' => RombonganBelajar::with('tahunAjaran')->orderBy('nama_rombel')->get(),
            'taList' => TahunAjaran::orderByDesc('id')->get(),
        ]);
    }

    public function update(Request $r, GuruMapel $guruMapel)
    {
        $data = $r->validate([
            'guru_id' => 'required|exists:guru,id',
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'rombongan_belajar_id' => 'required|exists:rombongan_belajar,id',
        ]);
        $guruMapel->update($data);
        return redirect()->route('guru-mapel.index')->with('success', 'Data diperbarui.');
    }

    public function destroy(GuruMapel $guruMapel)
    {
        $guruMapel->delete();
        return back()->with('success', 'Data dihapus.');
    }

    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:guru_mapel,id',
        ]);

        $count = GuruMapel::whereIn('id', $data['ids'])->count();
        GuruMapel::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} data guru mapel dihapus.");
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.guru-mapel.import');
    }

    public function importStore(Request $r, GuruMapelExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:5120']);

        // File dengan banyak baris bisa melebihi max_execution_time default
        // dan berhenti mendadak (500) di tengah loop.
        set_time_limit(0);

        $result = $svc->import($r->file('file'));

        return redirect()->route('guru-mapel.import.form')
            ->with('success', "Import selesai: {$result->success} pembelajaran tersimpan, {$result->failed} gagal.")
            ->with('importErrors', $result->errors)
            ->with('importNotes', $result->notes);
    }

    public function importTemplate(GuruMapelExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, GuruMapelExcelService $svc)
    {
        $query = GuruMapel::with('guru', 'mapel', 'rombel', 'tahunAjaran')
            ->when($r->guru, fn ($q) => $q->where('guru_id', $r->guru))
            ->when($r->mapel, fn ($q) => $q->where('mata_pelajaran_id', $r->mapel))
            ->when($r->ta, fn ($q) => $q->where('tahun_ajaran_id', $r->ta))
            ->orderBy('guru_id');

        return $svc->export($query->get());
    }
}
