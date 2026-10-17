<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the role catalog contains the eight primary school roles', function () {
    expect(Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name')->all())
        ->toBe([
            'Accountant',
            'Librarian',
            'Parent',
            'Principal',
            'Receptionist',
            'Student',
            'Super Admin',
            'Teacher',
        ]);
});

test('the seeder can run repeatedly without duplicating roles or permissions', function () {
    $roleCount = Role::query()->count();
    $permissionCount = Permission::query()->count();

    $this->seed(RolePermissionSeeder::class);

    expect(Role::query()->count())->toBe($roleCount)
        ->and(Permission::query()->count())->toBe($permissionCount);
});

test('the seeder restores the Super Admin all-permissions baseline', function () {
    $superAdmin = Role::findByName('Super Admin', 'web');
    $superAdmin->revokePermissionTo('students.view');

    $this->seed(RolePermissionSeeder::class);

    expect($superAdmin->fresh()->hasPermissionTo('students.view'))->toBeTrue();
});

test('super admins bypass individual permission checks', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');

    expect($superAdmin->can('students.delete'))->toBeTrue()
        ->and($superAdmin->can('a.future.permission'))->toBeTrue();
});

test('teachers have teaching permissions but not financial permissions', function () {
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    expect($teacher->can('attendance.mark'))->toBeTrue()
        ->and($teacher->can('homework.grade'))->toBeTrue()
        ->and($teacher->can('fees.view'))->toBeFalse()
        ->and($teacher->can('payroll.process'))->toBeFalse();
});

test('librarians and receptionists receive only their assigned module permissions', function () {
    $librarian = User::factory()->create();
    $librarian->assignRole('Librarian');

    $receptionist = User::factory()->create();
    $receptionist->assignRole('Receptionist');

    expect($librarian->can('library.issue'))->toBeTrue()
        ->and($librarian->can('fees.record_payment'))->toBeFalse()
        ->and($receptionist->can('admissions.enrol'))->toBeTrue()
        ->and($receptionist->can('payroll.process'))->toBeFalse();
});

test('only users with the required permission can open access management pages', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant)->get('/users')->assertForbidden();
    $this->actingAs($accountant)->get('/roles')->assertForbidden();
    $this->actingAs($accountant)->get('/permissions')->assertForbidden();

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->get('/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users/Index'));

    $this->actingAs($admin)->get('/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Roles/Index')
            ->has('roles', 8)
            ->where('auth.user.roles', ['Super Admin']));

    $this->actingAs($admin)->get('/permissions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Permissions/Index'));
});

test('super admins can create user accounts and assign a primary role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->post('/users', [
        'name' => 'Taylor Teacher',
        'email' => 'taylor.teacher@example.test',
        'password' => 'SecureTeacherPassword42',
        'password_confirmation' => 'SecureTeacherPassword42',
        'role' => 'Teacher',
    ])->assertRedirect();

    $teacher = User::query()->where('email', 'taylor.teacher@example.test')->firstOrFail();

    expect(Hash::check('SecureTeacherPassword42', $teacher->password))->toBeTrue()
        ->and($teacher->hasRole('Teacher'))->toBeTrue()
        ->and($teacher->hasRole('Super Admin'))->toBeFalse();
});

test('super admins can change another account role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($admin)->patch("/users/{$teacher->id}", [
        'name' => $teacher->name,
        'email' => $teacher->email,
        'role' => 'Parent',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect();

    expect($teacher->fresh()->hasRole('Parent'))->toBeTrue()
        ->and($teacher->fresh()->hasRole('Teacher'))->toBeFalse();
});

test('a super admin cannot remove their own final administrator access', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->patch("/users/{$admin->id}", [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'Teacher',
        'password' => '',
        'password_confirmation' => '',
    ])->assertForbidden();

    expect($admin->fresh()->hasRole('Super Admin'))->toBeTrue();
});

test('role permission changes replace the assigned permission set', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $teacherRole = Role::findByName('Teacher', 'web');
    $permission = Permission::findByName('reports.view', 'web');

    $this->actingAs($admin)->put("/roles/{$teacherRole->id}/permissions", [
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    expect($teacherRole->fresh()->hasPermissionTo('reports.view'))->toBeTrue()
        ->and($teacherRole->fresh()->hasPermissionTo('attendance.mark'))->toBeFalse();
});

test('normal database seeding never provisions a default administrator', function () {
    $this->artisan('db:seed')->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

test('the first super admin is provisioned only through the hidden password prompt', function () {
    $this->artisan('school:provision-super-admin', [
        'name' => 'Initial Administrator',
        'email' => 'initial.admin@example.test',
    ])
        ->expectsQuestion('Password', 'StrongAdministratorPassword42')
        ->expectsQuestion('Confirm password', 'StrongAdministratorPassword42')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'initial.admin@example.test')->firstOrFail();

    expect(Hash::check('StrongAdministratorPassword42', $admin->password))->toBeTrue()
        ->and($admin->hasRole('Super Admin'))->toBeTrue();
});

test('the bootstrap command refuses to create a second super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->artisan('school:provision-super-admin', [
        'name' => 'Second Administrator',
        'email' => 'second.admin@example.test',
    ])->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'second.admin@example.test')->exists())->toBeFalse();
});

test('super admins can create and edit custom roles without changing the primary role set', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $permission = Permission::findByName('attendance.mark', 'web');

    $this->actingAs($admin)->post('/roles', [
        'name' => 'Attendance Officer',
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    $role = Role::findByName('Attendance Officer', 'web');
    expect($role->hasPermissionTo('attendance.mark'))->toBeTrue()
        ->and(Role::query()->whereIn('name', [
            'Accountant', 'Librarian', 'Parent', 'Principal',
            'Receptionist', 'Student', 'Super Admin', 'Teacher',
        ])->count())->toBe(8);

    $this->actingAs($admin)->put("/roles/{$role->id}/permissions", [
        'permission_ids' => [],
    ])->assertRedirect();

    expect($role->fresh()->hasPermissionTo('attendance.mark'))->toBeFalse();
});

test('primary roles cannot be deleted and custom roles with assigned users are retained', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $teacherRole = Role::findByName('Teacher', 'web');
    $customRole = Role::create(['name' => 'Exam Assistant', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($customRole);

    $this->actingAs($admin)->delete('/roles/'.$teacherRole->id)->assertForbidden();
    $this->actingAs($admin)->delete('/roles/'.$customRole->id)->assertSessionHasErrors('role');

    expect(Role::query()->whereKey($customRole->id)->exists())->toBeTrue();

    $user->syncRoles(['Teacher']);
    $this->actingAs($admin)->delete('/roles/'.$customRole->id)->assertRedirect();

    expect(Role::query()->whereKey($customRole->id)->exists())->toBeFalse();
});

test('custom permissions can only be deleted after they are removed from all roles', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->post('/permissions', [
        'name' => 'attendance.export_custom',
    ])->assertRedirect();

    $permission = Permission::findByName('attendance.export_custom', 'web');
    $teacherRole = Role::findByName('Teacher', 'web');
    $teacherRole->givePermissionTo($permission);

    $this->actingAs($admin)->delete('/permissions/'.$permission->id)
        ->assertSessionHasErrors('permission');
    expect(Permission::query()->whereKey($permission->id)->exists())->toBeTrue();

    $teacherRole->revokePermissionTo($permission);
    $this->actingAs($admin)->delete('/permissions/'.$permission->id)->assertRedirect();

    expect(Permission::query()->whereKey($permission->id)->exists())->toBeFalse();
});

test('custom permission keys must use lowercase dotted names', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->post('/permissions', [
        'name' => 'Attendance Export',
    ])->assertSessionHasErrors('name');

    expect(Permission::query()->where('name', 'Attendance Export')->exists())->toBeFalse();
});

test('system permission keys cannot be deleted from the catalog', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $permission = Permission::findByName('users.view', 'web');

    $this->actingAs($admin)->delete('/permissions/'.$permission->id)->assertForbidden();

    expect(Permission::query()->whereKey($permission->id)->exists())->toBeTrue();
});

test('super admins cannot alter the protected super admin role permission set', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $superAdminRole = Role::findByName('Super Admin', 'web');

    $this->actingAs($admin)->put("/roles/{$superAdminRole->id}/permissions", [
        'permission_ids' => [],
    ])->assertForbidden();

    expect($superAdminRole->fresh()->hasPermissionTo('users.view'))->toBeTrue();
});

test('user deletion deactivates the account and retains its historical row', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $teacher = User::factory()->create();
    $teacher->assignRole('Teacher');

    $this->actingAs($admin)->delete("/users/{$teacher->id}")->assertRedirect();

    $this->assertSoftDeleted('users', ['id' => $teacher->id]);
    expect(User::query()->whereKey($teacher->id)->exists())->toBeFalse();
});

test('a super admin cannot deactivate their own account', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)->delete("/users/{$admin->id}")->assertForbidden();

    $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
});

test('a role with user deletion permission cannot deactivate the final super admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $operator = User::factory()->create();
    $operatorRole = Role::create(['name' => 'Account Manager', 'guard_name' => 'web']);
    $operatorRole->givePermissionTo(Permission::findByName('users.delete', 'web'));
    $operator->assignRole($operatorRole);

    $this->actingAs($operator)->delete("/users/{$admin->id}")->assertForbidden();

    $this->assertNotSoftDeleted('users', ['id' => $admin->id]);
});

test('delegated user managers cannot create promote or remove super admins', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $operator = User::factory()->create();
    $operatorRole = Role::create(['name' => 'Account Manager', 'guard_name' => 'web']);
    $operatorRole->givePermissionTo(Permission::query()
        ->whereIn('name', ['users.create', 'users.update', 'users.delete'])
        ->where('guard_name', 'web')
        ->get());
    $operator->assignRole($operatorRole);
    $target = User::factory()->create();
    $target->assignRole('Teacher');

    $this->actingAs($operator)->post('/users', [
        'name' => 'Unauthorized Administrator',
        'email' => 'unauthorized.admin@example.test',
        'password' => 'StrongUnauthorizedPassword42',
        'password_confirmation' => 'StrongUnauthorizedPassword42',
        'role' => 'Super Admin',
    ])->assertForbidden();

    $this->actingAs($operator)->patch("/users/{$target->id}", [
        'name' => $target->name,
        'email' => $target->email,
        'role' => 'Super Admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertForbidden();

    $this->actingAs($operator)->patch("/users/{$admin->id}", [
        'name' => 'Changed Administrator',
        'email' => $admin->email,
        'role' => 'Super Admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertForbidden();

    $this->actingAs($operator)->delete("/users/{$admin->id}")->assertForbidden();

    expect(User::query()->where('email', 'unauthorized.admin@example.test')->exists())->toBeFalse()
        ->and($target->fresh()->hasRole('Teacher'))->toBeTrue()
        ->and($admin->fresh()->name)->not->toBe('Changed Administrator');
});

test('accountants cannot create or delete users roles or permissions', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');
    $customRole = Role::create(['name' => 'Temporary Role', 'guard_name' => 'web']);
    $customPermission = Permission::create(['name' => 'custom.view', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->actingAs($accountant)->delete("/users/{$user->id}")->assertForbidden();
    $this->actingAs($accountant)->post('/roles', ['name' => 'Unauthorized Role'])->assertForbidden();
    $this->actingAs($accountant)->delete('/roles/'.$customRole->id)->assertForbidden();
    $this->actingAs($accountant)->post('/permissions', ['name' => 'custom.create'])->assertForbidden();
    $this->actingAs($accountant)->delete('/permissions/'.$customPermission->id)->assertForbidden();
});
