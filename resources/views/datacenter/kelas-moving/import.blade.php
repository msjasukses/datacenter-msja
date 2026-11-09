@extends('layouts.app')
@section('title', 'Import Kelas Moving')
@section('breadcrumb', 'Data Center / Kelas Moving / Import')

@section('content')
<x-page-header title="Import Kelas Moving"
               subtitle="Upload file Excel (.xlsx) untuk membuat kelas moving sekaligus mengisi anggotanya dari Data Siswa">
    <x-slot:action>
        <a href="{{ route('kelas-moving.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('kelas-moving.import.store') }}" enctype="multipart/form-data"
          class="card card-pad space-y-4">
        @csrf
        <h3 class="font-semibold text-ink-900">Upload File Excel</h3>

        <label class="block">
            <span class="label">Pilih file (.xlsx, .xls, .csv)</span>
            <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            <p class="mt-1 text-xs text-ink-500">Maks. 10 MB. Header wajib ada di baris 1.</p>
        </label>

        <div class="flex items-center gap-2 pt-2 border-t border-slate-100">
            <button class="btn-primary">Mulai Import</button>
            <a href="{{ route('kelas-moving.import.template') }}" class="btn-secondary">Unduh Template</a>
        </div>

        @if(session('importNotes') && count(session('importNotes')))
            <div class="mt-3 p-3 rounded-lg bg-sky-50 border border-sky-200 text-sm">
                <div class="font-semibold text-sky-700 mb-2">Catatan:</div>
                <ul class="list-disc pl-5 text-sky-700 text-xs space-y-0.5 max-h-48 overflow-auto">
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

    <div class="card card-pad space-y-3 text-sm">
        <h3 class="font-semibold text-ink-900">Format Kolom</h3>
        <code class="block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
            nama_kelas_moving | kode_mapel | tahun_ajaran | nip_guru | tingkat | kapasitas | ruang | nisn
        </code>
        <ul class="text-xs text-ink-600 list-disc pl-5 space-y-1">
            <li><strong>Satu baris = satu siswa</strong> di satu kelas moving. Untuk satu kelas berisi 30 siswa, buat 30 baris dengan kolom kelas yang sama.</li>
            <li><code>nama_kelas_moving</code> & <code>kode_mapel</code> wajib diisi.</li>
            <li>Kunci unik: <code>nama_kelas_moving</code> + <code>tahun_ajaran</code>. Jika sudah ada → di-update; jika belum → dibuat baru.</li>
            <li><code>tahun_ajaran</code> opsional — nama TA persis (mis. <code>2026/2027</code>). Kosong → pakai Tahun Ajaran aktif.</li>
            <li><code>nip_guru</code> opsional — NIP guru pengampu yang sudah terdaftar.</li>
            <li><code>tingkat</code> opsional — angka. Membatasi kandidat siswa di halaman Anggota.</li>
            <li><code>kapasitas</code> opsional — angka. Kosong → 36. <code>ruang</code> opsional.</li>
            <li><code>nisn</code> opsional — NISN siswa dari menu <strong>Data Siswa</strong>. Kosong → baris hanya membuat kelasnya, anggota diisi manual nanti.</li>
        </ul>

        <h3 class="font-semibold text-ink-900 pt-2 border-t border-slate-100">Aturan</h3>
        <ul class="text-xs text-ink-600 list-disc pl-5 space-y-1">
            <li>Import hanya <strong>menambah</strong> anggota — tidak mengeluarkan anggota lama. Pengeluaran dilakukan dari halaman Anggota.</li>
            <li>Satu siswa tidak boleh ikut dua kelas moving untuk <strong>mapel yang sama</strong> dalam satu tahun ajaran; baris seperti itu dilaporkan gagal.</li>
            <li>Siswa yang sudah jadi anggota kelas yang sama akan dilewati (tidak dobel, tidak dianggap gagal).</li>
            <li>Anggota yang melebihi kapasitas tetap tersimpan, tapi muncul sebagai catatan peringatan.</li>
        </ul>
    </div>
</div>
@endsection
