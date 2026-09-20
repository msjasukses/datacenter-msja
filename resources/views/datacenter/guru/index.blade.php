@extends('layouts.app')
@section('title', 'Data Guru')
@section('breadcrumb', 'Data Center / Guru')

@section('content')
<x-page-header title="Data Guru" subtitle="Pendidik dan tenaga kependidikan">
    <x-slot:action>
        <a href="{{ route('guru.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('guru.export.excel', request()->query()) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('guru.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah Guru</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 max-w-md flex gap-2">
    <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari nama, NIP, atau NUPTK...">
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<form method="POST" action="{{ route('guru.bulk-destroy') }}"
      x-data="{ selected: [], ids: @js($items->pluck('id')->map(fn ($id) => (string) $id)) }"
      @submit="if (!confirm(`Hapus ${selected.length} data guru terpilih? Tindakan ini tidak bisa dibatalkan.`)) $event.preventDefault()">
    @csrf
    @method('DELETE')

    <div class="card card-pad mb-3 flex items-center justify-between bg-rose-50/60" x-show="selected.length > 0" x-cloak>
        <span class="text-sm font-medium text-rose-700" x-text="selected.length + ' data dipilih'"></span>
        <button type="submit" class="btn-danger">
            <x-icon name="trash" class="w-4 h-4"/> Hapus Terpilih
        </button>
    </div>

    <div class="card">
        <table class="table-modern">
            <thead><tr>
                <th class="w-10">
                    <input type="checkbox"
                           :checked="ids.length > 0 && selected.length === ids.length"
                           @change="selected = $event.target.checked ? [...ids] : []">
                </th>
                <th>NIP</th><th>Nama</th><th>JK</th><th>Mapel Diajar</th><th>Jabatan</th><th>Status</th><th>Aktif</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($items as $g)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $g->id }}" x-model="selected"></td>
                    <td class="font-mono text-xs">
                        {{ $g->nip }}
                        @if($g->nuptk)<div class="text-ink-400">NUPTK {{ $g->nuptk }}</div>@endif
                    </td>
                    <td class="flex items-center gap-2 font-semibold text-ink-900">
                        <x-avatar :src="$g->profile_photo_url" :name="$g->nama_ptk" size="w-8 h-8"/>
                        <div>
                            {{ $g->nama_ptk }}
                            <div class="text-xs text-ink-500 font-normal">{{ $g->email ?: '—' }}</div>
                        </div>
                    </td>
                    <td>{{ $g->jenis_kelamin }}</td>
                    <td>
                        @if($g->mapel)
                            <span class="badge-info">{{ $g->mapel->nama_mapel }}</span>
                        @else
                            <span class="text-ink-400">—</span>
                        @endif
                    </td>
                    <td>{{ $g->jabatan ?: '—' }}</td>
                    <td>{{ $g->status_kepegawaian ?: '—' }}</td>
                    <td>
                        @if($g->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif
                        @if($g->is_terkunci)
                            <span class="badge-danger block mt-1 w-fit">Terkunci</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($g->is_terkunci)
                            <button type="button" class="btn-ghost p-2 text-amber-600" title="Buka Kunci Akun"
                                    onclick="if(confirm('Buka kunci akun {{ $g->nama_ptk }} sekarang?')) document.getElementById('unlock-guru-{{ $g->id }}').submit()">
                                <x-icon name="key"/>
                            </button>
                        @endif
                        <a href="{{ route('guru.edit', $g) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                        <button type="button" class="btn-ghost p-2 text-rose-600"
                                onclick="if(confirm('Hapus guru ini?')) document.getElementById('destroy-guru-{{ $g->id }}').submit()">
                            <x-icon name="trash"/>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
<div class="mt-4">{{ $items->links() }}</div>

{{-- Form terpisah utk unlock/hapus per-baris, supaya tidak nested di dalam form bulk di atas --}}
@foreach($items as $g)
    @if($g->is_terkunci)
        <form id="unlock-guru-{{ $g->id }}" method="POST" action="{{ route('guru.unlock', $g) }}" class="hidden">@csrf</form>
    @endif
    <form id="destroy-guru-{{ $g->id }}" method="POST" action="{{ route('guru.destroy', $g) }}" class="hidden">@csrf @method('DELETE')</form>
@endforeach
@endsection
