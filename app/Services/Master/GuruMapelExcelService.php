<?php

namespace App\Services\Master;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export pembelajaran: Guru ↔ Mapel ↔ Rombel (Excel).
 *
 * Import menerima 2 bentuk file sekaligus (dideteksi otomatis):
 *
 * 1. Unduhan Dapodik "Rekap Pembelajaran" apa adanya — 7 baris judul di atas,
 *    header di baris 8, satu baris = satu pembelajaran:
 *      No | Jenis Rombel | Tingkat | Nama Rombel | Kurikulum | Program/Kompetensi Keahlian |
 *      Nama PTK | NUPTK | PTK Induk | Kepegawaian | Nama Matpel | Kode Matpel | JJM |
 *      Jml Siswa | Tgl SK Mengajar | SK Mengajar | Status di Kurikulum
 *    Yang dipakai hanya kolom tingkat, rombel, guru, dan mapel — sisanya diabaikan.
 *    File ini tidak punya kolom NIP — guru dicocokkan lewat NUPTK, lalu nama.
 *
 * 2. Template internal (lihat self::HEADERS) — header di baris 1, kolom Rombel
 *    boleh berisi beberapa rombel dipisah koma ("7-1,7-2,7-3"), dan NIP boleh
 *    kosong pada baris lanjutan (diwarisi dari baris terisi terakhir).
 *
 * Pencocokan:
 * - Guru  : NUPTK → NIP → nama (harus sudah terdaftar; tidak pernah dibuat otomatis).
 * - Mapel : kode → nama → nama tanpa embel-embel kurung ("Matematika (Umum)" →
 *           "Matematika") → singkatan dalam kurung dicocokkan ke kode ("(IPS)" → IPS).
 * - Rombel: nama dinormalisasi, angka romawi disetarakan dengan angka biasa
 *           ("VII-A" ≡ "7-A"), dibatasi Tahun Ajaran aktif.
 *
 * Rombel & mapel yang belum ada dibuat otomatis dan dicatat di ImportResult::$notes.
 * Kunci unik pembelajaran: guru + mapel + rombel + TA aktif (import ulang tidak
 * menggandakan baris).
 */
class GuruMapelExcelService
{
    public const HEADERS = [
        'NIP', 'NUPTK', 'Nama PTK', 'Tingkat', 'Rombel', 'Kode Mapel', 'Nama Mapel',
    ];

    /** Label kolom (dinormalisasi: huruf kecil, non-alfanumerik jadi spasi) → key internal. */
    protected const ALIASES = [
        // guru
        'nip'                          => 'nip',
        'nuptk'                        => 'nuptk',
        'nama ptk'                     => 'nama_ptk',
        'nama lengkap'                 => 'nama_ptk',
        'nama guru'                    => 'nama_ptk',
        'nama'                         => 'nama_ptk',

        // rombel
        'tingkat'                      => 'tingkat',
        'rombel'                       => 'rombel',
        'nama rombel'                  => 'rombel',
        'rombongan belajar'            => 'rombel',
        'kelas'                        => 'rombel',

        // mapel
        'kode mapel'                   => 'kode_mapel',
        'kode matpel'                  => 'kode_mapel',
        'kode mata pelajaran'          => 'kode_mapel',
        'nama mapel'                   => 'nama_mapel',
        'nama matpel'                  => 'nama_mapel',
        'mata pelajaran'               => 'nama_mapel',
        'matpel'                       => 'nama_mapel',
    ];

    /** Berapa baris pertama yang dipindai saat mencari baris header. */
    protected const HEADER_SCAN_ROWS = 25;

    protected const ROMAWI = [
        'XII' => 12, 'XI' => 11, 'X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7,
        'VI' => 6, 'V' => 5, 'IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1,
    ];

    /* ===================== IMPORT ===================== */

    public function import(UploadedFile $file): ImportResult
    {
        $result = new ImportResult();

        $taAktif = TahunAjaran::aktif();
        if (! $taAktif) {
            $result->failed++;
            $result->errors[] = 'Tidak ada Tahun Ajaran aktif. Aktifkan dulu salah satu tahun ajaran.';
            return $result;
        }

        // formatData = false: NUPTK disimpan sebagai teks tapi diberi number
        // format, kalau diformat leading zero-nya hilang dan NIP panjang
        // kehilangan presisi karena dilewatkan sebagai float.
        $data = IOFactory::load($file->getRealPath())
            ->getActiveSheet()->toArray(null, true, false, false);

        $headerRow = $this->locateHeaderRow($data);
        if ($headerRow === null) {
            $result->failed++;
            $result->errors[] = 'Header kolom tidak ditemukan. Pastikan file memuat kolom guru '
                .'(NUPTK/NIP/Nama PTK), rombel, dan mata pelajaran — unduhan Dapodik '
                .'"Rekap Pembelajaran" atau template import.';
            return $result;
        }

        $map = $this->mapColumns($data[$headerRow]);

        // Cache lookup supaya tidak query per baris.
        $guruByNuptk = Guru::whereNotNull('nuptk')->pluck('id', 'nuptk');
        $guruByNip   = Guru::pluck('id', 'nip');
        $guruByNama  = $this->indexUnik(Guru::get(['id', 'nama_ptk']), fn ($g) => $this->normNama($g->nama_ptk));
        $mapelByKode = MataPelajaran::get(['id', 'kode_mapel'])
            ->mapWithKeys(fn ($m) => [strtoupper(trim($m->kode_mapel)) => $m->id]);
        $mapelByNama = $this->indexUnik(MataPelajaran::get(['id', 'nama_mapel']), fn ($m) => $this->normText($m->nama_mapel));
        $mapelByBase = $this->indexUnik(MataPelajaran::get(['id', 'nama_mapel']), fn ($m) => $this->normText($this->tanpaKurung($m->nama_mapel)));
        $rombelCache = RombonganBelajar::where('tahun_ajaran_id', $taAktif->id)
            ->get(['id', 'nama_rombel'])->mapWithKeys(fn ($r) => [$this->normRombel($r->nama_rombel) => $r->id]);

        $mapelBaru = [];
        $rombelBaru = [];

        // Sel "digabung" pada rekap: kosong = lanjutan baris di atasnya.
        $lastGuru = ['nip' => '', 'nuptk' => '', 'nama_ptk' => ''];

        foreach ($data as $rowNo => $row) {
            if ($rowNo <= $headerRow) continue;
            if (! $this->hasContent($row)) continue;

            $line  = $rowNo + 1;
            $assoc = $this->readRow($row, $map);

            foreach (['nip', 'nuptk', 'nama_ptk'] as $k) {
                $v = $this->text($assoc[$k] ?? null);
                if ($v !== '') $lastGuru[$k] = $v;
                else $assoc[$k] = $lastGuru[$k];
            }

            try {
                $guruId = $this->cariGuru($assoc, $guruByNuptk, $guruByNip, $guruByNama);

                $namaMapel = $this->text($assoc['nama_mapel'] ?? null);
                $kodeMapel = $this->text($assoc['kode_mapel'] ?? null);
                if ($namaMapel === '' && $kodeMapel === '') {
                    throw new \RuntimeException('Kolom mata pelajaran kosong (kode dan nama dua-duanya)');
                }
                $mapelId = $this->cariAtauBuatMapel(
                    $kodeMapel, $namaMapel, $mapelByKode, $mapelByNama, $mapelByBase, $mapelBaru
                );

                $rombelRaw = $this->text($assoc['rombel'] ?? null);
                if ($rombelRaw === '') throw new \RuntimeException('Kolom rombel kosong');

                // Template internal membolehkan beberapa rombel dipisah koma.
                foreach (array_filter(array_map('trim', explode(',', $rombelRaw))) as $namaRombel) {
                    $rombelId = $this->cariAtauBuatRombel(
                        $namaRombel, $assoc['tingkat'] ?? null, $taAktif->id, $rombelCache, $rombelBaru
                    );

                    GuruMapel::firstOrCreate([
                        'guru_id'              => $guruId,
                        'mata_pelajaran_id'    => $mapelId,
                        'rombongan_belajar_id' => $rombelId,
                        'tahun_ajaran_id'      => $taAktif->id,
                    ]);

                    $result->success++;
                }
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = "Baris {$line}: ".$e->getMessage();
            }
        }

        if ($rombelBaru) {
            $result->notes[] = count($rombelBaru).' rombel dibuat otomatis di TA '
                .$taAktif->nama_tahun_ajaran.': '.implode(', ', $rombelBaru);
        }
        if ($mapelBaru) {
            $result->notes[] = count($mapelBaru).' mata pelajaran dibuat otomatis: '.implode(', ', $mapelBaru);
        }

        return $result;
    }

    /* ---------- pencocokan ---------- */

    protected function cariGuru(array $assoc, $byNuptk, $byNip, $byNama): int
    {
        $nuptk = $this->text($assoc['nuptk'] ?? null);
        $nip   = $this->text($assoc['nip'] ?? null);
        $nama  = $this->text($assoc['nama_ptk'] ?? null);

        if ($nuptk !== '' && isset($byNuptk[$nuptk])) return $byNuptk[$nuptk];
        if ($nip !== '' && isset($byNip[$nip]))       return $byNip[$nip];

        if ($nama !== '') {
            $key = $this->normNama($nama);
            if (isset($byNama[$key])) return $byNama[$key];
            if (array_key_exists($key, $byNama)) {
                throw new \RuntimeException("Nama guru '{$nama}' cocok dengan lebih dari satu guru — lengkapi NUPTK-nya");
            }
        }

        $petunjuk = array_filter(["NUPTK '{$nuptk}'", "NIP '{$nip}'", "nama '{$nama}'"],
            fn ($v) => ! str_contains($v, "''"));

        throw new \RuntimeException(
            'Guru tidak ditemukan ('.implode(' / ', $petunjuk).'). Import data guru dulu.'
        );
    }

    protected function cariAtauBuatMapel(
        string $kode, string $nama, &$byKode, &$byNama, &$byBase, array &$dibuat
    ): int {
        $kodeKey = strtoupper($kode);
        if ($kode !== '' && isset($byKode[$kodeKey])) return $byKode[$kodeKey];

        if ($nama !== '') {
            $norm = $this->normText($nama);
            if (isset($byNama[$norm])) return $byNama[$norm];

            // "Matematika (Umum)" → "Matematika"
            $base = $this->normText($this->tanpaKurung($nama));
            if ($base !== '' && isset($byBase[$base])) return $byBase[$base];
            if ($base !== '' && isset($byNama[$base])) return $byNama[$base];

            // "Ilmu Pengetahuan Sosial (IPS)" → cocokkan "IPS" ke kode_mapel
            $singkatan = $this->singkatanKurung($nama);
            if ($singkatan !== '' && isset($byKode[$singkatan])) return $byKode[$singkatan];
        }

        if ($nama === '') {
            throw new \RuntimeException("Mapel dengan kode '{$kode}' tidak ditemukan dan namanya kosong");
        }

        $mapel = MataPelajaran::create([
            'kode_mapel' => $this->kodeMapelBaru($nama, $byKode),
            'nama_mapel' => $nama,
            'is_aktif'   => true,
        ]);

        $byKode[strtoupper($mapel->kode_mapel)] = $mapel->id;
        $byNama[$this->normText($nama)] = $mapel->id;
        $byBase[$this->normText($this->tanpaKurung($nama))] = $mapel->id;
        $dibuat[] = $nama;

        return $mapel->id;
    }

    protected function cariAtauBuatRombel(
        string $nama, $tingkat, int $taId, &$cache, array &$dibuat
    ): int {
        $key = $this->normRombel($nama);
        if (isset($cache[$key])) return $cache[$key];

        $tingkat = $this->parseTingkat($tingkat) ?? $this->tingkatDariNama($nama);
        if (! $tingkat) {
            throw new \RuntimeException("Rombel '{$nama}' belum ada dan tingkatnya tidak bisa ditentukan");
        }

        $rombel = RombonganBelajar::create([
            'nama_rombel'     => $nama,
            'tingkat'         => $tingkat,
            'tahun_ajaran_id' => $taId,
        ]);

        $cache[$key] = $rombel->id;
        $dibuat[] = $nama;

        return $rombel->id;
    }

    /** Kode mapel untuk mapel yang dibuat otomatis: pakai singkatan dalam kurung, kalau tidak ada pakai inisial. */
    protected function kodeMapelBaru(string $nama, $byKode): string
    {
        $kode = $this->singkatanKurung($nama);

        if ($kode === '') {
            $kata = preg_split('/\s+/', $this->normText($this->tanpaKurung($nama)), -1, PREG_SPLIT_NO_EMPTY);
            $abai = ['dan', 'atau', 'di', 'ke', 'dari', 'yang', 'the'];
            $inti = array_values(array_filter($kata, fn ($k) => ! in_array($k, $abai, true)));

            // Nama satu kata ("Prakarya") jadi "PRA", bukan "P".
            $kode = count($inti) >= 3
                ? implode('', array_map(fn ($k) => strtoupper($k[0]), $inti))
                : strtoupper(substr(implode('', $inti), 0, 4));
            if ($kode === '') $kode = 'MPL';
        }

        $kode = substr($kode, 0, 20);
        $dasar = $kode;
        $n = 2;
        while (isset($byKode[strtoupper($kode)])) {
            $kode = substr($dasar, 0, 18).$n;
            $n++;
        }

        return $kode;
    }

    /* ===================== EXPORT / TEMPLATE ===================== */

    public function export(?\Illuminate\Database\Eloquent\Collection $items = null): StreamedResponse
    {
        $items ??= GuruMapel::with('guru', 'mapel', 'rombel', 'tahunAjaran')
                    ->orderBy('guru_id')->orderBy('mata_pelajaran_id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Guru Mapel');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        // Kelompokkan per guru + mapel + tingkat rombel, rombel-nya digabung koma
        // (meniru format rekap: kolom guru hanya tampil di baris pertama guru terkait).
        $grouped = $items
            ->filter(fn ($gm) => $gm->guru && $gm->mapel && $gm->rombel)
            ->groupBy(fn ($gm) => $gm->guru_id.'|'.$gm->mata_pelajaran_id.'|'.$gm->rombel->tingkat);

        $rows = [];
        $lastGuruId = null;
        foreach ($grouped as $group) {
            $first = $group->first();
            $guru  = $first->guru;
            $sameGuru = $lastGuruId === $guru->id;

            $rows[] = [
                $sameGuru ? '' : $guru->nip,
                $sameGuru ? '' : $guru->nuptk,
                $sameGuru ? '' : $guru->nama_ptk,
                $first->rombel->tingkat,
                $group->pluck('rombel.nama_rombel')->unique()->implode(','),
                $first->mapel->kode_mapel,
                $first->mapel->nama_mapel,
            ];
            $lastGuruId = $guru->id;
        }

        $this->writeRowsAsText($sheet, $rows, 2);
        $this->autoSize($sheet, count(self::HEADERS));

        return $this->stream($spreadsheet, 'data-guru-mapel-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Guru Mapel');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $this->writeRowsAsText($sheet, [
            ['193924832948203482', '1234567890123456', 'Bambang Susilo, S.Pd.', '7', '7-1,7-2,7-3', '',    'Ilmu Pengetahuan Alam'],
            ['',                   '',                 '',                      '9', '9-1,9-2',     '',    'Ilmu Pengetahuan Alam'],
            ['198546456456564566', '6543210987654321', 'Teguh Satrio, S.Pd.',   '7', '7-1',         'MTK', 'Matematika'],
        ], 2);

        $this->autoSize($sheet, count(self::HEADERS));

        return $this->stream($spreadsheet, 'template-import-guru-mapel.xlsx');
    }

    /* ===================== deteksi header & baca baris ===================== */

    /**
     * Cari baris header. Unduhan Dapodik menaruh 7 baris judul di atas tabel.
     * Baris dianggap header kalau memuat kolom guru, rombel, dan mapel.
     */
    protected function locateHeaderRow(array $data): ?int
    {
        $batas = min(count($data), self::HEADER_SCAN_ROWS);

        for ($i = 0; $i < $batas; $i++) {
            $fields = array_values($this->mapColumns($data[$i]));
            if (! array_intersect(['nuptk', 'nip', 'nama_ptk'], $fields)) continue;
            if (! in_array('rombel', $fields, true)) continue;
            if (! array_intersect(['kode_mapel', 'nama_mapel'], $fields)) continue;
            return $i;
        }

        return null;
    }

    /** @return array<int,string> indeks kolom => key internal */
    protected function mapColumns(?array $headerRow): array
    {
        $map = [];
        foreach ($headerRow ?? [] as $idx => $label) {
            $key = $this->normText($label);
            if ($key !== '' && isset(self::ALIASES[$key])) {
                $map[$idx] = self::ALIASES[$key];
            }
        }
        return $map;
    }

    /**
     * Ambil satu baris jadi array key internal. Kolom yang memetakan ke key
     * sama (mis. "Rombel" dan "Nama Rombel") tidak saling menimpa: nilai
     * pertama yang terisi yang dipakai.
     *
     * @param array<int,string> $map
     */
    protected function readRow(array $row, array $map): array
    {
        $assoc = [];
        foreach ($map as $idx => $field) {
            $value = $row[$idx] ?? null;
            if (! isset($assoc[$field]) || $this->text($assoc[$field]) === '') {
                $assoc[$field] = $value;
            }
        }
        return $assoc;
    }

    protected function hasContent(?array $row): bool
    {
        foreach ($row ?? [] as $v) {
            if ($this->text($v) !== '') return true;
        }
        return false;
    }

    /**
     * Index by key, tapi key yang muncul lebih dari sekali dibuat null supaya
     * pencocokan yang ambigu ketahuan (bukan diam-diam ambil yang pertama).
     */
    protected function indexUnik($items, callable $keyBy): array
    {
        $out = [];
        foreach ($items as $item) {
            $key = $keyBy($item);
            if ($key === '') continue;
            $out[$key] = array_key_exists($key, $out) ? null : $item->id;
        }
        return $out;
    }

    /* ===================== normalisasi nilai ===================== */

    /** Nilai sel jadi string bersih, tanpa notasi ilmiah untuk angka panjang. */
    protected function text($value): string
    {
        if ($value === null || is_bool($value)) return '';
        if (is_float($value)) {
            return floor($value) == $value ? sprintf('%.0F', $value) : (string) $value;
        }
        $value = trim((string) $value);
        if (preg_match('/^\d+(\.\d+)?E\+\d+$/i', $value)) {
            return sprintf('%.0F', (float) $value);
        }
        return $value;
    }

    /** "Desa/Kelurahan" → "desa kelurahan"; dipakai untuk header & nama mapel. */
    protected function normText($value): string
    {
        $v = strtolower($this->text($value));
        $v = preg_replace('/[^a-z0-9]+/', ' ', $v);
        return trim(preg_replace('/\s+/', ' ', $v));
    }

    /** Buang embel-embel dalam kurung: "Matematika (Umum)" → "Matematika". */
    protected function tanpaKurung(string $nama): string
    {
        return trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $nama));
    }

    /** Isi kurung terakhir sebagai singkatan: "... (BP/BK)" → "BPBK". */
    protected function singkatanKurung(string $nama): string
    {
        if (! preg_match_all('/\(([^)]+)\)/', $nama, $m)) return '';
        $isi = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', end($m[1])));
        return strlen($isi) >= 2 && strlen($isi) <= 20 ? $isi : '';
    }

    /**
     * Nama guru untuk pencocokan: gelar di belakang koma dan sapaan di depan
     * dibuang. "Dra. Lucky Saor Trine" & "BAMBANG SUSILO, S.Pd." → "lucky saor
     * trine" & "bambang susilo".
     */
    protected function normNama($value): string
    {
        $v = $this->text($value);
        if (str_contains($v, ',')) $v = substr($v, 0, strpos($v, ','));
        $v = $this->normText($v);

        $sapaan = ['dr', 'drs', 'dra', 'hj', 'h', 'ir', 'prof'];
        $kata = preg_split('/\s+/', $v, -1, PREG_SPLIT_NO_EMPTY);
        while ($kata && in_array($kata[0], $sapaan, true)) array_shift($kata);

        return implode(' ', $kata);
    }

    /** "VII-A" → "7 a", "7-1" → "7 1". Angka romawi disetarakan dengan angka biasa. */
    protected function normRombel($value): string
    {
        $bagian = preg_split('/\s+/', $this->normText($value), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($bagian as $i => $b) {
            $upper = strtoupper($b);
            if (isset(self::ROMAWI[$upper])) $bagian[$i] = (string) self::ROMAWI[$upper];
        }

        return implode(' ', $bagian);
    }

    protected function parseTingkat($value): ?int
    {
        $v = strtoupper($this->text($value));
        if ($v === '') return null;
        if (isset(self::ROMAWI[$v])) return self::ROMAWI[$v];
        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    /** Tebak tingkat dari nama rombel: "VII-A" → 7, "9-1" → 9. */
    protected function tingkatDariNama(string $nama): ?int
    {
        $bagian = preg_split('/\s+/', $this->normRombel($nama), -1, PREG_SPLIT_NO_EMPTY);
        return $bagian && is_numeric($bagian[0]) ? (int) $bagian[0] : null;
    }

    protected function parseDate($value): ?string
    {
        $raw = $this->text($value);
        if ($raw === '') return null;

        try {
            if (is_numeric($raw)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            }
            if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $raw, $m)) {
                return \Carbon\Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /* ===================== helpers tulis file ===================== */

    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:'.$this->colName($colCount).'1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Paksa SEMUA kolom data jadi format TEXT supaya Excel tidak meng-auto-convert
     * "7-1" jadi tanggal, NIP panjang jadi notasi ilmiah, dsb.
     */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = $this->colName($colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getStyle($this->colName($i))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /** Tulis baris-baris data dengan tipe STRING eksplisit (anti auto-cast Excel). */
    protected function writeRowsAsText($sheet, array $rows, int $startRow = 2): void
    {
        foreach ($rows as $rIdx => $row) {
            $r = $startRow + $rIdx;
            $cIdx = 1;
            foreach ($row as $value) {
                $sheet->setCellValueExplicit($this->colName($cIdx).$r, (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    protected function autoSize($sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension($this->colName($i))->setAutoSize(true);
        }
    }

    /** 1 => A, 27 => AA. */
    protected function colName(int $index): string
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
