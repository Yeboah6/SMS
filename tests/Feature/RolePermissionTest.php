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
            ->has('roles', 8));

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
