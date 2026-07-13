<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspection;
use App\Models\QcInspector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_pages_render_for_each_role(): void
    {
        Carbon::setTestNow(now()->setTime(10, 0));
        $this->seed();

        $admin = User::where('role', 'admin')->firstOrFail();
        $supervisor = $this->makeUser('supervisor-smoke', 'Supervisor Smoke', 'supervisor');
        $qcUser = $this->makeUser('qc-smoke', 'QC Smoke', 'qc_inspector');

        $department = Department::create([
            'name' => 'Press Smoke',
            'description' => 'Smoke section',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'SMK-1',
            'name' => 'SMK-1',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = $this->makeUser('SMK-1', 'Operator SMK-1', 'akun_mesin');
        $machineUser->machines()->attach($machine->id);

        $product = Product::create([
            'product_code' => 'PART-SMK',
            'process_name' => 'Proses 1',
            'name' => 'Part Smoke',
            'customer_name' => 'Customer Smoke',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-SMK',
            'name' => 'Inspector Smoke',
            'pin' => '123456',
            'status' => 'aktif',
        ]);
        QcInspection::create([
            'inspection_code' => 'QC-SMOKE-001',
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'quantity_inspected' => 1,
            'quantity_passed' => 1,
            'quantity_failed' => 0,
            'pass_percentage' => 100,
            'status' => 'pass',
            'result_status' => 'RP',
            'inspection_date' => now(),
            'start_time' => '08:00',
            'end_time' => '08:10',
            'notes' => 'Smoke data',
        ]);

        $this->actingAsSession($admin)->get('/admin/dashboard')->assertOk();
        $this->actingAsSession($admin)->get('/admin/users')->assertOk();
        $this->actingAsSession($admin)->get('/admin/members-qc')->assertOk();
        $this->actingAsSession($admin)->get('/admin/products')->assertOk();
        $this->actingAsSession($admin)->get('/admin/ng-types')->assertOk();
        $this->actingAsSession($admin)->get('/admin/machines')->assertOk();
        $this->actingAsSession($admin)->get('/admin/departments')->assertOk();
        $this->actingAsSession($admin)->get('/admin/activity-logs')->assertOk();

        $this->actingAsSession($qcUser)->get('/qc/dashboard')
            ->assertOk()
            ->assertSee('Total Hasil Cek QC Hari Ini')
            ->assertSee('Waiting Belum Selesai');
        $this->actingAsSession($qcUser)->get('/qc/input')->assertOk();
        $this->actingAsSession($qcUser)->get('/qc/results?search=Proses%201')->assertOk()->assertSee('Hasil QC Semua Mesin')->assertSee('Proses 1');
        $this->actingAsSession($qcUser)->get('/qc/report?date_from='.now()->toDateString())->assertOk();
        // /report/pdf tanpa parameter menampilkan halaman pratinjau (HTML).
        $this->actingAsSession($qcUser)->get('/qc/report/pdf')
            ->assertOk()
            ->assertSee('LAPORAN HASIL PENGECEKAN QUALITY')
            ->assertSee('Unduh PDF');
        // /report/pdf?download=1 menghasilkan berkas PDF asli untuk diunduh.
        $this->actingAsSession($qcUser)->get('/qc/report/pdf?download=1')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->actingAsSession($qcUser)->get('/qc/report/excel')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAsSession($supervisor)->get('/supervisor/dashboard')->assertOk();
        $this->actingAsSession($supervisor)->get('/supervisor/dashboard?periode_ng=last_3_months')->assertOk()->assertSee('3 bulan terakhir');
        $this->actingAsSession($supervisor)->get('/supervisor/results')->assertOk()->assertSee('Hasil QC Semua Mesin');
        $this->actingAsSession($supervisor)->get('/supervisor/report')->assertOk();
        $this->actingAsSession($supervisor)->get('/supervisor/report/pdf')->assertOk();
        $this->actingAsSession($supervisor)->get('/supervisor/report/excel')->assertOk();

        $this->actingAsSession($machineUser)->get('/mesin/dashboard')->assertOk()->assertSee('SMK-1');
        $this->actingAsSession($machineUser)->get('/mesin/results')->assertOk()->assertSee('Part Smoke');
    }

    public function test_web_pages_include_security_headers(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAsSession($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    private function makeUser(string $username, string $name, string $role): User
    {
        return User::create([
            'username' => $username,
            'name' => $name,
            'email' => $username.'@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => $role,
            'status' => 'aktif',
        ]);
    }

    private function actingAsSession(User $user): self
    {
        return $this->withSession([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
        ]);
    }
}
