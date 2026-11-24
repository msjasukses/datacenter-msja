<?php

namespace App\Services;

use App\Models\Guru;
use App\Models\Siswa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pengolahan foto siswa & guru:
 *  - normalisasi gambar (resize maks 600x800, simpan JPG, perbaiki rotasi EXIF)
 *  - pencocokan nama file → siswa/guru (NISN/NIS/NIP/NUPTK/nama)
 *  - simpan ke disk public & hapus foto lama
 */
class FotoService
{
    public const MAX_W = 600;
    public const MAX_H = 800;
    public const EXT   = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    /** Daftar tipe yang didukung beserta konfigurasinya. */
    public static function tipe(string $tipe): array
    {
        return match ($tipe) {
            'siswa' => [
                'model' => Siswa::class,
                'label' => 'Siswa',
                'nama'  => 'nama_siswa',
                'kunci' => ['nisn', 'nis'],
                'dir'   => 'foto/siswa',
            ],
            'guru' => [
                'model' => Guru::class,
                'label' => 'Guru',
                'nama'  => 'nama_ptk',
                'kunci' => ['nip', 'nuptk'],
                'dir'   => 'foto/guru',
            ],
            default => abort(404),
        };
    }

    /**
     * Ubah isi file gambar mentah menjadi JPG yang sudah di-resize.
     * Mengembalikan string biner JPG, atau null jika bukan gambar valid.
     */
    public function normalisasi(string $binary): ?string
    {
        $img = @imagecreatefromstring($binary);
        if (! $img) return null;

        $img = $this->perbaikiRotasi($img, $binary);

        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, self::MAX_W / $w, self::MAX_H / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $out = imagecreatetruecolor($nw, $nh);
        // Latar putih supaya PNG transparan tidak jadi hitam saat jadi JPG.
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($out, null, 85);
        $jpg = ob_get_clean();

        imagedestroy($img);
        imagedestroy($out);

        return $jpg ?: null;
    }

    /** Foto dari HP sering tersimpan miring — putar sesuai tag EXIF Orientation. */
    protected function perbaikiRotasi(\GdImage $img, string $binary): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($binary, "\xFF\xD8")) {
            return $img;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($binary));
        $o = $exif['Orientation'] ?? 1;
        $rotated = match ((int) $o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => null,
        };
        if ($rotated) {
            imagedestroy($img);
            return $rotated;
        }
        return $img;
    }

    /** Simpan foto baru untuk 1 orang (menghapus foto lama). Return URL publik. */
    public function simpan(Model $orang, string $tipe, string $binary): ?string
    {
        $jpg = $this->normalisasi($binary);
        if (! $jpg) return null;

        $cfg = self::tipe($tipe);
        $path = $cfg['dir'].'/'.$orang->getKey().'_'.Str::random(8).'.jpg';

        Storage::disk('public')->put($path, $jpg);
        $this->hapusFile($orang->foto);
        $orang->forceFill(['foto' => $path])->save();

        return asset('storage/'.$path);
    }

    public function hapus(Model $orang): void
    {
        $this->hapusFile($orang->foto);
        $orang->forceFill(['foto' => null])->save();
    }

    protected function hapusFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Cari siswa/guru dari nama file. Urutan pencocokan:
     *  1. nama file utuh = NISN/NIS (siswa) atau NIP/NUPTK (guru)
     *  2. bagian pertama sebelum spasi/_/- (mis. "0081234567_Budi Santoso.jpg")
     *  3. nama lengkap (mis. "Budi Santoso.jpg" / "budi_santoso.jpg")
     */
    public function cocokkan(string $tipe, string $namaFile): ?Model
    {
        $cfg = self::tipe($tipe);
        $model = $cfg['model'];

        $stem = trim(pathinfo(basename(str_replace('\\', '/', $namaFile)), PATHINFO_FILENAME));
        if ($stem === '') return null;

        $kandidat = array_unique(array_filter([
            $stem,
            preg_replace('/\s+/', '', $stem),
            preg_split('/[\s_\-.]+/', $stem)[0] ?? null,
        ]));

        foreach ($kandidat as $k) {
            foreach ($cfg['kunci'] as $kolom) {
                $hit = $model::where($kolom, $k)->first();
                if ($hit) return $hit;
            }
        }

        // Pencocokan nama — hanya diterima jika hasilnya tepat 1 orang.
        $nama = trim(preg_replace('/[\s_\-.]+/', ' ', preg_replace('/^\d+[\s_\-.]+/', '', $stem)));
        if (mb_strlen($nama) >= 3) {
            $hits = $model::whereRaw("LOWER({$cfg['nama']}) = ?", [mb_strtolower($nama)])->limit(2)->get();
            if ($hits->count() === 1) return $hits->first();
        }

        return null;
    }

    public function ekstensiValid(string $namaFile): bool
    {
        return in_array(strtolower(pathinfo($namaFile, PATHINFO_EXTENSION)), self::EXT, true);
    }
}
