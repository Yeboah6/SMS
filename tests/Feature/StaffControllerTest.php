<?php

use App\Models\Staff;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function staffAdministrator(): User
{
    $user = User::factory()->create();
    $user->assignRole('Super Admin');

    return $user;
}

it('provides separate staff index, create, show, and edit pages with account roles', function () {
    $admin = staffAdministrator();
    $teacherAccount = User::factory()->create();
    $teacherAccount->assignRole('Teacher');

    $this->actingAs($admin)->get(route('staff.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Create')
            ->where('userAccounts.0.id', $teacherAccount->id)
            ->where('userAccounts.0.roles', ['Teacher']));

    $this->actingAs($admin)->post(route('staff.store'), [
        'user_id' => $teacherAccount->id,
        'staff_number' => 'STAFF-CRUD-001',
        'is_teacher' => true,
        'name' => 'Taylor Teacher',
        'status' => 'active',
    ])->assertRedirect(route('staff.show', Staff::query()->first()));

    $staff = Staff::query()->firstOrFail();

    $this->actingAs($admin)->get(route('staff.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Index')
            ->where('staff.data.0.userRoles', ['Teacher']));

    $this->actingAs($admin)->get(route('staff.show', $staff))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Show')
            ->where('member.user.roles', ['Teacher']));

    $this->actingAs($admin)->get(route('staff.edit', $staff))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Staff/Edit')
            ->where('member.userId', $teacherAccount->id)
            ->where('userAccounts.0.id', $teacherAccount->id));
});

it('links a non-teaching profile only to a non-teaching staff account', function () {
    $admin = staffAdministrator();
    $teacherAccount = User::factory()->create();
    $teacherAccount->assignRole('Teacher');
    $receptionistAccount = User::factory()->create();
    $receptionistAccount->assignRole('Receptionist');

    $this->actingAs($admin)->post(route('staff.store'), [
        'user_id' => $teacherAccount->id,
        'staff_number' => 'STAFF-CRUD-002',
        'is_teacher' => false,
        'name' => 'Wrong Role Link',
        'status' => 'active',
    ])->assertSessionHasErrors([
        'user_id' => 'Link a user account with a non-teaching staff role.',
    ]);

    expect(Staff::query()->count())->toBe(0);

    $this->actingAs($admin)->post(route('staff.store'), [
        'user_id' => $receptionistAccount->id,
        'staff_number' => 'STAFF-CRUD-003',
        'is_teacher' => false,
        'name' => 'Riley Receptionist',
        'status' => 'active',
    ])->assertRedirect();

    expect(Staff::query()->sole()->user_id)->toBe($receptionistAccount->id)
        ->and($receptionistAccount->fresh()->hasRole('Receptionist'))->toBeTrue();
});

it('forbids users without staff permissions from opening staff create and edit pages', function () {
    $student = User::factory()->create();
    $student->assignRole('Student');
    $staff = Staff::query()->create([
        'staff_number' => 'STAFF-CRUD-004',
        'name' => 'Restricted Staff',
        'is_teacher' => false,
        'status' => 'active',
    ]);

    $this->actingAs($student)->get(route('staff.create'))->assertForbidden();
    $this->actingAs($student)->get(route('staff.edit', $staff))->assertForbidden();
});
