<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration:
 *  - Tambah permission baru: kelas-moving/*
 *  - Sync ke role 'admin' dan 'super-admin' yang sudah ada di DB.
 *
 * Tanpa migration ini, admin/super-admin yang akunnya terhubung ke role custom
 * (role_id terisi) akan mendapat 403 saat membuka menu Kelas Moving, karena
 * HasRbac::getPermissions() memakai daftar permission eksplisit dari role
 * tersebut. Mengikuti pola 2026_07_02_000001_grant_periodikal_permission.php.
 *
 * Tidak merubah skema — hanya isi data tabel permissions & role_permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->tableExists('roles') || ! $this->tableExists('permissions') || ! $this->tableExists('role_permissions')) {
            return;
        }

        $permission = DB::table('permissions')->where('permission', 'kelas-moving/*')->first();
        if (! $permission) {
            $permId = DB::table('permissions')->insertGetId([
                'permission' => 'kelas-moving/*',
                'label' => 'Kelas Moving (Kelas per Mata Pelajaran)',
                'group' => 'datacenter',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $permId = $permission->id;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['admin', 'super-admin'])
            ->pluck('id')->toArray();

        foreach ($roleIds as $roleId) {
            $already = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permId)
                ->exists();
            if (! $already) {
                DB::table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! $this->tableExists('permissions')) return;

        $permId = DB::table('permissions')->where('permission', 'kelas-moving/*')->value('id');

        if ($permId) {
            DB::table('role_permissions')->where('permission_id', $permId)->delete();
            DB::table('permissions')->where('id', $permId)->delete();
        }
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
};
