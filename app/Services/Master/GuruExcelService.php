<?php

namespace App\Services\Master;

use App\Models\Guru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export data guru via Excel (.xlsx).
 *
 * Import menerima 2 bentuk file sekaligus (dideteksi otomatis):
 *
 * 1. Unduhan Dapodik "Daftar Guru" apa adanya — 4 baris judul di atas, header
 *    di baris 5, kolom bernama tampilan: Nama | NUPTK | JK | NIP |
 *    Status Kepegawaian | Agama | Alamat Jalan | RT | RW | Desa/Kelurahan | ...
 *    Kolom Dapodik yang tidak dipakai diabaikan.
 *
 * 2. Template internal (lihat self::HEADERS) — header di baris 1:
 *    nip | nuptk | nama_ptk | email | nomor_hp | jenis_kelamin | tempat_lahir |
 *    tanggal_lahir | agama | alamat | jabatan | status_kepegawaian | password | is_aktif
 *
 * Catatan:
 * - Kunci unik = NIP. Jika NIP kosong, NUPTK dipakai sebagai penggantinya.
 * - jenis_kelamin: L / P (juga menerima "Laki-laki" / "Perempuan").
 * - tanggal_lahir: YYYY-MM-DD, dd/mm/yyyy, atau date serial Excel.
 * - alamat: pada file Dapodik dirangkai dari Alamat Jalan + RT/RW + dusun +
 *   kelurahan + kecamatan + kode pos.
 * - jabatan: diambil dari "Tugas Tambahan", jatuh ke "Jenis PTK" bila kosong.
 * - password: opsional. Jika kosong pada akun baru, default = password.
 * - is_aktif: 1 / 0 / kosong (default 1).
 */
class GuruExcelService
{
    public const HEADERS = [
        'nip','nuptk','nama_ptk','email','nomor_hp','jenis_kelamin','tempat_lahir',
        'tanggal_lahir','agama','alamat','jabatan','status_kepegawaian','password','is_aktif',
    ];

    /**
     * Nama kolom (sudah dinormalisasi: huruf kecil, non-alfanumerik jadi spasi)
     * => nama field internal. Mencakup nama kolom template internal maupun
     * label tampilan pada unduhan Dapodik.
     */
    protected const ALIASES = [
        // identitas
        'nip'                 => 'nip',
        'nuptk'               => 'nuptk',
        'nama'                => 'nama_ptk',
        'nama ptk'            => 'nama_ptk',
        'nama guru'           => 'nama_ptk',
        'nama lengkap'        => 'nama_ptk',
        'jk'                  => 'jenis_kelamin',
        'jenis kelamin'       => 'jenis_kelamin',
        'tempat lahir'        => 'tempat_lahir',
        'tanggal lahir'       => 'tanggal_lahir',
        'tgl lahir'           => 'tanggal_lahir',
        'agama'               => 'agama',

        // kepegawaian
        'status kepegawaian'  => 'status_kepegawaian',
        'jenis ptk'           => 'jenis_ptk',
        'tugas tambahan'      => 'jabatan',
        'jabatan'             => 'jabatan',

        // kontak
        'email'               => 'email',
        'e mail'              => 'email',
        'surel'               => 'email',
        'hp'                  => 'nomor_hp',
        'no hp'               => 'nomor_hp',
        'nomor hp'            => 'nomor_hp',
        'handphone'           => 'nomor_hp',
        'telepon'             => 'telepon',
        'telp'                => 'telepon',
        'no telepon'          => 'telepon',

        // alamat (Dapodik memecahnya jadi beberapa kolom)
        'alamat'              => 'alamat_jalan',
        'alamat jalan'        => 'alamat_jalan',
        'jalan'               => 'alamat_jalan',
        'rt'                  => 'rt',
        'rw'                  => 'rw',
        'nama dusun'          => 'dusun',
        'dusun'               => 'dusun',
        'desa kelurahan'      => 'kelurahan',
        'kelurahan'           => 'kelurahan',
        'desa'                => 'kelurahan',
        'kecamatan'           => 'kecamatan',
        'kode pos'            => 'kode_pos',

        // khusus template internal
        'password'            => 'password',
        'kata sandi'          => 'password',
        'is aktif'            => 'is_aktif',
        'aktif'               => 'is_aktif',
    ];

    /** Berapa baris pertama yang dipindai saat mencari baris header. */
    protected const HEADER_SCAN_ROWS = 20;

    public function import(UploadedFile $file): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        // formatData = false: unduhan Dapodik menyimpan NIP/NUPTK sebagai teks
        // tapi memberinya number format "0". Kalau diformat, PhpSpreadsheet
        // melewatkannya sebagai float — NIP 18 digit kehilangan presisi dan
        // NUPTK kehilangan angka 0 di depan. Nilai mentah dipakai apa adanya;
        // tanggal serial Excel tetap ditangani parseDate().
        $data = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $result = new ImportResult();

        $headerRow = $this->locateHeaderRow($data);
        if ($headerRow === null) {
            $result->failed++;
            $result->errors[] = 'Header kolom tidak ditemukan. Pastikan file memuat kolom Nama dan NIP/NUPTK '
                .'(unduhan Dapodik "Daftar Guru" atau template import).';
            return $result;
        }

        $map = $this->mapColumns($data[$headerRow]);

        foreach ($data as $rowNo => $row) {
            if ($rowNo <= $headerRow) continue;
            if (! $this->hasContent($row)) continue;

            $assoc = $this->readRow($row, $map);
            $nip   = $this->text($assoc['nip'] ?? null);
            $nuptk = $this->text($assoc['nuptk'] ?? null);
            $nama  = $this->text($assoc['nama_ptk'] ?? null);

            // NIP boleh kosong (mis. guru honorer baru) selama NUPTK ada.
            $kunci = $nip !== '' ? $nip : $nuptk;
            if ($nama === '' || $kunci === '') continue;

            try {
                $payload = [
                    'nama_ptk'           => $nama,
                    'nuptk'              => $nuptk ?: null,
                    'email'              => $this->text($assoc['email'] ?? null) ?: null,
                    'nomor_hp'           => $this->text($assoc['nomor_hp'] ?? null)
                                            ?: ($this->text($assoc['telepon'] ?? null) ?: null),
                    'jenis_kelamin'      => $this->parseJenisKelamin($assoc['jenis_kelamin'] ?? null),
                    'tempat_lahir'       => $this->text($assoc['tempat_lahir'] ?? null) ?: null,
                    'tanggal_lahir'      => $this->parseDate($assoc['tanggal_lahir'] ?? null),
                    'agama'              => $this->text($assoc['agama'] ?? null) ?: null,
                    'alamat'             => $this->composeAlamat($assoc),
                    'jabatan'            => $this->text($assoc['jabatan'] ?? null)
                                            ?: ($this->text($assoc['jenis_ptk'] ?? null) ?: null),
                    'status_kepegawaian' => $this->text($assoc['status_kepegawaian'] ?? null) ?: null,
                    'is_aktif'           => $this->parseBool($assoc['is_aktif'] ?? null, true),
                ];

                $pwd = $this->text($assoc['password'] ?? null);
                if ($pwd !== '') {
                    $payload['password'] = Hash::make($pwd);
                }

                $g = $this->findExisting($nip, $nuptk);
                if ($g) {
                    // Jangan menimpa nilai lama dengan kolom yang kosong di file.
                    // NIP sengaja tidak diubah: itu username guru untuk login.
                    $g->update(array_filter($payload, fn ($v) => $v !== null));
                } else {
                    $payload['nip'] = $kunci;
                    // Akun baru: password default = password jika tidak diisi.
                    $payload['password'] ??= Hash::make('password');
                    Guru::create($payload);
                }
                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = 'Baris '.($rowNo + 1).' ('.$nama.'): '.$e->getMessage();
            }
        }

        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $guru = null): StreamedResponse
    {
        $guru ??= Guru::orderBy('nama_ptk')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Guru');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $guru->map(fn ($g) => [
            $g->nip, $g->nuptk, $g->nama_ptk, $g->email, $g->nomor_hp, $g->jenis_kelamin,
            $g->tempat_lahir, optional($g->tanggal_lahir)->format('Y-m-d'), $g->agama,
            $g->alamat, $g->jabatan, $g->status_kepegawaian,
            '', // password kosong saat export (alasan keamanan)
            $g->is_aktif ? '1' : '0',
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);
        $this->autoSize($sheet, count(self::HEADERS));

        return $this->stream($spreadsheet, 'data-guru-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Guru');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        // 2 baris contoh
        $this->writeRowsAsText($sheet, [
            ['198001012000031000', '1234567890123456', 'Andi Wijaya, S.Pd.', 'andi@sekolah.test', '081234567890', 'L', 'Bandung', '1980-01-01', 'Islam',   'Jl. Mawar 1',  'Guru',    'PNS',  '', '1'],
            ['198502102001012001', '6543210987654321', 'Sri Wahyuni, M.Pd.', 'sri@sekolah.test',  '081234567891', 'P', 'Bogor',   '1985-02-10', 'Kristen', 'Jl. Melati 2', 'Wakasek', 'PPPK', '', '1'],
        ], 2);

        $this->autoSize($sheet, count(self::HEADERS));

        return $this->stream($spreadsheet, 'template-import-guru.xlsx');
    }

    /*    deteksi header    */

    /**
     * Cari baris header. Unduhan Dapodik menaruh 4 baris judul di atas tabel,
     * jadi header tidak selalu di baris 1. Baris dianggap header kalau memuat
     * kolom nama sekaligus kolom NIP atau NUPTK.
     */
    protected function locateHeaderRow(array $data): ?int
    {
        $batas = min(count($data), self::HEADER_SCAN_ROWS);

        for ($i = 0; $i < $batas; $i++) {
            $fields = array_values($this->mapColumns($data[$i]));
            if (! in_array('nama_ptk', $fields, true)) continue;
            if (! array_intersect(['nip', 'nuptk'], $fields)) continue;
            return $i;
        }

        return null;
    }

    /** @return array<int,string> indeks kolom => nama field internal */
    protected function mapColumns(?array $headerRow): array
    {
        $map = [];
        foreach ($headerRow ?? [] as $idx => $label) {
            $key = $this->normalizeHeader($label);
            if ($key !== '' && isset(self::ALIASES[$key])) {
                $map[$idx] = self::ALIASES[$key];
            }
        }
        return $map;
    }

    /** "Desa/Kelurahan" => "desa kelurahan", "nama_ptk" => "nama ptk". */
    protected function normalizeHeader($label): string
    {
        $label = strtolower(trim((string) $label));
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);
        return trim(preg_replace('/\s+/', ' ', $label));
    }

    /**
     * Ambil satu baris jadi array field internal. Kolom yang memetakan ke field
     * yang sama (mis. "Jabatan" dan "Tugas Tambahan") tidak saling menimpa:
     * nilai pertama yang terisi yang dipakai.
     *
     * @param array<int,string> $map
     */
    protected function readRow(array $row, array $map): array
    {
        $assoc = [];
        foreach ($map as $idx => $field) {
            $value = $row[$idx] ?? null;
            if ($this->text($value) === '' && isset($assoc[$field])) continue;
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

    protected function findExisting(string $nip, string $nuptk): ?Guru
    {
        if ($nip !== '' && $g = Guru::where('nip', $nip)->first()) return $g;
        if ($nuptk !== '' && $g = Guru::where('nuptk', $nuptk)->first()) return $g;
        return null;
    }

    /*    normalisasi nilai    */

    /**
     * Nilai sel jadi string bersih. Angka besar (NIP/NUPTK/NIK) yang terbaca
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
        if ($jalan !== '') $parts[] = $jalan;

        $rt = $this->text($assoc['rt'] ?? null);
        $rw = $this->text($assoc['rw'] ?? null);
        if ($rt !== '' || $rw !== '') $parts[] = 'RT '.($rt ?: '-').'/RW '.($rw ?: '-');

        foreach (['dusun', 'kelurahan', 'kecamatan', 'kode_pos'] as $key) {
            $v = $this->text($assoc[$key] ?? null);
            // Dapodik sering mengisi dusun = kelurahan; jangan diulang.
            if ($v === '' || ($parts && strcasecmp(end($parts), $v) === 0)) continue;
            $parts[] = $v;
        }

        $alamat = implode(', ', $parts);
        return $alamat !== '' ? mb_substr($alamat, 0, 255) : null;
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

    /*    helpers tulis file    */

    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:'.$this->colName($colCount).'1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Paksa kolom data jadi format TEXT supaya Excel tidak auto-cast (NIP, tanggal "7-1", dll). */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = $this->colName($colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getStyle($this->colName($i))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /** Tulis baris dengan tipe STRING eksplisit, anti auto-cast. */
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
