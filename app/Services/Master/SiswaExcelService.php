<?php

namespace App\Services\Master;

use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export data siswa via Excel (.xlsx).
 *
 * Import menerima 2 bentuk file sekaligus (dideteksi otomatis):
 *
 * 1. Unduhan Dapodik "Daftar Peserta Didik" apa adanya — 4 baris judul di atas,
 *    header di baris 5. Dua varian sama-sama didukung:
 *      - varian ringkas (19 kolom):
 *        No | Nama | NIPD | JK | NISN | Tempat Lahir | Tanggal Lahir | NIK | Agama |
 *        Alamat | RT | RW | Dusun | Kelurahan | Kecamatan | Kode Pos | HP |
 *        Rombel Saat Ini | No KK
 *      - varian lengkap (66 kolom) yang header-nya 2 baris: baris 5 memuat grup
 *        "Data Ayah/Ibu/Wali" (sel digabung) dan baris 6 memuat sub-kolom
 *        Nama | Tahun Lahir | Jenjang Pendidikan | Pekerjaan | Penghasilan | NIK.
 *    Kolom yang tidak ada padanannya di database diabaikan.
 *
 * 2. Template internal (lihat self::HEADERS) — header di baris 1.
 *
 * Catatan:
 * - Kunci unik = NISN. Sudah ada → di-update, belum ada → dibuat baru.
 * - jenis_kelamin: L / P (juga menerima "Laki-laki" / "Perempuan").
 * - tanggal_lahir: YYYY-MM-DD, dd/mm/yyyy, atau date serial Excel.
 * - alamat: pada file Dapodik dirangkai dari Alamat + RT/RW + dusun + kelurahan +
 *   kecamatan + kode pos.
 * - rombel: dicocokkan pada Tahun Ajaran aktif, angka romawi disetarakan angka
 *   biasa ("VII-1" ≡ "7-1") dan awalan "Kelas" diabaikan ("Kelas 7B" ≡ "7-B").
 *   Rombel yang belum ada dibuat otomatis dan dicatat di ImportResult::$notes.
 * - password: opsional. Jika kosong pada akun baru, default = NISN.
 * - is_aktif: 1 / 0 / kosong (default 1).
 */
class SiswaExcelService
{
    public const HEADERS = [
        'nisn','nis','nama_siswa','jenis_kelamin','tempat_lahir','tanggal_lahir',
        'agama','alamat','nomor_hp','email','nama_ayah','nama_ibu','nomor_hp_ortu',
        'rombel','password','is_aktif',
    ];

    /**
     * Label kolom (dinormalisasi: huruf kecil, non-alfanumerik jadi spasi)
     * => nama field internal. Mencakup nama kolom template internal maupun
     * label tampilan pada unduhan Dapodik.
     */
    protected const ALIASES = [
        // identitas
        'nisn'                 => 'nisn',
        'nis'                  => 'nis',
        'nipd'                 => 'nis',
        'nama'                 => 'nama_siswa',
        'nama siswa'           => 'nama_siswa',
        'nama peserta didik'   => 'nama_siswa',
        'nama lengkap'         => 'nama_siswa',
        'jk'                   => 'jenis_kelamin',
        'jenis kelamin'        => 'jenis_kelamin',
        'tempat lahir'         => 'tempat_lahir',
        'tanggal lahir'        => 'tanggal_lahir',
        'tgl lahir'            => 'tanggal_lahir',
        'agama'                => 'agama',

        // kontak
        'email'                => 'email',
        'e mail'               => 'email',
        'surel'                => 'email',
        'hp'                   => 'nomor_hp',
        'no hp'                => 'nomor_hp',
        'nomor hp'             => 'nomor_hp',
        'handphone'            => 'nomor_hp',
        'telepon'              => 'telepon',
        'telp'                 => 'telepon',

        // alamat (Dapodik memecahnya jadi beberapa kolom)
        'alamat'               => 'alamat_jalan',
        'alamat jalan'         => 'alamat_jalan',
        'jalan'                => 'alamat_jalan',
        'rt'                   => 'rt',
        'rw'                   => 'rw',
        'dusun'                => 'dusun',
        'nama dusun'           => 'dusun',
        'kelurahan'            => 'kelurahan',
        'desa kelurahan'       => 'kelurahan',
        'desa'                 => 'kelurahan',
        'kecamatan'            => 'kecamatan',
        'kode pos'             => 'kode_pos',

        // orang tua — pada varian 66 kolom header-nya bertingkat ("Data Ayah" + "Nama")
        'nama ayah'            => 'nama_ayah',
        'data ayah nama'       => 'nama_ayah',
        'nama ibu'             => 'nama_ibu',
        'data ibu nama'        => 'nama_ibu',
        'nomor hp ortu'        => 'nomor_hp_ortu',
        'no hp ortu'           => 'nomor_hp_ortu',
        'hp orang tua'         => 'nomor_hp_ortu',
        'nomor hp orang tua'   => 'nomor_hp_ortu',

        // rombel
        'rombel'               => 'rombel',
        'rombel saat ini'      => 'rombel',
        'rombongan belajar'    => 'rombel',
        'kelas'                => 'rombel',

        // khusus template internal
        'password'             => 'password',
        'kata sandi'           => 'password',
        'is aktif'             => 'is_aktif',
        'aktif'                => 'is_aktif',
    ];

    /** Berapa baris pertama yang dipindai saat mencari baris header. */
    protected const HEADER_SCAN_ROWS = 20;

    protected const ROMAWI = [
        'XII' => 12, 'XI' => 11, 'X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7,
        'VI' => 6, 'V' => 5, 'IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1,
    ];

    /** Kata di depan nama rombel yang tidak ikut dibandingkan/disimpan. */
    protected const AWALAN_ROMBEL = ['kelas', 'kls', 'rombel'];

    /* ===================== IMPORT ===================== */

    public function import(UploadedFile $file): ImportResult
    {
        $result = new ImportResult();

        try {
            // formatData = false: NISN/NIK disimpan sebagai teks tapi diberi number
            // format, kalau diformat leading zero-nya hilang (NISN "0117017066")
            // dan angka panjang kehilangan presisi karena dilewatkan sebagai float.
            $data = IOFactory::load($file->getRealPath())
                ->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            $result->failed++;
            $result->errors[] = 'Gagal membaca file Excel: '.$e->getMessage();
            return $result;
        }

        $headerRow = $this->locateHeaderRow($data);
        if ($headerRow === null) {
            $result->failed++;
            $result->errors[] = 'Header kolom tidak ditemukan. Pastikan file memuat kolom NISN dan Nama '
                .'— unduhan Dapodik "Daftar Peserta Didik" atau template import.';
            return $result;
        }

        // Varian 66 kolom memakai header 2 baris: baris grup ("Data Ayah") di atas,
        // baris sub-kolom ("Nama") di bawahnya.
        $map = $this->mapColumns($data[$headerRow]);
        $barisMulai = $headerRow + 1;
        if ($this->isSubHeader($data[$barisMulai] ?? null, $map)) {
            $map = $this->mapColumns($this->gabungHeader($data[$headerRow], $data[$barisMulai]));
            $barisMulai++;
        }

        $ta = TahunAjaran::aktif();
        $rombelCache = $ta
            ? RombonganBelajar::where('tahun_ajaran_id', $ta->id)->get(['id', 'nama_rombel'])
                ->mapWithKeys(fn ($r) => [$this->normRombel($r->nama_rombel) => $r->id])
            : collect();
        $rombelBaru = [];

        foreach ($data as $rowNo => $row) {
            if ($rowNo < $barisMulai) continue;
            if (! $this->hasContent($row)) continue;

            $assoc = $this->readRow($row, $map);
            $nisn  = $this->text($assoc['nisn'] ?? null);
            $nama  = $this->text($assoc['nama_siswa'] ?? null);
            if ($nisn === '' || $nama === '') continue;

            try {
                $payload = [
                    'nis'           => $this->text($assoc['nis'] ?? null) ?: null,
                    'nama_siswa'    => $nama,
                    'jenis_kelamin' => $this->parseJenisKelamin($assoc['jenis_kelamin'] ?? null),
                    'tempat_lahir'  => $this->text($assoc['tempat_lahir'] ?? null) ?: null,
                    'tanggal_lahir' => $this->parseDate($assoc['tanggal_lahir'] ?? null),
                    'agama'         => $this->text($assoc['agama'] ?? null) ?: null,
                    'alamat'        => $this->composeAlamat($assoc),
                    'nomor_hp'      => $this->text($assoc['nomor_hp'] ?? null)
                                        ?: ($this->text($assoc['telepon'] ?? null) ?: null),
                    'email'         => $this->text($assoc['email'] ?? null) ?: null,
                    'nama_ayah'     => $this->text($assoc['nama_ayah'] ?? null) ?: null,
                    'nama_ibu'      => $this->text($assoc['nama_ibu'] ?? null) ?: null,
                    'nomor_hp_ortu' => $this->text($assoc['nomor_hp_ortu'] ?? null) ?: null,
                    'is_aktif'      => $this->parseBool($assoc['is_aktif'] ?? null, true),
                ];

                $pwd = $this->text($assoc['password'] ?? null);
                if ($pwd !== '') {
                    $payload['password'] = Hash::make($pwd);
                }

                $namaRombel = $this->text($assoc['rombel'] ?? null);

                DB::transaction(function () use ($nisn, $payload, $namaRombel, $ta, &$rombelCache, &$rombelBaru) {
                    $s = Siswa::where('nisn', $nisn)->first();
                    if ($s) {
                        // Kolom yang kosong di file tidak menghapus data lama.
                        $s->update(array_filter($payload, fn ($v) => $v !== null));
                    } else {
                        $payload['nisn'] = $nisn;
                        $payload['password'] ??= Hash::make($nisn); // default = NISN
                        $s = Siswa::create($payload);
                    }

                    if ($namaRombel === '') return;
                    if (! $ta) throw new \RuntimeException('Tidak ada Tahun Ajaran aktif untuk menempatkan rombel.');

                    $rombelId = $this->cariAtauBuatRombel($namaRombel, $ta->id, $rombelCache, $rombelBaru);

                    SiswaRombel::updateOrCreate(
                        ['siswa_id' => $s->id, 'tahun_ajaran_id' => $ta->id],
                        ['rombongan_belajar_id' => $rombelId]
                    );
                });

                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = 'Baris '.($rowNo + 1).' ('.$nama.'): '.$e->getMessage();
            }
        }

        if ($rombelBaru) {
            $result->notes[] = count($rombelBaru).' rombel dibuat otomatis di TA '
                .$ta->nama_tahun_ajaran.': '.implode(', ', $rombelBaru);
        }

        return $result;
    }

    protected function cariAtauBuatRombel(string $nama, int $taId, &$cache, array &$dibuat): int
    {
        $key = $this->normRombel($nama);
        if (isset($cache[$key])) return $cache[$key];

        $nama = $this->bersihkanNamaRombel($nama);
        $tingkat = $this->tingkatDariNama($nama);
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

    /* ===================== EXPORT / TEMPLATE ===================== */

    public function export(?\Illuminate\Database\Eloquent\Collection $siswa = null): StreamedResponse
    {
        $siswa ??= Siswa::with('rombelSekarang.rombel')->orderBy('nama_siswa')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Siswa');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $siswa->map(fn ($s) => [
            $s->nisn, $s->nis, $s->nama_siswa, $s->jenis_kelamin,
            $s->tempat_lahir, optional($s->tanggal_lahir)->format('Y-m-d'),
            $s->agama, $s->alamat, $s->nomor_hp, $s->email,
            $s->nama_ayah, $s->nama_ibu, $s->nomor_hp_ortu,
            optional($s->rombelSekarang?->rombel ?? null)->nama_rombel,
            '', // password kosong saat export (alasan keamanan)
            $s->is_aktif ? '1' : '0',
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);

        $this->autoSize($sheet, count(self::HEADERS));
        return $this->stream($spreadsheet, 'data-siswa-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Siswa');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $this->writeRowsAsText($sheet, [
            ['009900000001','NIS0001','Ahmad Fauzi','L','Jakarta','2009-05-12','Islam','Jl. Anggrek 1','081200000001','ahmad@test','Budi','Siti','081200000099','7-1','','1'],
            ['009900000002','NIS0002','Bunga Citra','P','Bekasi','2009-07-22','Islam','Jl. Mawar 2','081200000002','bunga@test','Hasan','Aminah','081200000098','7-2','','1'],
        ], 2);

        $this->autoSize($sheet, count(self::HEADERS));
        return $this->stream($spreadsheet, 'template-import-siswa.xlsx');
    }

    /* ===================== deteksi header & baca baris ===================== */

    /**
     * Cari baris header. Unduhan Dapodik menaruh 4 baris judul di atas tabel,
     * jadi header tidak selalu di baris 1.
     */
    protected function locateHeaderRow(array $data): ?int
    {
        $batas = min(count($data), self::HEADER_SCAN_ROWS);

        for ($i = 0; $i < $batas; $i++) {
            $fields = array_values($this->mapColumns($data[$i]));
            if (in_array('nisn', $fields, true) && in_array('nama_siswa', $fields, true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Baris di bawah header adalah baris sub-kolom (bukan data) kalau ada isinya
     * tapi kolom identitas (NISN & Nama) justru kosong.
     *
     * @param array<int,string> $map
     */
    protected function isSubHeader(?array $row, array $map): bool
    {
        if (! $this->hasContent($row)) return false;

        foreach ($map as $idx => $field) {
            if (in_array($field, ['nisn', 'nama_siswa'], true) && $this->text($row[$idx] ?? null) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Gabungkan header 2 baris jadi satu. Label grup pada baris atas hanya ada di
     * kolom pertama grup (sel digabung), jadi dirambatkan ke kanan lalu disambung
     * dengan sub-kolomnya: "Data Ayah" + "Nama" → "Data Ayah Nama".
     */
    protected function gabungHeader(array $atas, array $bawah): array
    {
        $grup = null;
        $gabungan = [];

        foreach ($atas as $idx => $label) {
            $label = $this->text($label);
            if ($label !== '') $grup = $label;

            $sub = $this->text($bawah[$idx] ?? null);
            $gabungan[$idx] = $sub !== '' ? trim($grup.' '.$sub) : $label;
        }

        return $gabungan;
    }

    /** @return array<int,string> indeks kolom => nama field internal */
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
     * Ambil satu baris jadi array field internal. Kolom yang memetakan ke field
     * yang sama tidak saling menimpa: nilai pertama yang terisi yang dipakai.
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

    /* ===================== normalisasi nilai ===================== */

    /**
     * Nilai sel jadi string bersih. Angka panjang (NISN/NIK/No KK) yang terbaca
     * sebagai float dikembalikan utuh, bukan notasi ilmiah.
     */
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

    /** "Desa/Kelurahan" → "desa kelurahan", "nama_siswa" → "nama siswa". */
    protected function normText($value): string
    {
        $v = strtolower($this->text($value));
        $v = preg_replace('/[^a-z0-9]+/', ' ', $v);
        return trim(preg_replace('/\s+/', ' ', $v));
    }

    protected function parseJenisKelamin($value): ?string
    {
        $v = strtoupper($this->text($value));
        if ($v === '') return null;
        if ($v === '1' || str_starts_with($v, 'L')) return 'L';
        if ($v === '2' || str_starts_with($v, 'P')) return 'P';
        return null;
    }

    /** Rangkai alamat lengkap dari kolom-kolom terpisah gaya Dapodik. */
    protected function composeAlamat(array $assoc): ?string
    {
        $parts = [];

        $jalan = $this->text($assoc['alamat_jalan'] ?? null);
        if ($jalan !== '' && $jalan !== '-') $parts[] = $jalan;

        $rt = $this->text($assoc['rt'] ?? null);
        $rw = $this->text($assoc['rw'] ?? null);
        if (($rt !== '' && $rt !== '0') || ($rw !== '' && $rw !== '0')) {
            $parts[] = 'RT '.($rt ?: '-').'/RW '.($rw ?: '-');
        }

        foreach (['dusun', 'kelurahan', 'kecamatan', 'kode_pos'] as $key) {
            $v = $this->text($assoc[$key] ?? null);
            // Dapodik sering mengisi dusun = kelurahan; jangan diulang.
            if ($v === '' || $v === '-' || $v === '0') continue;
            if ($parts && strcasecmp(end($parts), $v) === 0) continue;
            $parts[] = $v;
        }

        $alamat = implode(', ', $parts);
        return $alamat !== '' ? $alamat : null;
    }

    /**
     * Kunci pembanding nama rombel: awalan "Kelas" dibuang, angka romawi
     * disetarakan angka biasa, dan huruf yang menempel angka dipisah.
     * "Kelas 7B", "7-B", dan "VII-B" sama-sama jadi "7 b".
     */
    protected function normRombel($value): string
    {
        $v = $this->normText($value);
        // "7b" → "7 b" supaya sepadan dengan "7-B"
        $v = preg_replace('/(\d)([a-z])/', '$1 $2', $v);
        $v = preg_replace('/([a-z])(\d)/', '$1 $2', $v);

        $bagian = preg_split('/\s+/', $v, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        while ($bagian && in_array($bagian[0], self::AWALAN_ROMBEL, true)) array_shift($bagian);

        foreach ($bagian as $i => $b) {
            $upper = strtoupper($b);
            if (isset(self::ROMAWI[$upper])) $bagian[$i] = (string) self::ROMAWI[$upper];
        }

        return implode(' ', $bagian);
    }

    /** Buang awalan "Kelas" dari nama yang akan disimpan: "Kelas 7B" → "7B". */
    protected function bersihkanNamaRombel(string $nama): string
    {
        $nama = trim($nama);
        foreach (self::AWALAN_ROMBEL as $awalan) {
            if (preg_match('/^'.$awalan.'\s+(.+)$/i', $nama, $m)) return trim($m[1]);
        }
        return $nama;
    }

    /** Tebak tingkat dari nama rombel: "VII-1" → 7, "Kelas 9D" → 9. */
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
                // Excel serial date
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            }
            // dd/mm/yyyy dan dd-mm-yyyy dibaca gaya Indonesia, bukan gaya AS.
            if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $raw, $m)) {
                return \Carbon\Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseBool($v, bool $default = true): bool
    {
        if ($v === null || $v === '') return $default;
        if (is_bool($v)) return $v;
        $v = strtolower($this->text($v));
        if ($v === '') return $default;
        return in_array($v, ['1','y','ya','yes','true','aktif','active'], true);
    }

    /* ===================== helpers tulis file ===================== */

    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:'.$this->colLetter($colCount).'1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function autoSize($sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension($this->colLetter($i))->setAutoSize(true);
        }
    }

    /** Paksa kolom data jadi format TEXT supaya Excel tidak auto-cast (NIS, "7-1", nomor HP). */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = $this->colLetter($colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getStyle($this->colLetter($i))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /** Tulis baris dengan tipe STRING eksplisit, anti auto-cast. */
    protected function writeRowsAsText($sheet, array $rows, int $startRow = 2): void
    {
        foreach ($rows as $rIdx => $row) {
            $r = $startRow + $rIdx;
            $cIdx = 1;
            foreach ($row as $value) {
                $sheet->setCellValueExplicit($this->colLetter($cIdx).$r, (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    /** 1 => A, 27 => AA. */
    protected function colLetter(int $n): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
    }

    protected function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
