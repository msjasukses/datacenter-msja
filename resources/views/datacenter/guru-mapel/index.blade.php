@extends('layouts.app')
@section('title', 'Data Guru Mata Pelajaran')
@section('breadcrumb', 'Data Center / Guru Mapel')

@section('content')
<x-page-header title="Data Guru Mata Pelajaran" subtitle="Atur guru mengajar mapel & rombel mana saja">
    <x-slot:action>
        <a href="{{ route('guru-mapel.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('guru-mapel.export.excel', request()->query()) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('guru-mapel.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah Data</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 grid md:grid-cols-4 gap-2">
    <select name="guru" class="select"><option value="">Semua guru</option>
        @foreach($guruList as $g)<option value="{{ $g->id }}" @selected(request('guru')==$g->id)>{{ $g->nama_ptk }}</option>@endforeach
    </select>
    <select name="mapel" class="select"><option value="">Semua mapel</option>
        @foreach($mapelList as $m)<option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>{{ $m->nama_mapel }}</option>@endforeach
    </select>
    <select name="ta" class="select"><option value="">Semua TA</option>
        @foreach($taList as $t)<option value="{{ $t->id }}" @selected(request('ta')==$t->id)>{{ $t->nama_tahun_ajaran }}</option>@endforeach
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<form method="POST" action="{{ route('guru-mapel.bulk-destroy') }}"
      x-data="{ selected: [], ids: @js($items->pluck('id')->map(fn ($id) => (string) $id)) }"
      @submit="if (!confirm(`Hapus ${selected.length} data guru mapel terpilih? Tindakan ini tidak bisa dibatalkan.`)) $event.preventDefault()">
    @csrf
    @method('DELETE')

    <div class="card card-pad mb-3 flex items-center justify-between bg-rose-50/60" x-show="selected.length > 0" x-cloak>
        <span class="text-sm font-medium text-rose-700" x-text="selected.length + ' data dipilih'"></span>
        <button type="submit" class="btn-danger">
            <x-icon name="trash" class="w-4 h-4"/> Hapus Terpilih
        </button>
    </div>

    <div class="card overflow-x-auto">
        <table class="table-modern">
            <thead><tr>
                <th class="w-10">
                    <input type="checkbox"
                           :checked="ids.length > 0 && selected.length === ids.length"
                           @change="selected = $event.target.checked ? [...ids] : []">
                </th>
                <th>Guru</th><th>Mata Pelajaran</th><th>Tingkat</th><th>Rombel</th><th>Tahun Ajaran</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($items as $gm)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $gm->id }}" x-model="selected"></td>
                    <td class="font-semibold text-ink-900">{{ optional($gm->guru)->nama_ptk ?? '-' }}
                        <div class="text-[10px] text-ink-500 font-mono">{{ optional($gm->guru)->nip }}</div>
                    </td>
                    <td>{{ optional($gm->mapel)->nama_mapel ?? '-' }}</td>
                    <td class="text-center">{{ optional($gm->rombel)->tingkat ?? '-' }}</td>
                    <td><span class="badge-info">{{ optional($gm->rombel)->nama_rombel ?? '-' }}</span></td>
                    <td class="text-xs">{{ optional($gm->tahunAjaran)->nama_tahun_ajaran ?? '-' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('guru-mapel.edit', $gm) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                        <button type="button" class="btn-ghost p-2 text-rose-600"
                                onclick="if(confirm('Hapus data ini?')) document.getElementById('destroy-gm-{{ $gm->id }}').submit()">
                            <x-icon name="trash"/>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
<div class="mt-4">{{ $items->links() }}</div>

{{-- Form hapus per-baris terpisah, supaya tidak nested di dalam form bulk di atas --}}
@foreach($items as $gm)
    <form id="destroy-gm-{{ $gm->id }}" method="POST" action="{{ route('guru-mapel.destroy', $gm) }}" class="hidden">@csrf @method('DELETE')</form>
@endforeach
@endsection
