<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Master part/produk yang menjadi objek pengecekan QC.
 */
class AdminProductController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('product_code', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhere('process_name', 'like', $term)
                        ->orWhere('customer_name', 'like', $term);
                });
            })
            ->orderBy('customer_name')
            ->orderBy('name')
            ->orderBy('product_code')
            ->orderBy('process_name')
            ->paginate(50)
            ->withQueryString();
        $editingProduct = $request->filled('edit') ? Product::find($request->integer('edit')) : null;
        $showForm = $request->boolean('create') || (bool) $editingProduct;

        return view('admin.products', [
            'products' => $products,
            'editingProduct' => $editingProduct,
            'showForm' => $showForm,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_code' => [
                'required',
                'string',
                // Nomor part boleh sama bila nama part atau prosesnya berbeda.
                Rule::unique('products')->where(fn ($query) => $query
                    ->where('name', $request->name)
                    ->where('process_name', $request->process_name)),
            ],
            'process_name' => 'required|string|max:100',
            'name' => 'required|string',
            'customer_name' => 'required|string',
            'status' => 'nullable|in:aktif,nonaktif',
        ]);

        $validated['status'] = $validated['status'] ?? 'aktif';
        $product = Product::create($validated);
        $this->logActivity($request, 'Tambah part', $product, "Part {$product->product_code} - {$product->name} ditambahkan.");

        return redirect('/admin/products')->with('success', 'Part berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'product_code' => [
                'required',
                'string',
                // Nomor part boleh sama bila nama part atau prosesnya berbeda.
                Rule::unique('products')->ignore($product->id)->where(fn ($query) => $query
                    ->where('name', $request->name)
                    ->where('process_name', $request->process_name)),
            ],
            'process_name' => 'required|string|max:100',
            'name' => 'required|string',
            'customer_name' => 'required|string',
            'status' => 'nullable|in:aktif,nonaktif',
        ]);

        $validated['status'] = $validated['status'] ?? $product->status;
        $product->update($validated);
        $this->logActivity($request, 'Ubah part', $product, "Part {$product->product_code} - {$product->name} diperbarui.");

        return redirect('/admin/products')->with('success', 'Part berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        if ($product->qcInspections()->exists()) {
            // Part yang sudah dipakai QC tidak dihapus permanen.
            $product->update(['status' => 'nonaktif']);
            $this->logActivity($request, 'Nonaktifkan part', $product, "Part {$product->product_code} dinonaktifkan karena sudah memiliki riwayat QC.");

            return back()->with('success', 'Part sudah dipakai di riwayat QC, sehingga statusnya dinonaktifkan.');
        }

        $product->delete();
        $this->logActivity($request, 'Hapus part', $product, "Part {$product->product_code} dihapus.");

        return back()->with('success', 'Part berhasil dihapus.');
    }
}
