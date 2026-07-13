<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

/**
 * Master bagian (departemen) tempat mesin bernaung.
 */
class AdminDepartmentController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $departments = Department::withCount('machines')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('name', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('name')
            ->get();
        $editingItem = $request->filled('edit') ? Department::find($request->integer('edit')) : null;
        $showForm = $request->boolean('create') || (bool) $editingItem;

        return view('admin.simple-master', [
            'type' => 'Bagian',
            'items' => $departments,
            'editingItem' => $editingItem,
            'showForm' => $showForm,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:departments',
            'description' => 'nullable|string',
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => 'aktif',
        ]);
        $this->logActivity($request, 'Tambah bagian', $department, "Bagian {$department->name} ditambahkan.");

        return redirect('/admin/departments')->with('success', 'Bagian berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|unique:departments,name,'.$id,
            'description' => 'nullable|string',
        ]);

        $department->update($validated);
        $this->logActivity($request, 'Ubah bagian', $department, "Bagian {$department->name} diperbarui.");

        return redirect('/admin/departments')->with('success', 'Bagian berhasil diperbarui.');
    }

    public function destroy(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        if ($department->machines()->exists()) {
            return back()->withErrors(['section' => 'Bagian masih dipakai oleh data mesin. Pindahkan atau hapus mesin terlebih dahulu.']);
        }

        $department->delete();
        $this->logActivity($request, 'Hapus bagian', $department, "Bagian {$department->name} dihapus.");

        return back()->with('success', 'Bagian berhasil dihapus.');
    }
}
