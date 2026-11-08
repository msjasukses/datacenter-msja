@extends('layouts.app')
@section('title', 'Penugasan Guru')
@section('breadcrumb', 'Data Center / Penugasan Guru')

@section('content')
<x-page-header title="Penugasan Guru"
               subtitle="Tentukan mapel dan kelas/tingkat yang diajar tiap guru. Guru hanya melihat data sesuai penugasan ini; admin tetap melihat semua.">
    <x-slot:action>
        <a href="{{ route('guru-mapel.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('guru-mapel.export.excel', request()->only('guru', 'ta')) }}" class="btn-secondary">
            <x-icon name="chart" class="w-4 h-4"/> Export Excel
        </a>
    </x-slot:action>
</x-page-header>

@if(! $ta)
    <div class="card card-pad text-sm text-amber-800 bg-amber-50 border border-amber-200">
        Belum ada Tahun Ajaran. Tambahkan tahun ajaran dulu sebelum mengatur penugasan guru.
    </div>
@else

{{-- ============ PEMILIH GURU ============ --}}
<form method="GET" class="card card-pad mb-5">
    <div class="grid gap-3 md:grid-cols-12 items-end">
        <div class="md:col-span-3">
            <label class="label">Tahun ajaran</label>
            <select name="ta" class="select">
                @foreach($taList as $t)
                    <option value="{{ $t->id }}" @selected($t->id == $ta->id)>
                        {{ $t->nama_tahun_ajaran }}{{ $t->is_aktif ? ' (aktif)' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-7">
            <label class="label">Pilih guru</label>
            <select name="guru" class="select">
                <option value="">— Lihat rekap semua guru —</option>
                @foreach($guruList as $g)
                    <option value="{{ $g->id }}" @selected($guru && $g->id == $guru->id)>
                        {{ $g->nama_ptk }}{{ $g->nip ? ' ('.$g->nip.')' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="md:col-span-2">
            <button class="btn-primary w-full justify-center">Tampilkan</button>
        </div>
    </div>
</form>

@if($rombelGroups->isEmpty())
    <div class="card card-pad text-sm text-amber-800 bg-amber-50 border border-amber-200">
        Belum ada rombongan belajar pada TA {{ $ta->nama_tahun_ajaran }}, jadi belum ada kelas yang bisa ditugaskan.
    </div>

@elseif(! $guru)
    {{-- ============ MODE LIHAT DATA: REKAP SEMUA GURU ============ --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="font-semibold text-ink-900">Rekap Penugasan — TA {{ $ta->nama_tahun_ajaran }}</h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    Pilih guru di atas (atau klik <strong>Atur</strong>) untuk membuka matriks centang mapel × kelas.
                </p>
            </div>
            <span class="badge-muted">{{ $rekap->where('jumlahPenugasan', '>', 0)->count() }} / {{ $rekap->count() }} guru ditugaskan</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table-modern">
                <thead><tr>
                    <th>Guru</th>
                    <th>Mata Pelajaran Diajar</th>
                    <th class="text-center whitespace-nowrap">Kelas</th>
                    <th class="text-center whitespace-nowrap">Penugasan</th>
                    <th></th>
                </tr></thead>
                <tbody>
                @forelse($rekap as $row)
                    <tr>
                        <td class="font-semibold text-ink-900 whitespace-nowrap">
                            {{ $row->guru->nama_ptk }}
                            <div class="text-[10px] text-ink-500 font-mono">{{ $row->guru->nip }}</div>
                        </td>
                        <td>
                            @forelse($row->mapel as $nama)
                                <span class="badge-info mr-1 mb-1">{{ $nama }}</span>
                            @empty
                                <span class="text-xs text-ink-500">Belum ditugaskan</span>
                            @endforelse
                        </td>
                        <td class="text-center">{{ $row->jumlahKelas ?: '-' }}</td>
                        <td class="text-center">
                            @if($row->jumlahPenugasan)
                                <span class="badge-brand">{{ $row->jumlahPenugasan }}</span>
                            @else
                                <span class="text-ink-400">0</span>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route('guru-mapel.index', ['guru' => $row->guru->id, 'ta' => $ta->id]) }}"
                               class="btn-ghost p-2" title="Atur penugasan">
                                <x-icon name="edit"/>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center py-8 text-ink-500">Belum ada data guru.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

@elseif($mapelList->isEmpty())
    <div class="card card-pad text-sm text-amber-800 bg-amber-50 border border-amber-200">
        Belum ada mata pelajaran. Tambahkan mapel dulu sebelum mengatur penugasan guru.
    </div>

@else
    {{-- ============ MODE INPUT: MATRIKS MAPEL × KELAS ============ --}}
    @php
        $rombelFlat = $rombelGroups->flatten();
        $allPairs = [];
        $rowPairs = [];
        $colPairs = [];
        foreach ($mapelList as $m) {
            $rowPairs[$m->id] = [];
            foreach ($rombelFlat as $rb) {
                $pair = $m->id.'-'.$rb->id;
                $allPairs[] = $pair;
                $rowPairs[$m->id][] = $pair;
                $colPairs[$rb->id][] = $pair;
            }
        }
    @endphp

    <form method="POST" action="{{ route('guru-mapel.penugasan.store') }}" class="card"
          x-data="{
              awal: @js($checked),
              sel: @js($checked),
              all: @js($allPairs),
              rows: @js($rowPairs),
              cols: @js($colPairs),
              groupOn(list) { return list.length > 0 && list.every(p => this.sel.includes(p)) },
              toggleGroup(list) {
                  this.sel = this.groupOn(list)
                      ? this.sel.filter(p => ! list.includes(p))
                      : [...new Set([...this.sel, ...list])]
              },
              get berubah() {
                  return this.sel.length !== this.awal.length
                      || this.sel.some(p => ! this.awal.includes(p))
              },
          }">
        @csrf
        <input type="hidden" name="guru_id" value="{{ $guru->id }}">
        <input type="hidden" name="tahun_ajaran_id" value="{{ $ta->id }}">

        <div class="card-header">
            <div>
                <h3 class="font-semibold text-ink-900">Penugasan: {{ $guru->nama_ptk }}</h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    Centang persilangan <strong>mapel × kelas</strong>. Baris = mapel, kolom = kelas dikelompokkan per tingkat.
                    Kosong semua = guru belum ditugaskan.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary" @click="sel = [...all]">Centang semua</button>
                <button type="button" class="btn-secondary" @click="sel = []">Kosongkan</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-slate-50">
                        <th rowspan="2"
                            class="sticky left-0 z-20 bg-slate-50 px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wider text-ink-500 border-b border-r border-slate-200 min-w-[220px]">
                            Mata Pelajaran
                        </th>
                        @foreach($rombelGroups as $tingkat => $rombels)
                            <th colspan="{{ $rombels->count() }}"
                                class="px-3 py-2 text-center text-[11px] font-semibold uppercase tracking-wider text-ink-500 border-b border-r border-slate-200">
                                Tingkat {{ $tingkat }}
                            </th>
                        @endforeach
                    </tr>
                    <tr class="bg-slate-50">
                        @foreach($rombelGroups as $rombels)
                            @foreach($rombels as $rb)
                                <th class="px-3 py-2 border-b border-r border-slate-200 whitespace-nowrap">
                                    <button type="button"
                                            class="text-[11px] font-semibold uppercase tracking-wider text-ink-500 hover:text-brand-700"
                                            :class="groupOn(cols[{{ $rb->id }}]) ? 'text-brand-700' : ''"
                                            @click="toggleGroup(cols[{{ $rb->id }}])"
                                            title="Centang / kosongkan seluruh kolom {{ $rb->nama_rombel }}">
                                        {{ $rb->nama_rombel }}
                                    </button>
                                </th>
                            @endforeach
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($mapelList as $m)
                    <tr class="hover:bg-brand-50/40">
                        <td class="sticky left-0 z-10 bg-white px-4 py-3 border-b border-r border-slate-200 whitespace-nowrap">
                            <label class="flex items-center gap-2 cursor-pointer"
                                   title="Centang / kosongkan seluruh baris {{ $m->nama_mapel }}">
                                <input type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                       :checked="groupOn(rows[{{ $m->id }}])"
                                       @change="toggleGroup(rows[{{ $m->id }}])">
                                <span class="text-ink-800">{{ $m->nama_mapel }}</span>
                            </label>
                        </td>
                        @foreach($rombelGroups as $rombels)
                            @foreach($rombels as $rb)
                                <td class="px-3 py-3 text-center border-b border-r border-slate-200">
                                    <input type="checkbox" name="cells[]" value="{{ $m->id }}-{{ $rb->id }}" x-model="sel"
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 cursor-pointer"
                                           aria-label="{{ $m->nama_mapel }} di {{ $rb->nama_rombel }}">
                                </td>
                            @endforeach
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-ink-600">
                <span class="font-semibold text-ink-900" x-text="sel.length"></span> penugasan dicentang
                dari {{ count($allPairs) }} kemungkinan · TA {{ $ta->nama_tahun_ajaran }}
                <span class="badge-warning ml-2" x-show="berubah" x-cloak>Belum disimpan</span>
            </p>
            <div class="flex items-center gap-2">
                <a href="{{ route('guru-mapel.index', ['ta' => $ta->id]) }}" class="btn-secondary">Kembali ke rekap</a>
                <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Simpan Penugasan</button>
            </div>
        </div>
    </form>
@endif

@endif
@endsection
