<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sekolah extends Model
{
    protected $table = 'sekolah';
    protected $guarded = ['id'];

    public function getLogoUrlAttribute(): string
    {
        return $this->logo
            ? asset('storage/'.$this->logo)
            : asset('img/logo-default.svg');
    }

    public function getTtdKepalaSekolahUrlAttribute(): ?string
    {
        return $this->ttd_kepala_sekolah
            ? asset('storage/'.$this->ttd_kepala_sekolah)
            : null;
    }
}
