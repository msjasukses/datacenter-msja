<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->string('nuptk', 30)->nullable()->after('nip')->index();
            $table->string('agama', 50)->nullable()->after('tanggal_lahir');
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropIndex(['nuptk']);
            $table->dropColumn(['nuptk', 'agama']);
        });
    }
};
