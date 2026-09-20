@extends('layouts.app')
@section('title', $item->exists ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna')
@section('breadcrumb', 'Administrasi / Akun Pengguna')

@section('content')
@php $isMe = $item->exists && $item->is(auth()->user()); @endphp
<x-page-header :title="$item->exists ? 'Edit Akun Pengguna' : 'Tambah Akun Pengguna'"/>
<form method="POST" action="{{ $item->exists ? route('pengguna.update', $item) : route('pengguna.store') }}" class="card card-pad space-y-4 max-w-2xl">
    @csrf @if($item->exists) @method('PUT') @endif
    <div class="grid md:grid-cols-2 gap-4">
        <x-field name="name" label="Nama" :value="$item->name" required/>
        <x-field type="email" name="email" label="Email (untuk login)" :value="$item->email" required/>
        <x-field name="nomor_hp" label="Nomor HP" :value="$item->nomor_hp"/>
        <x-field type="select" name="role_id" label="Peran" :value="$item->role_id" :options="$roles" required
                 :disabled="$isMe" :help="$isMe ? 'Peran akun Anda sendiri tidak dapat diubah.' : null"/>
        <x-field type="select" name="account_status" label="Status Akun" :value="$item->account_status ?? 'active'"
                 :options="\App\Http\Controllers\PenggunaController::STATUS" required
                 :disabled="$isMe" :help="$isMe ? 'Status akun Anda sendiri tidak dapat diubah.' : null"/>
    </div>

    @if($isMe)
        {{-- field disabled tidak ikut terkirim; kirim nilai aslinya agar lolos validasi --}}
        <input type="hidden" name="role_id" value="{{ $item->role_id }}">
        <input type="hidden" name="account_status" value="active">
    @endif

    <div class="grid md:grid-cols-2 gap-4 pt-3 border-t border-slate-100">
        <x-field type="password" name="password" :label="$item->exists ? 'Password Baru' : 'Password'" :required="! $item->exists"
                 autocomplete="new-password" :help="$item->exists ? 'Kosongkan jika tidak ingin mengganti password.' : 'Minimal 8 karakter.'"/>
        <x-field type="password" name="password_confirmation" label="Ulangi Password" :required="! $item->exists" autocomplete="new-password"/>
    </div>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('pengguna.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan</button>
    </div>
</form>
@endsection
