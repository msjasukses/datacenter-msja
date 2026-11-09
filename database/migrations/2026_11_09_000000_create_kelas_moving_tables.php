<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas Moving (moving class) — kelas per mata pelajaran yang anggotanya
 * diambil dari Data Siswa, lintas rombongan belajar.
 *
 * Bedanya dengan rombongan_belajar: rombel adalah kelas "rumah" siswa
 * (satu siswa satu rombel per tahun ajaran), sedangkan kelas moving dibentuk
 * per mapel sehingga satu siswa bisa ikut beberapa kelas moving sekaligus
 * (satu untuk tiap mapel) dan satu kelas moving bisa berisi siswa dari
 * rombel mana saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_moving', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas_moving', 100);                 // mis. "Matematika Lanjut 1"
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('guru')->nullOnDelete(); // guru pengampu
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->cascadeOnDelete();
            $table->unsignedTinyInteger('tingkat')->nullable();        // batasi kandidat siswa per tingkat
            $table->unsignedSmallInteger('kapasitas')->default(36);
            $table->string('ruang', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();

            // Nama kelas moving unik dalam satu tahun ajaran — dipakai juga
            // sebagai kunci upsert oleh import Excel.
            $table->unique(['nama_kelas_moving', 'tahun_ajaran_id']);
            $table->index(['tahun_ajaran_id', 'mata_pelajaran_id']);
        });

        // Anggota kelas moving — diambil dari tabel siswa (menu Data Siswa).
        Schema::create('kelas_moving_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_moving_id')->constrained('kelas_moving')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kelas_moving_id', 'siswa_id']);
            $table->index('siswa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_moving_siswa');
        Schema::dropIfExists('kelas_moving');
    }
};
