<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            // Path file gambar tanda tangan kepala sekolah di disk "public".
            $table->string('ttd_kepala_sekolah')->nullable()->after('nip_kepala_sekolah');
        });
    }

    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropColumn('ttd_kepala_sekolah');
        });
    }
};
