<?php

namespace App\Http\Controllers\Datacenter;

use App\Http\Controllers\Controller;
use App\Models\RombonganBelajar;
use App\Services\FotoService;
use Illuminate\Http\Request;

/**
 * Menu Upload Foto Siswa & Guru.
 *
 *  - index   : galeri foto per tipe (siswa/guru) + filter + statistik kelengkapan
 *  - upload  : upload/ganti foto 1 orang (AJAX, dipanggil dari kartu)
 *  - destroy : hapus foto 1 orang
 *  - massal  : upload banyak foto sekaligus; nama file dicocokkan otomatis
 *              ke NISN/NIS/NIP/NUPTK/nama. Menerima gambar satuan atau file ZIP.
 */
class FotoController extends Controller
{
    public function __construct(protected FotoService $svc) {}

    public function index(Request $r)
    {
        $tipe = in_array($r->tipe, ['siswa', 'guru'], true) ? $r->tipe : 'siswa';
        $cfg = FotoService::tipe($tipe);
        $model = $cfg['model'];
        $table = (new $model)->getTable();

        $q = $model::query()->select("$table.*");

        if ($tipe === 'siswa') {
            $q->leftJoin('siswa_rombel', function ($j) {
                    $j->on('siswa_rombel.siswa_id', '=', 'siswa.id')
                      ->whereIn('siswa_rombel.tahun_ajaran_id', fn ($s) => $s->select('id')->from('tahun_ajaran')->where('is_aktif', true));
                })
                ->leftJoin('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
                ->with('rombelSekarang.rombel')
                ->when($r->rombel, fn ($x) => $x->where('rombongan_belajar.id', $r->rombel))
                ->orderByRaw('rombongan_belajar.tingkat is null')
                ->orderBy('rombongan_belajar.tingkat')
                ->orderBy('rombongan_belajar.nama_rombel');
        }

        $q->when($r->q, function ($x) use ($r, $cfg, $table) {
                $x->where(function ($w) use ($r, $cfg, $table) {
                    $w->where("$table.{$cfg['nama']}", 'like', "%{$r->q}%");
                    foreach ($cfg['kunci'] as $k) $w->orWhere("$table.$k", 'like', "%{$r->q}%");
                });
            })
            ->when($r->status === 'ada', fn ($x) => $x->whereNotNull("$table.foto"))
            ->when($r->status === 'belum', fn ($x) => $x->whereNull("$table.foto"))
            ->orderBy("$table.{$cfg['nama']}");

        $items = $q->paginate(min(max((int) $r->input('per', 48), 12), 200))->withQueryString();

        $stat = [
            'total' => $model::count(),
            'ada'   => $model::whereNotNull('foto')->count(),
        ];
        $stat['belum'] = $stat['total'] - $stat['ada'];
        $stat['persen'] = $stat['total'] ? round($stat['ada'] / $stat['total'] * 100) : 0;

        $rombelList = $tipe === 'siswa'
            ? RombonganBelajar::whereHas('tahunAjaran', fn ($x) => $x->where('is_aktif', true))
                ->orderBy('tingkat')->orderBy('nama_rombel')->get()
            : collect();

        return view('datacenter.foto.index', compact('tipe', 'cfg', 'items', 'stat', 'rombelList'));
    }

    /** Upload / ganti foto satu orang. */
    public function upload(Request $r, string $tipe, int $id)
    {
        $cfg = FotoService::tipe($tipe);
        $r->validate(['foto' => 'required|file|image|max:15360'], [
            'foto.image' => 'File harus berupa gambar (JPG/PNG/WEBP).',
            'foto.max'   => 'Ukuran foto maksimal 15 MB.',
        ]);

        $orang = $cfg['model']::findOrFail($id);
        $url = $this->svc->simpan($orang, $tipe, file_get_contents($r->file('foto')->getRealPath()));

        if (! $url) {
            return response()->json(['ok' => false, 'message' => 'Gambar tidak bisa dibaca.'], 422);
        }

        return response()->json([
            'ok' => true,
            'url' => $url,
            'message' => 'Foto '.$orang->{$cfg['nama']}.' tersimpan.',
        ]);
    }

    public function destroy(string $tipe, int $id)
    {
        $cfg = FotoService::tipe($tipe);
        $orang = $cfg['model']::findOrFail($id);
        $this->svc->hapus($orang);

        return response()->json(['ok' => true, 'message' => 'Foto '.$orang->{$cfg['nama']}.' dihapus.']);
    }

    /**
     * Upload massal. Front-end mengirim file satu per satu (agar tidak terbentur
     * batas jumlah file per request & bisa menampilkan progres). File ZIP diekstrak
     * di server dan setiap gambar di dalamnya dicocokkan.
     */
    public function massal(Request $r, string $tipe)
    {
        FotoService::tipe($tipe);
        $r->validate(['file' => 'required|file|max:512000']);
        $timpa = $r->boolean('timpa', true);
        $file = $r->file('file');
        $nama = $file->getClientOriginalName();

        set_time_limit(0);

        if (strtolower($file->getClientOriginalExtension()) === 'zip') {
            return response()->json(['ok' => true, 'zip' => true, 'hasil' => $this->prosesZip($tipe, $file->getRealPath(), $timpa)]);
        }

        return response()->json(['ok' => true, 'zip' => false, 'hasil' => [
            $this->prosesSatu($tipe, $nama, file_get_contents($file->getRealPath()), $timpa),
        ]]);
    }

    protected function prosesZip(string $tipe, string $path, bool $timpa): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [['file' => 'ZIP', 'status' => 'gagal', 'pesan' => 'File ZIP rusak / tidak bisa dibuka.']];
        }

        $hasil = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $nama = $st['name'];
            if (str_ends_with($nama, '/') || str_contains($nama, '__MACOSX') || str_starts_with(basename($nama), '.')) continue;
            if (! $this->svc->ekstensiValid($nama)) continue;
            if ($st['size'] > 20 * 1024 * 1024) {
                $hasil[] = ['file' => basename($nama), 'status' => 'gagal', 'pesan' => 'Ukuran lebih dari 20 MB.'];
                continue;
            }
            $hasil[] = $this->prosesSatu($tipe, $nama, (string) $zip->getFromIndex($i), $timpa);
        }
        $zip->close();

        if (! $hasil) {
            $hasil[] = ['file' => 'ZIP', 'status' => 'gagal', 'pesan' => 'Tidak ada file gambar di dalam ZIP.'];
        }
        return $hasil;
    }

    protected function prosesSatu(string $tipe, string $nama, string $binary, bool $timpa): array
    {
        $cfg = FotoService::tipe($tipe);
        $base = basename(str_replace('\\', '/', $nama));

        if (! $this->svc->ekstensiValid($base)) {
            return ['file' => $base, 'status' => 'gagal', 'pesan' => 'Bukan file gambar.'];
        }

        $orang = $this->svc->cocokkan($tipe, $base);
        if (! $orang) {
            return ['file' => $base, 'status' => 'gagal',
                'pesan' => 'Tidak cocok dengan '.implode('/', array_map('strtoupper', $cfg['kunci'])).' atau nama '.strtolower($cfg['label']).' manapun.'];
        }

        $label = $orang->{$cfg['nama']};
        if (! $timpa && $orang->foto) {
            return ['file' => $base, 'status' => 'lewat', 'nama' => $label, 'pesan' => 'Sudah punya foto (dilewati).'];
        }

        $baru = ! $orang->foto;
        $url = $this->svc->simpan($orang, $tipe, $binary);
        if (! $url) {
            return ['file' => $base, 'status' => 'gagal', 'nama' => $label, 'pesan' => 'Gambar tidak bisa dibaca.'];
        }

        return ['file' => $base, 'status' => 'ok', 'baru' => $baru, 'id' => $orang->getKey(), 'nama' => $label, 'url' => $url, 'pesan' => 'Tersimpan.'];
    }
}
