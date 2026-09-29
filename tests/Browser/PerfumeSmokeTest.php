<?php

use App\Models\Perfume;
use App\Models\User;
use App\Perfumes\Enums\Family;

/*
 * The main public flow with JavaScript running: search the catalogue live,
 * open a perfume, and follow it. The Livewire round-trips are what a feature
 * test cannot see break.
 */

test('a member can search for a perfume and follow it', function () {
    Perfume::factory()->create(['name' => 'Shalimar', 'slug' => 'guerlain-shalimar', 'family' => Family::Amber, 'base_notes' => ['Vanilla']]);
    Perfume::factory()->create(['name' => 'Philosykos', 'family' => Family::Woody, 'base_notes' => ['Cedar']]);

    $this->actingAs(User::factory()->create());

    visit('/perfumes')
        ->assertSee('Philosykos')
        ->fill('[wire\\:model\\.live\\.debounce\\.300ms="search"]', 'vanilla')
        ->assertDontSee('Philosykos')
        ->click('Shalimar')
        ->assertPathIs('/perfumes/guerlain-shalimar')
        ->click('@follow-button')
        ->assertSee('Following')
        ->assertSee('1 follower')
        ->assertNoJavaScriptErrors();
});
