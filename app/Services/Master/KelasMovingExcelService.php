<?php

namespace App\Services\Master;

use App\Models\Guru;
use App\Models\KelasMoving;
use App\Models\KelasMovingSiswa;
use App\Models\MataPelajaran;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export Kelas Moving + anggotanya (Excel).
 *
 * Kolom (baris 1):
 *   nama_kelas_moving | kode_mapel | tahun_ajaran | nip_guru | tingkat | kapasitas | ruang | nisn
 *
 * Satu baris = satu keanggotaan siswa di satu kelas moving:
 *   - nama_kelas_moving : wajib. Kunci unik bersama tahun_ajaran (upsert).
 *   - kode_mapel        : wajib. Kode mapel yang sudah terdaftar.
 *   - tahun_ajaran      : opsional. Nama TA persis. Kosong -> TA aktif.
 *   - nip_guru          : opsional. NIP guru pengampu (harus sudah terdaftar).
 *   - tingkat           : opsional. Angka (7-12 dst) utk membatasi kandidat siswa.
 *   - kapasitas         : opsional. Angka. Kosong -> 36.
 *   - ruang             : opsional. Nama/kode ruang.
 *   - nisn              : opsional. NISN siswa (dari menu Data Siswa) yang jadi
 *                         anggota. Kosong -> baris hanya membuat/memperbarui
 *                         kelas moving-nya saja, tanpa menambah anggota.
 *
 * Kelas moving yang sama boleh muncul di banyak baris (satu baris per siswa);
 * kolom kelas cukup diisi ulang sama. Import bersifat menambah anggota, TIDAK
 * mengeluarkan anggota lama — pengeluaran dilakukan dari halaman Anggota.
 *
 * Satu siswa tidak boleh terdaftar di dua kelas moving untuk mapel yang sama
 * dalam satu TA; baris seperti itu dilaporkan gagal agar bisa diperbaiki.
 */
class KelasMovingExcelService
{
    public const HEADERS = [
        'nama_kelas_moving', 'kode_mapel', 'tahun_ajaran', 'nip_guru',
        'tingkat', 'kapasitas', 'ruang', 'nisn',
    ];

    /** Kolom template/import anggota untuk SATU kelas moving (halaman Anggota). */
    public const HEADERS_ANGGOTA = ['nisn', 'nis', 'nama_siswa', 'kelas_asal'];

    /** Kolom tambahan khusus hasil export (informatif, diabaikan saat import). */
    public const EXPORT_EXTRA = ['nama_mapel', 'nama_guru', 'nama_siswa', 'kelas_asal'];

    public function import(UploadedFile $file): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);
        $result = new ImportResult();

        if (count($data) < 2) return $result;

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));
        $taAktif = TahunAjaran::aktif();

        // Cache lookup natural key -> id
        $mapelCache = MataPelajaran::pluck('id', 'kode_mapel');
        $guruCache = Guru::pluck('id', 'nip');
        $taCache = TahunAjaran::pluck('id', 'nama_tahun_ajaran');
        $siswaCache = Siswa::pluck('id', 'nisn');

        $kelasCache = [];        // "nama|taId" => KelasMoving
        $anggotaBaru = 0;

        foreach ($data as $i => $row) {
            $line = $i + 2;
            try {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $assoc[$h] = $row[$idx] ?? null;
                }

                $nama = trim((string) ($assoc['nama_kelas_moving'] ?? ''));
                $kodeMapel = trim((string) ($assoc['kode_mapel'] ?? ''));
                $namaTa = trim((string) ($assoc['tahun_ajaran'] ?? ''));
                $nipGuru = trim((string) ($assoc['nip_guru'] ?? ''));
                $tingkat = trim((string) ($assoc['tingkat'] ?? ''));
                $kapasitas = trim((string) ($assoc['kapasitas'] ?? ''));
                $ruang = trim((string) ($assoc['ruang'] ?? ''));
                $nisn = trim((string) ($assoc['nisn'] ?? ''));

                // Baris benar-benar kosong (sisa format Excel) → lewati tanpa dihitung.
                if ($nama === '' && $kodeMapel === '' && $nisn === '') {
                    continue;
                }

                if ($nama === '' || $kodeMapel === '') {
                    throw new \RuntimeException('nama_kelas_moving & kode_mapel wajib diisi');
                }

                $taId = null;
                if ($namaTa !== '') {
                    $taId = $taCache[$namaTa] ?? null;
                    if (! $taId) throw new \RuntimeException("Tahun ajaran '{$namaTa}' tidak ditemukan");
                } else {
                    if (! $taAktif) throw new \RuntimeException('Tidak ada Tahun Ajaran aktif');
                    $taId = $taAktif->id;
                }

                $mapelId = $mapelCache[$kodeMapel] ?? null;
                if (! $mapelId) throw new \RuntimeException("Mapel kode '{$kodeMapel}' tidak ditemukan");

                $guruId = null;
                if ($nipGuru !== '') {
                    $guruId = $guruCache[$nipGuru] ?? null;
                    if (! $guruId) throw new \RuntimeException("Guru NIP '{$nipGuru}' tidak ditemukan");
                }

                $cacheKey = $nama.'|'.$taId;
                if (! isset($kelasCache[$cacheKey])) {
                    $kelas = KelasMoving::updateOrCreate(
                        ['nama_kelas_moving' => $nama, 'tahun_ajaran_id' => $taId],
                        [
                            'mata_pelajaran_id' => $mapelId,
                            'guru_id' => $guruId,
                            'tingkat' => $tingkat !== '' ? (int) $tingkat : null,
                            'kapasitas' => $kapasitas !== '' ? (int) $kapasitas : 36,
                            'ruang' => $ruang !== '' ? $ruang : null,
                        ]
                    );
                    if ($kelas->wasRecentlyCreated) {
                        $result->notes[] = "Kelas moving '{$nama}' dibuat otomatis.";
                    }
                    $kelasCache[$cacheKey] = $kelas;
                }
                $kelas = $kelasCache[$cacheKey];

                // Tanpa nisn: baris hanya mendefinisikan kelas moving-nya.
                if ($nisn === '') {
                    $result->success++;
                    continue;
                }

                $siswaId = $siswaCache[$nisn] ?? null;
                if (! $siswaId) throw new \RuntimeException("Siswa NISN '{$nisn}' tidak ditemukan di Data Siswa");

                // Sudah jadi anggota kelas ini → idempotent, tidak dianggap gagal.
                $sudah = KelasMovingSiswa::where('kelas_moving_id', $kelas->id)
                    ->where('siswa_id', $siswaId)->exists();
                if ($sudah) {
                    $result->success++;
                    continue;
                }

                $kelasLain = KelasMovingSiswa::query()
                    ->join('kelas_moving', 'kelas_moving.id', '=', 'kelas_moving_siswa.kelas_moving_id')
                    ->where('kelas_moving.tahun_ajaran_id', $taId)
                    ->where('kelas_moving.mata_pelajaran_id', $kelas->mata_pelajaran_id)
                    ->where('kelas_moving.id', '!=', $kelas->id)
                    ->where('kelas_moving_siswa.siswa_id', $siswaId)
                    ->value('kelas_moving.nama_kelas_moving');

                if ($kelasLain) {
                    throw new \RuntimeException("Siswa NISN '{$nisn}' sudah terdaftar di kelas moving '{$kelasLain}' untuk mapel yang sama");
                }

                KelasMovingSiswa::create([
                    'kelas_moving_id' => $kelas->id,
                    'siswa_id' => $siswaId,
                ]);
                $anggotaBaru++;

                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = "Baris {$line}: ".$e->getMessage();
            }
        }

        if ($anggotaBaru) {
            $result->notes[] = "{$anggotaBaru} keanggotaan siswa baru ditambahkan.";
        }

        // Peringatan kalau ada kelas yang anggotanya melebihi kapasitas.
        foreach ($kelasCache as $kelas) {
            $jumlah = KelasMovingSiswa::where('kelas_moving_id', $kelas->id)->count();
            if ($kelas->kapasitas && $jumlah > $kelas->kapasitas) {
                $result->notes[] = "Kelas '{$kelas->nama_kelas_moving}' berisi {$jumlah} siswa, melebihi kapasitas {$kelas->kapasitas}.";
            }
        }

        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $items = null): StreamedResponse
    {
        $items ??= KelasMoving::with(['mapel', 'guru', 'tahunAjaran', 'siswa'])
            ->orderBy('tingkat')->orderBy('nama_kelas_moving')->get();

        $headers = array_merge(self::HEADERS, self::EXPORT_EXTRA);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Kelas Moving');

        $sheet->fromArray([$headers], null, 'A1');
        $this->styleHeader($sheet, count($headers));
        $this->forceTextColumns($sheet, count($headers));

        // Kelas asal (rombel) siswa pada TA kelas moving masing-masing.
        $kelasAsal = $this->petaKelasAsal($items);

        $rows = [];
        foreach ($items as $km) {
            $dasar = [
                $km->nama_kelas_moving,
                optional($km->mapel)->kode_mapel,
                optional($km->tahunAjaran)->nama_tahun_ajaran,
                optional($km->guru)->nip,
                $km->tingkat,
                $km->kapasitas,
                $km->ruang,
            ];
            $info = [
                optional($km->mapel)->nama_mapel,
                optional($km->guru)->nama_ptk,
            ];

            if ($km->siswa->isEmpty()) {
                $rows[] = array_merge($dasar, [''], $info, ['', '']);
                continue;
            }

            foreach ($km->siswa->sortBy('nama_siswa') as $s) {
                $rows[] = array_merge($dasar, [$s->nisn], $info, [
                    $s->nama_siswa,
                    $kelasAsal[$km->tahun_ajaran_id][$s->id] ?? '',
                ]);
            }
        }

        $this->writeRowsAsText($sheet, $rows, 2);
        $this->autoSize($sheet, count($headers));

        return $this->stream($spreadsheet, 'data-kelas-moving-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Kelas Moving');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $mapel = MataPelajaran::orderBy('nama_mapel')->first();
        $kodeMapel = $mapel->kode_mapel ?? 'MTK';
        $nisn = Siswa::orderBy('nama_siswa')->pluck('nisn')->take(2)->all();

        $this->writeRowsAsText($sheet, [
            // Tiga baris pertama: satu kelas moving dengan dua anggota.
            [$kodeMapel.' Lanjut 1', $kodeMapel, '', '', '7', '32', 'R-101', $nisn[0] ?? '0012345678'],
            [$kodeMapel.' Lanjut 1', $kodeMapel, '', '', '7', '32', 'R-101', $nisn[1] ?? '0012345679'],
            // Baris tanpa nisn: hanya mendefinisikan kelas moving (anggota diisi manual nanti).
            [$kodeMapel.' Lanjut 2', $kodeMapel, '', '', '8', '32', 'R-102', ''],
        ], 2);

        $this->autoSize($sheet, count(self::HEADERS));

        return $this->stream($spreadsheet, 'template-import-kelas-moving.xlsx');
    }

    /* ===================== ANGGOTA SATU KELAS ===================== */

    /**
     * Import daftar anggota untuk SATU kelas moving (dipakai dari halaman Anggota).
     *
     * Kolom yang dibaca: nisn (utama) atau nis (cadangan). Kolom lain seperti
     * nama_siswa / kelas_asal hanya ikut di template sebagai rujukan.
     *
     * @param  bool  $ganti  true  = anggota kelas diganti persis isi file
     *                               (yang tidak ada di file dikeluarkan),
     *                       false = hanya menambah, anggota lama dibiarkan.
     */
    public function importAnggota(UploadedFile $file, KelasMoving $kelas, bool $ganti = false): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);
        $result = new ImportResult();

        if (count($data) < 2) {
            $result->notes[] = 'File tidak berisi baris data (hanya header).';
            return $result;
        }

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));
        $taId = $kelas->tahun_ajaran_id;

        // Lookup NISN/NIS -> siswa. Siswa non-aktif ikut diambil supaya pesan
        // gagalnya jelas ("ada tapi non-aktif", bukan "tidak ditemukan").
        $byNisn = [];
        $byNis = [];
        foreach (Siswa::get(['id', 'nisn', 'nis', 'nama_siswa', 'is_aktif']) as $s) {
            $kNisn = trim((string) $s->nisn);
            $kNis = trim((string) $s->nis);
            if ($kNisn !== '' && ! isset($byNisn[$kNisn])) $byNisn[$kNisn] = $s;
            if ($kNis !== '' && ! isset($byNis[$kNis])) $byNis[$kNis] = $s;
        }

        // Tingkat rombel tiap siswa pada TA kelas ini — hanya perlu kalau kelas
        // moving dibatasi tingkat tertentu.
        $tingkatSiswa = [];
        if ($kelas->tingkat) {
            DB::table('siswa_rombel')
                ->join('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
                ->where('siswa_rombel.tahun_ajaran_id', $taId)
                ->get(['siswa_rombel.siswa_id', 'rombongan_belajar.tingkat'])
                ->each(function ($row) use (&$tingkatSiswa) {
                    $tingkatSiswa[(int) $row->siswa_id] = (int) $row->tingkat;
                });
        }

        $sekarang = KelasMovingSiswa::where('kelas_moving_id', $kelas->id)
            ->pluck('siswa_id')->map(fn ($id) => (int) $id)->all();

        // Siswa yang sudah dipakai kelas moving LAIN pada mapel & TA yang sama.
        $bentrok = KelasMovingSiswa::query()
            ->join('kelas_moving', 'kelas_moving.id', '=', 'kelas_moving_siswa.kelas_moving_id')
            ->where('kelas_moving.tahun_ajaran_id', $taId)
            ->where('kelas_moving.mata_pelajaran_id', $kelas->mata_pelajaran_id)
            ->where('kelas_moving.id', '!=', $kelas->id)
            ->pluck('kelas_moving.nama_kelas_moving', 'kelas_moving_siswa.siswa_id')
            ->mapWithKeys(fn ($nama, $id) => [(int) $id => $nama])
            ->all();

        $final = [];     // siswa_id => true : hasil akhir pembacaan file
        $sudahAda = 0;   // baris yang siswanya memang sudah jadi anggota

        foreach ($data as $i => $row) {
            $line = $i + 2;
            try {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $assoc[$h] = $row[$idx] ?? null;
                }

                $nisn = trim((string) ($assoc['nisn'] ?? ''));
                $nis = trim((string) ($assoc['nis'] ?? ''));

                // Baris benar-benar kosong (sisa format Excel) -> lewati.
                if ($nisn === '' && $nis === '') {
                    continue;
                }

                $siswa = ($nisn !== '' ? ($byNisn[$nisn] ?? null) : null)
                    ?? ($nis !== '' ? ($byNis[$nis] ?? null) : null);

                if (! $siswa) {
                    $kunci = $nisn !== '' ? "NISN '{$nisn}'" : "NIS '{$nis}'";
                    throw new \RuntimeException("Siswa {$kunci} tidak ditemukan di Data Siswa");
                }

                $sid = (int) $siswa->id;

                // Baris kembar di dalam file yang sama -> cukup dihitung sekali.
                if (isset($final[$sid])) {
                    $result->success++;
                    continue;
                }

                if (! $siswa->is_aktif) {
                    throw new \RuntimeException("Siswa '{$siswa->nama_siswa}' berstatus non-aktif di Data Siswa");
                }

                if ($kelas->tingkat && ($tingkatSiswa[$sid] ?? null) !== (int) $kelas->tingkat) {
                    throw new \RuntimeException("Siswa '{$siswa->nama_siswa}' bukan tingkat {$kelas->tingkat} pada tahun ajaran ini");
                }

                if (isset($bentrok[$sid])) {
                    throw new \RuntimeException("Siswa '{$siswa->nama_siswa}' sudah terdaftar di kelas moving '{$bentrok[$sid]}' untuk mapel yang sama");
                }

                $final[$sid] = true;
                if (in_array($sid, $sekarang, true)) {
                    $sudahAda++;
                }
                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = "Baris {$line}: ".$e->getMessage();
            }
        }

        $ids = array_keys($final);
        $tambah = array_values(array_diff($ids, $sekarang));
        $hapus = $ganti ? array_values(array_diff($sekarang, $ids)) : [];

        DB::transaction(function () use ($kelas, $tambah, $hapus) {
            if ($hapus) {
                KelasMovingSiswa::where('kelas_moving_id', $kelas->id)
                    ->whereIn('siswa_id', $hapus)->delete();
            }
            if ($tambah) {
                KelasMovingSiswa::insert(array_map(fn ($id) => [
                    'kelas_moving_id' => $kelas->id,
                    'siswa_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $tambah));
            }
        });

        if ($tambah) $result->notes[] = count($tambah).' anggota baru ditambahkan.';
        if ($sudahAda) $result->notes[] = $sudahAda.' siswa di file sudah jadi anggota sebelumnya (dilewati, tidak dobel).';
        if ($hapus) $result->notes[] = count($hapus).' anggota lama dikeluarkan karena tidak ada di file.';
        if (! $tambah && ! $hapus) $result->notes[] = 'Tidak ada perubahan anggota.';

        $total = KelasMovingSiswa::where('kelas_moving_id', $kelas->id)->count();
        $result->notes[] = "Jumlah anggota sekarang: {$total} siswa.";
        if ($kelas->kapasitas && $total > $kelas->kapasitas) {
            $result->notes[] = "Melebihi kapasitas kelas ({$kelas->kapasitas}).";
        }

        return $result;
    }

    /**
     * Template import anggota satu kelas: sudah berisi anggota yang ada sekarang
     * supaya bisa diunduh, diedit, lalu diimpor ulang. Kalau kelas masih kosong,
     * diisi dua contoh siswa aktif agar format kolomnya terlihat.
     */
    public function templateAnggota(KelasMoving $kelas): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Anggota');

        $sheet->fromArray([self::HEADERS_ANGGOTA], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS_ANGGOTA));
        $this->forceTextColumns($sheet, count(self::HEADERS_ANGGOTA));

        $kelas->loadMissing('siswa');
        $kelasAsal = $this->petaKelasAsal(collect([$kelas]))[$kelas->tahun_ajaran_id] ?? [];

        $rows = [];
        foreach ($kelas->siswa->sortBy('nama_siswa') as $s) {
            $rows[] = [$s->nisn, $s->nis, $s->nama_siswa, $kelasAsal[$s->id] ?? ''];
        }

        if (! $rows) {
            $contoh = Siswa::where('is_aktif', true)->orderBy('nama_siswa')
                ->take(2)->get(['id', 'nisn', 'nis', 'nama_siswa']);
            foreach ($contoh as $s) {
                $rows[] = [$s->nisn, $s->nis, $s->nama_siswa, $kelasAsal[$s->id] ?? ''];
            }
            if (! $rows) {
                $rows[] = ['0012345678', '000123', 'Contoh Siswa', ''];
            }
        }

        $this->writeRowsAsText($sheet, $rows, 2);
        $this->autoSize($sheet, count(self::HEADERS_ANGGOTA));

        $nama = \Illuminate\Support\Str::slug($kelas->nama_kelas_moving) ?: 'kelas-moving';

        return $this->stream($spreadsheet, 'anggota-'.$nama.'.xlsx');
    }

    /** [tahun_ajaran_id][siswa_id] => nama_rombel */
    protected function petaKelasAsal($items): array
    {
        $taIds = $items->pluck('tahun_ajaran_id')->unique()->filter()->values();
        if ($taIds->isEmpty()) return [];

        $peta = [];
        \Illuminate\Support\Facades\DB::table('siswa_rombel')
            ->join('rombongan_belajar', 'rombongan_belajar.id', '=', 'siswa_rombel.rombongan_belajar_id')
            ->whereIn('siswa_rombel.tahun_ajaran_id', $taIds)
            ->get(['siswa_rombel.tahun_ajaran_id', 'siswa_rombel.siswa_id', 'rombongan_belajar.nama_rombel'])
            ->each(function ($row) use (&$peta) {
                $peta[$row->tahun_ajaran_id][$row->siswa_id] = $row->nama_rombel;
            });

        return $peta;
    }

    /* ---------- helpers (sama seperti service Excel lainnya) ---------- */

    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:'.$this->col($colCount).'1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = $this->col($colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getStyle($this->col($i))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    protected function writeRowsAsText($sheet, array $rows, int $startRow = 2): void
    {
        foreach ($rows as $rIdx => $row) {
            $r = $startRow + $rIdx;
            $cIdx = 1;
            foreach ($row as $value) {
                $sheet->setCellValueExplicit($this->col($cIdx).$r, (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    protected function autoSize($sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension($this->col($i))->setAutoSize(true);
        }
    }

    /** 1 => A, 26 => Z, 27 => AA (aman utk kolom > 26). */
    protected function col(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }

    protected function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
