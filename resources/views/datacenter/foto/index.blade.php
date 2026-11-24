@extends('layouts.app')
@section('title', 'Upload Foto '.$cfg['label'])
@section('breadcrumb', 'Data Center / Upload Foto')

@php
    $kunciLabel = implode(' / ', array_map('strtoupper', $cfg['kunci']));
@endphp

@section('content')
<x-page-header title="Upload Foto" subtitle="Kelola foto siswa dan guru — satu per satu atau sekaligus banyak">
    <x-slot:action>
        <a href="{{ route($tipe === 'siswa' ? 'siswa.index' : 'guru.index') }}" class="btn-secondary">
            <x-icon name="{{ $tipe === 'siswa' ? 'users' : 'user-tie' }}" class="w-4 h-4"/> Data {{ $cfg['label'] }}
        </a>
    </x-slot:action>
</x-page-header>

{{-- Tab Siswa / Guru --}}
<div class="flex gap-1 p-1 mb-4 bg-white border border-slate-200 rounded-xl w-fit shadow-soft">
    @foreach(['siswa' => ['Siswa', 'users'], 'guru' => ['Guru', 'user-tie']] as $key => [$lbl, $ico])
        <a href="{{ route('foto.index', ['tipe' => $key]) }}"
           class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition
                  {{ $tipe === $key ? 'bg-brand-600 text-white shadow-soft' : 'text-ink-600 hover:bg-brand-50 hover:text-brand-700' }}">
            <x-icon name="{{ $ico }}" class="w-4 h-4"/> Foto {{ $lbl }}
        </a>
    @endforeach
</div>

{{-- Statistik kelengkapan foto --}}
<div class="card card-pad mb-4" x-data>
    <div class="flex flex-wrap items-center gap-x-8 gap-y-3">
        <div>
            <div class="text-xs text-ink-500">Total {{ $cfg['label'] }}</div>
            <div class="text-2xl font-bold text-ink-900">{{ number_format($stat['total']) }}</div>
        </div>
        <div>
            <div class="text-xs text-ink-500">Sudah Ada Foto</div>
            <div class="text-2xl font-bold text-emerald-600" x-text="$store.foto.ada.toLocaleString('id-ID')">{{ number_format($stat['ada']) }}</div>
        </div>
        <div>
            <div class="text-xs text-ink-500">Belum Ada Foto</div>
            <div class="text-2xl font-bold text-rose-600" x-text="($store.foto.total - $store.foto.ada).toLocaleString('id-ID')">{{ number_format($stat['belum']) }}</div>
        </div>
        <div class="flex-1 min-w-[200px]">
            <div class="flex justify-between text-xs text-ink-500 mb-1">
                <span>Kelengkapan foto</span>
                <span class="font-semibold text-ink-700" x-text="$store.foto.persen() + '%'">{{ $stat['persen'] }}%</span>
            </div>
            <div class="h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full bg-emerald-500 transition-all duration-500" :style="`width:${$store.foto.persen()}%`" style="width: {{ $stat['persen'] }}%"></div>
            </div>
        </div>
    </div>
</div>

{{-- Upload massal --}}
<div class="card mb-4" x-data="fotoMassal('{{ route('foto.massal', $tipe) }}')">
    <button type="button" @click="open = !open" class="w-full card-header hover:bg-slate-50/60 transition text-left">
        <div class="flex items-center gap-3">
            <span class="w-9 h-9 rounded-lg bg-brand-50 text-brand-700 grid place-items-center"><x-icon name="upload" class="w-5 h-5"/></span>
            <div>
                <div class="font-semibold text-ink-900">Upload Massal</div>
                <div class="text-xs text-ink-500">Pilih banyak foto atau 1 file ZIP — dicocokkan otomatis dari nama file</div>
            </div>
        </div>
        <x-icon name="chevron-down" class="w-4 h-4 transition-transform" x-bind:class="open ? '' : '-rotate-90'"/>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="card-pad space-y-4">
        <div class="grid md:grid-cols-5 gap-4">
            <label class="md:col-span-3 flex flex-col items-center justify-center gap-2 text-center border-2 border-dashed rounded-xl p-8 cursor-pointer transition"
                   :class="drag ? 'border-brand-500 bg-brand-50' : 'border-slate-300 hover:border-brand-400 hover:bg-slate-50'"
                   @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
                   @drop.prevent="drag = false; tambah($event.dataTransfer.files)">
                <x-icon name="image" class="w-10 h-10 text-brand-500"/>
                <div class="font-semibold text-ink-800">Seret & lepas foto di sini</div>
                <div class="text-sm text-ink-500">atau <span class="text-brand-700 font-semibold underline">klik untuk memilih file</span></div>
                <div class="text-xs text-ink-500">JPG, PNG, WEBP, atau ZIP berisi foto · boleh ratusan file sekaligus</div>
                <input type="file" multiple accept="image/*,.zip" class="hidden" @change="tambah($event.target.files); $event.target.value = ''">
            </label>

            <div class="md:col-span-2 rounded-xl bg-slate-50 border border-slate-200 p-4 text-sm text-ink-700 space-y-2">
                <div class="font-semibold text-ink-900">Cara memberi nama file</div>
                <p>Beri nama file sesuai <b>{{ $kunciLabel }}</b> atau <b>nama lengkap</b>. Contoh:</p>
                <ul class="font-mono text-xs space-y-1">
                    @if($tipe === 'siswa')
                        <li>0081234567.jpg</li>
                        <li>0081234567_Budi Santoso.jpg</li>
                        <li>Budi Santoso.png</li>
                    @else
                        <li>198501012010011001.jpg</li>
                        <li>198501012010011001_Siti Aminah.jpg</li>
                        <li>Siti Aminah.png</li>
                    @endif
                </ul>
                <p class="text-xs text-ink-500">Foto otomatis diperkecil & dirapikan (maks 600×800 px), jadi tidak perlu diedit dulu.</p>
                <label class="flex items-center gap-2 pt-1 cursor-pointer">
                    <input type="checkbox" x-model="timpa" class="rounded border-slate-300 text-brand-600">
                    <span>Ganti foto yang sudah ada</span>
                </label>
            </div>
        </div>

        <template x-if="antrian.length">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex-1 min-w-[200px]">
                        <div class="flex justify-between text-xs text-ink-500 mb-1">
                            <span x-text="jalan ? 'Mengunggah…' : 'Selesai'"></span>
                            <span x-text="`${selesai} / ${antrian.length} file`"></span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-brand-500 transition-all" :style="`width:${antrian.length ? selesai / antrian.length * 100 : 0}%`"></div>
                        </div>
                    </div>
                    <span class="badge-success" x-text="`${hitung('ok')} berhasil`"></span>
                    <span class="badge-warning" x-show="hitung('lewat')" x-text="`${hitung('lewat')} dilewati`"></span>
                    <span class="badge-danger" x-show="hitung('gagal')" x-text="`${hitung('gagal')} gagal`"></span>
                    <label class="text-xs flex items-center gap-1 cursor-pointer" x-show="hitung('gagal')">
                        <input type="checkbox" x-model="hanyaGagal" class="rounded border-slate-300 text-brand-600"> Tampilkan yang gagal saja
                    </label>
                    <button type="button" class="btn-secondary" x-show="!jalan" @click="bersihkan()">Bersihkan</button>
                </div>

                <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 divide-y divide-slate-100 text-sm">
                    <template x-for="(it, i) in antrian.filter(a => !hanyaGagal || a.status === 'gagal')" :key="i">
                        <div class="flex items-center gap-3 px-3 py-2">
                            <template x-if="it.url"><img :src="it.url" class="w-8 h-10 rounded object-cover bg-slate-100"></template>
                            <template x-if="!it.url"><div class="w-8 h-10 rounded bg-slate-100 grid place-items-center text-ink-500"><x-icon name="image" class="w-4 h-4"/></div></template>
                            <div class="min-w-0 flex-1">
                                <div class="font-mono text-xs truncate" x-text="it.file"></div>
                                <div class="text-xs" :class="{'text-emerald-700': it.status === 'ok', 'text-rose-600': it.status === 'gagal', 'text-amber-700': it.status === 'lewat', 'text-ink-500': it.status === 'antri' || it.status === 'proses'}">
                                    <span x-show="it.nama" class="font-semibold" x-text="it.nama + ' — '"></span><span x-text="it.pesan"></span>
                                </div>
                            </div>
                            <span x-show="it.status === 'proses'" class="w-4 h-4 rounded-full border-2 border-brand-500 border-t-transparent animate-spin"></span>
                            <span x-show="it.status === 'ok'" class="text-emerald-600"><x-icon name="check" class="w-4 h-4"/></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

{{-- Filter --}}
<form class="card card-pad mb-4 flex flex-wrap gap-2">
    <input type="hidden" name="tipe" value="{{ $tipe }}">
    <input name="q" value="{{ request('q') }}" class="input flex-1 min-w-[220px]" placeholder="Cari nama atau {{ $kunciLabel }}...">
    @if($tipe === 'siswa')
        <select name="rombel" class="select w-full sm:w-48" onchange="this.form.submit()">
            <option value="">Semua Kelas</option>
            @foreach($rombelList as $rb)
                <option value="{{ $rb->id }}" @selected(request('rombel') == $rb->id)>{{ $rb->nama_rombel }}</option>
            @endforeach
        </select>
    @endif
    <select name="status" class="select w-full sm:w-44" onchange="this.form.submit()">
        <option value="">Semua Status</option>
        <option value="belum" @selected(request('status') === 'belum')>Belum ada foto</option>
        <option value="ada" @selected(request('status') === 'ada')>Sudah ada foto</option>
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<p class="text-xs text-ink-500 mb-3 flex items-center gap-1.5">
    <x-icon name="camera" class="w-4 h-4"/>
    Klik kartu atau seret foto langsung ke kartu untuk upload / ganti foto. Tersimpan otomatis.
</p>

{{-- Galeri kartu --}}
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
    @forelse($items as $o)
        @php
            $nama = $o->{$cfg['nama']};
            $kode = $tipe === 'siswa' ? $o->nisn : $o->nip;
            $sub  = $tipe === 'siswa'
                ? (optional($o->rombelSekarang?->rombel)->nama_rombel ?? 'Tanpa kelas')
                : ($o->jabatan ?: ($o->nuptk ? 'NUPTK '.$o->nuptk : 'Guru'));
        @endphp
        <div class="card overflow-hidden group"
             x-data="fotoKartu({
                id: {{ $o->id }},
                url: @js($o->profile_photo_url),
                nama: @js($nama),
                uploadUrl: @js(route('foto.upload', [$tipe, $o->id])),
                hapusUrl: @js(route('foto.destroy', [$tipe, $o->id])),
             })"
             @foto-updated.window="if ($event.detail.id === id) url = $event.detail.url">
            <div class="relative aspect-[3/4] bg-slate-100 cursor-pointer transition"
                 :class="drag ? 'ring-4 ring-inset ring-brand-500' : ''"
                 @click="$refs.file.click()"
                 @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
                 @drop.prevent="drag = false; kirim($event.dataTransfer.files[0])">
                <template x-if="url">
                    <img :src="url" alt="" class="absolute inset-0 w-full h-full object-cover">
                </template>
                <template x-if="!url">
                    <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-ink-500">
                        <x-avatar :name="$nama" size="w-14 h-14" class="text-lg opacity-60"/>
                        <span class="text-[11px] flex items-center gap-1"><x-icon name="camera" class="w-3.5 h-3.5"/> Upload foto</span>
                    </div>
                </template>

                {{-- Overlay saat hover (desktop) --}}
                <div x-show="url && !busy" class="absolute inset-0 bg-ink-900/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                    <span class="px-2.5 py-1.5 rounded-lg bg-white text-xs font-semibold text-ink-800 flex items-center gap-1"><x-icon name="camera" class="w-3.5 h-3.5"/> Ganti</span>
                    <button type="button" @click.stop="hapus()" class="px-2.5 py-1.5 rounded-lg bg-rose-600 text-xs font-semibold text-white flex items-center gap-1">
                        <x-icon name="trash" class="w-3.5 h-3.5"/> Hapus
                    </button>
                </div>

                <div x-show="busy" x-cloak class="absolute inset-0 bg-white/70 grid place-items-center">
                    <span class="w-8 h-8 rounded-full border-4 border-brand-500 border-t-transparent animate-spin"></span>
                </div>

                <span x-show="url" class="absolute top-2 right-2 w-6 h-6 rounded-full bg-emerald-500 text-white grid place-items-center shadow">
                    <x-icon name="check" class="w-3.5 h-3.5"/>
                </span>

                <input type="file" accept="image/*" class="hidden" x-ref="file" @change="kirim($event.target.files[0]); $event.target.value = ''">
            </div>
            <div class="p-2.5">
                <div class="text-sm font-semibold text-ink-900 truncate" title="{{ $nama }}">{{ $nama }}</div>
                <div class="text-[11px] text-ink-500 font-mono truncate">{{ $kode }}</div>
                <div class="text-[11px] text-ink-500 truncate">{{ $sub }}</div>
                <div x-show="error" x-cloak class="text-[11px] text-rose-600 mt-1" x-text="error"></div>
            </div>
        </div>
    @empty
        <div class="col-span-full card card-pad text-center text-ink-500">Tidak ada data {{ strtolower($cfg['label']) }} yang cocok.</div>
    @endforelse
</div>

<div class="mt-4">{{ $items->links() }}</div>
@endsection

@push('scripts')
<script>
(() => {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    /** Perkecil foto di browser dulu supaya upload cepat (foto HP bisa 5–10 MB). */
    async function perkecil(file, maks = 1200) {
        if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
        try {
            const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
            const s = Math.min(1, maks / Math.max(bmp.width, bmp.height));
            const c = document.createElement('canvas');
            c.width = Math.round(bmp.width * s);
            c.height = Math.round(bmp.height * s);
            const ctx = c.getContext('2d');
            ctx.fillStyle = '#fff';
            ctx.fillRect(0, 0, c.width, c.height);
            ctx.drawImage(bmp, 0, 0, c.width, c.height);
            const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.9));
            return blob && blob.size < file.size ? blob : file;
        } catch (e) {
            return file;
        }
    }

    async function kirimForm(url, fd, method = 'POST') {
        const res = await fetch(url, {
            method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: fd,
        });
        let data = {};
        try { data = await res.json(); } catch (e) {}
        if (!res.ok) {
            const msg = data.errors ? Object.values(data.errors).flat()[0] : (data.message || `Gagal (HTTP ${res.status})`);
            throw new Error(msg);
        }
        return data;
    }

    document.addEventListener('alpine:init', () => {
        Alpine.store('foto', {
            total: {{ $stat['total'] }},
            ada: {{ $stat['ada'] }},
            persen() { return this.total ? Math.round(this.ada / this.total * 100) : 0; },
        });

        Alpine.data('fotoKartu', (cfg) => ({
            ...cfg, busy: false, drag: false, error: '',
            setUrl(url) {
                if (!this.url && url) Alpine.store('foto').ada++;
                if (this.url && !url) Alpine.store('foto').ada--;
                this.url = url;
            },
            async kirim(file) {
                if (!file) return;
                if (!file.type.startsWith('image/')) { this.error = 'File harus berupa gambar.'; return; }
                this.busy = true; this.error = '';
                try {
                    const fd = new FormData();
                    const kecil = await perkecil(file);
                    fd.append('foto', kecil, file.name.replace(/\.\w+$/, '') + '.jpg');
                    const data = await kirimForm(this.uploadUrl, fd);
                    this.setUrl(data.url);
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.busy = false;
                }
            },
            async hapus() {
                if (!confirm(`Hapus foto ${this.nama}?`)) return;
                this.busy = true; this.error = '';
                try {
                    await kirimForm(this.hapusUrl, null, 'DELETE');
                    this.setUrl(null);
                } catch (e) {
                    this.error = e.message;
                } finally {
                    this.busy = false;
                }
            },
        }));

        Alpine.data('fotoMassal', (url) => ({
            open: true, drag: false, timpa: true, hanyaGagal: false,
            antrian: [], jalan: false,
            get selesai() { return this.antrian.filter(a => !['antri', 'proses'].includes(a.status)).length; },
            hitung(st) { return this.antrian.filter(a => a.status === st).length; },
            bersihkan() { this.antrian = []; this.hanyaGagal = false; },
            tambah(files) {
                for (const f of files) {
                    const zip = /\.zip$/i.test(f.name);
                    if (!zip && !f.type.startsWith('image/')) {
                        this.antrian.push({ file: f.name, status: 'gagal', pesan: 'Bukan gambar / ZIP.' });
                        continue;
                    }
                    this.antrian.push({ file: f.name, status: 'antri', pesan: zip ? 'Menunggu (ZIP)…' : 'Menunggu…', _f: f, zip });
                }
                this.mulai();
            },
            async mulai() {
                if (this.jalan) return;
                this.jalan = true;
                const worker = async () => {
                    let it;
                    while ((it = this.antrian.find(a => a.status === 'antri'))) {
                        it.status = 'proses';
                        it.pesan = it.zip ? 'Mengekstrak & mencocokkan isi ZIP…' : 'Mengunggah…';
                        await this.proses(it);
                    }
                };
                await Promise.all([worker(), worker(), worker()]);
                this.jalan = false;
            },
            async proses(it) {
                try {
                    const fd = new FormData();
                    const f = it.zip ? it._f : await perkecil(it._f);
                    fd.append('file', f, it.zip ? it._f.name : it._f.name.replace(/\.\w+$/, '') + '.jpg');
                    fd.append('timpa', this.timpa ? 1 : 0);
                    const data = await kirimForm(url, fd);
                    const hasil = data.hasil || [];
                    hasil.forEach(h => {
                        if (h.status === 'ok' && h.baru) Alpine.store('foto').ada++;
                        if (h.status === 'ok') window.dispatchEvent(new CustomEvent('foto-updated', { detail: { id: h.id, url: h.url } }));
                    });
                    if (it.zip) {
                        // Ganti baris ZIP dengan hasil per file di dalamnya.
                        const idx = this.antrian.indexOf(it);
                        this.antrian.splice(idx, 1, ...hasil);
                    } else {
                        Object.assign(it, hasil[0] || { status: 'gagal', pesan: 'Tidak ada respon.' });
                        // Nama asli file dari user (bukan nama .jpg hasil perkecil).
                        it.file = it._f.name;
                    }
                } catch (e) {
                    it.status = 'gagal';
                    it.pesan = e.message;
                }
                delete it._f;
            },
        }));
    });
})();
</script>
@endpush
