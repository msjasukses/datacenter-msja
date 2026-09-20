@extends('layouts.app')
@section('title', $item->exists ? 'Edit Guru' : 'Tambah Guru')

@section('content')
@php
    /*
     * Nilai hasil import Dapodik bisa di luar daftar pilihan bawaan
     * (mis. "PPPK Paruh Waktu"). Selipkan nilai yang tersimpan ke dalam opsi
     * supaya tidak ikut terhapus begitu form ini di-simpan ulang.
     */
    $opsi = function (array $daftar, ?string $terpakai) {
        if ($terpakai && ! in_array($terpakai, $daftar, true)) $daftar[] = $terpakai;
        return array_combine($daftar, $daftar);
    };
    $statusList = $opsi(['PNS', 'PPPK', 'PPPK Paruh Waktu', 'GTT', 'GTY', 'Honorer'], $item->status_kepegawaian);
    $agamaList  = $opsi(['Islam', 'Kristen', 'Katholik', 'Hindu', 'Budha', 'Konghucu', 'Lainnya'], $item->agama);
@endphp
<x-page-header :title="$item->exists ? 'Edit Data Guru' : 'Tambah Data Guru'"/>
<form method="POST" action="{{ $item->exists ? route('guru.update', $item) : route('guru.store') }}" class="card card-pad space-y-4 max-w-4xl">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="nip" label="NIP" :value="$item->nip" required/>
        <x-field name="nuptk" label="NUPTK" :value="$item->nuptk"/>
        <x-field name="nama_ptk" label="Nama Lengkap" :value="$item->nama_ptk" required/>
        <x-field name="email" type="email" label="Email" :value="$item->email"/>
        <x-field name="nomor_hp" label="Nomor HP" :value="$item->nomor_hp"/>
        <x-field type="select" name="jenis_kelamin" label="Jenis Kelamin" :value="$item->jenis_kelamin"
                 :options="['L'=>'Laki-laki','P'=>'Perempuan']"/>
        <x-field name="tempat_lahir" label="Tempat Lahir" :value="$item->tempat_lahir"/>
        <x-field name="tanggal_lahir" type="date" label="Tanggal Lahir" :value="optional($item->tanggal_lahir)->format('Y-m-d')"/>
        <x-field type="select" name="agama" label="Agama" :value="$item->agama" :options="$agamaList"/>
        <x-field name="jabatan" label="Jabatan" :value="$item->jabatan"/>
        <x-field type="select" name="mata_pelajaran_id" label="Guru Mata Pelajaran" :value="$item->mata_pelajaran_id"
                 :options="$mapelList->toArray()"/>
        <x-field type="select" name="status_kepegawaian" label="Status Kepegawaian" :value="$item->status_kepegawaian"
                 :options="$statusList"/>
        <x-field name="password" type="password" label="Password" :help="$item->exists ? 'Kosongkan jika tidak diubah' : 'Default sama dengan password'"/>
    </div>
    <x-field name="alamat" label="Alamat" :value="$item->alamat"/>
    <x-field type="checkbox" name="is_aktif" :value="$item->is_aktif ?? true"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('guru.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
