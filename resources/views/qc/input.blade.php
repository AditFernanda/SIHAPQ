{{--
    Halaman Input Hasil QC (dipakai oleh peran QC Inspector).

    Alur halaman ini:
      1. Blok PHP    : menyiapkan nilai awal form (data lama, atau data inspeksi
                       Waiting yang sedang diselesaikan) + opsi pencarian mesin
                       dan part yang dikirim ke JavaScript.
      2. Form        : form utama input hasil QC (mesin, part, waktu, hasil, NG).
      3. Riwayat     : tabel inspeksi hari ini di bawah form.
      4. Blok script : JS untuk pencarian mesin/part dan konfirmasi PIN inspector.

    Catatan: hasil yang disimpan langsung tampil di layar Akun Mesin terkait.
--}}
<x-layouts.app role="qc" title="Input Hasil QC">
    {{-- Siapkan nilai awal seluruh field form sebelum dirender di bawah. --}}
    @php
        $savedInspection = session('savedInspectionId')
            ? $todayInspections->firstWhere('id', session('savedInspectionId'))
            : null;
        $pendingInspection = $pendingInspection ?? null;
        $selectedMachineId = old('machine_id', $pendingInspection?->machine_id);
        $selectedMachine = $selectedMachineId ? $machines->firstWhere('id', (int) $selectedMachineId) : null;
        $selectedProductId = old('product_id', $pendingInspection?->product_id);
        $selectedProduct = $selectedProductId ? $products->firstWhere('id', (int) $selectedProductId) : null;
        $inspectionDateValue = old(
            'inspection_date',
            $pendingInspection?->inspection_date?->format('Y-m-d') ?: now()->format('Y-m-d'),
        );
        $startTimeValue = old('start_time', $pendingInspection?->start_time ?: now()->format('H:i'));
        $endTimeValue = old('end_time', $pendingInspection?->end_time ?: now()->format('H:i'));
        $selectedNgTypeIds = collect(old('ng_type_ids', []))->map(fn($id) => (string) $id)->all();
        // Status hasil sengaja tidak diisi otomatis: QC harus memilih ulang keputusan.
        $selectedResultStatus = old('result_status');
        $selectedInspectorId = old('qc_inspector_id', $pendingInspection?->qc_inspector_id);
        $notesValue = old('notes', $pendingInspection?->notes);
        $machineSearchOptions = $machines
            ->map(function ($machine) {
                return [
                    'id' => $machine->id,
                    'code' => $machine->machine_code,
                    'name' => $machine->name,
                    'department' => $machine->department?->name,
                    'label' => $machine->detailName(),
                    'search' => trim($machine->machine_code . ' ' . $machine->name . ' ' . $machine->department?->name),
                ];
            })
            ->values();
        $productSearchOptions = $products
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'code' => $product->product_code,
                    'name' => $product->name,
                    'process' => $product->processName(),
                    'customer' => $product->customer_name,
                    'label' =>
                        $product->product_code .
                        ' | ' .
                        $product->name .
                        ($product->customer_name ? ' | ' . $product->customer_name : ''),
                    'search' => trim(
                        $product->product_code .
                            ' ' .
                            $product->name .
                            ' ' .
                            $product->processName() .
                            ' ' .
                            $product->customer_name,
                    ),
                ];
            })
            ->values();
    @endphp

    <div class="mb-4">
        <h1 class="text-[18px] font-semibold">Input Hasil Pengecekan QC</h1>
        <p class="text-[12px] text-text-secondary">Hasil yang disimpan langsung muncul di akun mesin terkait dan rekap.
        </p>
    </div>

    {{-- Pesan "berhasil disimpan" muncul sebagai toast global; panel ini hanya
         merangkum detail hasil yang barusan tersimpan. Seluruh kontainer diberi
         nuansa warna sesuai status hasil agar selaras dengan kartu status. --}}
    @if ($savedInspection)
        @php
            $summaryTone =
                [
                    'RP' => 'border-status-rp-text/30 bg-status-rp-bg',
                    'NG' => 'border-status-ng-text/40 bg-status-ng-bg',
                    'SR' => 'border-status-sr-text/30 bg-status-sr-bg',
                    'SC' => 'border-status-sc-text/30 bg-status-sc-bg',
                    'WAITING' => 'border-amber-300 bg-amber-50',
                ][$savedInspection->result_status] ?? 'border-border-tertiary bg-background-secondary';
        @endphp
        <div class="mb-4 rounded-xl border {{ $summaryTone }} p-4 text-[12px] text-text-primary">
            <p class="flex items-center gap-1.5 font-semibold text-text-secondary">
                <i class="ti ti-history text-[14px]"></i> Ringkasan input terakhir
            </p>
            <div class="mt-2 grid items-center gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <span>Mesin: <strong>{{ $savedInspection->machineName() }}</strong></span>
                <span>Part: <strong>{{ $savedInspection->partName() }}</strong></span>
                <span class="flex items-center gap-1.5">Status: <x-status-badge :status="$savedInspection->result_status" /></span>
                <span>Jenis NG: <strong>{{ $savedInspection->ngTypeNames() }}</strong></span>
                <span>Jam: <strong>{{ $savedInspection->start_time }} - {{ $savedInspection->end_time }}</strong></span>
            </div>
        </div>
    @endif

    @if ($pendingInspection)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-[12px] text-amber-900">
            <p class="font-semibold">Selesaikan Waiting QC</p>
            <p class="mt-1">Input ini sedang menunggu konfirmasi. Pilih keputusan final setelah informasi tambahan
                sudah tersedia.</p>
        </div>
    @endif

    <form action="{{ $pendingInspection ? '/qc/input/' . $pendingInspection->id . '/resolve' : '/qc/input' }}"
        method="post" autocomplete="off" class="space-y-4" id="qcInputForm">
        @csrf
        @if ($pendingInspection)
            @method('PUT')
        @endif
        <section class="overflow-hidden rounded-xl border border-border-tertiary bg-white">
            <div class="border-b border-border-tertiary px-5 py-4">
                <h2 class="text-[15px] font-semibold">{{ $pendingInspection ? 'Keputusan Final QC' : 'Input Hasil QC' }}
                </h2>
                <p class="text-[12px] text-text-secondary">Isi data utama. Bagian, customer, dan proses mengikuti data
                    master.</p>
            </div>

            <div class="space-y-5 p-5">
                <div class="grid gap-3 lg:grid-cols-3">
                    <label>
                        <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Tanggal cek</span>
                        <input name="inspection_date" type="date" value="{{ $inspectionDateValue }}"
                            max="{{ now()->format('Y-m-d') }}" min="{{ now()->subDays(7)->format('Y-m-d') }}"
                            class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            required>
                    </label>
                    <div class="relative">
                        <label for="machineSearch">
                            <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Cari mesin</span>
                            <input id="machineSearch" type="text"
                                value="{{ $selectedMachine?->detailName() ?? '' }}"
                                placeholder="Contoh: 60T-4, WASHINO"
                                class="h-10 w-full rounded-lg border border-border-tertiary px-3 pr-10 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                data-validation-label="mesin" autocomplete="off" required>
                        </label>
                        <input type="hidden" name="machine_id" id="machineSelect" value="{{ $selectedMachineId }}">
                        <button type="button" id="clearMachineSearch"
                            class="absolute right-2 bottom-2 hidden size-6 items-center justify-center rounded text-text-secondary hover:bg-background-secondary"
                            aria-label="Bersihkan pilihan mesin">
                            <i class="ti ti-x text-[14px]"></i>
                        </button>
                        <div id="machineSearchResults"
                            class="absolute left-0 right-0 z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-lg border border-border-tertiary bg-white shadow-lg">
                        </div>
                        <p id="machineSearchError" class="mt-1 hidden text-[12px] font-medium text-status-ng-text"></p>
                    </div>
                    <div class="relative">
                        <label for="productSearch">
                            <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Cari part</span>
                            <input id="productSearch" type="text"
                                value="{{ $selectedProduct ? $selectedProduct->product_code . ' | ' . $selectedProduct->name . ($selectedProduct->customer_name ? ' | ' . $selectedProduct->customer_name : '') : '' }}"
                                placeholder="Contoh: Source Blank Key K1A"
                                class="h-10 w-full rounded-lg border border-border-tertiary px-3 pr-10 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                data-validation-label="part" autocomplete="off" required>
                        </label>
                        <input type="hidden" name="product_id" id="productSelect" value="{{ $selectedProductId }}">
                        <button type="button" id="clearProductSearch"
                            class="absolute right-2 bottom-2 hidden size-6 items-center justify-center rounded text-text-secondary hover:bg-background-secondary"
                            aria-label="Bersihkan pilihan part">
                            <i class="ti ti-x text-[14px]"></i>
                        </button>
                        <div id="productSearchResults"
                            class="absolute left-0 right-0 z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-lg border border-border-tertiary bg-white shadow-lg">
                        </div>
                        <p id="productSearchError" class="mt-1 hidden text-[12px] font-medium text-status-ng-text"></p>
                    </div>
                </div>

                <dl
                    class="grid gap-2 rounded-lg border border-border-tertiary bg-background-secondary p-3 text-[12px] md:grid-cols-3">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Bagian
                        </dt>
                        <dd id="sectionName" class="mt-1 font-semibold text-text-primary">-</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Customer
                        </dt>
                        <dd id="customerName" class="mt-1 font-semibold text-text-primary">-</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-semibold uppercase tracking-[0.06em] text-text-secondary">Proses
                        </dt>
                        <dd id="processName" class="mt-1 font-semibold text-text-primary">-</dd>
                    </div>
                </dl>

                <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label>
                            <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Mulai cek</span>
                            <input name="start_time" type="time" value="{{ $startTimeValue }}"
                                class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                required>
                        </label>
                        <label>
                            <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Selesai cek</span>
                            <input name="end_time" type="time" value="{{ $endTimeValue }}"
                                class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                                readonly required>
                            <span class="mt-1 block text-[11px] text-text-secondary">Otomatis memakai jam saat data
                                disimpan.</span>
                        </label>
                    </div>

                    <div>
                        <span class="mb-2 block text-[12px] font-semibold text-text-secondary">Hasil cek</span>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([['RP', 'RP', 'Running Process', 'bg-status-rp-bg text-status-rp-text'], ['NG', 'NG', 'No Good', 'bg-status-ng-bg text-status-ng-text'], ['SR', 'SR', 'Special Request', 'bg-status-sr-bg text-status-sr-text'], ['SC', 'SC', 'Special Control', 'bg-status-sc-bg text-status-sc-text'], ['WAITING', 'WAITING', 'Menunggu Konfirmasi', 'bg-amber-100 text-amber-800']] as [$code, $displayCode, $label, $style])
                                @if ($pendingInspection && $code === 'WAITING')
                                    @continue
                                @endif
                                <label data-status-card
                                    class="group flex min-h-11 cursor-pointer items-center justify-between gap-2 rounded-lg border border-border-tertiary bg-white px-3 py-2 text-left transition hover:border-primary hover:bg-primary/5 {{ $selectedResultStatus === $code ? 'is-selected border-primary ring-2 ring-primary/15' : '' }}">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <input type="radio" name="result_status" value="{{ $code }}"
                                            class="sr-only" required @checked($selectedResultStatus === $code)>
                                        <span
                                            class="{{ $style }} shrink-0 rounded px-2 py-0.5 text-[11px] font-bold">{{ $displayCode }}</span>
                                        <span
                                            class="text-[12px] font-semibold leading-tight text-text-primary">{{ $label }}</span>
                                    </span>
                                    <span
                                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-primary text-white opacity-0 transition group-[.is-selected]:opacity-100">
                                        <i class="ti ti-check text-[13px]"></i>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
                    <label>
                        <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">PIC QC</span>
                        <select name="qc_inspector_id"
                            class="h-10 w-full rounded-lg border border-border-tertiary px-3 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            required>
                            <option value="">Pilih PIC QC</option>
                            @foreach ($inspectors as $inspector)
                                <option value="{{ $inspector->id }}" @selected($selectedInspectorId == $inspector->id)>
                                    {{ $inspector->employee_id }} - {{ $inspector->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div data-ng-type-field class="{{ old('result_status') === 'NG' ? '' : 'hidden' }}">
                        <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Jenis NG</span>
                        <div
                            class="grid gap-2 rounded-lg border border-border-tertiary bg-white p-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($ngTypes as $ngType)
                                <label
                                    class="flex min-h-10 cursor-pointer items-center gap-2 rounded-md bg-background-secondary px-3 py-2 text-[12px] font-semibold text-text-primary">
                                    <input type="checkbox" name="ng_type_ids[]" value="{{ $ngType->id }}"
                                        class="size-4 rounded border-border-tertiary text-primary focus:ring-primary/20"
                                        data-validation-label="jenis NG" @checked(in_array((string) $ngType->id, $selectedNgTypeIds, true))>
                                    <span>{{ $ngType->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p data-ng-type-error class="mt-1 hidden text-[12px] font-medium text-status-ng-text">Silakan
                            pilih jenis NG.</p>
                    </div>
                </div>

                <div class="grid gap-3">
                    <label>
                        <span class="mb-1.5 block text-[12px] font-semibold text-text-secondary">Keterangan</span>
                        <textarea name="notes" rows="2"
                            class="w-full rounded-lg border border-border-tertiary px-3 py-2 text-[13px] outline-none focus:border-primary focus:ring-2 focus:ring-primary/10"
                            placeholder="Contoh: NG item 2,3,4" data-default-placeholder="Contoh: NG item 2,3,4"
                            data-pending-placeholder="Contoh: Menunggu potongan part / barelan untuk konfirmasi">{{ $notesValue }}</textarea>
                    </label>
                </div>

            </div>

            <div
                class="flex flex-col-reverse gap-2 border-t border-border-tertiary bg-background-secondary px-5 py-4 sm:flex-row sm:justify-end">
                <a href="/qc/dashboard"
                    class="rounded-lg border border-border-tertiary bg-white px-4 py-2 text-center text-[13px] font-semibold">Batal</a>
                <button type="submit"
                    class="rounded-lg bg-primary px-4 py-2 text-[13px] font-semibold text-white shadow-sm">{{ $pendingInspection ? 'Simpan Keputusan Final' : 'Simpan Hasil Cek' }}</button>
            </div>
        </section>

        <div id="pinModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/45 p-4">
            <div class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
                <div class="mb-4">
                    <h2 class="text-[15px] font-semibold">Verifikasi PIN PIC QC</h2>
                    <p class="mt-1 text-[12px] text-text-secondary">Masukkan PIN PIC QC untuk menyimpan hasil
                        pengecekan.</p>
                </div>
                <label>
                    <span
                        class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">PIN
                        PIC QC</span>
                    <input id="pinInput" name="pin" type="password" inputmode="numeric" maxlength="6"
                        pattern="[0-9]{6}" autocomplete="one-time-code"
                        class="w-full rounded-lg border border-border-tertiary px-3 py-2 text-center text-[18px] tracking-[0.35em] outline-none focus:border-primary"
                        required disabled>
                </label>
                @error('pin')
                    <p class="mt-2 text-center text-[12px] font-medium text-red-600">{{ $message }}</p>
                @enderror
                <div class="mt-5 flex gap-2">
                    <button type="button" id="cancelPinModal"
                        class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</button>
                    <button type="submit" id="confirmPinSubmit"
                        class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">Verifikasi &
                        Simpan</button>
                </div>
            </div>
        </div>
    </form>

    <section class="mt-5 rounded-xl border border-border-tertiary bg-white p-4">
        <div class="mb-3 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
            <div>
                <h2 class="text-[14px] font-semibold">Input Terakhir Hari QC Ini</h2>
                <p class="text-[12px] text-text-secondary">Cek cepat 8 input terakhir pada periode
                    {{ \App\Models\QcInspection::operationalDayStart() }} sampai
                    {{ \App\Models\QcInspection::operationalDayStart() }}. Detail lengkap tetap ada di laporan.</p>
            </div>
        </div>
        <x-data-table :headers="['Jam', 'Mesin', 'Part', 'Hasil', 'Jenis NG', 'PIC QC', 'Aksi']">
            @forelse ($todayInspections as $inspection)
                <tr>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->start_time }} -
                        {{ $inspection->end_time }}</td>
                    <td class="whitespace-nowrap px-3 py-3 font-semibold">{{ $inspection->machineName() }}</td>
                    <td class="min-w-55 px-3 py-3">
                        <p class="font-medium">{{ $inspection->partName() }}</p>
                        <p class="mt-0.5 font-mono text-[11px] text-text-secondary">{{ $inspection->partNumber() }} ·
                            {{ $inspection->processName() }}</p>
                    </td>
                    <td class="px-3 py-3"><x-status-badge :status="$inspection->result_status" /></td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->ngTypeNames() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">{{ $inspection->inspectorName() }}</td>
                    <td class="whitespace-nowrap px-3 py-3">
                        @if ($inspection->result_status === \App\Support\QcStatus::WAITING)
                            <a href="/qc/input?resolve={{ $inspection->id }}"
                                class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[12px] font-semibold text-amber-800 hover:bg-amber-100">
                                <i class="ti ti-checkup-list"></i>Selesaikan
                            </a>
                        @else
                            <span class="text-text-secondary">-</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-3 py-6 text-center text-text-secondary">Belum ada input QC pada hari
                        QC ini.</td>
                </tr>
            @endforelse
        </x-data-table>
    </section>

    {{--
        Script khusus halaman input QC:
          - Kotak pencarian mesin dan part (autocomplete) yang mengisi select tersembunyi.
          - Isi otomatis field bagian/customer/proses saat mesin/part dipilih.
          - Modal PIN: inspector wajib memasukkan PIN sebelum data disimpan (verifikasi identitas).
        Data opsi mesin/part dikirim dari blok PHP di atas dalam format JSON.
    --}}
    @push('scripts')
        <script>
            // ── SEKSI 1: Referensi elemen DOM (input, tombol, modal) ──────────────
            const machineSelect = document.getElementById('machineSelect');
            const machineSearch = document.getElementById('machineSearch');
            const machineSearchResults = document.getElementById('machineSearchResults');
            const clearMachineSearch = document.getElementById('clearMachineSearch');
            const machineSearchError = document.getElementById('machineSearchError');
            const productSelect = document.getElementById('productSelect');
            const productSearch = document.getElementById('productSearch');
            const productSearchResults = document.getElementById('productSearchResults');
            const clearProductSearch = document.getElementById('clearProductSearch');
            const productSearchError = document.getElementById('productSearchError');
            const sectionName = document.getElementById('sectionName');
            const customerName = document.getElementById('customerName');
            const processName = document.getElementById('processName');
            const qcInputForm = document.getElementById('qcInputForm');
            const pinModal = document.getElementById('pinModal');
            const pinInput = document.getElementById('pinInput');
            const cancelPinModal = document.getElementById('cancelPinModal');
            const confirmPinSubmit = document.getElementById('confirmPinSubmit');
            const ngTypeFieldWrapper = document.querySelector('[data-ng-type-field]');
            const ngTypeError = document.querySelector('[data-ng-type-error]');
            const shouldResetInput = {{ !$errors->any() && !$pendingInspection ? 'true' : 'false' }};
            const machineOptions = @json($machineSearchOptions);
            const productOptions = @json($productSearchOptions);
            let pinConfirmed = false;
            let selectedMachine = machineOptions.find((machine) => String(machine.id) === String(machineSelect?.value || '')) ||
                null;
            let selectedProduct = productOptions.find((product) => String(product.id) === String(productSelect?.value || '')) ||
                null;
            let machineMatches = [];
            let activeMachineIndex = -1;
            let productMatches = [];
            let activeProductIndex = -1;

            // ── SEKSI 2: Fungsi bantu umum (format jam, isi teks, cocokkan kata) ──
            const formatLocalTime = (date) => {
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${hours}:${minutes}`;
            };
            const setAutoText = (element, value) => {
                if (!element) return;
                element.textContent = value || '-';
            };
            const matchesSearchText = (text, term) => {
                const source = String(text || '').toLowerCase();

                return term
                    .split(/\s+/)
                    .filter(Boolean)
                    .every((token) => source.includes(token));
            };

            // ── SEKSI 3: Pencarian MESIN (autocomplete + navigasi keyboard) ──────
            const hideMachineResults = () => {
                if (!machineSearchResults) return;
                machineSearchResults.classList.add('hidden');
                machineSearchResults.innerHTML = '';
                machineMatches = [];
                activeMachineIndex = -1;
            };

            const setActiveMachineResult = (index) => {
                if (!machineSearchResults || machineMatches.length === 0) return;

                activeMachineIndex = (index + machineMatches.length) % machineMatches.length;
                machineSearchResults.querySelectorAll('[data-result-index]').forEach((button) => {
                    const active = Number(button.dataset.resultIndex) === activeMachineIndex;
                    button.classList.toggle('bg-primary/10', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                    if (active) button.scrollIntoView({
                        block: 'nearest'
                    });
                });
            };

            const syncMachineSearchValidity = () => {
                if (!machineSearch) return;
                if (selectedMachine && String(machineSelect?.value || '') === String(selectedMachine.id) && machineSearch
                    .value === selectedMachine.label) {
                    machineSearch.setCustomValidity('');
                    if (machineSearchError) {
                        machineSearchError.textContent = '';
                        machineSearchError.classList.add('hidden');
                    }
                    return;
                }

                const message = machineSearch.value.trim() ? 'Silakan pilih mesin dari master data.' :
                    'Silakan pilih mesin.';
                machineSearch.setCustomValidity(message);
                if (machineSearchError) {
                    machineSearchError.textContent = message;
                    machineSearchError.classList.toggle('hidden', !machineSearch.value.trim());
                }
            };

            const syncMachine = () => {
                setAutoText(sectionName, selectedMachine?.department);
                if (clearMachineSearch) {
                    clearMachineSearch.classList.toggle('hidden', !machineSearch?.value);
                    clearMachineSearch.classList.toggle('inline-flex', Boolean(machineSearch?.value));
                }
                syncMachineSearchValidity();
            };

            const selectMachine = (machine) => {
                selectedMachine = machine;
                if (machineSelect) machineSelect.value = machine?.id || '';
                if (machineSearch) machineSearch.value = machine?.label || '';
                syncMachine();
                hideMachineResults();
            };

            const renderMachineResults = () => {
                if (!machineSearch || !machineSearchResults) return;

                const term = machineSearch.value.trim().toLowerCase();
                if (term.length < 2) {
                    machineSearchResults.innerHTML =
                        '<div class="px-3 py-2 text-[12px] text-text-secondary">Ketik minimal 2 karakter untuk mencari mesin.</div>';
                    machineSearchResults.classList.remove('hidden');
                    return;
                }

                const matches = machineOptions
                    .filter((machine) => matchesSearchText(machine.search, term))
                    .slice(0, 12);
                machineMatches = matches;
                activeMachineIndex = matches.length ? 0 : -1;

                machineSearchResults.innerHTML = '';

                if (!matches.length) {
                    machineSearchResults.innerHTML =
                        '<div class="px-3 py-2 text-[12px] text-text-secondary">Mesin tidak ditemukan di master data.</div>';
                    machineSearchResults.classList.remove('hidden');
                    return;
                }

                matches.forEach((machine, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.resultIndex = String(index);
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', index === activeMachineIndex ? 'true' : 'false');
                    button.className =
                        `block w-full border-b border-border-tertiary px-3 py-2 text-left last:border-b-0 hover:bg-primary/10 focus:bg-primary/10 focus:outline-none ${index === activeMachineIndex ? 'bg-primary/10' : ''}`;
                    button.innerHTML = `
                        <span class="block truncate text-[13px] font-semibold text-text-primary"></span>
                        <span class="mt-0.5 block truncate text-[12px] text-text-secondary"></span>
                    `;
                    button.querySelector('span:first-child').textContent = `${machine.code} | ${machine.name}`;
                    button.querySelector('span:last-child').textContent = machine.department || '-';
                    button.addEventListener('mouseenter', () => setActiveMachineResult(index));
                    button.addEventListener('click', () => selectMachine(machine));
                    machineSearchResults.appendChild(button);
                });

                machineSearchResults.classList.remove('hidden');
            };

            // ── SEKSI 4: Pencarian PART (autocomplete + navigasi keyboard) ───────
            const hideProductResults = () => {
                if (!productSearchResults) return;
                productSearchResults.classList.add('hidden');
                productSearchResults.innerHTML = '';
                productMatches = [];
                activeProductIndex = -1;
            };

            const setActiveProductResult = (index) => {
                if (!productSearchResults || productMatches.length === 0) return;

                activeProductIndex = (index + productMatches.length) % productMatches.length;
                productSearchResults.querySelectorAll('[data-result-index]').forEach((button) => {
                    const active = Number(button.dataset.resultIndex) === activeProductIndex;
                    button.classList.toggle('bg-primary/10', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                    if (active) button.scrollIntoView({
                        block: 'nearest'
                    });
                });
            };

            const syncProductSearchValidity = () => {
                if (!productSearch) return;
                if (selectedProduct && String(productSelect?.value || '') === String(selectedProduct.id) && productSearch
                    .value === selectedProduct.label) {
                    productSearch.setCustomValidity('');
                    if (productSearchError) {
                        productSearchError.textContent = '';
                        productSearchError.classList.add('hidden');
                    }
                    return;
                }

                const message = productSearch.value.trim() ? 'Silakan pilih part dari master data.' :
                    'Silakan pilih part.';
                productSearch.setCustomValidity(message);
                if (productSearchError) {
                    productSearchError.textContent = message;
                    productSearchError.classList.toggle('hidden', !productSearch.value.trim());
                }
            };

            const syncProduct = () => {
                setAutoText(customerName, selectedProduct?.customer);
                setAutoText(processName, selectedProduct?.process);
                if (clearProductSearch) {
                    clearProductSearch.classList.toggle('hidden', !productSearch?.value);
                    clearProductSearch.classList.toggle('inline-flex', Boolean(productSearch?.value));
                }
                syncProductSearchValidity();
            };

            const selectProduct = (product) => {
                selectedProduct = product;
                if (productSelect) productSelect.value = product?.id || '';
                if (productSearch) productSearch.value = product?.label || '';
                syncProduct();
                hideProductResults();
            };

            const renderProductResults = () => {
                if (!productSearch || !productSearchResults) return;

                const term = productSearch.value.trim().toLowerCase();
                if (term.length < 2) {
                    productSearchResults.innerHTML =
                        '<div class="px-3 py-2 text-[12px] text-text-secondary">Ketik minimal 2 karakter untuk mencari part.</div>';
                    productSearchResults.classList.remove('hidden');
                    return;
                }

                const matches = productOptions
                    .filter((product) => matchesSearchText(product.search, term))
                    .slice(0, 10);
                productMatches = matches;
                activeProductIndex = matches.length ? 0 : -1;

                productSearchResults.innerHTML = '';

                if (!matches.length) {
                    productSearchResults.innerHTML =
                        '<div class="px-3 py-2 text-[12px] text-text-secondary">Part tidak ditemukan di master data.</div>';
                    productSearchResults.classList.remove('hidden');
                    return;
                }

                matches.forEach((product, index) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.dataset.resultIndex = String(index);
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', index === activeProductIndex ? 'true' : 'false');
                    button.className =
                        `block w-full border-b border-border-tertiary px-3 py-2 text-left last:border-b-0 hover:bg-primary/10 focus:bg-primary/10 focus:outline-none ${index === activeProductIndex ? 'bg-primary/10' : ''}`;
                    button.innerHTML = `
                        <span class="block truncate text-[13px] font-semibold text-text-primary"></span>
                        <span class="mt-0.5 block truncate text-[12px] text-text-secondary"></span>
                    `;
                    button.querySelector('span:first-child').textContent = `${product.code} | ${product.name}`;
                    button.querySelector('span:last-child').textContent = [product.customer, product.process]
                        .filter(Boolean).join(' | ') || '-';
                    button.addEventListener('mouseenter', () => setActiveProductResult(index));
                    button.addEventListener('click', () => selectProduct(product));
                    productSearchResults.appendChild(button);
                });

                productSearchResults.classList.remove('hidden');
            };
            // ── SEKSI 5: Pasang event listener kotak pencarian (ketik/fokus/klik) ─
            machineSearch?.addEventListener('input', () => {
                if (!selectedMachine || machineSearch.value !== selectedMachine.label) {
                    selectedMachine = null;
                    if (machineSelect) machineSelect.value = '';
                    setAutoText(sectionName, null);
                }
                syncMachine();
                renderMachineResults();
            });
            machineSearch?.addEventListener('focus', renderMachineResults);
            machineSearch?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideMachineResults();
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (machineSearchResults?.classList.contains('hidden')) renderMachineResults();
                    if (machineMatches.length) setActiveMachineResult(activeMachineIndex + 1);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (machineSearchResults?.classList.contains('hidden')) renderMachineResults();
                    if (machineMatches.length) setActiveMachineResult(activeMachineIndex - 1);
                    return;
                }

                if (event.key === 'Enter' && !machineSearchResults?.classList.contains('hidden') && machineMatches[
                        activeMachineIndex]) {
                    event.preventDefault();
                    selectMachine(machineMatches[activeMachineIndex]);
                }
            });
            clearMachineSearch?.addEventListener('click', () => {
                selectMachine(null);
                machineSearch?.focus();
            });
            productSearch?.addEventListener('input', () => {
                if (!selectedProduct || productSearch.value !== selectedProduct.label) {
                    selectedProduct = null;
                    if (productSelect) productSelect.value = '';
                    setAutoText(customerName, null);
                    setAutoText(processName, null);
                }
                syncProduct();
                renderProductResults();
            });
            productSearch?.addEventListener('focus', renderProductResults);
            productSearch?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    hideProductResults();
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (productSearchResults?.classList.contains('hidden')) renderProductResults();
                    if (productMatches.length) setActiveProductResult(activeProductIndex + 1);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (productSearchResults?.classList.contains('hidden')) renderProductResults();
                    if (productMatches.length) setActiveProductResult(activeProductIndex - 1);
                    return;
                }

                if (event.key === 'Enter' && !productSearchResults?.classList.contains('hidden') && productMatches[
                        activeProductIndex]) {
                    event.preventDefault();
                    selectProduct(productMatches[activeProductIndex]);
                }
            });
            clearProductSearch?.addEventListener('click', () => {
                selectProduct(null);
                productSearch?.focus();
            });
            document.addEventListener('click', (event) => {
                if (!event.target.closest('#machineSearch') && !event.target.closest('#machineSearchResults') && !event
                    .target.closest('#clearMachineSearch')) {
                    hideMachineResults();
                }

                if (!event.target.closest('#productSearch') && !event.target.closest('#productSearchResults') && !event
                    .target.closest('#clearProductSearch')) {
                    hideProductResults();
                }
            });

            // ── SEKSI 6: Aturan dinamis form (kartu status, wajib NG & keterangan) ─
            const syncStatusCards = () => {
                document.querySelectorAll('[data-status-card]').forEach((card) => {
                    card.classList.toggle('is-selected', card.querySelector('input[type="radio"]')?.checked);
                    card.classList.toggle('border-primary', card.querySelector('input[type="radio"]')?.checked);
                    card.classList.toggle('ring-2', card.querySelector('input[type="radio"]')?.checked);
                    card.classList.toggle('ring-primary/15', card.querySelector('input[type="radio"]')?.checked);
                });
            };

            const syncNotesRequirement = () => {
                const notesField = qcInputForm?.elements.notes;
                const ngTypeFields = qcInputForm ? Array.from(qcInputForm.querySelectorAll('input[name="ng_type_ids[]"]')) :
                    [];
                const selectedStatus = qcInputForm?.querySelector('input[name="result_status"]:checked')?.value;
                if (!notesField) return;

                notesField.required = false;
                notesField.setCustomValidity('');
                notesField.placeholder = notesField.dataset.defaultPlaceholder || notesField.getAttribute('placeholder') ||
                    '';
                if (selectedStatus === 'WAITING') {
                    notesField.required = true;
                    notesField.placeholder = notesField.dataset.pendingPlaceholder || notesField.placeholder;
                }
                const needsNgType = selectedStatus === 'NG';
                ngTypeFields.forEach((field) => {
                    field.required = false;
                    field.disabled = !needsNgType;
                    field.setCustomValidity('');
                    if (!needsNgType) field.checked = false;
                });
                if (needsNgType) {
                    ngTypeFields.forEach((field) => {
                        field.addEventListener('change', syncNotesRequirement, {
                            once: true
                        });
                    });
                }
                if (ngTypeError && (!needsNgType || ngTypeFields.some((item) => item.checked))) {
                    ngTypeError.classList.add('hidden');
                }
                ngTypeFieldWrapper?.classList.toggle('hidden', !needsNgType);
            };

            qcInputForm?.querySelectorAll('input[name="result_status"]').forEach((input) => {
                input.addEventListener('change', syncNotesRequirement);
            });

            // ── SEKSI 7: Reset form untuk input baru + inisialisasi saat load ────
            const resetNewInputForm = () => {
                Object.keys(localStorage).forEach((key) => {
                    if (key.startsWith('qc-input')) localStorage.removeItem(key);
                });

                if (!shouldResetInput || !qcInputForm) return;

                qcInputForm.reset();

                ['machine_id', 'product_id', 'qc_inspector_id', 'notes'].forEach((name) => {
                    const field = qcInputForm.elements[name];
                    if (field) field.value = '';
                });
                qcInputForm.querySelectorAll('input[name="ng_type_ids[]"]').forEach((input) => {
                    input.checked = false;
                });
                selectProduct(null);
                selectMachine(null);

                qcInputForm.querySelectorAll('input[name="result_status"]').forEach((input) => {
                    input.checked = false;
                });

                const startField = qcInputForm.elements.start_time;
                const endField = qcInputForm.elements.end_time;
                const currentTime = new Date();

                if (startField) startField.value = formatLocalTime(currentTime);
                if (endField) endField.value = formatLocalTime(currentTime);
            };

            resetNewInputForm();
            syncMachine();
            syncProduct();
            syncStatusCards();
            syncNotesRequirement();

            // ── SEKSI 8: Submit form — validasi lalu buka modal verifikasi PIN ───
            qcInputForm?.addEventListener('submit', (event) => {
                if (pinConfirmed) {
                    return;
                }

                if (pinModal?.classList.contains('flex') && !pinInput?.disabled) {
                    if (pinInput.checkValidity()) {
                        pinConfirmed = true;
                    }
                    return;
                }

                event.preventDefault();
                syncNotesRequirement();
                syncMachineSearchValidity();
                syncProductSearchValidity();
                const selectedStatus = qcInputForm.querySelector('input[name="result_status"]:checked')?.value;
                const ngTypeFields = Array.from(qcInputForm.querySelectorAll('input[name="ng_type_ids[]"]'));
                if (selectedStatus === 'NG' && !ngTypeFields.some((field) => field.checked)) {
                    ngTypeError?.classList.remove('hidden');
                    ngTypeFieldWrapper?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    ngTypeFields[0]?.focus({
                        preventScroll: true
                    });
                    return;
                }
                const startTime = qcInputForm.elements.start_time.value;
                const endTime = formatLocalTime(new Date());
                qcInputForm.elements.end_time.value = endTime;
                const durationMinutes = (() => {
                    if (!startTime || !endTime) return 0;
                    const [startHour, startMinute] = startTime.split(':').map(Number);
                    const [endHour, endMinute] = endTime.split(':').map(Number);
                    let start = (startHour * 60) + startMinute;
                    let end = (endHour * 60) + endMinute;
                    if (end < start) end += 1440;
                    return end - start;
                })();
                if (startTime && endTime && durationMinutes > 720) {
                    qcInputForm.elements.end_time.setCustomValidity(
                        'Waktu selesai cek harus setelah waktu mulai cek dan maksimal dalam rentang 12 jam.');
                } else {
                    qcInputForm.elements.end_time.setCustomValidity('');
                }

                if (!qcInputForm.checkValidity()) {
                    qcInputForm.reportValidity();
                    return;
                }

                pinModal.classList.remove('hidden');
                pinModal.classList.add('flex');
                pinInput.disabled = false;
                pinInput.focus();
            });

            // ── SEKSI 9: Kontrol modal PIN (batal, konfirmasi, buka ulang saat salah) ─
            cancelPinModal?.addEventListener('click', () => {
                pinModal.classList.add('hidden');
                pinModal.classList.remove('flex');
                pinInput.value = '';
                pinInput.disabled = true;
                pinConfirmed = false;
            });

            confirmPinSubmit?.addEventListener('click', (event) => {
                if (!pinInput.checkValidity()) {
                    return;
                }

                pinConfirmed = true;
            });

            // PIN salah: buka kembali modal agar pesan error terlihat di tempat input PIN.
            @if ($errors->has('pin'))
                pinModal.classList.remove('hidden');
                pinModal.classList.add('flex');
                pinInput.disabled = false;
                pinInput.focus();
            @endif
        </script>
    @endpush
</x-layouts.app>
