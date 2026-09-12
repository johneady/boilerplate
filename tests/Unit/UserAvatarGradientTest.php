<?php

use App\Models\User;

test('the avatar gradient is deterministic across calls', function () {
    $user = new User(['name' => 'Ada Lovelace']);

    expect($user->avatarGradient())->toBe($user->avatarGradient())
        ->and($user->avatarGradientStyle())->toBe($user->avatarGradientStyle());
});

test('the avatar gradient always returns a pair from the palette', function () {
    $palette = (new ReflectionClass(User::class))->getConstant('AVATAR_GRADIENTS');

    foreach (['Ada Lovelace', 'Grace Hopper', 'Alan Turing', 'Katherine Johnson', 'Edsger Dijkstra'] as $name) {
        $gradient = (new User(['name' => $name]))->avatarGradient();

        expect($gradient)->toBeArray()
            ->toHaveKeys(['from', 'to'])
            ->and($gradient)->toBeIn($palette)
            ->and($gradient['from'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($gradient['to'])->toMatch('/^#[0-9a-f]{6}$/')
            ->and($gradient['from'])->not->toBe($gradient['to']);
    }
});

test('unsaved users fall back to seeding the gradient from the name', function () {
    // Unsaved models have no key yet, so the name stands in until the first
    // save: two unsaved users with the same name share a gradient.
    $first = new User(['name' => 'Ada Lovelace']);
    $second = new User(['name' => 'Ada Lovelace']);

    expect($first->avatarGradient())->toBe($second->avatarGradient());
});

test('the gradient style string embeds both stops of the gradient', function () {
    $user = new User(['name' => 'Ada Lovelace']);
    $gradient = $user->avatarGradient();

    expect($user->avatarGradientStyle())
        ->toBe(sprintf('background-image:linear-gradient(135deg,%s,%s)', $gradient['from'], $gradient['to']));
});
