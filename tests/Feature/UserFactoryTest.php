<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('hashes passwords and authenticates users', function () {
    $user = User::factory()->create(['password_hash' => 'secret']);

    expect(Hash::check('secret', $user->password_hash))->toBeTrue()
        ->and($user->password_hash)->not->toBe('secret');

    $this->actingAs($user);

    expect(auth()->id())->toBe($user->id);
});

it('creates users without a local password for external identities', function () {
    $user = User::factory()->withoutPassword()->create();

    expect($user->password_hash)->toBeNull()
        ->and($user->auth_subject)->toStartWith('external-');
});
