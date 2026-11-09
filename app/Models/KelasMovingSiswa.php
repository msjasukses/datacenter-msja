<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Baris keanggotaan siswa di satu kelas moving. */
class KelasMovingSiswa extends Model
{
    protected $table = 'kelas_moving_siswa';
    protected $guarded = ['id'];

    public function kelasMoving()
    {
        return $this->belongsTo(KelasMoving::class, 'kelas_moving_id');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class);
    }
}
