<?php

namespace App\Http\Controllers;

use App\Models\QcInspector;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

/**
 * Master PIC QC (petugas pemeriksa) beserta PIN verifikasi input.
 */
class AdminMemberQcController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $inspectors = QcInspector::with('user')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('employee_id', 'like', $term)
                        ->orWhere('name', 'like', $term);
                });
            })
            ->orderBy('employee_id')
            ->get();
        $editingInspector = $request->filled('edit') ? QcInspector::with('user')->find($request->integer('edit')) : null;
        $showForm = $request->boolean('create') || (bool) $editingInspector;

        return view('admin.members-qc', compact('inspectors', 'editingInspector', 'showForm'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|string|unique:qc_inspectors,employee_id',
            'name' => 'required|string',
            'pin' => 'required|digits:6',
            'status' => 'required|in:aktif,nonaktif',
        ], [
            'employee_id.unique' => 'NIK anggota QC sudah terdaftar.',
        ]);

        $inspector = QcInspector::create([
            'employee_id' => $validated['employee_id'],
            'name' => $validated['name'],
            'pin' => $validated['pin'],
            'status' => $validated['status'],
        ]);
        $this->logActivity($request, 'Tambah PIC QC', $inspector, "PIC QC {$inspector->employee_id} - {$inspector->name} ditambahkan.");

        return redirect('/admin/members-qc')->with('success', 'PIC QC berhasil ditambahkan');
    }

    public function update(Request $request, $id)
    {
        $inspector = QcInspector::with('user')->findOrFail($id);
        $validated = $request->validate([
            'employee_id' => 'required|string|unique:qc_inspectors,employee_id,'.$id,
            'name' => 'required|string',
            'pin' => 'nullable|digits:6',
            'status' => 'required|in:aktif,nonaktif',
        ], [
            'employee_id.unique' => 'NIK anggota QC sudah terdaftar.',
        ]);

        $inspector->update([
            'employee_id' => $validated['employee_id'],
            'name' => $validated['name'],
            'status' => $validated['status'],
        ]);
        if (! empty($validated['pin'])) {
            $inspector->update(['pin' => $validated['pin']]);
        }
        $this->logActivity($request, 'Ubah PIC QC', $inspector, "PIC QC {$inspector->employee_id} - {$inspector->name} diperbarui.");

        return redirect('/admin/members-qc')->with('success', 'PIC QC berhasil diperbarui');
    }

    public function destroy(Request $request, $id)
    {
        $inspector = QcInspector::with('user')->findOrFail($id);
        if ($inspector->inspections()->exists()) {
            $inspector->update(['status' => 'nonaktif']);
            $this->logActivity($request, 'Nonaktifkan PIC QC', $inspector, "PIC QC {$inspector->employee_id} dinonaktifkan karena sudah memiliki riwayat QC.");

            return back()->with('success', 'PIC QC sudah dipakai di riwayat QC, sehingga statusnya dinonaktifkan.');
        }

        $inspector->delete();
        $this->logActivity($request, 'Hapus PIC QC', $inspector, "PIC QC {$inspector->employee_id} dihapus.");

        return back()->with('success', 'PIC QC berhasil dihapus');
    }
}
