/**
 * app.js — Skrip antarmuka (frontend) SIHAPQ.
 *
 * Berisi kumpulan fungsi kecil yang masing-masing menangani satu perilaku UI
 * (sidebar, kartu status, tabel responsif, notifikasi, validasi form, dialog
 * konfirmasi, dan toggle password). Semua fungsi dijalankan di akhir file oleh
 * fungsi boot() setelah halaman siap. Pola ini dipilih agar mudah dibaca:
 * satu fungsi = satu tanggung jawab, tanpa framework JS tambahan.
 */

// Sidebar: buka/tutup menu samping di layar kecil, dan lipat/lebarkan di desktop.
const initSidebar = () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!sidebar) return;

    const openSidebar = () => {
        sidebar.classList.add('is-open');
        sidebar.setAttribute('aria-hidden', 'false');
        if (backdrop) backdrop.classList.remove('hidden');
        document.body.classList.add('sidebar-open');
    };

    const closeSidebar = () => {
        sidebar.classList.remove('is-open');
        sidebar.setAttribute('aria-hidden', 'true');
        if (backdrop) backdrop.classList.add('hidden');
        document.body.classList.remove('sidebar-open');
    };

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sidebar-open]')) {
            e.preventDefault();
            openSidebar();
        } else if (e.target.closest('[data-sidebar-close]')) {
            e.preventDefault();
            closeSidebar();
        } else if (e.target.closest('[data-sidebar-toggle]')) {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', collapsed ? 'true' : 'false');
        } else if (backdrop && e.target === backdrop) {
            closeSidebar();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
            closeSidebar();
        }
    });

    sidebar.addEventListener('click', (e) => {
        if (e.target.closest('nav a') && window.matchMedia('(max-width: 1023px)').matches) {
            closeSidebar();
        }
    });

    window.matchMedia('(min-width: 1024px)').addEventListener('change', (e) => {
        if (e.matches) closeSidebar();
    });
};

// Kartu status: menandai kartu pilihan (mis. RP/NG/SC) saat diklik pada form input QC.
const initStatusCards = () => {
    document.querySelectorAll('[data-status-card]').forEach((card) => {
        if (card.querySelector('input[type="radio"]')?.checked) {
            card.classList.add('is-selected', 'border-primary', 'ring-2', 'ring-primary/15');
        }

        card.addEventListener('click', () => {
            document.querySelectorAll('[data-status-card]').forEach((item) => {
                item.classList.remove('is-selected', 'border-primary', 'ring-2', 'ring-primary/15');
            });
            card.classList.add('is-selected', 'border-primary', 'ring-2', 'ring-primary/15');
        });
    });
};

// Auto-scroll: setelah submit pencarian, langsung geser ke area hasil.
const initSearchScroll = () => {
    if (!window.location.search) return;
    const target = document.querySelector('[data-search-results]');
    if (!target) return;
    requestAnimationFrame(() => {
        target.scrollIntoView({ behavior: 'auto', block: 'start' });
    });
};

// Tabel responsif: menyalin judul kolom ke tiap sel (data-label) agar tabel
// tetap terbaca saat ditampilkan sebagai kartu di layar HP.
const initResponsiveTables = () => {
    document.querySelectorAll('[data-responsive-table]').forEach((table) => {
        let headers = [];

        try {
            headers = JSON.parse(table.dataset.headers || '[]');
        } catch {
            headers = [];
        }

        if (!headers.length) return;

        table.querySelectorAll('tbody tr').forEach((row) => {
            const cells = Array.from(row.children).filter((cell) => cell.tagName === 'TD');
            if (cells.length <= 1 && cells[0]?.hasAttribute('colspan')) return;

            cells.forEach((cell, index) => {
                if (!cell.dataset.label && headers[index]) {
                    cell.dataset.label = headers[index];
                }
            });
        });
    });
};

// Notifikasi flash: menambah tombol tutup; alert sukses (hijau) hilang otomatis
// setelah 10 detik, alert error (merah) menetap sampai ditutup pengguna.
const initFlashDismiss = () => {
    const SELECTOR = '.bg-green-50.border-green-200, .bg-red-50.border-red-200';
    const TIMEOUT_MS = 10000;
    const FADE_MS = 500;

    document.querySelectorAll(SELECTOR).forEach((el) => {
        if (el.dataset.persist !== undefined) return;

        // Alert error (merah) dibiarkan tampil sampai ditutup manual, supaya tidak
        // terlewat. Hanya notifikasi sukses (hijau) yang hilang otomatis.
        const isError = el.classList.contains('bg-red-50');

        if (!el.querySelector('[data-flash-close]')) {
            if (getComputedStyle(el).position === 'static') el.style.position = 'relative';
            el.style.paddingRight = '32px';
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-flash-close', '');
            btn.setAttribute('aria-label', 'Tutup');
            btn.className = 'absolute right-2 top-2 inline-flex size-5 items-center justify-center rounded text-current opacity-60 hover:opacity-100';
            btn.innerHTML = '<i class="ti ti-x text-[14px]"></i>';
            el.appendChild(btn);
        }

        const dismiss = () => {
            el.style.transition = `opacity ${FADE_MS}ms ease`;
            el.style.opacity = '0';
            setTimeout(() => el.remove(), FADE_MS);
        };

        el.querySelector('[data-flash-close]')?.addEventListener('click', dismiss);
        if (!isError) setTimeout(dismiss, TIMEOUT_MS);
    });

    // Pastikan alert error terlihat walau halaman ter-scroll oleh autofocus form.
    const firstError = document.querySelector('.bg-red-50.border-red-200');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
};

// Pesan validasi form: mengubah pesan bawaan browser (bahasa Inggris) menjadi
// pesan berbahasa Indonesia yang ramah, dengan nama kolom yang mudah dipahami.
const initFormValidationMessages = () => {
    const labels = {
        current_password: 'kata sandi lama',
        customer_name: 'customer',
        date_from: 'tanggal mulai',
        date_to: 'tanggal selesai',
        department_id: 'bagian',
        description: 'deskripsi',
        employee_id: 'NIK',
        end_time: 'waktu selesai cek',
        inspection_date: 'tanggal cek',
        machine_code: 'kode mesin',
        machine_id: 'mesin',
        name: 'nama',
        notes: 'keterangan',
        password: 'kata sandi',
        password_confirmation: 'konfirmasi kata sandi',
        pin: 'PIN',
        process_name: 'proses',
        product_code: 'no part',
        product_id: 'part',
        qc_inspector_id: 'PIC QC',
        result_status: 'hasil cek',
        role: 'peran',
        search: 'pencarian',
        start_time: 'waktu mulai cek',
        status: 'status',
        username: 'nama pengguna',
    };

    const fieldLabel = (field) => {
        if (field.dataset.validationLabel) return field.dataset.validationLabel;
        if (field.name && labels[field.name]) return labels[field.name];

        const labelText = field.closest('label')?.querySelector('span')?.textContent?.trim();
        return labelText ? labelText.toLowerCase() : 'data';
    };

    const messageFor = (field) => {
        const label = fieldLabel(field);
        const validity = field.validity;

        if (validity.valueMissing) {
            if (['SELECT', 'BUTTON'].includes(field.tagName) || ['radio', 'checkbox'].includes(field.type)) {
                return `Silakan pilih ${label}.`;
            }
            return `Kolom ${label} wajib diisi.`;
        }

        if (validity.tooShort) return `Kolom ${label} minimal harus berisi ${field.minLength} karakter.`;
        if (validity.tooLong) return `Kolom ${label} tidak boleh lebih dari ${field.maxLength} karakter.`;
        if (validity.patternMismatch) return `Format ${label} belum sesuai.`;
        if (validity.typeMismatch) return `Format ${label} belum sesuai.`;
        if (validity.rangeUnderflow) return `Nilai ${label} terlalu kecil.`;
        if (validity.rangeOverflow) return `Nilai ${label} terlalu besar.`;
        if (validity.badInput) return `Isi ${label} dengan data yang valid.`;

        return '';
    };

    document.addEventListener('invalid', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;

        field.setCustomValidity(messageFor(field));
    }, true);

    document.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement) {
            event.target.setCustomValidity('');
        }
    });

    document.addEventListener('change', (event) => {
        if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement) {
            event.target.setCustomValidity('');
        }
    });
};

// Dialog konfirmasi: mengganti confirm() bawaan browser dengan popup yang serasi
// dengan tema aplikasi. Dipicu oleh elemen ber-atribut data-confirm="pesan".
const initConfirmDialog = () => {
    let activeResolver = null;
    let activeTrigger = null;

    const overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 z-[60] hidden items-center justify-center bg-black/45 p-4';
    overlay.innerHTML = `
        <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-[0_24px_70px_rgb(44_44_42/0.22)]">
            <div class="mb-4 flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-status-ng-bg text-status-ng-text">
                    <i class="ti ti-alert-triangle text-[18px]"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="text-[15px] font-semibold">Konfirmasi</h2>
                    <p data-confirm-message class="mt-1 text-[12px] text-text-secondary"></p>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" data-confirm-cancel class="rounded-lg border border-border-tertiary bg-white px-4 py-2 text-[13px] font-semibold text-text-primary">Batal</button>
                <button type="button" data-confirm-ok class="rounded-lg bg-status-ng-text px-4 py-2 text-[13px] font-semibold text-white">Ya, lanjutkan</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const messageEl = overlay.querySelector('[data-confirm-message]');
    const okBtn = overlay.querySelector('[data-confirm-ok]');
    const cancelBtn = overlay.querySelector('[data-confirm-cancel]');

    const open = (message) => {
        return new Promise((resolve) => {
            messageEl.textContent = message;
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            activeResolver = resolve;
            setTimeout(() => okBtn.focus(), 50);
        });
    };

    const close = (result) => {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        const resolver = activeResolver;
        activeResolver = null;
        if (resolver) resolver(result);
    };

    okBtn.addEventListener('click', () => close(true));
    cancelBtn.addEventListener('click', () => close(false));
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(false); });
    document.addEventListener('keydown', (e) => {
        if (overlay.classList.contains('hidden')) return;
        if (e.key === 'Escape') close(false);
        if (e.key === 'Enter') close(true);
    });

    document.addEventListener('click', async (e) => {
        const trigger = e.target.closest('[data-confirm]');
        if (!trigger) return;
        if (trigger === activeTrigger) {
            activeTrigger = null;
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        const message = trigger.getAttribute('data-confirm') || 'Lanjutkan aksi ini?';
        const confirmed = await open(message);
        if (confirmed) {
            activeTrigger = trigger;
            trigger.click();
        }
    }, true);
};

// Toggle password: tombol mata untuk menampilkan/menyembunyikan isi kolom sandi/PIN.
const initPasswordToggle = () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-password-toggle]');
        if (!btn) return;
        e.preventDefault();
        const wrapper = btn.closest('[data-password-field]') || btn.parentElement;
        const input = wrapper?.querySelector('input');
        if (!input) return;
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if (icon) icon.className = reveal ? 'ti ti-eye-off text-[16px]' : 'ti ti-eye text-[16px]';
        btn.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
};

// boot(): titik masuk — menjalankan semua modul UI setelah DOM siap.
const boot = () => {
    initSidebar();
    initStatusCards();
    initSearchScroll();
    initResponsiveTables();
    initFlashDismiss();
    initFormValidationMessages();
    initConfirmDialog();
    initPasswordToggle();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
