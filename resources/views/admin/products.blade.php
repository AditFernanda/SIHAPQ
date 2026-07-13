<x-layouts.app role="admin" title="Master Part">
    <div class="mb-4 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
        <div><h1 class="text-[18px] font-semibold">Kelola Master Part</h1><p class="text-[12px] text-text-secondary">Data part, proses, dan customer untuk kebutuhan QC.</p></div>
        <a href="/admin/products?create=1" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 font-semibold text-white"><i class="ti ti-plus"></i>Tambah Part</a>
    </div>


    <x-search-form action="/admin/products" :value="$filters['search'] ?? ''" placeholder="Cari no part, nama part, proses, atau customer..." reset-url="/admin/products" />

    <div class="grid gap-4">
        <x-data-table data-search-results :headers="['No Part', 'Nama Part', 'Proses', 'Customer', 'Status', 'Aksi']">
            @forelse ($products as $product)
                <tr>
                    <td class="px-3 py-3">{{ $product->product_code }}</td>
                    <td class="px-3 py-3">{{ $product->name }}</td>
                    <td class="px-3 py-3">{{ $product->processName() }}</td>
                    <td class="px-3 py-3">{{ $product->customer_name ?? '-' }}</td>
                    <td class="px-3 py-3"><x-status-badge :status="$product->status === 'aktif' ? 'Aktif' : 'Nonaktif'" /></td>
                    <td class="px-3 py-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <a href="/admin/products?edit={{ $product->id }}" class="inline-flex items-center gap-1 rounded-lg border border-primary/25 bg-primary/5 px-2.5 py-1 text-[12px] font-semibold text-primary hover:bg-primary/10">
                                <i class="ti ti-pencil text-[13px]"></i>Ubah
                            </a>
                            <form method="POST" action="/admin/products/{{ $product->id }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-status-ng-bg bg-status-ng-bg/40 px-2.5 py-1 text-[12px] font-semibold text-status-ng-text hover:bg-status-ng-bg" data-confirm="Hapus part {{ $product->name }} ({{ $product->product_code }})?">
                                    <i class="ti ti-trash text-[13px]"></i>Hapus
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-text-secondary">Tidak ada data part</td></tr>
            @endforelse
            <x-slot:pagination>{{ $products->links() }}</x-slot:pagination>
        </x-data-table>
    </div>

    @if($showForm)
        <x-modal :title="$editingProduct ? 'Ubah Part' : 'Tambah Part'" subtitle="Isi data part, proses, dan customer." close-url="/admin/products">
            <form method="POST" action="{{ $editingProduct ? '/admin/products/'.$editingProduct->id : '/admin/products' }}">
                @csrf
                @if($editingProduct) @method('PUT') @endif
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">No Part</span><input name="product_code" value="{{ old('product_code', $editingProduct->product_code ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required autofocus></label>
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Nama Part</span><input name="name" value="{{ old('name', $editingProduct->name ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Proses</span><input name="process_name" value="{{ old('process_name', $editingProduct->process_name ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" placeholder="Contoh: Proses 1, Cutting" required></label>
                <label class="mb-3 block"><span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Customer</span><input name="customer_name" value="{{ old('customer_name', $editingProduct->customer_name ?? '') }}" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary" required></label>
                <label class="mb-4 block">
                    <span class="mb-1 block text-[11px] font-medium uppercase tracking-[0.08em] text-text-secondary">Status</span>
                    <select name="status" class="w-full rounded-lg border border-border-tertiary px-3 py-2 outline-none focus:border-primary">
                        <option value="aktif" @selected(old('status', $editingProduct->status ?? 'aktif') === 'aktif')>Aktif</option>
                        <option value="nonaktif" @selected(old('status', $editingProduct->status ?? 'aktif') === 'nonaktif')>Nonaktif</option>
                    </select>
                    <span class="mt-1 block text-[12px] text-text-secondary">Part nonaktif tidak bisa dipilih saat input hasil QC baru.</span>
                </label>
                <div class="flex gap-2"><a href="/admin/products" class="flex-1 rounded-lg border border-border-tertiary px-4 py-2 text-center">Batal</a><button class="flex-1 rounded-lg bg-primary px-4 py-2 font-semibold text-white">{{ $editingProduct ? 'Perbarui' : 'Simpan' }}</button></div>
            </form>
        </x-modal>
    @endif
</x-layouts.app>
