<?php

use App\Models\AuditLog;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentActivityService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('model updates record changed field names without storing field values', function () {
    $actor = User::factory()->create();
    $this->actingAs($actor);
    $user = User::factory()->create();

    $user->update([
        'email' => 'updated.member@example.test',
        'password' => 'SensitiveNewPassword42',
    ]);

    $log = AuditLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $user->id)
        ->where('action', 'updated')
        ->firstOrFail();

    expect($log->changed_fields)->toBe(['email', 'password'])
        ->and($log->actor_id)->toBe($actor->id)
        ->and($log->context)->toBeNull()
        ->and($log->toJson())->not->toContain('updated.member@example.test')
        ->and($log->toJson())->not->toContain('SensitiveNewPassword42');
});

test('principals can view student activity without seeing private activity context', function () {
    $principal = User::factory()->create();
    $principal->assignRole('Principal');
    $student = Student::query()->create([
        'first_name' => 'Avery',
        'last_name' => 'Student',
        'date_of_birth' => '2012-04-15',
        'gender' => 'female',
        'admission_date' => '2025-09-01',
    ]);

    app(StudentActivityService::class)->record(
        $student,
        $principal,
        'note.created',
        'Student note created',
        details: ['private_note' => 'confidential activity detail'],
    );

    $this->actingAs($principal)
        ->get('/audit-logs?action=note.created')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AuditLogs/Index')
            ->where('filters.action', 'note.created')
            ->has('logs.data', 1)
            ->where('logs.data.0.actorName', $principal->name)
            ->where('logs.data.0.subjectName', 'Avery Student'))
        ->assertDontSee('confidential activity detail');
});

test('users without audit permission cannot view the audit log', function () {
    $accountant = User::factory()->create();
    $accountant->assignRole('Accountant');

    $this->actingAs($accountant)->get('/audit-logs')->assertForbidden();
});

test('super admins can view the audit log', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    $this->actingAs($admin)
        ->get('/audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('AuditLogs/Index'));
});

test('role permission changes are included in the audit log', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $teacherRole = Role::findByName('Teacher', 'web');
    $permission = Permission::findByName('reports.view', 'web');

    $this->actingAs($admin)->put("/roles/{$teacherRole->id}/permissions", [
        'permission_ids' => [$permission->id],
    ])->assertRedirect();

    $log = AuditLog::query()
        ->where('subject_type', Role::class)
        ->where('subject_id', $teacherRole->id)
        ->where('action', 'permissions.updated')
        ->firstOrFail();

    expect($log->changed_fields)->toBe(['permissions'])
        ->and($log->actor_id)->toBe($admin->id);
});

test('user role changes are included in the audit log', function () {
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

    $log = AuditLog::query()
        ->where('subject_type', User::class)
        ->where('subject_id', $teacher->id)
        ->where('action', 'roles.updated')
        ->firstOrFail();

    expect($log->changed_fields)->toBe(['roles'])
        ->and($log->actor_id)->toBe($admin->id);
});

test('existing student activity is backfilled when the audit table is created', function () {
    $actor = User::factory()->create();
    $student = Student::query()->create([
        'first_name' => 'Jordan',
        'last_name' => 'Learner',
        'date_of_birth' => '2011-06-20',
        'gender' => 'male',
        'admission_date' => '2025-09-01',
    ]);
    $activity = $student->activities()->create([
        'actor_id' => $actor->id,
        'event_type' => 'student.promoted',
        'summary' => 'Student promoted',
        'details' => ['class_id' => 2],
        'occurred_at' => '2025-06-20 09:00:00',
    ]);

    Schema::dropIfExists('audit_logs');
    $migration = require database_path('migrations/2026_11_01_044556_create_audit_logs_table.php');
    $migration->up();

    $log = AuditLog::query()
        ->where('source_type', 'student_activity')
        ->where('source_id', $activity->id)
        ->firstOrFail();

    expect($log->actor_name)->toBe($actor->name)
        ->and($log->subject_name)->toBe('Jordan Learner')
        ->and($log->action)->toBe('student.promoted')
        ->and($log->context)->toBe(['class_id' => 2]);
});
