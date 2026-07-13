<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspection;
use App\Models\QcInspector;
use App\Models\QcNgType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QcInspectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_qc_input_appears_on_machine_account(): void
    {
        Carbon::setTestNow(now()->setTime(10, 0));

        $this->seed();
        $burryTypeId = QcNgType::where('name', 'Burry/Burr')->value('id');
        $scratchTypeId = QcNgType::where('name', 'Scratch')->value('id');

        $department = Department::create([
            'name' => 'Press',
            'description' => 'Press shop',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => '150T-1',
            'name' => '150T-1',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => '150T-1',
            'name' => 'Operator 150T-1',
            'email' => '150t-1@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $product = Product::create([
            'product_code' => 'NP-150',
            'process_name' => 'Proses 1',
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

        $qcUser = User::create([
            'username' => 'qc-flow',
            'name' => 'QC Flow',
            'email' => 'qc-flow@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);
        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $this->post('/qc/input', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'NG',
            'ng_type_ids' => [$burryTypeId],
            'start_time' => '08:00',
            'end_time' => '08:10',
            'notes' => 'Burr area lubang',
        ])->assertSessionHasNoErrors()->assertRedirect('/qc/input');

        $this->assertDatabaseHas('qc_inspections', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'result_status' => 'NG',
            'ng_type' => 'Burry/Burr',
            'start_time' => '08:00',
            'end_time' => now()->format('H:i'),
            'notes' => 'Burr area lubang',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $qcUser->id,
            'activity' => 'Input hasil QC',
            'description' => 'Hasil QC NG untuk mesin 150T-1, part Bracket Engine, PIC Inspector A.',
        ]);

        $inspection = QcInspection::where('machine_id', $machine->id)->firstOrFail();

        $this->post('/qc/input', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'NG',
            'ng_type_ids' => [$burryTypeId],
            'start_time' => now()->addMinutes(10)->format('H:i'),
            'end_time' => '08:00',
            'notes' => 'Invalid time',
        ])->assertSessionHasErrors('end_time');

        $this->get("/qc/input/{$inspection->id}/edit")->assertNotFound();

        $this->put("/qc/input/{$inspection->id}", [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'RP',
            'start_time' => '08:00:00',
            'end_time' => '08:15:00',
            'notes' => 'OK setelah recheck',
        ])->assertNotFound();

        $this->assertDatabaseHas('qc_inspections', [
            'id' => $inspection->id,
            'result_status' => 'NG',
            'start_time' => '08:00',
            'end_time' => now()->format('H:i'),
            'notes' => 'Burr area lubang',
        ]);

        foreach (['SR', 'SC'] as $status) {
            $this->post('/qc/input', [
                'machine_id' => $machine->id,
                'product_id' => $product->id,
                'qc_inspector_id' => $inspector->id,
                'inspection_date' => now()->format('Y-m-d'),
                'pin' => '123456',
                'result_status' => $status,
                'start_time' => '09:00',
                'end_time' => '09:10',
                'notes' => $status.' masih lolos QC',
            ])->assertSessionHasNoErrors()->assertRedirect('/qc/input');

            $this->assertDatabaseHas('qc_inspections', [
                'machine_id' => $machine->id,
                'result_status' => $status,
                'status' => 'pass',
                'quantity_passed' => 1,
                'quantity_failed' => 0,
            ]);
        }

        $this->post('/qc/input', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'XX',
            'start_time' => '09:20',
            'end_time' => '09:30',
            'notes' => 'Status lama tidak boleh dipakai',
        ])->assertSessionHasErrors('result_status');

        $this->post('/qc/input', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'RP',
            'start_time' => '23:50',
            'end_time' => '00:10',
            'notes' => 'Cek lintas tengah malam',
        ])->assertSessionHasNoErrors()->assertRedirect('/qc/input');

        $this->assertDatabaseHas('qc_inspections', [
            'machine_id' => $machine->id,
            'result_status' => 'RP',
            'notes' => 'Cek lintas tengah malam',
        ]);

        $supervisorUser = User::create([
            'username' => 'supervisor-flow',
            'name' => 'Supervisor Flow',
            'email' => 'supervisor-flow@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'supervisor',
            'status' => 'aktif',
        ]);
        $this->withSession([
            'user_id' => $supervisorUser->id,
            'user_name' => $supervisorUser->name,
            'user_role' => 'supervisor',
        ]);

        $this->get('/supervisor/dashboard')
            ->assertOk()
            ->assertViewHas('totalToday', 4)
            ->assertViewHas('todayStatusCounts', fn ($counts) => $counts['RP'] === 1 && $counts['SR'] === 1 && $counts['SC'] === 1 && $counts['NG'] === 1)
            ->assertViewHas('ngRateToday', 25.0);

        $otherMachine = Machine::create([
            'machine_code' => '150T-2',
            'name' => '150T-2',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $otherMachineUser = User::create([
            'username' => '150T-2',
            'name' => 'Operator 150T-2',
            'email' => '150t-2@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $otherMachineUser->machines()->attach($otherMachine->id);

        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $this->post('/qc/input', [
            'machine_id' => $otherMachine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'NG',
            'ng_type_ids' => [$scratchTypeId],
            'start_time' => '10:00',
            'end_time' => '10:10',
            'notes' => 'AB dari mesin lain',
        ])->assertSessionHasNoErrors()->assertRedirect('/qc/input');

        $this->withSession([
            'user_id' => $machineUser->id,
            'user_name' => $machineUser->name,
            'user_role' => 'akun_mesin',
        ]);

        $this->get('/mesin/results')
            ->assertOk()
            ->assertSee('150T-1')
            ->assertSee('150T-2')
            ->assertSee('AB dari mesin lain');

        $this->get('/mesin/results?machine_id='.$otherMachine->id)
            ->assertOk()
            ->assertSee('AB dari mesin lain');

        $this->withSession([
            'user_id' => $supervisorUser->id,
            'user_name' => $supervisorUser->name,
            'user_role' => 'supervisor',
        ]);

        $this->get('/supervisor/dashboard')
            ->assertOk()
            ->assertViewHas('machineLabels', fn ($labels) => collect($labels)->contains($otherMachine->displayName()))
            ->assertViewHas('machineTotals', fn ($totals) => collect($totals)->contains(1))
            ->assertViewHas('ngTypeParetoLabels', fn ($labels) => collect($labels)->contains('Burry/Burr') && collect($labels)->contains('Scratch'))
            ->assertViewHas('ngTypeParetoTotals', fn ($totals) => collect($totals)->contains(1))
            ->assertViewHas('topPartRows', fn ($parts) => $parts->contains(fn ($item) => $item->part_name === $product->name && $item->ng_count >= 1))
            ->assertViewHas('trendData', fn ($trend) => (float) collect($trend)->last() > 0);

        $this->withSession([
            'user_id' => $machineUser->id,
            'user_name' => $machineUser->name,
            'user_role' => 'akun_mesin',
        ]);

        $this->get('/mesin/dashboard')
            ->assertOk()
            ->assertSee('150T-1')
            ->assertSee('Bracket Engine')
            ->assertSee('Jenis NG')
            ->assertSee('Burry/Burr')
            ->assertSee('BOLEH PRODUKSI')
            ->assertDontSee('BELUM DIBACA')
            ->assertDontSee('Tandai Sudah Dibaca')
            ->assertDontSee('Masuk');

        $this->get('/mesin/results')
            ->assertOk()
            ->assertSee('Hasil QC Semua Mesin')
            ->assertSee('Cek lintas tengah malam')
            ->assertSee('150T-2')
            ->assertSee('AB dari mesin lain');
    }

    public function test_pending_qc_requires_notes_and_can_be_resolved_to_final_status(): void
    {
        Carbon::setTestNow(now()->setTime(10, 0));

        $this->seed();

        $department = Department::create([
            'name' => 'Pending Section',
            'description' => 'Pending test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'PENDING-1',
            'name' => 'Pending Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => 'PENDING-1',
            'name' => 'Operator Pending',
            'email' => 'pending-machine@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $product = Product::create([
            'product_code' => 'PENDING-PRD',
            'process_name' => 'Proses Pending',
            'name' => 'Part Pending',
            'customer_name' => 'Customer Pending',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-PENDING',
            'name' => 'Inspector Pending',
            'pin' => '123456',
            'status' => 'aktif',
        ]);
        $qcUser = User::create([
            'username' => 'qc-pending',
            'name' => 'QC Pending',
            'email' => 'qc-pending@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $basePayload = [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'WAITING',
            'start_time' => '09:50',
            'end_time' => '10:00',
        ];

        $this->post('/qc/input', $basePayload)
            ->assertSessionHasErrors('notes');

        $this->post('/qc/input', [...$basePayload, 'notes' => 'Menunggu potongan part'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/qc/input');

        $pendingInspection = QcInspection::where('machine_id', $machine->id)->firstOrFail();

        $this->assertDatabaseHas('qc_inspections', [
            'id' => $pendingInspection->id,
            'result_status' => 'WAITING',
            'quantity_passed' => 0,
            'quantity_failed' => 1,
            'notes' => 'Menunggu potongan part',
        ]);

        $this->get('/qc/dashboard')
            ->assertOk()
            ->assertSee('Waiting Belum Selesai')
            ->assertSee('Menunggu potongan part')
            ->assertViewHas('totalToday', 0)
            ->assertViewHas('statusCounts', fn ($counts) => $counts['WAITING'] === 1);

        $this->withSession([
            'user_id' => $machineUser->id,
            'user_name' => $machineUser->name,
            'user_role' => 'akun_mesin',
        ]);

        $this->get('/mesin/dashboard')
            ->assertOk()
            ->assertSee('WAITING')
            ->assertSee('TUNGGU KEPUTUSAN QC')
            ->assertSee('Menunggu potongan part');

        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $this->get('/qc/input?resolve='.$pendingInspection->id)
            ->assertOk()
            ->assertSee('Selesaikan Waiting QC')
            ->assertSee('Simpan Keputusan Final');

        $this->put('/qc/input/'.$pendingInspection->id.'/resolve', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'RP',
            'start_time' => '09:50',
            'end_time' => '10:00',
            'notes' => 'OK setelah potongan part diterima',
        ])->assertSessionHasNoErrors()->assertRedirect('/qc/input');

        $this->assertDatabaseHas('qc_inspections', [
            'id' => $pendingInspection->id,
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'result_status' => 'RP',
            'quantity_passed' => 1,
            'quantity_failed' => 0,
            'status' => 'pass',
            'start_time' => '09:50',
            'end_time' => now()->format('H:i'),
            'notes' => 'OK setelah potongan part diterima',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $qcUser->id,
            'activity' => 'Input hasil QC',
            'description' => 'Hasil QC WAITING untuk mesin PENDING-1, part Part Pending, PIC Inspector Pending.',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $qcUser->id,
            'activity' => 'Selesaikan waiting QC',
            'description' => 'Waiting QC diselesaikan menjadi RP untuk mesin PENDING-1, part Part Pending, PIC Inspector Pending.',
        ]);

        $this->assertSame(1, QcInspection::where('machine_id', $machine->id)->count());
    }

    public function test_qc_input_rejects_inactive_master_data(): void
    {
        Carbon::setTestNow(now()->setTime(10, 0));
        $this->seed();

        $department = Department::create([
            'name' => 'Inactive Master Section',
            'description' => 'Section test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'INACTIVE-MCH',
            'name' => 'Inactive Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => 'INACTIVE-MCH',
            'name' => 'Inactive Machine Account',
            'email' => 'inactive-mch@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $inactiveProduct = Product::create([
            'product_code' => 'INACTIVE-PRD',
            'process_name' => 'Proses Nonaktif',
            'name' => 'Part Nonaktif',
            'customer_name' => 'Customer Test',
            'status' => 'nonaktif',
        ]);
        $activeProduct = Product::create([
            'product_code' => 'ACTIVE-PRD',
            'process_name' => 'Proses Aktif',
            'name' => 'Part Aktif',
            'customer_name' => 'Customer Test',
            'status' => 'aktif',
        ]);
        $inactiveInspector = QcInspector::create([
            'employee_id' => 'QC-INACTIVE',
            'name' => 'Inspector Nonaktif',
            'pin' => '123456',
            'status' => 'nonaktif',
        ]);
        $activeInspector = QcInspector::create([
            'employee_id' => 'QC-ACTIVE',
            'name' => 'Inspector Aktif',
            'pin' => '123456',
            'status' => 'aktif',
        ]);
        $qcUser = User::create([
            'username' => 'qc-inactive-master',
            'name' => 'QC Inactive Master',
            'email' => 'qc-inactive-master@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $basePayload = [
            'machine_id' => $machine->id,
            'product_id' => $activeProduct->id,
            'qc_inspector_id' => $activeInspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'RP',
            'start_time' => '08:00',
            'end_time' => '08:10',
            'notes' => 'Payload valid',
        ];

        $this->post('/qc/input', [...$basePayload, 'product_id' => $inactiveProduct->id])
            ->assertSessionHasErrors('product_id');

        $this->post('/qc/input', [...$basePayload, 'qc_inspector_id' => $inactiveInspector->id])
            ->assertSessionHasErrors('qc_inspector_id');

        $machine->update(['status' => 'nonaktif']);
        $this->post('/qc/input', $basePayload)
            ->assertSessionHasErrors('machine_id');
    }

    public function test_rp_input_allows_empty_notes(): void
    {
        Carbon::setTestNow(now()->setTime(10, 0));
        $this->seed();

        $department = Department::create([
            'name' => 'RP Notes Dept',
            'description' => 'RP notes test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'RP-NOTES-1',
            'name' => 'RP Notes Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => 'RP-NOTES-1',
            'name' => 'Operator RP Notes',
            'email' => 'rp-notes-machine@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $product = Product::create([
            'product_code' => 'RP-NOTES-PRD',
            'process_name' => 'Proses RP Notes',
            'name' => 'Part RP Notes',
            'customer_name' => 'Customer RP Notes',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-RP-NOTES',
            'name' => 'Inspector RP Notes',
            'pin' => '123456',
            'status' => 'aktif',
        ]);
        $qcUser = User::create([
            'username' => 'qc-rp-notes',
            'name' => 'QC RP Notes',
            'email' => 'qc-rp-notes@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $this->post('/qc/input', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'RP',
            'start_time' => '09:50',
            'end_time' => '10:00',
            'notes' => '',
        ])->assertSessionHasNoErrors()->assertRedirect('/qc/input');

        $this->assertDatabaseHas('qc_inspections', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'result_status' => 'RP',
            'notes' => null,
        ]);
    }

    public function test_ng_input_requires_ng_type(): void
    {
        Carbon::setTestNow(now()->setTime(10, 0));
        $this->seed();

        $department = Department::create([
            'name' => 'NG Type Dept',
            'description' => 'NG type test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'NG-TYPE-1',
            'name' => 'NG Type Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => 'NG-TYPE-1',
            'name' => 'Operator NG Type',
            'email' => 'ng-type-machine@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $product = Product::create([
            'product_code' => 'NG-TYPE-PRD',
            'process_name' => 'Proses NG Type',
            'name' => 'Part NG Type',
            'customer_name' => 'Customer NG Type',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-NG-TYPE',
            'name' => 'Inspector NG Type',
            'pin' => '123456',
            'status' => 'aktif',
        ]);
        $qcUser = User::create([
            'username' => 'qc-ng-type',
            'name' => 'QC NG Type',
            'email' => 'qc-ng-type@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $this->post('/qc/input', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'inspection_date' => now()->format('Y-m-d'),
            'pin' => '123456',
            'result_status' => 'NG',
            'start_time' => '09:50',
            'end_time' => '10:00',
            'notes' => '',
        ])->assertSessionHasErrors('ng_type_ids');

        $this->assertDatabaseMissing('qc_inspections', [
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'result_status' => 'NG',
        ]);
    }

    public function test_qc_results_date_filter_shows_latest_inspection_inside_selected_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-10 10:00:00'));
        $this->seed();

        $department = Department::create([
            'name' => 'Filter Section',
            'description' => 'Section filter',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'FILTER-1',
            'name' => 'Filter Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => 'FILTER-1',
            'name' => 'Filter Machine Account',
            'email' => 'filter-1@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $product = Product::create([
            'product_code' => 'FILTER-PRD',
            'process_name' => 'Proses Filter',
            'name' => 'Part Filter',
            'customer_name' => 'Customer Filter',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-FILTER',
            'name' => 'Inspector Filter',
            'pin' => '123456',
            'status' => 'aktif',
        ]);

        QcInspection::create([
            'inspection_code' => 'QC-FILTER-OLD',
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'quantity_inspected' => 1,
            'quantity_passed' => 1,
            'quantity_failed' => 0,
            'pass_percentage' => 100,
            'status' => 'pass',
            'result_status' => 'RP',
            'inspection_date' => Carbon::parse('2026-06-01 09:00:00'),
            'start_time' => '09:00',
            'end_time' => '09:10',
            'notes' => 'Hasil dalam rentang',
        ]);
        QcInspection::create([
            'inspection_code' => 'QC-FILTER-NEW',
            'machine_id' => $machine->id,
            'product_id' => $product->id,
            'qc_inspector_id' => $inspector->id,
            'quantity_inspected' => 1,
            'quantity_passed' => 0,
            'quantity_failed' => 1,
            'pass_percentage' => 0,
            'status' => 'fail',
            'result_status' => 'NG',
            'inspection_date' => Carbon::parse('2026-06-03 09:00:00'),
            'start_time' => '09:00',
            'end_time' => '09:10',
            'notes' => 'Hasil di luar rentang',
        ]);

        $qcUser = User::create([
            'username' => 'qc-filter',
            'name' => 'QC Filter',
            'email' => 'qc-filter@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);
        $this->withSession([
            'user_id' => $qcUser->id,
            'user_name' => $qcUser->name,
            'user_role' => 'qc_inspector',
        ]);

        $this->get('/qc/results?date_from=2026-06-01&date_to=2026-06-01')
            ->assertOk()
            ->assertSee('Hasil dalam rentang')
            ->assertDontSee('Hasil di luar rentang');
    }

    public function test_operational_date_range_uses_qc_cutoff_without_losing_boundary_records(): void
    {
        $this->seed();

        $department = Department::create([
            'name' => 'Cutoff Section',
            'description' => 'Section cutoff',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'CUTOFF-1',
            'name' => 'Cutoff Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $product = Product::create([
            'product_code' => 'CUTOFF-PRD',
            'process_name' => 'Proses Cutoff',
            'name' => 'Part Cutoff',
            'customer_name' => 'Customer Cutoff',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-CUTOFF',
            'name' => 'Inspector Cutoff',
            'pin' => '123456',
            'status' => 'aktif',
        ]);

        foreach ([
            ['QC-CUTOFF-BEFORE-START', '2026-06-01 06:50:00', '06:50'],
            ['QC-CUTOFF-START', '2026-06-01 07:00:00', '07:00'],
            ['QC-CUTOFF-NEXT-MORNING', '2026-06-02 06:59:00', '06:59'],
            ['QC-CUTOFF-AFTER-END', '2026-06-02 07:00:00', '07:00'],
        ] as [$code, $inspectionDate, $startTime]) {
            QcInspection::create([
                'inspection_code' => $code,
                'machine_id' => $machine->id,
                'product_id' => $product->id,
                'qc_inspector_id' => $inspector->id,
                'quantity_inspected' => 1,
                'quantity_passed' => 1,
                'quantity_failed' => 0,
                'pass_percentage' => 100,
                'status' => 'pass',
                'result_status' => 'RP',
                'inspection_date' => Carbon::parse($inspectionDate),
                'start_time' => $startTime,
                'end_time' => '07:10',
                'notes' => $code,
            ]);
        }

        $codes = QcInspection::forOperationalDateRange('2026-06-01', '2026-06-01')
            ->orderBy('inspection_date')
            ->pluck('inspection_code')
            ->all();

        $this->assertSame([
            'QC-CUTOFF-START',
            'QC-CUTOFF-NEXT-MORNING',
        ], $codes);
    }
}
