<?php

namespace App\Models;

use App\Concerns\HasRbac;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Siswa extends Authenticatable
{
    use HasFactory, Notifiable, HasRbac;

    protected $table = 'siswa';
    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_aktif' => 'boolean',
            'password' => 'hashed',
            'last_seen_at' => 'datetime',
            'locked_until' => 'datetime',
            'otp_enabled' => 'boolean',
        ];
    }

    public function otpCodes(): MorphMany
    {
        return $this->morphMany(OtpCode::class, 'authable');
    }

    public function username(): string { return 'nisn'; }

    public function getUserTypeAttribute(): string { return 'siswa'; }
    public function getNameAttribute(): ?string { return $this->attributes['nama_siswa'] ?? null; }
    public function getUserNameAttribute(): string { return $this->nisn; }

    /** Apakah akun sedang terkunci karena terlalu banyak percobaan login gagal. */
    public function getIsTerkunciAttribute(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->foto ? asset('storage/'.$this->foto) : null;
    }

    public function rombelSekarang()
    {
        return $this->hasOne(SiswaRombel::class)
            ->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true));
    }

    public function siswaRombel()
    {
        return $this->hasMany(SiswaRombel::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** Kelas moving (kelas per mata pelajaran) yang diikuti siswa ini. */
    public function kelasMoving()
    {
        return $this->belongsToMany(KelasMoving::class, 'kelas_moving_siswa', 'siswa_id', 'kelas_moving_id')
            ->withTimestamps();
    }

    /** Kelas moving pada tahun ajaran yang sedang aktif. */
    public function kelasMovingAktif()
    {
        return $this->kelasMoving()->whereHas('tahunAjaran', fn ($q) => $q->where('is_aktif', true));
    }
}
