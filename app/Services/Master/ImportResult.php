<?php

namespace App\Services\Master;

/** Hasil ringkas proses import Excel: dipakai bersama oleh semua *ExcelService. */
class ImportResult
{
    public int $success = 0;
    public int $failed = 0;
    public array $errors = [];

    /**
     * Catatan informatif (bukan kegagalan) — mis. master data yang dibuat
     * otomatis saat import. Ditampilkan terpisah dari daftar error.
     */
    public array $notes = [];
}
