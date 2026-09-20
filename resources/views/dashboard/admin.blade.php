@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Beranda / Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <x-stat-card label="Total Siswa" :value="number_format($stats['siswa'])" icon="users" tone="brand" href="{{ route('siswa.index') }}"/>
        <x-stat-card label="Total Guru" :value="number_format($stats['guru'])" icon="user-tie" tone="emerald" href="{{ route('guru.index') }}"/>
        <x-stat-card label="Mata Pelajaran" :value="$stats['mapel']" icon="book" tone="sky" href="{{ route('mapel.index') }}"/>
        <x-stat-card label="Jurusan" :value="$stats['jurusan']" icon="layers" tone="amber" href="{{ route('jurusan.index') }}"/>
        <x-stat-card label="Rombongan Belajar" :value="$stats['rombel']" icon="grid" tone="violet" href="{{ route('rombel.index') }}"/>
    </div>

    {{-- Sebaran siswa per rombel pada tahun ajaran aktif --}}
    @php
        $totalDitempatkan = $rombelAktif->sum('jumlah_siswa');
        $barisRombel = $rombelAktif->map(function ($rb) {
            $kapasitas = (int) $rb->kapasitas;
            return [
                'nama'      => $rb->nama_rombel,
                'tingkat'   => (int) $rb->tingkat,
                'wali'      => optional($rb->waliKelas)->nama_ptk,
                'jumlah'    => (int) $rb->jumlah_siswa,
                'kapasitas' => $kapasitas,
                'persen'    => $kapasitas > 0 ? min(100, (int) round($rb->jumlah_siswa / $kapasitas * 100)) : 0,
                'lebih'     => $kapasitas > 0 && $rb->jumlah_siswa > $kapasitas,
                'sisa'      => $kapasitas > 0 ? $kapasitas - $rb->jumlah_siswa : null,
                'url'       => route('siswa.index', ['rombel' => $rb->id]),
            ];
        })->values();
    @endphp
    <div class="card">
        <div class="card-header flex-wrap gap-3">
            <div class="min-w-0">
                <h3 class="font-semibold text-ink-900">Siswa per Rombongan Belajar</h3>
                <p class="text-xs text-ink-500 mt-0.5">
                    @if($tahunAjaranAktif)
                        Tahun ajaran <span class="font-semibold text-ink-700">{{ $tahunAjaranAktif->nama_tahun_ajaran }}</span>
                        · <span class="font-semibold text-ink-700 tabular-nums">{{ number_format($totalDitempatkan) }}</span> siswa
                        di <span class="font-semibold text-ink-700 tabular-nums">{{ $rombelAktif->count() }}</span> rombel
                    @else
                        Belum ada tahun ajaran yang diaktifkan
                    @endif
                </p>
            </div>
            <a href="{{ route('rombel.index') }}" class="btn-secondary shrink-0">
                <x-icon name="grid" class="w-4 h-4"/> Kelola Rombel
            </a>
        </div>

        @if(! $tahunAjaranAktif)
            <div class="card-pad text-sm text-ink-500">
                Aktifkan salah satu tahun ajaran lebih dulu di
                <a href="{{ route('tahun-ajaran.index') }}" class="text-brand-700 font-semibold hover:underline">Tahun Ajaran</a>
                supaya sebaran siswa bisa dihitung.
            </div>
        @elseif($rombelAktif->isEmpty())
            <div class="card-pad text-sm text-ink-500">
                Belum ada rombongan belajar di tahun ajaran ini.
                <a href="{{ route('rombel.create') }}" class="text-brand-700 font-semibold hover:underline">Tambah rombel</a>
                atau import data siswa — rombel dari file Dapodik dibuat otomatis.
            </div>
        @else
            {{-- Datatable ringan: cari + sortir kolom pakai Alpine (tanpa library tambahan) --}}
            <div x-data="{
                    baris: @js($barisRombel),
                    cari: '',
                    urut: 'nama',
                    naik: true,
                    sortir(kolom) {
                        this.naik = this.urut === kolom ? ! this.naik : true;
                        this.urut = kolom;
                    },
                    get tampil() {
                        const q = this.cari.trim().toLowerCase();
                        const hasil = this.baris.filter(r => ! q
                            || r.nama.toLowerCase().includes(q)
                            || String(r.tingkat).includes(q)
                            || (r.wali || '').toLowerCase().includes(q));
                        const arah = this.naik ? 1 : -1;
                        return hasil.sort((a, b) => {
                            let x = a[this.urut], y = b[this.urut];
                            if (this.urut === 'nama') {
                                // urutkan tingkat dulu, baru nama rombel
                                if (a.tingkat !== b.tingkat) return (a.tingkat - b.tingkat) * arah;
                                return String(x).localeCompare(String(y), 'id', { numeric: true }) * arah;
                            }
                            if (typeof x === 'string' || typeof y === 'string') {
                                return String(x ?? '').localeCompare(String(y ?? ''), 'id', { numeric: true }) * arah;
                            }
                            return ((x ?? 0) - (y ?? 0)) * arah;
                        });
                    },
                    get totalSiswa() { return this.tampil.reduce((t, r) => t + r.jumlah, 0); },
                    get totalKapasitas() { return this.tampil.reduce((t, r) => t + r.kapasitas, 0); },
                }">
                <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="relative w-full sm:w-72">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-500 pointer-events-none">
                            <x-icon name="search" class="w-4 h-4"/>
                        </span>
                        <input x-model="cari" type="search" class="input pl-9"
                               placeholder="Cari rombel, tingkat, atau wali kelas...">
                    </div>
                    <span class="text-xs text-ink-500 tabular-nums"
                          x-text="tampil.length === baris.length
                                    ? `${baris.length} rombel`
                                    : `${tampil.length} dari ${baris.length} rombel`"></span>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                @foreach([
                                    'tingkat'   => ['Tingkat', 'text-center'],
                                    'nama'      => ['Rombel', ''],
                                    'wali'      => ['Wali Kelas', ''],
                                    'jumlah'    => ['Jumlah Siswa', 'text-center'],
                                    'kapasitas' => ['Kapasitas', 'text-center'],
                                    'sisa'      => ['Sisa Kuota', 'text-center'],
                                    'persen'    => ['Keterisian', ''],
                                ] as $kolom => [$judul, $align])
                                    <th class="{{ $align }} whitespace-nowrap">
                                        <button type="button" @click="sortir('{{ $kolom }}')"
                                                class="inline-flex items-center gap-1 hover:text-brand-700 transition
                                                       {{ $align === 'text-center' ? 'justify-center' : '' }}"
                                                :class="urut === '{{ $kolom }}' && 'text-brand-700'">
                                            {{ $judul }}
                                            <span class="text-[10px] leading-none transition"
                                                  :class="urut === '{{ $kolom }}' ? 'text-brand-600' : 'text-slate-300'"
                                                  x-text="urut === '{{ $kolom }}' ? (naik ? '▲' : '▼') : '⇅'"></span>
                                        </button>
                                    </th>
                                @endforeach
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="r in tampil" :key="r.nama">
                                <tr class="group">
                                    <td class="text-center">
                                        <span class="badge-muted tabular-nums" x-text="r.tingkat || '—'"></span>
                                    </td>
                                    <td class="font-semibold text-ink-900 whitespace-nowrap" x-text="r.nama"></td>
                                    <td class="max-w-[16rem] truncate" :title="r.wali || ''">
                                        <span x-show="r.wali" class="text-ink-700" x-text="r.wali"></span>
                                        <span x-show="! r.wali" class="text-slate-400">Belum ditentukan</span>
                                    </td>
                                    <td class="text-center font-bold text-base tabular-nums"
                                        :class="r.lebih ? 'text-rose-600' : 'text-brand-700'" x-text="r.jumlah"></td>
                                    <td class="text-center tabular-nums text-ink-600" x-text="r.kapasitas || '—'"></td>
                                    <td class="text-center tabular-nums">
                                        <span x-show="r.sisa === null" class="text-slate-400">—</span>
                                        <span x-show="r.sisa !== null && ! r.lebih" class="text-ink-600" x-text="r.sisa"></span>
                                        <span x-show="r.lebih" class="badge-danger tabular-nums"
                                              x-text="`${r.sisa} lebih`"></span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2.5 w-40">
                                            <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full rounded-full transition-all duration-500"
                                                     :class="r.lebih ? 'bg-rose-500' : (r.persen >= 100 ? 'bg-amber-500' : 'bg-brand-500')"
                                                     :style="`width: ${r.persen}%`"></div>
                                            </div>
                                            <span class="text-xs font-medium tabular-nums text-ink-600 w-10 text-right"
                                                  x-text="`${r.persen}%`"></span>
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        <a :href="r.url" class="btn-ghost p-2 opacity-60 group-hover:opacity-100 transition"
                                           title="Lihat siswa kelas ini">
                                            <x-icon name="arrow-right" class="w-4 h-4"/>
                                        </a>
                                    </td>
                                </tr>
                            </template>
                            {{-- x-if, bukan x-show: efek x-show di sini tidak ikut
                                 dievaluasi ulang saat hasil filter berubah. --}}
                            <template x-if="tampil.length === 0">
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-ink-500">
                                        Tidak ada rombel yang cocok dengan pencarian.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <template x-if="tampil.length > 0">
                            <tr class="bg-slate-50 text-ink-900 font-bold">
                                <td colspan="3" class="px-4 py-3.5 text-xs uppercase tracking-wider text-ink-500">
                                    Total <span x-show="tampil.length !== baris.length" class="normal-case">(terfilter)</span>
                                </td>
                                <td class="px-4 py-3.5 text-center tabular-nums text-brand-700" x-text="totalSiswa"></td>
                                <td class="px-4 py-3.5 text-center tabular-nums" x-text="totalKapasitas || '—'"></td>
                                <td class="px-4 py-3.5 text-center tabular-nums"
                                    x-text="totalKapasitas ? totalKapasitas - totalSiswa : '—'"></td>
                                <td class="px-4 py-3.5" colspan="2">
                                    <span class="text-xs font-medium text-ink-500 tabular-nums"
                                          x-text="totalKapasitas ? `${Math.round(totalSiswa / totalKapasitas * 100)}% terisi` : ''"></span>
                                </td>
                            </tr>
                            </template>
                        </tfoot>
                    </table>
                </div>

                @if($siswaBelumDitempatkan > 0)
                    <div class="px-6 py-4 border-t border-slate-100">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800
                                    flex flex-wrap items-center justify-between gap-2">
                            <span>
                                <strong class="tabular-nums">{{ number_format($siswaBelumDitempatkan) }}</strong>
                                siswa belum punya rombel di tahun ajaran ini.
                            </span>
                            <a href="{{ route('siswa.index') }}" class="font-semibold hover:underline shrink-0">
                                Lihat data siswa →
                            </a>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
