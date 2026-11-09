<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\KelasMoving;
use App\Models\KelasMovingSiswa;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TingkatKelas;
use App\Services\Master\KelasMovingExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Kelas Moving — kelas per mata pelajaran yang anggotanya DIAMBIL DARI
 * MENU DATA SISWA (tabel siswa), lintas rombongan belajar.
 *
 * Alur pemakaian:
 *   1. index   : daftar kelas moving per tahun ajaran (+ jumlah anggota).
 *   2. form    : buat/ubah kelas moving (nama, mapel, guru pengampu, tingkat, kapasitas).
 *   3. anggota : pilih siswa dari Data Siswa lewat centang (dengan pencarian
 *                & filter kelas asal), lalu simpan satu set penuh sekaligus.
 *
 * Aturan: satu siswa hanya boleh berada di SATU kelas moving untuk mapel yang
 * sama dalam satu tahun ajaran (kalau tidak, jadwalnya bentrok). Siswa yang
 * sudah terdaftar di kelas moving lain pada mapel yang sama ditandai di UI dan
 * dilewati saat penyimpanan (tidak membatalkan simpanan yang lain).
 */
class KelasMovingController extends Controller
{
    public function index(Request $r)
    {
        $taList = TahunAjaran::orderByDesc('id')->get();
        $ta = $taList->firstWhere('id', (int) $r->ta) ?? TahunAjaran::aktif() ?? $taList->first();

        $items = collect();
        if ($ta) {
            $items = KelasMoving::with('mapel:id,kode_mapel,nama_mapel', 'guru:id,nip,nama_ptk', 'tahunAjaran:id,nama_tahun_ajaran')
                ->withCount('anggota')
                ->where('tahun_ajaran_id', $ta->id)
                ->when($r->q, fn ($x) => $x->where('nama_kelas_moving', 'like', "%{$r->q}%"))
                ->when($r->mapel, fn ($x) => $x->where('mata_pelajaran_id', $r->mapel))
                ->when($r->tingkat, fn ($x) => $x->where('tingkat', $r->tingkat))
                ->orderByRaw('tingkat is null')
                ->orderBy('tingkat')
                ->orderBy('nama_kelas_moving')
                ->paginate(20)->withQueryString();
        }

        return view('datacenter.kelas-moving.index', [
            'items' => $items,
            'ta' => $ta,
            'taList' => $taList,
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(['id', 'kode_mapel', 'nama_mapel']),
            'tingkatList' => TingkatKelas::aktif()->orderBy('urutan')->orderBy('nomor')->get(),
        ]);
    }

    public function create()
    {
        return view('datacenter.kelas-moving.form', $this->formData(new KelasMoving([
            'tahun_ajaran_id' => optional(TahunAjaran::aktif())->id,
            'kapasitas' => 36,
            'is_aktif' => true,
        ])));
    }

    public function store(Request $r)
    {
        $kelasMoving = KelasMoving::create($this->v($r));

        return redirect()->route('kelas-moving.anggota', $kelasMoving)
            ->with('success', "Kelas moving \"{$kelasMoving->nama_kelas_moving}\" dibuat. Sekarang pilih anggotanya dari Data Siswa.");
    }

    public function edit(KelasMoving $kelasMoving)
    {
        return view('datacenter.kelas-moving.form', $this->formData($kelasMoving));
    }

    public function update(Request $r, KelasMoving $kelasMoving)
    {
        $kelasMoving->update($this->v($r, $kelasMoving->id));

        return redirect()->route('kelas-moving.index', ['ta' => $kelasMoving->tahun_ajaran_id])
            ->with('success', 'Kelas moving diperbarui.');
    }

    public function destroy(KelasMoving $kelasMoving)
    {
        $kelasMoving->delete();   // anggota ikut terhapus (cascade), siswa tidak tersentuh

        return back()->with('success', 'Kelas moving dihapus.');
    }

    public function bulkDestroy(Request $r)
    {
        $data = $r->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:kelas_moving,id',
        ]);

        $count = KelasMoving::whereIn('id', $data['ids'])->count();
        KelasMoving::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} kelas moving dihapus.");
    }

    /* ===================== ANGGOTA (ambil dari Data Siswa) ===================== */

    /**
     * Halaman pilih anggota: seluruh kandidat siswa dimuat sekali (dengan kelas
     * asal pada TA kelas moving ini), lalu dicari/difilter di sisi browser
     * supaya centang tidak hilang seperti kalau pakai paginasi.
     */
    public function anggota(KelasMoving $kelasMoving)
    {
        $kelasMoving->load('mapel', 'guru', 'tahunAjaran');
        $taId = $kelasMoving->tahun_ajaran_id;

        // Kandidat = siswa aktif. Kalau kelas moving dibatasi tingkat tertentu,
        // hanya siswa yang rombel-nya setingkat itu (pada TA ini) yang muncul.
        $kandidat = Siswa::query()
            ->select('siswa.*')
            ->addSelect([
                'kelas_asal' => DB::table('siswa_rombel')
                    ->join('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
                    ->whereColumn('siswa_rombel.siswa_id', 'siswa.id')
                    ->where('siswa_rombel.tahun_ajaran_id', $taId)
                    ->select('rombongan_belajar.nama_rombel')
                    ->limit(1),
                'tingkat_asal' => DB::table('siswa_rombel')
                    ->join('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
                    ->whereColumn('siswa_rombel.siswa_id', 'siswa.id')
                    ->where('siswa_rombel.tahun_ajaran_id', $taId)
                    ->select('rombongan_belajar.tingkat')
                    ->limit(1),
            ])
            ->where('siswa.is_aktif', true)
            ->when($kelasMoving->tingkat, function ($x) use ($kelasMoving, $taId) {
                $x->whereExists(function ($q) use ($kelasMoving, $taId) {
                    $q->select(DB::raw(1))->from('siswa_rombel')
                      ->join('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
                      ->whereColumn('siswa_rombel.siswa_id', 'siswa.id')
                      ->where('siswa_rombel.tahun_ajaran_id', $taId)
                      ->where('rombongan_belajar.tingkat', $kelasMoving->tingkat);
                });
            })
            ->orderByRaw('(select rombongan_belajar.tingkat from siswa_rombel join rombongan_belajar on rombongan_belajar.id = siswa_rombel.rombongan_belajar_id where siswa_rombel.siswa_id = siswa.id and siswa_rombel.tahun_ajaran_id = ? limit 1) is null', [$taId])
            ->orderBy('tingkat_asal')
            ->orderBy('kelas_asal')
            ->orderBy('siswa.nama_siswa')
            ->get();

        $terpilih = $kelasMoving->anggota()->pluck('siswa_id')->map(fn ($id) => (string) $id)->values()->all();

        // Siswa yang sudah dipakai kelas moving LAIN pada mapel & TA yang sama →
        // tidak boleh dicentang di sini (bentrok jadwal mapel yang sama).
        $bentrok = KelasMovingSiswa::query()
            ->join('kelas_moving', 'kelas_moving.id', '=', 'kelas_moving_siswa.kelas_moving_id')
            ->where('kelas_moving.tahun_ajaran_id', $taId)
            ->where('kelas_moving.mata_pelajaran_id', $kelasMoving->mata_pelajaran_id)
            ->where('kelas_moving.id', '!=', $kelasMoving->id)
            ->pluck('kelas_moving.nama_kelas_moving', 'kelas_moving_siswa.siswa_id')
            ->all();

        return view('datacenter.kelas-moving.anggota', [
            'item' => $kelasMoving,
            'kandidat' => $kandidat,
            'terpilih' => $terpilih,
            'bentrok' => $bentrok,
        ]);
    }

    /** Simpan satu set penuh anggota: dicentang = anggota, tidak = dikeluarkan. */
    public function anggotaStore(Request $r, KelasMoving $kelasMoving)
    {
        $data = $r->validate([
            'siswa_ids' => 'nullable|array',
            'siswa_ids.*' => 'integer|exists:siswa,id',
        ]);

        $taId = $kelasMoving->tahun_ajaran_id;
        $diminta = collect($data['siswa_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();

        // Buang siswa yang sedang dipakai kelas moving lain pada mapel & TA sama.
        $bentrokIds = KelasMovingSiswa::query()
            ->join('kelas_moving', 'kelas_moving.id', '=', 'kelas_moving_siswa.kelas_moving_id')
            ->where('kelas_moving.tahun_ajaran_id', $taId)
            ->where('kelas_moving.mata_pelajaran_id', $kelasMoving->mata_pelajaran_id)
            ->where('kelas_moving.id', '!=', $kelasMoving->id)
            ->whereIn('kelas_moving_siswa.siswa_id', $diminta)
            ->pluck('kelas_moving_siswa.siswa_id')
            ->map(fn ($id) => (int) $id)->unique();

        $simpan = $diminta->reject(fn ($id) => $bentrokIds->contains($id))->values();

        $hasil = DB::transaction(function () use ($kelasMoving, $simpan) {
            $sekarang = $kelasMoving->anggota()->pluck('siswa_id')->map(fn ($id) => (int) $id);

            $tambah = $simpan->diff($sekarang)->values();
            $hapus = $sekarang->diff($simpan)->values();

            if ($hapus->isNotEmpty()) {
                KelasMovingSiswa::where('kelas_moving_id', $kelasMoving->id)
                    ->whereIn('siswa_id', $hapus)->delete();
            }

            if ($tambah->isNotEmpty()) {
                KelasMovingSiswa::insert($tambah->map(fn ($id) => [
                    'kelas_moving_id' => $kelasMoving->id,
                    'siswa_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            }

            return ['tambah' => $tambah->count(), 'hapus' => $hapus->count(), 'total' => $simpan->count()];
        });

        $pesan = "Anggota disimpan: {$hasil['tambah']} ditambah, {$hasil['hapus']} dikeluarkan. Total sekarang {$hasil['total']} siswa.";

        $redirect = redirect()->route('kelas-moving.anggota', $kelasMoving)->with('success', $pesan);

        $catatan = [];
        if ($bentrokIds->isNotEmpty()) {
            $nama = Siswa::whereIn('id', $bentrokIds)->orderBy('nama_siswa')->pluck('nama_siswa');
            $catatan[] = $bentrokIds->count().' siswa dilewati karena sudah terdaftar di kelas moving lain '
                .'untuk mapel yang sama: '.$nama->implode(', ').'.';
        }
        if ($kelasMoving->kapasitas && $hasil['total'] > $kelasMoving->kapasitas) {
            $catatan[] = 'Jumlah anggota ('.$hasil['total'].') melebihi kapasitas kelas ('.$kelasMoving->kapasitas.').';
        }
        if ($catatan) {
            $redirect->with('warning', implode(' ', $catatan));
        }

        return $redirect;
    }

    /** Unduh daftar/template anggota kelas ini (xlsx) untuk diedit lalu diimpor ulang. */
    public function anggotaTemplate(KelasMoving $kelasMoving, KelasMovingExcelService $svc)
    {
        return $svc->templateAnggota($kelasMoving);
    }

    /** Import anggota kelas ini dari file Excel berisi daftar NISN/NIS. */
    public function anggotaImport(Request $r, KelasMoving $kelasMoving, KelasMovingExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        // File panjang bisa melewati max_execution_time default dan berhenti di
        // tengah loop — sama seperti import kelas moving.
        set_time_limit(0);

        $result = $svc->importAnggota($r->file('file'), $kelasMoving, $r->boolean('ganti'));

        $pesan = "Import anggota selesai: {$result->success} baris sukses, {$result->failed} gagal.";

        return redirect()->route('kelas-moving.anggota', $kelasMoving)
            ->with($result->failed ? 'warning' : 'success', $pesan)
            ->with('importErrors', $result->errors)
            ->with('importNotes', $result->notes);
    }

    /* ===================== IMPORT / EXPORT ===================== */

    public function importForm()
    {
        return view('datacenter.kelas-moving.import');
    }

    public function importStore(Request $r, KelasMovingExcelService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);

        // File dengan banyak baris bisa melebihi max_execution_time default
        // dan berhenti mendadak (500) di tengah loop.
        set_time_limit(0);

        $result = $svc->import($r->file('file'));

        return redirect()->route('kelas-moving.import.form')
            ->with('success', "Import selesai: {$result->success} baris sukses, {$result->failed} gagal.")
            ->with('importErrors', $result->errors)
            ->with('importNotes', $result->notes);
    }

    public function importTemplate(KelasMovingExcelService $svc)
    {
        return $svc->template();
    }

    public function exportExcel(Request $r, KelasMovingExcelService $svc)
    {
        $query = KelasMoving::with(['mapel', 'guru', 'tahunAjaran', 'siswa'])
            ->when($r->ta, fn ($x) => $x->where('tahun_ajaran_id', $r->ta))
            ->when($r->mapel, fn ($x) => $x->where('mata_pelajaran_id', $r->mapel))
            ->when($r->tingkat, fn ($x) => $x->where('tingkat', $r->tingkat))
            ->when($r->q, fn ($x) => $x->where('nama_kelas_moving', 'like', "%{$r->q}%"));

        return $svc->export($query->orderBy('tingkat')->orderBy('nama_kelas_moving')->get());
    }

    /* ===================== helpers ===================== */

    protected function formData(KelasMoving $item): array
    {
        return [
            'item' => $item,
            'mapelList' => MataPelajaran::orderBy('nama_mapel')->get(),
            'guruList' => Guru::orderBy('nama_ptk')->get(),
            'taList' => TahunAjaran::orderByDesc('id')->get(),
            'tingkatList' => TingkatKelas::aktif()->orderBy('urutan')->orderBy('nomor')->get(),
        ];
    }

    protected function v(Request $r, $id = null): array
    {
        $data = $r->validate([
            'nama_kelas_moving' => [
                'required', 'string', 'max:100',
                // Unik per tahun ajaran — sama seperti aturan nama rombel.
                function ($attr, $value, $fail) use ($r, $id) {
                    $ada = KelasMoving::where('nama_kelas_moving', $value)
                        ->where('tahun_ajaran_id', $r->tahun_ajaran_id)
                        ->when($id, fn ($q) => $q->where('id', '!=', $id))
                        ->exists();
                    if ($ada) {
                        $fail('Nama kelas moving ini sudah dipakai pada tahun ajaran yang sama.');
                    }
                },
            ],
            'mata_pelajaran_id' => 'required|exists:mata_pelajaran,id',
            'guru_id' => 'nullable|exists:guru,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'tingkat' => 'nullable|integer|between:1,12',
            'kapasitas' => 'nullable|integer|min:1|max:999',
            'ruang' => 'nullable|string|max:50',
            'keterangan' => 'nullable|string',
        ]);

        // Union array (`+`) tidak menimpa key yang sudah ada walau nilainya null,
        // jadi default diisi eksplisit di sini.
        $data['kapasitas'] = $r->integer('kapasitas') ?: 36;
        $data['is_aktif'] = $r->boolean('is_aktif', true);

        return $data;
    }
}
