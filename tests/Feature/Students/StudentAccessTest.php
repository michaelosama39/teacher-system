<?php

use App\Enums\Permission;
use App\Models\Student;
use App\Models\User;

it('lets staff with students.view list and filter students', function () {
    Student::factory()->count(2)->create();
    Student::factory()->pending()->create(['name' => 'Waiting']);

    $headers = authHeaders(assistant([Permission::StudentsView]));

    $this->getJson('/api/students', $headers)->assertOk()->assertJsonCount(3, 'data');
    $this->getJson('/api/students?status=pending', $headers)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Waiting');
});

it('searches students by parent phone in any format', function () {
    $parent = User::factory()->parent()->create(['phone' => '01202222222']);
    $child = Student::factory()->create();
    Student::factory()->create();
    $parent->children()->attach($child);

    $this->getJson('/api/students?search=%2B201202222222', authHeaders(teacher()))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $child->id);
});

it('forbids an assistant without students.view', function () {
    $headers = authHeaders(assistant([Permission::AttendanceScan]));

    $this->getJson('/api/students', $headers)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->getJson('/api/students/'.Student::factory()->create()->id, $headers)->assertForbidden();
});

it('lets a student see only their own record', function () {
    $own = Student::factory()->withAccount()->create();
    $other = Student::factory()->create();
    $headers = authHeaders($own->user);

    $this->getJson("/api/students/{$own->id}", $headers)->assertOk()->assertJsonPath('data.id', $own->id);
    $this->getJson("/api/students/{$other->id}", $headers)->assertForbidden();
    $this->getJson('/api/students', $headers)->assertForbidden();
});

it('lets a parent see only their own children', function () {
    $parent = User::factory()->parent()->create();
    [$first, $second] = Student::factory()->count(2)->create();
    $stranger = Student::factory()->create();
    $parent->children()->attach([$first->id, $second->id]);
    $headers = authHeaders($parent);

    $this->getJson("/api/students/{$first->id}", $headers)->assertOk();
    $this->getJson("/api/students/{$second->id}", $headers)->assertOk();
    $this->getJson("/api/students/{$stranger->id}", $headers)->assertForbidden();
});

it('hides staff notes from parents', function () {
    $parent = User::factory()->parent()->create();
    $child = Student::factory()->create(['notes' => 'internal note']);
    $parent->children()->attach($child);

    $this->getJson("/api/students/{$child->id}", authHeaders($parent))->assertOk()->assertJsonMissingPath('data.notes');
});

it('updates a student and regenerates the qr token with students.edit', function () {
    $student = Student::factory()->create();
    $oldToken = $student->qr_token;
    $headers = authHeaders(assistant([Permission::StudentsEdit]));

    $this->patchJson("/api/students/{$student->id}", ['status' => 'suspended', 'name' => 'New Name'], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended')
        ->assertJsonPath('data.name', 'New Name');

    $token = $this->postJson("/api/students/{$student->id}/regenerate-qr", [], $headers)
        ->assertOk()
        ->json('data.qr_token');

    expect($token)->not->toBe($oldToken)->toHaveLength(48);
});

it('forbids updating without students.edit', function () {
    $student = Student::factory()->create();

    $this->patchJson("/api/students/{$student->id}", ['name' => 'X'], authHeaders(assistant([Permission::StudentsView])))
        ->assertForbidden();
});
