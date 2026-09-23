<?php

use App\Livewire\FindYourCar;
use App\Models\Vehicle;
use Livewire\Livewire;

/**
 * The verdict the finder gives each car, keyed by name.
 *
 * @return array<string, string>
 */
function finderVerdicts(array $answers): array
{
    $component = Livewire::test(FindYourCar::class);

    foreach ($answers as $question => $answer) {
        $component->set($question, $answer);
    }

    return collect($component->instance()->results())
        ->mapWithKeys(fn (array $result): array => [$result['vehicle']->name => $result['verdict']])
        ->sortKeys()
        ->all();
}

test('an AM licence rules out the L7e cars', function () {
    Vehicle::factory()->published()->create(['name' => 'Town Car']);
    Vehicle::factory()->published()->l7e()->create(['name' => 'Island Car']);

    expect(finderVerdicts(['licence' => 'am']))
        ->toBe(['Island Car' => 'unsuitable', 'Town Car' => 'match']);
});

test('main roads rule out the cars limited to 45 km/h', function () {
    Vehicle::factory()->published()->create(['name' => 'Town Car']);
    Vehicle::factory()->published()->l7e()->create(['name' => 'Island Car']);

    expect(finderVerdicts(['licence' => 'car', 'roads' => 'main']))
        ->toBe(['Island Car' => 'match', 'Town Car' => 'unsuitable']);
});

test('a car over budget or short on range is still worth a look', function () {
    Vehicle::factory()->published()->create(['name' => 'Short Range', 'range_km' => 60, 'price_cents' => 900000]);
    Vehicle::factory()->published()->create(['name' => 'Pricey', 'range_km' => 160, 'price_cents' => 1600000]);
    Vehicle::factory()->published()->create(['name' => 'Right One', 'range_km' => 160, 'price_cents' => 1400000]);

    expect(finderVerdicts(['distance' => 'medium', 'budget' => 'up_to_15k']))
        ->toBe(['Pricey' => 'consider', 'Right One' => 'match', 'Short Range' => 'consider']);
});

test('answers that no car satisfies explain why', function () {
    Vehicle::factory()->published()->create();
    Vehicle::factory()->published()->l7e()->create();

    Livewire::test(FindYourCar::class)
        ->set('licence', 'am')
        ->set('roads', 'main')
        ->assertSee('None of our cars fits every answer yet.');
});
