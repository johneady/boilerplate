<?php

test('choosing a language translates the site', function (string $locale, string $heading) {
    $this->get(route('locale', $locale));

    $this->get('/')
        ->assertSee('<html lang="'.$locale.'"', false)
        ->assertSee($heading);
})->with([
    ['es', 'Movilidad eléctrica, fácil'],
    ['de', 'Elektromobilität, ganz einfach'],
    ['fr', 'La mobilité électrique en toute simplicité'],
]);

test('every language translates every string the english site uses', function (string $locale) {
    $english = json_decode((string) file_get_contents(lang_path('en.json')), true);
    $translated = json_decode((string) file_get_contents(lang_path($locale.'.json')), true);
    $voltivaKeys = array_keys(json_decode((string) file_get_contents(lang_path('es.json')), true));

    // Every key the Voltiva site added is present in each language.
    expect(array_diff($voltivaKeys, array_keys($translated)))->toBe([])
        ->and(array_diff($voltivaKeys, array_keys($english)))->toBe([]);
})->with(['de', 'fr']);

test('the switcher returns the visitor to the page they were on', function () {
    $this->from(route('cars.index'))
        ->get(route('locale', 'de'))
        ->assertRedirect(route('cars.index'));
});

test('the switcher will not redirect to another site', function () {
    $this->from('https://evil.example/phish')
        ->get(route('locale', 'de'))
        ->assertRedirect(route('home'));
});

test('an unsupported language is not found', function () {
    $this->get('/lang/xx')->assertNotFound();
});

test('a tampered session language is ignored', function () {
    $this->withSession(['locale' => '../../etc'])
        ->get('/')
        ->assertSee('<html lang="en"', false);
});
