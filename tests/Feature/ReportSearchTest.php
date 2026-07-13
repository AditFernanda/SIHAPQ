<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspection;
use App\Models\QcInspector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeInspection(string $resultStatus = 'NG'): QcInspection
    {
        $department = Department::create(['name' => 'Press', 'description' => '-', 'status' => 'aktif']);
        $machine = Machine::create([
            'machine_code' => 'PRS-01',
            'name' => 'Press 01',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $product = Product::create([
            'product_code' => 'NP-777',
            'process_name' => 'Stamping',
            'name' => 'Bracket Engine',
            'customer_name' => 'Toyota',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-001',
            'name' => 'Inspector A',
            'pin' => '123456',
            'status' => 'aktif',
        ]);

        return QcInspection::create([
            'inspection_code' => 'QC-TEST-0001',
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'quantity_inspected' => 1,
            'quantity_passed' => 0,
            'quantity_failed' => 1,
            'pass_percentage' => 0,
            'status' => 'fail',
            'result_status' => $resultStatus,
            'notes' => 'PenandaUnikBracketQC777',
            'inspection_date' => now()->setTime(10, 0),
            'start_time' => '08:00',
            'end_time' => '08:10',
        ]);
    }

    private function loginAsQc(): void
    {
        $qcUser = User::create([
            'username' => 'qc-report',
            'name' => 'QC Report',
            'email' => 'qc-report@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);
        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);
    }

    public function test_search_index_is_populated_on_save(): void
    {
        $inspection = $this->makeInspection();

        $this->assertNotEmpty($inspection->search_index);
        $this->assertStringContainsString('Bracket Engine', $inspection->search_index);
        $this->assertStringContainsString('Toyota', $inspection->search_index);
        $this->assertStringContainsString('PRS-01', $inspection->search_index);
        $this->assertStringContainsString('Press', $inspection->search_index);
    }

    public function test_report_search_finds_matching_inspection(): void
    {
        $inspection = $this->makeInspection();
        $this->loginAsQc();

        // Catatan inspeksi (kolom hasil) hanya muncul bila baris lolos pencarian.
        $this->get('/qc/report?search=Toyota')
            ->assertOk()
            ->assertSee($inspection->notes);
    }

    public function test_report_search_finds_matching_department(): void
    {
        $inspection = $this->makeInspection();
        $this->loginAsQc();

        $this->get('/qc/report?search=Press')
            ->assertOk()
            ->assertSee($inspection->notes);
    }

    public function test_report_search_excludes_non_matching_inspection(): void
    {
        $inspection = $this->makeInspection();
        $this->loginAsQc();

        $this->get('/qc/report?search=ZZZTIDAKADA')
            ->assertOk()
            ->assertDontSee($inspection->notes);
    }
}
