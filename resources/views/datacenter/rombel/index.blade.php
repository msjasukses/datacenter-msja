@extends('layouts.app')
@section('title', 'Rombongan Belajar')
@section('breadcrumb', 'Data Center / Rombel')

@section('content')
<x-page-header title="Rombongan Belajar" subtitle="Kelas / rombongan per tahun ajaran">
    <x-slot:action>
        <a href="{{ route('rombel.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('rombel.export.excel', request()->query()) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('rombel.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input flex-1 min-w-[200px]" placeholder="Cari rombel...">
    <select name="ta" class="select w-48">
        <option value="">Semua TA</option>
        @foreach($tahunAjaran as $ta)
            <option value="{{ $ta->id }}" @selected(request('ta')==$ta->id)>{{ $ta->nama_tahun_ajaran }}</option>
        @endforeach
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<form method="POST" action="{{ route('rombel.bulk-destroy') }}"
      x-data="{ selected: [], ids: @js($items->pluck('id')->map(fn ($id) => (string) $id)) }"
      @submit="if (!confirm(`Hapus ${selected.length} rombel terpilih? Data siswa di rombel & guru mapel terkait ikut terhapus.`)) $event.preventDefault()">
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
                <th>Tingkat</th><th>Nama Rombel</th><th>Jurusan</th><th>TA</th><th>Wali Kelas</th><th>Kapasitas</th><th></th>
            </tr></thead>
            <tbody>
            @forelse($items as $it)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $it->id }}" x-model="selected"></td>
                    <td>{{ $it->tingkat }}</td>
                    <td class="font-semibold text-ink-900">{{ $it->nama_rombel }}</td>
                    <td>{{ optional($it->jurusan)->nama_jurusan ?? '—' }}</td>
                    <td class="text-xs">{{ optional($it->tahunAjaran)->nama_tahun_ajaran }}</td>
                    <td>{{ optional($it->waliKelas)->nama_ptk ?? '—' }}</td>
                    <td>{{ $it->kapasitas }}</td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('rombel.edit', $it) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                        <button type="button" class="btn-ghost p-2 text-rose-600"
                                onclick="if(confirm('Hapus rombel {{ $it->nama_rombel }}?')) document.getElementById('destroy-rombel-{{ $it->id }}').submit()">
                            <x-icon name="trash"/>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-8 text-ink-500">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
<div class="mt-4">{{ $items->links() }}</div>

{{-- Form hapus per-baris terpisah, supaya tidak nested di dalam form bulk di atas --}}
@foreach($items as $it)
    <form id="destroy-rombel-{{ $it->id }}" method="POST" action="{{ route('rombel.destroy', $it) }}" class="hidden">@csrf @method('DELETE')</form>
@endforeach
@endsection
