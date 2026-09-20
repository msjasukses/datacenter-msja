@extends('layouts.app')
@section('title', 'Import Guru Mapel')
@section('breadcrumb', 'Data Center / Guru Mapel / Import')

@section('content')
<x-page-header title="Import Data Guru Mapel" subtitle="Upload rekap pembelajaran Dapodik atau template import untuk mengisi guru ↔ mapel ↔ rombel massal">
    <x-slot:action>
        <a href="{{ route('guru-mapel.index') }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    <form method="POST" action="{{ route('guru-mapel.import.store') }}" enctype="multipart/form-data"
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
            <a href="{{ route('guru-mapel.import.template') }}" class="btn-secondary">Unduh Template</a>
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
            <h3 class="font-semibold text-ink-900">1. Rekap Pembelajaran Dapodik <span class="badge-success ml-1">langsung pakai</span></h3>
            <p class="mt-1 text-xs text-ink-600">
                File <em>Rekap Pembelajaran</em> bisa di-upload apa adanya — 7 baris judul di atas tabel
                dilewati otomatis. Satu baris = satu pembelajaran. Kolom yang dibaca:
            </p>
            <code class="mt-2 block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                Tingkat | Nama Rombel | Nama PTK | NUPTK | Nama Matpel | Kode Matpel
            </code>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li>File ini <strong>tidak punya kolom NIP</strong> — guru dicocokkan lewat NUPTK, lalu nama.
                    Karena itu <strong>import data guru dulu</strong> (NUPTK-nya ikut terisi dari rekap Dapodik).</li>
                <li>Kolom lain (No, Jenis Rombel, Kurikulum, PTK Induk, JJM, Jml Siswa, SK Mengajar, dll.) diabaikan.</li>
            </ul>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <h3 class="font-semibold text-ink-900">2. Template Import</h3>
            <code class="mt-2 block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                NIP | NUPTK | Nama PTK | Tingkat | Rombel | Kode Mapel | Nama Mapel
            </code>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li><code>Rombel</code> boleh berisi beberapa rombel dipisah koma (mis. <code>7-1,7-2,7-3</code>).</li>
                <li>Baris lanjutan boleh mengosongkan kolom guru (sel "digabung") — mewarisi baris terakhir yang terisi.</li>
            </ul>
        </div>

        <div class="pt-3 border-t border-slate-100">
            <h3 class="font-semibold text-ink-900">Cara pencocokan</h3>
            <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                <li><strong>Guru</strong>: NUPTK → NIP → nama (gelar diabaikan). Guru <strong>tidak pernah</strong> dibuat otomatis.</li>
                <li><strong>Mapel</strong>: kode → nama → nama tanpa embel-embel kurung (<code>Matematika (Umum)</code> → <code>Matematika</code>)
                    → singkatan dalam kurung dicocokkan ke kode (<code>(IPS)</code> → <code>IPS</code>).</li>
                <li><strong>Rombel</strong>: angka romawi disetarakan angka biasa — <code>VII-A</code> ≡ <code>7-A</code>.</li>
                <li>Rombel &amp; mapel yang belum ada <strong>dibuat otomatis</strong>, lalu dilaporkan di atas supaya bisa dirapikan.</li>
                <li>Tahun ajaran selalu memakai <strong>Tahun Ajaran aktif</strong>.</li>
                <li>Kombinasi guru + mapel + rombel + TA yang sudah ada akan <strong>di-skip</strong>, tidak digandakan.</li>
            </ul>
        </div>
    </div>
</div>
@endsection
