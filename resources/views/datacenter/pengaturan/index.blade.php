@extends('layouts.app')
@section('title', 'Pengaturan Aplikasi')
@section('breadcrumb', 'Data Center / Pengaturan')

@section('content')
<x-page-header title="Pengaturan Aplikasi" subtitle="Identitas sekolah, logo, halaman login, dan identitas aplikasi — sumber tunggal, otomatis dipakai juga oleh CBT"/>

<div x-data="{ tab: '{{ request('tab', 'identitas') }}' }" class="space-y-6">

    <div class="flex gap-1 bg-white rounded-xl p-1 shadow-soft border border-slate-100 w-fit overflow-x-auto">
        @foreach([
            'identitas' => ' Identitas Sekolah',
            'logo'      => ' Logo Sekolah',
            'ttd'       => ' TTD Kepala Sekolah',
            'login'     => ' Halaman Login',
            'aplikasi'  => ' Identitas Aplikasi',
        ] as $key => $label)
            <button type="button" @click="tab='{{ $key }}'"
                    :class="tab==='{{ $key }}' ? 'bg-brand-600 text-white shadow-soft' : 'text-ink-600 hover:bg-slate-100'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition whitespace-nowrap">
                {{ $label }}
            </button>
        @endforeach
    </div>

    <form method="POST" action="{{ route('pengaturan.update') }}" enctype="multipart/form-data" class="space-y-6"
          @reset="$dispatch('ttd-reset')">
        @csrf @method('PUT')

        {{-- ============ TAB IDENTITAS SEKOLAH ============ --}}
        <div x-show="tab==='identitas'" x-cloak class="card card-pad space-y-4">
            <div class="grid md:grid-cols-2 gap-4">
                <x-field name="npsn" label="NPSN" :value="$sekolah->npsn" required/>
                <x-field name="nama_sekolah" label="Nama Sekolah" :value="$sekolah->nama_sekolah" required/>
                <x-field type="select" name="jenjang" label="Jenjang" :value="$sekolah->jenjang"
                         :options="['SD'=>'SD','SMP'=>'SMP','SMA'=>'SMA','SMK'=>'SMK','MA'=>'MA']" required/>
                <x-field name="telepon" label="Telepon" :value="$sekolah->telepon"/>
                <x-field name="email" type="email" label="Email" :value="$sekolah->email"/>
                <x-field name="website" label="Website" :value="$sekolah->website"/>
                <x-field name="kepala_sekolah" label="Kepala Sekolah" :value="$sekolah->kepala_sekolah"/>
                <x-field name="nip_kepala_sekolah" label="NIP Kepala Sekolah" :value="$sekolah->nip_kepala_sekolah"/>
            </div>
            <x-field name="alamat" label="Alamat" :value="$sekolah->alamat"/>
            <div class="grid md:grid-cols-4 gap-4">
                <x-field name="kelurahan" label="Kelurahan" :value="$sekolah->kelurahan"/>
                <x-field name="kecamatan" label="Kecamatan" :value="$sekolah->kecamatan"/>
                <x-field name="kabupaten" label="Kabupaten" :value="$sekolah->kabupaten"/>
                <x-field name="provinsi" label="Provinsi" :value="$sekolah->provinsi"/>
            </div>
        </div>

        {{-- ============ TAB LOGO SEKOLAH ============ --}}
        <div x-show="tab==='logo'" x-cloak class="card card-pad space-y-4">
            <h3 class="font-semibold text-ink-900">Logo Sekolah</h3>
            <p class="text-xs text-ink-500">Tampil di sidebar, header, dan halaman login Data Center &amp; CBT. Format: PNG/JPG/SVG/WEBP, max 2MB.</p>

            <div class="flex flex-wrap items-center gap-4">
                <div class="w-20 h-20 rounded-xl border border-slate-200 bg-slate-50 grid place-items-center overflow-hidden shrink-0">
                    @if($sekolah->logo)
                        <img src="{{ $sekolah->logo_url }}" alt="Logo" class="max-w-full max-h-full object-contain">
                    @else
                        <span class="text-xs text-slate-400 text-center">Belum ada<br>logo</span>
                    @endif
                </div>
                <div class="flex-1 min-w-[220px]">
                    <input type="file" name="logo" accept="image/*"
                           class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
                    @error('logo')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    @if($sekolah->logo)
                        <label class="inline-flex items-center gap-2 text-xs text-rose-600 mt-2">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            Hapus logo saat ini
                        </label>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ TAB TTD KEPALA SEKOLAH ============ --}}
        <div x-show="tab==='ttd'" x-cloak class="card card-pad space-y-4"
             x-data="{ preview: null }" @ttd-reset.window="preview = null">
            <h3 class="font-semibold text-ink-900">Tanda Tangan Kepala Sekolah</h3>
            <p class="text-xs text-ink-500">
                Gambar tanda tangan untuk dokumen/cetakan resmi sekolah. Format: PNG/JPG/WEBP, max 2MB.
                Disarankan PNG dengan latar transparan.
            </p>

            <div class="flex flex-wrap items-start gap-4">
                <div class="w-56 shrink-0 text-center">
                    <div class="h-28 rounded-xl border border-slate-200 bg-white grid place-items-center overflow-hidden p-2">
                        <template x-if="preview">
                            <img :src="preview" alt="Preview TTD" class="max-w-full max-h-full object-contain">
                        </template>
                        <template x-if="!preview">
                            @if($sekolah->ttd_kepala_sekolah)
                                <img src="{{ $sekolah->ttd_kepala_sekolah_url }}" alt="TTD Kepala Sekolah" class="max-w-full max-h-full object-contain">
                            @else
                                <span class="text-xs text-slate-400">Belum ada tanda tangan</span>
                            @endif
                        </template>
                    </div>
                    <p class="mt-2 text-sm font-semibold text-ink-900 underline underline-offset-2">{{ $sekolah->kepala_sekolah ?: 'Nama Kepala Sekolah' }}</p>
                    <p class="text-xs text-ink-500">NIP. {{ $sekolah->nip_kepala_sekolah ?: '-' }}</p>
                </div>
                <div class="flex-1 min-w-[220px]">
                    <input type="file" name="ttd_kepala_sekolah" accept="image/png,image/jpeg,image/webp"
                           @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null"
                           class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
                    @error('ttd_kepala_sekolah')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
                    @if($sekolah->ttd_kepala_sekolah)
                        <label class="inline-flex items-center gap-2 text-xs text-rose-600 mt-2">
                            <input type="checkbox" name="remove_ttd_kepala_sekolah" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            Hapus tanda tangan saat ini
                        </label>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============ TAB HALAMAN LOGIN ============ --}}
        <div x-show="tab==='login'" x-cloak class="card card-pad space-y-4">
            <h3 class="font-semibold text-ink-900">Background Halaman Login</h3>
            <p class="text-xs text-ink-500">
                Gambar akan ditampilkan sebagai latar di halaman login Data Center &amp; CBT. Format: PNG/JPG/WEBP, max 5MB. Saran ukuran 1920x1080.
            </p>

            @if($app['login_bg'])
                <div class="rounded-xl overflow-hidden border border-slate-200 relative h-48 bg-slate-100">
                    <img src="{{ asset('storage/'.$app['login_bg']) }}" alt="" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent"></div>
                    <div class="absolute bottom-3 left-3 text-white text-xs font-semibold drop-shadow">Preview</div>
                </div>
                <label class="flex items-center gap-2 text-xs text-rose-600">
                    <input type="checkbox" name="remove_login_bg" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                    Hapus background saat ini (kembali ke gradient default)
                </label>
            @else
                <div class="rounded-xl border-2 border-dashed border-slate-200 p-12 text-center text-sm text-ink-500 bg-gradient-to-br from-slate-50 to-slate-100">
                    Belum ada background custom — menggunakan gradient default
                </div>
            @endif

            <input type="file" name="login_bg" accept="image/*"
                   class="input file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-brand-50 file:text-brand-700 file:font-semibold">
            @error('login_bg')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror

            <div class="border-t border-slate-100 pt-4 grid md:grid-cols-2 gap-4">
                <x-field name="login_title" label="Judul Halaman Login" :value="$app['login_title']" help="Headline besar di kiri halaman login"/>
                <x-field name="login_subtitle" label="Sub-judul" :value="$app['login_subtitle']" help="Deskripsi pendek di bawah headline"/>
            </div>
        </div>

        {{-- ============ TAB IDENTITAS APLIKASI ============ --}}
        <div x-show="tab==='aplikasi'" x-cloak class="card card-pad space-y-4">
            <h3 class="font-semibold text-ink-900">Identitas Aplikasi</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <x-field name="app_name" label="Nama Aplikasi" :value="$app['app_name']" required
                         help="Tampil di judul tab browser & sidebar"/>
                <x-field name="app_tagline" label="Tagline" :value="$app['app_tagline']"/>
            </div>
            <x-field name="footer_text" label="Teks Footer" :value="$app['footer_text']"
                     placeholder="© 2026 Nama Sekolah. Hak cipta dilindungi."/>
        </div>

        <div class="card card-pad flex justify-end gap-2 sticky bottom-4 shadow-soft">
            <button type="reset" class="btn-secondary">Reset</button>
            <button type="submit" class="btn-primary">
                <x-icon name="check" class="w-4 h-4"/> Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection
