<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;

it('assigns a uuid external id on creation', function (): void {
    $user = User::factory()->create();

    expect($user->external_id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
});

it('routes by external id, never by the primary key', function (): void {
    expect(User::factory()->create()->getRouteKeyName())->toBe('external_id');
});

it('does not overwrite an explicitly provided external id', function (): void {
    $id = '01930000-0000-7000-8000-000000000000';

    expect(User::factory()->create(['external_id' => $id])->external_id)->toBe($id);
});

it('does not allow mass assigning the external id', function (): void {
    // external_id is absent from #[Fillable], and Model::shouldBeStrict() upgrades a
    // discarded attribute from silent to fatal — so request input can never reach it.
    expect(fn (): User => new User([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'external_id' => 'attacker-supplied',
    ]))->toThrow(MassAssignmentException::class);
});
