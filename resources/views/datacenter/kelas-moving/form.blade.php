@extends('layouts.app')
@section('title', $item->exists ? 'Edit Kelas Moving' : 'Tambah Kelas Moving')
@section('breadcrumb', 'Data Center / Kelas Moving / ' . ($item->exists ? 'Edit' : 'Tambah'))

@section('content')
<x-page-header :title="$item->exists ? 'Edit Kelas Moving' : 'Tambah Kelas Moving'"
               subtitle="Satu kelas moving = satu mata pelajaran. Anggotanya dipilih dari Data Siswa setelah kelas disimpan."/>

<form method="POST" action="{{ $item->exists ? route('kelas-moving.update', $item) : route('kelas-moving.store') }}"
      class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="nama_kelas_moving" label="Nama Kelas Moving" :value="$item->nama_kelas_moving" required
                 placeholder="Matematika Lanjut 1" help="Harus unik dalam satu tahun ajaran."/>
        <x-field type="select" name="mata_pelajaran_id" label="Mata Pelajaran" :value="$item->mata_pelajaran_id" required
                 :options="$mapelList->mapWithKeys(fn($m) => [$m->id => $m->nama_mapel . ' (' . $m->kode_mapel . ')'])->toArray()"/>
        <x-field type="select" name="guru_id" label="Guru Pengampu" :value="$item->guru_id"
                 :options="$guruList->pluck('nama_ptk', 'id')->toArray()"/>
        <x-field type="select" name="tahun_ajaran_id" label="Tahun Ajaran" :value="$item->tahun_ajaran_id" required
                 :options="$taList->mapWithKeys(fn($t) => [$t->id => $t->nama_tahun_ajaran . ($t->is_aktif ? ' (aktif)' : '')])->toArray()"/>
        <x-field type="select" name="tingkat" label="Tingkat Kelas" :value="$item->tingkat"
                 :options="$tingkatList->mapWithKeys(fn($t) => [$t->nomor => $t->nama])->toArray()"
                 help="Opsional. Kalau diisi, hanya siswa di tingkat itu yang muncul sebagai kandidat anggota."/>
        <x-field name="kapasitas" type="number" label="Kapasitas" :value="$item->kapasitas ?? 36"
                 help="Dipakai sebagai peringatan saja, tidak memblokir penambahan anggota."/>
        <x-field name="ruang" label="Ruang" :value="$item->ruang" placeholder="R-101"/>
        <div class="flex items-end">
            <x-field type="checkbox" name="is_aktif" :value="$item->exists ? $item->is_aktif : true" help="Kelas moving aktif"/>
        </div>
    </div>

    <x-field type="textarea" name="keterangan" label="Keterangan" :value="$item->keterangan"
             placeholder="Catatan tambahan, mis. jadwal pertemuan"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('kelas-moving.index', ['ta' => $item->tahun_ajaran_id]) }}" class="btn-secondary">Batal</a>
        @if($item->exists)
            <a href="{{ route('kelas-moving.anggota', $item) }}" class="btn-secondary">
                <x-icon name="users" class="w-4 h-4"/> Kelola Anggota
            </a>
        @endif
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
