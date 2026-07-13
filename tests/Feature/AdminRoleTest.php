<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Machine;
use App\Models\Product;
use App\Models\QcInspector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AdminRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $admin = User::where('role', 'admin')->first();
        $this->withSession([
            'user_id' => $admin->id,
            'user_name' => $admin->name,
            'user_role' => 'admin',
        ]);
    }

    public function test_admin_pages_render(): void
    {
        $department = Department::create([
            'name' => 'Press Test',
            'description' => 'Departemen test',
            'status' => 'aktif',
        ]);
        Machine::create([
            'machine_code' => 'MCH-TST',
            'name' => 'Mesin Test',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);

        foreach ([
            '/admin/dashboard',
            '/admin/users',
            '/admin/users?create=1',
            '/admin/members-qc',
            '/admin/products',
            '/admin/machines',
            '/admin/machines?create=1',
            '/admin/departments',
            '/admin/activity-logs',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_supervisor_can_open_all_machine_qc_results(): void
    {
        $supervisor = User::firstOrCreate(
            ['username' => 'supervisor-test'],
            [
                'name' => 'Supervisor Test',
                'email' => 'supervisor-test@pt-rmi.local',
                'password' => Hash::make('password123'),
                'role' => 'supervisor',
                'status' => 'aktif',
            ]
        );
        $this->withSession([
            'user_id' => $supervisor->id,
            'user_name' => $supervisor->name,
            'user_role' => 'supervisor',
        ]);

        $this->get('/supervisor/results')
            ->assertOk()
            ->assertSee('Hasil QC Semua Mesin');
    }

    public function test_admin_can_create_core_master_data(): void
    {
        $this->post('/admin/departments', [
            'name' => 'Assembly',
            'description' => 'Departemen Assembly',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $department = Department::where('name', 'Assembly')->firstOrFail();

        $this->post('/admin/machines', [
            'machine_code' => 'ASM-1',
            'name' => 'Assembly 1',
            'department_id' => $department->id,
            'status' => 'aktif',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/machines');

        $this->post('/admin/products', [
            'product_code' => 'PRD-TST',
            'process_name' => 'Proses Utama',
            'name' => 'Part Test',
            'customer_name' => 'Customer Test',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('machines', ['machine_code' => 'ASM-1']);
        $this->assertDatabaseHas('users', [
            'username' => 'ASM-1',
            'role' => 'akun_mesin',
        ]);
        $this->assertDatabaseHas('products', ['product_code' => 'PRD-TST']);
        $this->assertDatabaseHas('products', ['customer_name' => 'Customer Test']);
    }

    public function test_required_form_fields_show_indonesian_validation_message(): void
    {
        $this->from('/admin/products?create=1')
            ->post('/admin/products', [
                'product_code' => 'PRD-KOSONG',
                'name' => 'Part Tanpa Proses',
                'customer_name' => 'Customer Test',
            ])
            ->assertRedirect('/admin/products?create=1')
            ->assertSessionHasErrors([
                'process_name' => 'Kolom proses wajib diisi.',
            ]);
    }

    public function test_admin_can_update_product_status(): void
    {
        $product = Product::create([
            'product_code' => 'STAT-PRD',
            'process_name' => 'Proses Status',
            'name' => 'Part Status',
            'customer_name' => 'Customer Status',
            'status' => 'aktif',
        ]);

        $this->put('/admin/products/'.$product->id, [
            'product_code' => 'STAT-PRD',
            'process_name' => 'Proses Status',
            'name' => 'Part Status',
            'customer_name' => 'Customer Status',
            'status' => 'nonaktif',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'status' => 'nonaktif',
        ]);
    }

    public function test_machine_account_is_managed_from_master_machine(): void
    {
        $department = Department::create([
            'name' => 'Line Test',
            'description' => 'Departemen test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'LINE-1',
            'name' => 'Line 1',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);

        $this->post('/admin/users', [
            'username' => 'operator1',
            'name' => 'Operator Mesin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'akun_mesin',
            'status' => 'aktif',
            'machine_id' => $machine->id,
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'operator1']);
        $this->assertDatabaseMissing('user_machines', ['machine_id' => $machine->id]);
    }

    public function test_machine_name_is_required(): void
    {
        $department = Department::create([
            'name' => 'Name Required Dept',
            'description' => 'Departemen test',
            'status' => 'aktif',
        ]);

        $this->from('/admin/machines?create=1')
            ->post('/admin/machines', [
                'machine_code' => 'NO-NAME-1',
                'name' => '',
                'department_id' => $department->id,
                'status' => 'aktif',
            ])
            ->assertRedirect('/admin/machines?create=1')
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('machines', ['machine_code' => 'NO-NAME-1']);
        $this->assertDatabaseMissing('users', ['username' => 'NO-NAME-1']);
    }

    public function test_admin_user_search_accepts_display_role_labels(): void
    {
        User::create([
            'username' => 'quality-role-search',
            'name' => 'User Label Search',
            'email' => 'quality-role-search@pt-rmi.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->get('/admin/users?search=Quality+Control')
            ->assertOk()
            ->assertSee('quality-role-search')
            ->assertSee('QC Inspector')
            ->assertDontSee('Supervisor');

        $this->get('/admin/users?search=PIC+QC')
            ->assertOk()
            ->assertDontSee('quality-role-search');

        $this->get('/admin/users?search=inspector')
            ->assertOk()
            ->assertDontSee('quality-role-search');
    }

    public function test_member_qc_is_not_login_user(): void
    {
        $beforeUsers = User::count();

        $this->post('/admin/members-qc', [
            'employee_id' => 'QC-TEST',
            'name' => 'Member Verifikasi',
            'pin' => '123456',
            'status' => 'aktif',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/members-qc');

        $this->assertSame($beforeUsers, User::count());
        $this->assertDatabaseHas('qc_inspectors', [
            'employee_id' => 'QC-TEST',
            'name' => 'Member Verifikasi',
            'status' => 'aktif',
        ]);
        $this->assertTrue(QcInspector::where('employee_id', 'QC-TEST')->firstOrFail()->verifyPin('123456'));
        $this->assertDatabaseHas('activity_logs', [
            'activity' => 'Tambah PIC QC',
        ]);
    }

    public function test_admin_can_open_edit_forms(): void
    {
        $department = Department::create([
            'name' => 'Edit Dept',
            'description' => 'Departemen test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => 'EDIT-1',
            'name' => 'Edit Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $product = Product::create([
            'product_code' => 'EDIT-PRD',
            'process_name' => 'Proses Utama',
            'name' => 'Edit Product',
            'customer_name' => 'Edit Customer',
            'status' => 'aktif',
        ]);
        $inspector = QcInspector::create([
            'employee_id' => 'QC-EDIT',
            'name' => 'QC Edit',
            'pin' => '654321',
            'status' => 'aktif',
        ]);
        $loginUser = User::create([
            'username' => 'login_edit',
            'name' => 'Login Edit',
            'email' => 'login-edit@test.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->get('/admin/products?edit='.$product->id)->assertOk();
        $this->get('/admin/members-qc?edit='.$inspector->id)->assertOk();
        $this->get('/admin/machines?edit='.$machine->id)->assertOk();
        $this->get('/admin/users?edit='.$loginUser->id)->assertOk();
        $this->get('/admin/departments?edit='.$department->id)->assertOk();
    }

    public function test_admin_cannot_delete_last_admin_user(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->delete('/admin/users/'.$admin->id)
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_cannot_edit_last_active_admin_out_of_admin_access(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->put('/admin/users/'.$admin->id, [
            'username' => $admin->username,
            'name' => $admin->name,
            'role' => 'supervisor',
            'status' => 'aktif',
        ])->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        $this->put('/admin/users/'.$admin->id, [
            'username' => $admin->username,
            'name' => $admin->name,
            'role' => 'admin',
            'status' => 'nonaktif',
        ])->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'role' => 'admin',
            'status' => 'aktif',
        ]);
    }

    public function test_admin_cannot_delete_section_used_by_machine(): void
    {
        $department = Department::create([
            'name' => 'Used Section',
            'description' => 'Dipakai mesin',
            'status' => 'aktif',
        ]);
        Machine::create([
            'machine_code' => 'USED-1',
            'name' => 'Used Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);

        $this->delete('/admin/departments/'.$department->id)
            ->assertSessionHasErrors('section');

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_cannot_open_operational_role_pages(): void
    {
        $this->get('/qc/dashboard')
            ->assertRedirect('/admin/dashboard');

        $this->get('/supervisor/dashboard')
            ->assertRedirect('/admin/dashboard');

        $this->get('/mesin/dashboard')
            ->assertRedirect('/admin/dashboard');
    }

    public function test_inactive_session_user_is_forced_to_login(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $admin->update(['status' => 'nonaktif']);

        $this->get('/admin/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');
    }

    public function test_modal_shows_validation_error_inside_overlay(): void
    {
        $user = User::create([
            'username' => 'reset_err_user',
            'name' => 'Reset Err User',
            'email' => 'reset-err-user@test.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $errors = new ViewErrorBag;
        $errors->put('default', new MessageBag([
            'password' => 'Kata sandi minimal 8 karakter.',
        ]));

        $this->withSession(['errors' => $errors])
            ->get('/admin/users?reset='.$user->id)
            ->assertOk()
            ->assertSee('Kata sandi minimal 8 karakter.');
    }

    public function test_user_reset_modal_escapes_subtitle_data(): void
    {
        $user = User::create([
            'username' => 'evil_user',
            'name' => '<script>alert("xss")</script>',
            'email' => 'evil-user@test.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->get('/admin/users?reset='.$user->id)
            ->assertOk()
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', false);
    }

    public function test_machine_reset_modal_escapes_subtitle_data(): void
    {
        $department = Department::create([
            'name' => 'Security Dept',
            'description' => 'Departemen test',
            'status' => 'aktif',
        ]);
        $machine = Machine::create([
            'machine_code' => '<script>alert("machine")</script>',
            'name' => 'Security Machine',
            'department_id' => $department->id,
            'status' => 'aktif',
        ]);
        $machineUser = User::create([
            'username' => 'machine_xss_user',
            'name' => 'Machine XSS User',
            'email' => 'machine-xss-user@test.local',
            'password' => Hash::make('password123'),
            'role' => 'akun_mesin',
            'status' => 'aktif',
        ]);
        $machineUser->machines()->attach($machine->id);

        $this->get('/admin/machines?reset='.$machine->id)
            ->assertOk()
            ->assertDontSee('<script>alert("machine")</script>', false)
            ->assertSee('&lt;script&gt;alert(&quot;machine&quot;)&lt;/script&gt;', false);
    }

    public function test_admin_can_reset_user_password_and_view_activity_log(): void
    {
        $user = User::create([
            'username' => 'reset_user',
            'name' => 'Reset User',
            'email' => 'reset-user@test.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->get('/admin/users?reset='.$user->id)
            ->assertOk()
            ->assertSee('Atur ulang kata sandi');

        $this->put('/admin/users/'.$user->id.'/reset-password', [
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/users');

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'activity' => 'Atur ulang kata sandi pengguna',
            'entity_id' => (string) $user->id,
        ]);

        $this->get('/admin/activity-logs')
            ->assertOk()
            ->assertSee('Atur ulang kata sandi pengguna');

        $this->get('/admin/activity-logs?category=users')
            ->assertOk()
            ->assertSee('Atur ulang kata sandi pengguna');

        $this->get('/admin/activity-logs?category=machines')
            ->assertOk()
            ->assertDontSee('Atur ulang kata sandi pengguna');
    }

    public function test_admin_cannot_reset_own_password_from_user_management(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $oldPassword = $admin->password;

        $this->put('/admin/users/'.$admin->id.'/reset-password', [
            'password' => 'unsafe-reset',
            'password_confirmation' => 'unsafe-reset',
        ])->assertSessionHasErrors('user');

        $this->assertSame($oldPassword, $admin->fresh()->password);
    }

    public function test_edit_user_does_not_change_password(): void
    {
        $user = User::create([
            'username' => 'edit_password_user',
            'name' => 'Edit Password User',
            'email' => 'edit-password-user@test.local',
            'password' => Hash::make('password123'),
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ]);

        $this->put('/admin/users/'.$user->id, [
            'username' => 'edit_password_user',
            'name' => 'Nama Diperbarui',
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/users');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
        $this->assertSame('Nama Diperbarui', $user->fresh()->name);
    }

    public function test_user_can_change_own_password(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->get('/password')
            ->assertOk()
            ->assertSee('Ganti kata sandi');

        $this->put('/password', [
            'current_password' => config('qc.admin_default_password'),
            'password' => 'password-baru1',
            'password_confirmation' => 'password-baru1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('password-baru1', $admin->fresh()->password));
    }

    public function test_deleted_username_can_be_reused(): void
    {
        $this->post('/admin/users', [
            'username' => 'budi',
            'name' => 'Budi Lama',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ])->assertSessionHasNoErrors();

        $user = User::where('username', 'budi')->firstOrFail();

        $this->delete('/admin/users/'.$user->id)->assertSessionHasNoErrors();

        // Nama & username bebas dipakai lagi setelah pengguna dihapus.
        $this->post('/admin/users', [
            'username' => 'budi',
            'name' => 'Budi Baru',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'qc_inspector',
            'status' => 'aktif',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/users');

        $this->assertDatabaseHas('users', ['username' => 'budi', 'name' => 'Budi Baru']);
        // Baris lama tetap tersimpan sebagai riwayat (soft delete), username dilepas.
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
