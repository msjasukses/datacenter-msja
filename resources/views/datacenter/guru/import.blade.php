@extends('layouts.app')
@section('title', 'Import Data Guru')
@section('breadcrumb', 'Data Center / Guru / Import')

@section('content')
<x-page-header title="Import Data Guru" subtitle="Upload unduhan Dapodik atau template import untuk menambah / memperbarui guru massal">
    <x-slot:action>
        <a href="{{ route('guru.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('guru.import.store') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">Upload File Excel</h3>

        <label class="block">
            <span class="label">Pilih file (.xlsx, .xls, .csv)</span>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 5 MB. Baris header dicari otomatis — file Dapodik tidak perlu dirapikan dulu.</p>
        </label>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <button class="btn-primary">Mulai Import</button>
            <a href="{{ route('guru.import.template') }}" class="btn-secondary">Unduh Template</a>
        </div>

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
                File <em>Daftar Guru</em> dari Dapodik bisa di-upload apa adanya — 4 baris judul di atas tabel
                dilewati otomatis. Kolom yang dibaca:
            </p>
            <code class="mt-2 block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                Nama | NUPTK | JK | NIP | Tempat Lahir | Tanggal Lahir | Status Kepegawaian | Jenis PTK | Agama |
                Alamat Jalan | RT | RW | Nama Dusun | Desa/Kelurahan | Kecamatan | Kode Pos | Telepon | HP | Email | Tugas Tambahan
            </code>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li>Kolom Dapodik lain (NPWP, rekening, NIK, dll.) diabaikan.</li>
                <li><strong>Alamat</strong> dirangkai dari Alamat Jalan + RT/RW + dusun + kelurahan + kecamatan + kode pos.</li>
                <li><strong>Jabatan</strong> diambil dari <em>Tugas Tambahan</em>; kalau kosong dipakai <em>Jenis PTK</em>.</li>
                <li><strong>Nomor HP</strong> dari kolom HP; kalau kosong dipakai Telepon.</li>
            </ul>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <h3 class="font-semibold text-ink-900">2. Template Import</h3>
            <code class="mt-2 block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                nip | nuptk | nama_ptk | email | nomor_hp | jenis_kelamin | tempat_lahir | tanggal_lahir | agama | alamat | jabatan | status_kepegawaian | password | is_aktif
            </code>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li><code>password</code> opsional. Jika kosong &amp; akun baru, default = <code>password</code>.</li>
                <li><code>is_aktif</code>: 1 atau 0 (jika kosong = 1)</li>
            </ul>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <h3 class="font-semibold text-ink-900">Berlaku untuk keduanya</h3>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li>Nama wajib diisi, begitu juga NIP — atau NUPTK bila NIP belum ada.</li>
                <li><code>NIP</code> jadi kunci unik: sudah ada → di-update, belum ada → dibuat baru.</li>
                <li>Kolom yang kosong di file <strong>tidak</strong> menghapus data lama saat update.</li>
                <li><code>JK</code>: <code>L</code>/<code>P</code> atau Laki-laki/Perempuan.</li>
                <li>Tanggal: <code>YYYY-MM-DD</code>, <code>dd/mm/yyyy</code>, atau format tanggal Excel.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
