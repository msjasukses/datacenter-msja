@extends('layouts.app')
@section('title', 'Akun Pengguna')
@section('breadcrumb', 'Administrasi / Akun Pengguna')

@section('content')
<x-page-header title="Akun Pengguna" subtitle="Kelola akun admin & operator yang dapat masuk ke Data Center">
    <x-slot:action>
        <a href="{{ route('pengguna.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ $q }}" class="input" placeholder="Cari nama atau email...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="card overflow-x-auto">
    <table class="table-modern">
        <thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Terakhir Aktif</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $it)
            @php $isMe = $it->is(auth()->user()); @endphp
            <tr>
                <td class="font-semibold text-ink-900">
                    {{ $it->name }}
                    @if($isMe)<span class="badge-info ml-1">Anda</span>@endif
                </td>
                <td>{{ $it->email }}</td>
                <td><span class="badge-brand">{{ $it->peran?->label ?? '—' }}</span></td>
                <td>
                    @switch($it->account_status)
                        @case('active')    <span class="badge-success">Aktif</span> @break
                        @case('suspended') <span class="badge-danger">Ditangguhkan</span> @break
                        @case('locked')    <span class="badge-warning">Terkunci</span> @break
                        @default           <span class="badge-muted">Non-aktif</span>
                    @endswitch
                </td>
                <td class="text-xs text-ink-500">{{ $it->last_seen_at?->diffForHumans() ?? '—' }}</td>
                <td class="text-right whitespace-nowrap">
                    <a href="{{ route('pengguna.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                    @unless($isMe)
                        <form method="POST" action="{{ route('pengguna.destroy', $it) }}" class="inline" onsubmit="return confirm('Hapus akun {{ $it->email }}?')">
                            @csrf @method('DELETE')
                            <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
