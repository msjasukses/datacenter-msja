@extends('layouts.app')
@section('title', 'Import Data Siswa')
@section('breadcrumb', 'Data Center / Siswa / Import')

@section('content')
<x-page-header title="Import Data Siswa" subtitle="Upload unduhan Dapodik atau template import untuk menambah / memperbarui siswa massal">
    <x-slot:action>
        <a href="{{ route('siswa.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('siswa.import.store') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">Upload File Excel</h3>

        <label class="block">
            <span class="label">Pilih file (.xlsx, .xls, .csv)</span>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 10 MB. Baris header dicari otomatis — file Dapodik tidak perlu dirapikan dulu.</p>
        </label>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <button class="btn-primary">Mulai Import</button>
            <a href="{{ route('siswa.import.template') }}" class="btn-secondary">Unduh Template</a>
        </div>

        @if(session('importNotes') && count(session('importNotes')))
            <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm">
                <div class="font-semibold text-amber-800 mb-2">Master data dibuat otomatis — mohon diperiksa:</div>
                <ul class="list-disc pl-5 text-amber-700 text-xs space-y-1 max-h-48 overflow-auto">
                    @foreach(session('importNotes') as $note)<li>{{ $note }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(session('importErrors') && count(session('importErrors')))
            <div class="mt-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-sm">
                <div class="font-semibold text-rose-700 mb-2">{{ count(session('importErrors')) }} baris gagal:</div>
                <ul class="list-disc pl-5 text-rose-600 text-xs space-y-0.5 max-h-48 overflow-auto">
                    @foreach(session('importErrors') as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
    </form>

    <div class="card card-pad space-y-4 text-sm">
        <div>
            <h3 class="font-semibold text-ink-900">1. Unduhan Dapodik <span class="badge-success ml-1">langsung pakai</span></h3>
            <p class="mt-1 text-xs text-ink-600">
                File <em>Daftar Peserta Didik</em> bisa di-upload apa adanya — 4 baris judul di atas tabel
                dilewati otomatis. Dua varian sama-sama didukung: yang ringkas (19 kolom) maupun yang
                lengkap (66 kolom, header 2 baris dengan grup <em>Data Ayah/Ibu/Wali</em>).
            </p>
            <code class="mt-2 block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                Nama | NIPD | JK | NISN | Tempat Lahir | Tanggal Lahir | Agama | Alamat | RT | RW | Dusun |
                Kelurahan | Kecamatan | Kode Pos | Telepon | HP | E-Mail | Rombel Saat Ini | Data Ayah&gt;Nama | Data Ibu&gt;Nama
            </code>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li>Kolom Dapodik lain (NIK, No KK, data wali, penghasilan, KIP, dll.) diabaikan.</li>
                <li><strong>Alamat</strong> dirangkai dari Alamat + RT/RW + dusun + kelurahan + kecamatan + kode pos.</li>
                <li><strong>Nomor HP</strong> dari kolom HP; kalau kosong dipakai Telepon.</li>
            </ul>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <h3 class="font-semibold text-ink-900">2. Template Import</h3>
            <code class="mt-2 block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                nisn | nis | nama_siswa | jenis_kelamin | tempat_lahir | tanggal_lahir | agama | alamat | nomor_hp | email | nama_ayah | nama_ibu | nomor_hp_ortu | rombel | password | is_aktif
            </code>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li><code>password</code> opsional. Jika kosong &amp; akun baru, default = NISN.</li>
                <li><code>is_aktif</code>: 1 atau 0 (jika kosong = 1)</li>
            </ul>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <h3 class="font-semibold text-ink-900">Berlaku untuk keduanya</h3>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li>NISN dan Nama wajib diisi. <code>NISN</code> jadi kunci unik: sudah ada → di-update, belum ada → dibuat baru.</li>
                <li>Kolom yang kosong di file <strong>tidak</strong> menghapus data lama saat update.</li>
                <li><code>JK</code>: <code>L</code>/<code>P</code> atau Laki-laki/Perempuan.</li>
                <li>Tanggal: <code>YYYY-MM-DD</code>, <code>dd/mm/yyyy</code>, atau format tanggal Excel.</li>
                <li><strong>Rombel</strong> dipasang di Tahun Ajaran <strong>aktif</strong>. Angka romawi disetarakan angka biasa
                    dan awalan "Kelas" diabaikan — <code>VII-1</code> ≡ <code>7-1</code>, <code>Kelas 7B</code> ≡ <code>7-B</code>.</li>
                <li>Rombel yang belum ada <strong>dibuat otomatis</strong> (tingkat diambil dari namanya), lalu dilaporkan di atas.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
