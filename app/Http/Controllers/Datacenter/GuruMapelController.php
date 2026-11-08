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

/**
 * Penugasan guru: matriks centang mapel x rombel per guru.
 *
 * Satu baris guru_mapel = satu persilangan (guru, mapel, rombel, TA).
 * Halaman index punya dua mode:
 *   - tanpa ?guru= : rekap semua guru pada TA terpilih (lihat data)
 *   - dengan ?guru=: matriks centang untuk guru tersebut (input data)
 */
class GuruMapelController extends Controller
{
    public function index(Request $r)
    {
        $taList = TahunAjaran::orderByDesc('id')->get();
        $ta = $taList->firstWhere('id', (int) $r->ta) ?? TahunAjaran::aktif() ?? $taList->first();

        $guruList = Guru::orderBy('nama_ptk')->get(['id', 'nip', 'nama_ptk', 'jabatan']);
        $guru = $guruList->firstWhere('id', (int) $r->guru);

        $data = [
            'taList' => $taList,
            'ta' => $ta,
            'guruList' => $guruList,
            'guru' => $guru,
            'namaTingkat' => TingkatKelas::pluck('nama', 'nomor'),
            'rekap' => collect(),
            'mapelList' => collect(),
            'rombelGroups' => collect(),
            'checked' => [],
        ];

        if (! $ta) {
            return view('datacenter.guru-mapel.index', $data);
        }

        // Rombel TA terpilih, dikelompokkan per tingkat untuk header kolom.
        $data['rombelGroups'] = RombonganBelajar::where('tahun_ajaran_id', $ta->id)
            ->orderBy('tingkat')->orderBy('nama_rombel')
            ->get(['id', 'nama_rombel', 'tingkat'])
            ->groupBy('tingkat');

        if (! $guru) {
            $data['rekap'] = $this->rekap($ta, $guruList);

            return view('datacenter.guru-mapel.index', $data);
        }

        $data['mapelList'] = MataPelajaran::orderBy('nama_mapel')->get(['id', 'kode_mapel', 'nama_mapel']);
        $data['checked'] = GuruMapel::where('guru_id', $guru->id)
            ->where('tahun_ajaran_id', $ta->id)
            ->whereNotNull('rombongan_belajar_id')
            ->get(['mata_pelajaran_id', 'rombongan_belajar_id'])
            ->map(fn ($gm) => $gm->mata_pelajaran_id.'-'.$gm->rombongan_belajar_id)
            ->unique()->values()->all();

        return view('datacenter.guru-mapel.index', $data);
    }

    /** Rekap "sudah ditugaskan apa saja" untuk tiap guru pada satu TA. */
    private function rekap(TahunAjaran $ta, $guruList)
    {
        $perGuru = GuruMapel::where('tahun_ajaran_id', $ta->id)
            ->whereNotNull('rombongan_belajar_id')
            ->with('mapel:id,nama_mapel')
            ->get(['id', 'guru_id', 'mata_pelajaran_id', 'rombongan_belajar_id'])
            ->groupBy('guru_id');

        return $guruList->map(function ($g) use ($perGuru) {
            $rows = $perGuru->get($g->id) ?? collect();

            return (object) [
                'guru' => $g,
                'mapel' => $rows->pluck('mapel.nama_mapel')->filter()->unique()->sort()->values(),
                'jumlahKelas' => $rows->pluck('rombongan_belajar_id')->unique()->count(),
                'jumlahPenugasan' => $rows->map(fn ($x) => $x->mata_pelajaran_id.'-'.$x->rombongan_belajar_id)->unique()->count(),
            ];
        })->sortByDesc('jumlahPenugasan')->values();
    }

    /** Simpan satu matriks penuh: dicentang = ada, tidak dicentang = dihapus. */
    public function penugasanStore(Request $r)
    {
        $data = $r->validate([
            'guru_id' => 'required|exists:guru,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'cells' => 'nullable|array',
            'cells.*' => 'regex:/^\d+-\d+$/',
        ]);

        $guruId = (int) $data['guru_id'];
        $taId = (int) $data['tahun_ajaran_id'];

        // Hanya rombel milik TA ini & mapel yang benar-benar ada yang diproses,
        // supaya centang dari halaman basi tidak membuat baris liar.
        $rombelIds = RombonganBelajar::where('tahun_ajaran_id', $taId)->pluck('id')->all();
        $mapelIds = MataPelajaran::pluck('id')->all();

        $diminta = collect($data['cells'] ?? [])->unique()->filter(function ($pair) use ($rombelIds, $mapelIds) {
            [$mapelId, $rombelId] = array_map('intval', explode('-', $pair));

            return in_array($mapelId, $mapelIds) && in_array($rombelId, $rombelIds);
        })->values();

        $hasil = DB::transaction(function () use ($guruId, $taId, $rombelIds, $diminta) {
            $existing = GuruMapel::where('guru_id', $guruId)
                ->where('tahun_ajaran_id', $taId)
                ->whereIn('rombongan_belajar_id', $rombelIds)
                ->orderBy('id')
                ->get(['id', 'mata_pelajaran_id', 'rombongan_belajar_id']);

            $simpan = $diminta->flip();
            $dipertahankan = [];
            $hapus = [];

            foreach ($existing as $gm) {
                $key = $gm->mata_pelajaran_id.'-'.$gm->rombongan_belajar_id;
                // Tidak dicentang, atau duplikat dari baris yang sudah dipertahankan.
                if (! $simpan->has($key) || isset($dipertahankan[$key])) {
                    $hapus[] = $gm->id;
                    continue;
                }
                $dipertahankan[$key] = true;
            }

            if ($hapus) {
                GuruMapel::whereIn('id', $hapus)->delete();
            }

            $baru = $diminta->reject(fn ($key) => isset($dipertahankan[$key]))
                ->map(function ($key) use ($guruId, $taId) {
                    [$mapelId, $rombelId] = array_map('intval', explode('-', $key));

                    return [
                        'guru_id' => $guruId,
                        'mata_pelajaran_id' => $mapelId,
                        'rombongan_belajar_id' => $rombelId,
                        'tahun_ajaran_id' => $taId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->values()->all();

            if ($baru) {
                GuruMapel::insert($baru);
            }

            return ['tambah' => count($baru), 'hapus' => count($hapus)];
        });

        return redirect()->route('guru-mapel.index', ['guru' => $guruId, 'ta' => $taId])
            ->with('success', "Penugasan disimpan: {$hasil['tambah']} ditambah, {$hasil['hapus']} dihapus.");
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
