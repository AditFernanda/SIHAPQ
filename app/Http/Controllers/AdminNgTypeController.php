<?php

namespace App\Http\Controllers;

use App\Models\QcNgType;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

/**
 * Master jenis NG (kategori defect) untuk klasifikasi hasil cek NG.
 */
class AdminNgTypeController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $ngTypes = QcNgType::withCount('qcInspections')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where('name', 'like', $term);
            })
            ->orderBy('name')
            ->get();
        $editingNgType = $request->filled('edit') ? QcNgType::find($request->integer('edit')) : null;
        $showForm = $request->boolean('create') || (bool) $editingNgType;

        return view('admin.ng-types', [
            'ngTypes' => $ngTypes,
            'editingNgType' => $editingNgType,
            'showForm' => $showForm,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:qc_ng_types,name',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        $ngType = QcNgType::create($validated);
        $this->logActivity($request, 'Tambah jenis NG', $ngType, "Jenis NG {$ngType->name} ditambahkan.");

        return redirect('/admin/ng-types')->with('success', 'Jenis NG berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $ngType = QcNgType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:80|unique:qc_ng_types,name,'.$ngType->id,
            'status' => 'required|in:aktif,nonaktif',
        ]);

        $ngType->update($validated);
        $this->logActivity($request, 'Ubah jenis NG', $ngType, "Jenis NG {$ngType->name} diperbarui.");

        return redirect('/admin/ng-types')->with('success', 'Jenis NG berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        $ngType = QcNgType::findOrFail($id);

        if ($ngType->qcInspections()->exists()) {
            $ngType->update(['status' => 'nonaktif']);
            $this->logActivity($request, 'Nonaktifkan jenis NG', $ngType, "Jenis NG {$ngType->name} dinonaktifkan karena sudah dipakai.");

            return back()->with('success', 'Jenis NG sudah dipakai di riwayat QC, sehingga statusnya dinonaktifkan.');
        }

        $ngType->delete();
        $this->logActivity($request, 'Hapus jenis NG', $ngType, "Jenis NG {$ngType->name} dihapus.");

        return back()->with('success', 'Jenis NG berhasil dihapus.');
    }
}
