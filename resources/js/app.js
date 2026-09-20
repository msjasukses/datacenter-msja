import './bootstrap';
import Alpine from 'alpinejs';
import TomSelect from 'tom-select';

window.Alpine = Alpine;
window.TomSelect = TomSelect;

/*
 * Semua <select class="select"> otomatis jadi dropdown yang bisa dicari
 * (Tom Select). Diterapkan global supaya tiap form baru ikut tanpa perlu
 * diubah satu per satu.
 *
 * Lewati dengan menambah atribut data-no-select pada <select> terkait.
 */
function pasangSelect(el) {
    if (el.tomselect || el.dataset.noSelect !== undefined) return;

    // <option value=""> pertama dipakai sebagai placeholder, bukan pilihan.
    const kosong = el.querySelector('option[value=""]');
    const placeholder = kosong ? kosong.textContent.trim() : 'Pilih...';

    new TomSelect(el, {
        create: false,
        // <option value=""> tetap jadi placeholder, bukan item terpilih —
        // kalau true, teks "— Pilih —" terhitung sebagai isi.
        allowEmptyOption: false,
        placeholder,
        maxOptions: null,
        hideSelected: false,
        plugins: (! el.required && ! el.multiple) ? ['clear_button'] : [],
        render: {
            no_results: (data, escape) =>
                `<div class="no-results">Tidak ada hasil untuk "${escape(data.input)}"</div>`,
        },
    });
}

function pasangSemuaSelect(scope = document) {
    scope.querySelectorAll('select.select').forEach(pasangSelect);
}

document.addEventListener('DOMContentLoaded', () => {
    pasangSemuaSelect();

    // Select yang muncul belakangan (mis. dibuka lewat Alpine) ikut dipasang.
    new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches?.('select.select')) pasangSelect(node);
                else pasangSemuaSelect(node);
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
});

Alpine.start();
