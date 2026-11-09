<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kelas Moving: kelas per mata pelajaran yang anggotanya diambil dari
 * Data Siswa (lintas rombongan belajar).
 */
class KelasMoving extends Model
{
    protected $table = 'kelas_moving';
    protected $guarded = ['id'];

    protected $casts = [
        'is_aktif' => 'boolean',
        'tingkat' => 'integer',
        'kapasitas' => 'integer',
    ];

    public function mapel()
    {
        return $this->belongsTo(MataPelajaran::class, 'mata_pelajaran_id');
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class);
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /** Baris pivot anggota (kelas_moving_siswa). */
    public function anggota()
    {
        return $this->hasMany(KelasMovingSiswa::class, 'kelas_moving_id');
    }

    /** Siswa anggota kelas moving ini — sumber data: tabel siswa. */
    public function siswa()
    {
        return $this->belongsToMany(Siswa::class, 'kelas_moving_siswa', 'kelas_moving_id', 'siswa_id')
            ->withTimestamps();
    }

    /** Label gabungan utk dropdown & pesan: "Nama (Mapel)". */
    public function getLabelAttribute(): string
    {
        return $this->nama_kelas_moving.($this->mapel ? ' — '.$this->mapel->nama_mapel : '');
    }
}
