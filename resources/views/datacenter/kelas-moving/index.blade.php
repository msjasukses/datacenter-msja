@extends('layouts.app')
@section('title', 'Kelas Moving')
@section('breadcrumb', 'Data Center / Kelas Moving')

@section('content')
<x-page-header title="Kelas Moving"
               subtitle="Kelas per mata pelajaran. Anggotanya diambil dari menu Data Siswa, boleh lintas rombongan belajar.">
    <x-slot:action>
        <a href="{{ route('kelas-moving.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('kelas-moving.export.excel', request()->only('ta', 'mapel', 'tingkat', 'q')) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
        <a href="{{ route('kelas-moving.create') }}" class="btn-primary">
            <x-icon name="plus" class="w-4 h-4"/> Tambah Kelas Moving
        </a>
    </x-slot:action>
</x-page-header>

@if(! $ta)
    <div class="card card-pad text-sm text-amber-800 bg-amber-50 border border-amber-200">
        Belum ada Tahun Ajaran. Tambahkan tahun ajaran dulu sebelum membuat kelas moving.
    </div>
@else

<form class="card card-pad mb-4 grid gap-3 md:grid-cols-12 items-end">
    <div class="md:col-span-3">
        <label class="label">Tahun ajaran</label>
        <select name="ta" class="select" onchange="this.form.submit()">
            @foreach($taList as $t)
                <option value="{{ $t->id }}" @selected($t->id == $ta->id)>
                    {{ $t->nama_tahun_ajaran }}{{ $t->is_aktif ? ' (aktif)' : '' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-3">
        <label class="label">Mata pelajaran</label>
        <select name="mapel" class="select" onchange="this.form.submit()">
            <option value="">Semua mapel</option>
            @foreach($mapelList as $m)
                <option value="{{ $m->id }}" @selected(request('mapel') == $m->id)>{{ $m->nama_mapel }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-2">
        <label class="label">Tingkat</label>
        <select name="tingkat" class="select" onchange="this.form.submit()">
            <option value="">Semua</option>
            @foreach($tingkatList as $t)
                <option value="{{ $t->nomor }}" @selected(request('tingkat') == $t->nomor)>{{ $t->nama }}</option>
            @endforeach
        </select>
    </div>
    <div class="md:col-span-4 flex gap-2">
        <input name="q" value="{{ request('q') }}" class="input flex-1" placeholder="Cari nama kelas moving...">
        <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
    </div>
</form>

<form method="POST" action="{{ route('kelas-moving.bulk-destroy') }}"
      x-data="{ selected: [], ids: @js($items->pluck('id')->map(fn ($id) => (string) $id)) }"
      @submit="if (!confirm(`Hapus ${selected.length} kelas moving terpilih? Anggotanya ikut terhapus (data siswa tetap aman).`)) $event.preventDefault()">
    @csrf
    @method('DELETE')

    <div class="card card-pad mb-3 flex items-center justify-between bg-rose-50/60" x-show="selected.length > 0" x-cloak>
        <span class="text-sm font-medium text-rose-700" x-text="selected.length + ' kelas dipilih'"></span>
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
                <th>Nama Kelas Moving</th>
                <th>Mata Pelajaran</th>
                <th>Guru Pengampu</th>
                <th class="text-center">Tingkat</th>
                <th class="text-center">Anggota</th>
                <th>Status</th>
                <th></th>
            </tr></thead>
            <tbody>
            @forelse($items as $km)
                <tr>
                    <td><input type="checkbox" name="ids[]" value="{{ $km->id }}" x-model="selected"></td>
                    <td class="font-semibold text-ink-900">
                        {{ $km->nama_kelas_moving }}
                        @if($km->ruang)<div class="text-xs text-ink-500 font-normal">Ruang: {{ $km->ruang }}</div>@endif
                    </td>
                    <td>
                        {{ optional($km->mapel)->nama_mapel ?? '—' }}
                        <div class="text-[10px] text-ink-500 font-mono">{{ optional($km->mapel)->kode_mapel }}</div>
                    </td>
                    <td>{{ optional($km->guru)->nama_ptk ?? '—' }}</td>
                    <td class="text-center">{{ $km->tingkat ?: '—' }}</td>
                    <td class="text-center">
                        <a href="{{ route('kelas-moving.anggota', $km) }}"
                           class="{{ $km->kapasitas && $km->anggota_count > $km->kapasitas ? 'badge-warning' : 'badge-brand' }}"
                           title="Kelola anggota dari Data Siswa">
                            {{ $km->anggota_count }} / {{ $km->kapasitas }}
                        </a>
                    </td>
                    <td>
                        @if($km->is_aktif)<span class="badge-success">Aktif</span>@else<span class="badge-muted">Non-aktif</span>@endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('kelas-moving.anggota', $km) }}" class="btn-ghost p-2" title="Kelola anggota">
                            <x-icon name="users"/>
                        </a>
                        <a href="{{ route('kelas-moving.edit', $km) }}" class="btn-ghost p-2" title="Edit kelas">
                            <x-icon name="edit"/>
                        </a>
                        <button type="button" class="btn-ghost p-2 text-rose-600" title="Hapus kelas"
                                onclick="if(confirm('Hapus kelas moving ini? Anggotanya ikut terhapus, data siswa tetap aman.')) document.getElementById('destroy-km-{{ $km->id }}').submit()">
                            <x-icon name="trash"/>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center py-8 text-ink-500">
                    Belum ada kelas moving pada TA {{ $ta->nama_tahun_ajaran }}.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</form>
<div class="mt-4">{{ $items->links() }}</div>

{{-- Form hapus per-baris, dipisah supaya tidak nested di dalam form bulk --}}
@foreach($items as $km)
    <form id="destroy-km-{{ $km->id }}" method="POST" action="{{ route('kelas-moving.destroy', $km) }}" class="hidden">
        @csrf @method('DELETE')
    </form>
@endforeach

@endif
@endsection
