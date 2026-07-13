<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

/**
 * Menampilkan & memfilter riwayat aktivitas pengguna (audit trail).
 */
class AdminActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $categoryOptions = [
            'users' => 'Pengguna',
            'machines' => 'Mesin',
            'master_data' => 'Master Data',
            'input_qc' => 'Input QC',
            'pending_qc' => 'Waiting QC',
        ];
        $categoryActivities = [
            'users' => ['Tambah pengguna', 'Ubah pengguna', 'Hapus pengguna', 'Atur ulang kata sandi pengguna'],
            'machines' => ['Tambah mesin', 'Ubah mesin', 'Buat akun mesin', 'Atur ulang kata sandi akun mesin', 'Nonaktifkan mesin', 'Hapus mesin'],
            'master_data' => ['Tambah bagian', 'Ubah bagian', 'Hapus bagian', 'Tambah part', 'Ubah part', 'Nonaktifkan part', 'Hapus part', 'Tambah PIC QC', 'Ubah PIC QC', 'Nonaktifkan PIC QC', 'Hapus PIC QC', 'Tambah jenis NG', 'Ubah jenis NG', 'Nonaktifkan jenis NG', 'Hapus jenis NG'],
            'input_qc' => ['Input hasil QC'],
            'pending_qc' => ['Selesaikan waiting QC'],
        ];
        $selectedCategory = array_key_exists((string) $request->query('category'), $categoryOptions)
            ? (string) $request->query('category')
            : null;

        $logs = ActivityLog::with('user')
            ->when($selectedCategory, fn ($query) => $query->whereIn('activity', $categoryActivities[$selectedCategory]))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->search.'%';
                $query->where(function ($subQuery) use ($term) {
                    $subQuery
                        ->where('activity', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $term)->orWhere('username', 'like', $term));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.activity-logs', [
            'logs' => $logs,
            'filters' => [
                'search' => $request->query('search'),
                'category' => $selectedCategory,
            ],
            'categoryOptions' => $categoryOptions,
        ]);
    }
}
