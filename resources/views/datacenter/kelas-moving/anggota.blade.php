@extends('layouts.app')
@section('title', 'Anggota ' . $item->nama_kelas_moving)
@section('breadcrumb', 'Data Center / Kelas Moving / Anggota')

@php
    // Metadata tiap kandidat untuk pencarian & filter di sisi browser
    // (semua kandidat dimuat sekali supaya centang tidak hilang saat mencari).
    $meta = [];
    foreach ($kandidat as $s) {
        $meta[$s->id] = [
            'teks' => mb_strtolower(trim($s->nama_siswa.' '.$s->nisn.' '.$s->nis.' '.($s->kelas_asal ?? ''))),
            'kelas' => (string) ($s->kelas_asal ?? ''),
            'jk' => (string) ($s->jenis_kelamin ?? ''),
            'kunci' => isset($bentrok[$s->id]),
        ];
    }
    $daftarKelasAsal = $kandidat->pluck('kelas_asal')->filter()->unique()->sort()->values();
    $idKandidat = $kandidat->pluck('id')->map(fn ($id) => (string) $id)->values();
    // Anggota yang sudah tersimpan tapi tidak lagi masuk daftar kandidat
    // (mis. tingkat kelas moving diubah, atau siswa jadi non-aktif).
    $diluarKandidat = collect($terpilih)->diff($idKandidat)->values();
@endphp

@section('content')
<x-page-header :title="'Anggota: ' . $item->nama_kelas_moving"
               :subtitle="'Mapel ' . (optional($item->mapel)->nama_mapel ?? '—') . ' · TA ' . (optional($item->tahunAjaran)->nama_tahun_ajaran ?? '—') . ($item->tingkat ? ' · Tingkat ' . $item->tingkat : '') . ' · Kapasitas ' . $item->kapasitas">
    <x-slot:action>
        <a href="{{ route('kelas-moving.edit', $item) }}" class="btn-secondary">
            <x-icon name="edit" class="w-4 h-4"/> Edit Kelas
        </a>
        <a href="{{ route('kelas-moving.index', ['ta' => $item->tahun_ajaran_id]) }}" class="btn-secondary">Kembali</a>
    </x-slot:action>
</x-page-header>

@php
    // Panel import dibuka otomatis kalau baru saja ada hasil/kesalahan import.
    $bukaImport = $errors->has('file') || session()->has('importNotes') || session()->has('importErrors');
@endphp

{{-- Import anggota dari Excel: daftar NISN, alternatif dari mencentang satu per satu. --}}
<div class="card mb-6" x-data="{ buka: @js($bukaImport) }">
    <div class="card-header">
        <div>
            <h3 class="font-semibold text-ink-900">Import Anggota dari Excel</h3>
            <p class="text-xs text-ink-500 mt-0.5">
                Upload daftar NISN untuk mengisi anggota kelas ini sekaligus. Siswa tetap diambil
                dari Data Siswa — file hanya menentukan siapa saja yang masuk.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <a href="{{ route('kelas-moving.anggota.template', $item) }}" class="btn-secondary whitespace-nowrap">
                <x-icon name="document" class="w-4 h-4"/> Unduh Template
            </a>
            <button type="button" class="btn-secondary whitespace-nowrap" @click="buka = ! buka">
                <x-icon name="plus" class="w-4 h-4"/>
                <span x-text="buka ? 'Tutup' : 'Import Excel'">Import Excel</span>
            </button>
        </div>
    </div>

    <div x-show="buka" x-cloak x-transition.opacity class="px-6 py-4 border-t border-slate-100">
        <form method="POST" action="{{ route('kelas-moving.anggota.import', $item) }}"
              enctype="multipart/form-data" class="grid lg:grid-cols-2 gap-6">
            @csrf

            <div class="space-y-3">
                <label class="block">
                    <span class="label">Pilih file (.xlsx, .xls, .csv)</span>
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required
                           class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
                    @error('file')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-ink-500">Maks. 10 MB. Header wajib ada di baris 1.</p>
                </label>

                <label class="flex items-start gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="ganti" value="1"
                           class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    <span>
                        Ganti seluruh anggota dengan isi file
                        <span class="block text-xs text-ink-500">
                            Anggota lama yang tidak ada di file akan dikeluarkan (data siswa tidak terhapus).
                            Kalau tidak dicentang, import hanya menambah.
                        </span>
                    </span>
                </label>

                <div class="pt-1">
                    <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Mulai Import</button>
                </div>
            </div>

            <div class="text-sm">
                <h4 class="font-semibold text-ink-900 mb-2">Format Kolom</h4>
                <code class="block text-[10px] bg-slate-50 p-2 rounded border border-slate-200 break-all">
                    nisn | nis | nama_siswa | kelas_asal
                </code>
                <ul class="mt-2 text-xs text-ink-600 list-disc pl-5 space-y-1">
                    <li>Satu baris = satu siswa. Yang dibaca <code>nisn</code>; <code>nis</code> hanya dipakai kalau NISN kosong.</li>
                    <li><code>nama_siswa</code> &amp; <code>kelas_asal</code> sekadar rujukan — isinya diabaikan saat import.</li>
                    <li><strong>Unduh Template</strong> sudah berisi anggota kelas ini sekarang: tinggal diedit lalu diimpor ulang.</li>
                    {{-- @endif tidak boleh menempel pada huruf (Blade tidak mengenalinya), jadi kalimatnya diakhiri titik dulu. --}}
                    <li>Siswa harus sudah terdaftar &amp; berstatus aktif di <a class="underline" href="{{ route('siswa.index') }}">Data Siswa</a>.@if($item->tingkat) Khusus kelas ini, siswa juga harus masuk rombel <strong>tingkat {{ $item->tingkat }}</strong> pada TA ini.@endif</li>
                    <li>Siswa yang sudah ikut kelas moving lain untuk mapel yang sama dilaporkan gagal, baris lain tetap diproses.</li>
                    <li>Siswa yang sudah jadi anggota kelas ini dilewati (tidak dobel).</li>
                </ul>
            </div>
        </form>

        @if(session('importNotes') && count(session('importNotes')))
            <div class="mt-4 p-3 rounded-lg bg-sky-50 border border-sky-200 text-sm">
                <div class="font-semibold text-sky-700 mb-2">Catatan:</div>
                <ul class="list-disc pl-5 text-sky-700 text-xs space-y-0.5 max-h-48 overflow-auto">
                    @foreach(session('importNotes') as $note)<li>{{ $note }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(session('importErrors') && count(session('importErrors')))
            <div class="mt-3 p-3 rounded-lg bg-rose-50 border border-rose-200 text-sm">
                <div class="font-semibold text-rose-700 mb-2">{{ count(session('importErrors')) }} baris gagal:</div>
                <ul class="list-disc pl-5 text-rose-600 text-xs space-y-0.5 max-h-48 overflow-auto">
                    @foreach(session('importErrors') as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif
    </div>
</div>

@if($kandidat->isEmpty())
    <div class="card card-pad text-sm text-amber-800 bg-amber-50 border border-amber-200">
        Tidak ada kandidat siswa.
        @if($item->tingkat)
            Kelas moving ini dibatasi <strong>tingkat {{ $item->tingkat }}</strong>, tetapi belum ada siswa aktif
            yang terdaftar di rombel tingkat tersebut pada TA {{ optional($item->tahunAjaran)->nama_tahun_ajaran }}.
            Ubah tingkat di <a class="underline" href="{{ route('kelas-moving.edit', $item) }}">Edit Kelas</a>,
            atau tempatkan siswa ke rombel dulu lewat menu <a class="underline" href="{{ route('siswa.index') }}">Data Siswa</a>.
        @else
            Tambahkan siswa dulu lewat menu <a class="underline" href="{{ route('siswa.index') }}">Data Siswa</a>.
        @endif
    </div>
@else

<form method="POST" action="{{ route('kelas-moving.anggota.store', $item) }}" class="card"
      x-data="{
          sel: @js($terpilih),
          awal: @js($terpilih),
          ids: @js($idKandidat),
          meta: @js($meta),
          luar: @js($diluarKandidat),
          cari: '',
          kelas: '',
          jk: '',
          hanyaTerpilih: false,
          kapasitas: {{ (int) $item->kapasitas }},
          cocok(id) {
              const m = this.meta[id]
              if (! m) return false
              const q = this.cari.trim().toLowerCase()
              if (q && ! m.teks.includes(q)) return false
              if (this.kelas && m.kelas !== this.kelas) return false
              if (this.jk && m.jk !== this.jk) return false
              if (this.hanyaTerpilih && ! this.sel.includes(id)) return false
              return true
          },
          get tampil() { return this.ids.filter(id => this.cocok(id)) },
          get bisaDicentang() { return this.tampil.filter(id => ! this.meta[id].kunci) },
          get semuaTercentang() {
              const l = this.bisaDicentang
              return l.length > 0 && l.every(id => this.sel.includes(id))
          },
          centangTampil() { this.sel = [...new Set([...this.sel, ...this.bisaDicentang])] },
          kosongkanTampil() { this.sel = this.sel.filter(id => ! this.tampil.includes(id)) },
          get berubah() {
              return this.sel.length !== this.awal.length || this.sel.some(id => ! this.awal.includes(id))
          },
      }">
    @csrf

    <div class="card-header">
        <div>
            <h3 class="font-semibold text-ink-900">Pilih Anggota dari Data Siswa</h3>
            <p class="text-xs text-ink-500 mt-0.5">
                Centang siswa yang mengikuti kelas moving ini. Yang tidak dicentang akan dikeluarkan saat disimpan —
                data siswa itu sendiri tidak terhapus.
            </p>
        </div>
        <span class="badge-muted">{{ $kandidat->count() }} kandidat</span>
    </div>

    {{-- Toolbar: pencarian + filter kelas asal + aksi massal --}}
    <div class="px-6 py-3 border-b border-slate-100 bg-slate-50/60 flex flex-wrap items-center gap-3">
        <div class="relative grow sm:grow-0 sm:w-72">
            <span class="absolute inset-y-0 left-3 flex items-center text-ink-400 pointer-events-none">
                <x-icon name="search" class="w-4 h-4"/>
            </span>
            <input type="text" x-model="cari" placeholder="Cari nama, NISN, atau kelas asal…"
                   class="input pl-9 pr-9 w-full" aria-label="Cari siswa">
            <button type="button" x-show="cari" x-cloak @click="cari = ''"
                    class="absolute inset-y-0 right-3 flex items-center text-lg leading-none text-ink-400 hover:text-ink-700"
                    aria-label="Hapus pencarian">&times;</button>
        </div>

        {{-- Dibungkus div ber-lebar: Tom Select memakai 100% lebar induknya,
             jadi lebar diatur di pembungkus, bukan di <select>. --}}
        <div class="w-full sm:w-44">
            <select x-model="kelas" class="select" aria-label="Filter kelas asal">
                <option value="">Semua kelas asal</option>
                @foreach($daftarKelasAsal as $nama)
                    <option value="{{ $nama }}">{{ $nama }}</option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-36">
            <select x-model="jk" class="select" aria-label="Filter jenis kelamin">
                <option value="">L &amp; P</option>
                <option value="L">Laki-laki</option>
                <option value="P">Perempuan</option>
            </select>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-ink-700">
            <input type="checkbox" x-model="hanyaTerpilih" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            Hanya yang dicentang
        </label>

        <div class="flex items-center gap-2 ml-auto">
            <button type="button" class="btn-secondary" @click="centangTampil()"
                    :title="cari || kelas || jk ? 'Centang semua siswa yang sedang tampil' : 'Centang semua siswa'">
                <x-icon name="check" class="w-4 h-4"/> Centang tampil
            </button>
            <button type="button" class="btn-secondary" @click="kosongkanTampil()"
                    :title="cari || kelas || jk ? 'Kosongkan siswa yang sedang tampil' : 'Kosongkan semua'">
                <x-icon name="trash" class="w-4 h-4"/> Kosongkan
            </button>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="table-modern">
            <thead><tr>
                <th class="w-10">
                    <input type="checkbox" :checked="semuaTercentang"
                           @change="$event.target.checked ? centangTampil() : kosongkanTampil()"
                           title="Centang / kosongkan yang sedang tampil">
                </th>
                <th>NISN</th>
                <th>Nama Siswa</th>
                <th class="text-center">JK</th>
                <th>Kelas Asal</th>
                <th>Keterangan</th>
            </tr></thead>
            <tbody>
            @foreach($kandidat as $s)
                {{-- tanpa x-cloak: semua baris cocok sebelum ada filter, jadi tabel
                     tidak berkedip kosong sebelum Alpine siap --}}
                <tr x-show="cocok('{{ $s->id }}')"
                    :class="sel.includes('{{ $s->id }}') ? 'bg-brand-50/60' : ''">
                    <td>
                        @if(isset($bentrok[$s->id]))
                            <input type="checkbox" disabled class="opacity-40"
                                   title="Sudah terdaftar di kelas moving lain untuk mapel yang sama">
                        @else
                            <input type="checkbox" name="siswa_ids[]" value="{{ $s->id }}" x-model="sel"
                                   aria-label="Pilih {{ $s->nama_siswa }}">
                        @endif
                    </td>
                    <td class="font-mono text-xs">{{ $s->nisn }}</td>
                    <td class="font-semibold text-ink-900">
                        {{ $s->nama_siswa }}
                        <div class="text-xs text-ink-500 font-normal">NIS: {{ $s->nis ?: '—' }}</div>
                    </td>
                    <td class="text-center">{{ $s->jenis_kelamin ?: '—' }}</td>
                    <td>{{ $s->kelas_asal ?: '—' }}</td>
                    <td>
                        @if(isset($bentrok[$s->id]))
                            <span class="badge-warning">Sudah di: {{ $bentrok[$s->id] }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr x-show="tampil.length === 0" x-cloak>
                <td colspan="6" class="text-center py-8 text-ink-500">
                    Tidak ada siswa yang cocok dengan filter saat ini.
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    {{-- Anggota lama yang keluar dari daftar kandidat: tetap dikirim lewat hidden
         input supaya tidak ikut terhapus hanya karena tidak tampil di tabel. --}}
    @foreach($diluarKandidat as $id)
        <input type="hidden" name="siswa_ids[]" value="{{ $id }}">
    @endforeach

    <div class="px-6 py-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-ink-600">
            <span class="font-semibold text-ink-900" x-text="sel.length"></span> siswa dicentang
            dari {{ $kandidat->count() }} kandidat · kapasitas {{ $item->kapasitas }}
            <span class="badge-warning ml-2" x-show="sel.length > kapasitas" x-cloak>Melebihi kapasitas</span>
            <span class="badge-muted ml-2" x-show="cari || kelas || jk || hanyaTerpilih" x-cloak>
                <span x-text="tampil.length"></span> siswa tampil
            </span>
            @if($diluarKandidat->isNotEmpty())
                <span class="badge-info ml-2">{{ $diluarKandidat->count() }} anggota di luar daftar kandidat dipertahankan</span>
            @endif
            <span class="badge-warning ml-2" x-show="berubah" x-cloak>Belum disimpan</span>
        </p>
        <button class="btn-primary"><x-icon name="check" class="w-4 h-4"/> Simpan Anggota</button>
    </div>
</form>

@endif
@endsection
