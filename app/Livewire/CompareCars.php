<?php

namespace App\Livewire;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The comparison page.
 *
 * Built entirely from the columns the product pages already read -- nothing
 * here is typed twice, so a price or a range changed in the admin panel is
 * changed in the comparison at the same moment.
 */
class CompareCars extends Component
{
    /**
     * How many cars fit side by side before the table stops being readable.
     */
    public const int MAX_SELECTED = 4;

    /**
     * The compared cars' slugs, kept in the URL so a comparison can be shared.
     *
     * @var list<string>
     */
    #[Url(as: 'cars')]
    public array $selected = [];

    /**
     * Start with the first few cars of the range when the URL names none.
     */
    public function mount(): void
    {
        /** @var list<string> $known */
        $known = $this->allVehicles()->pluck('slug')->all();

        // Only slugs of published cars survive from the URL, in the order given.
        $selected = [];

        foreach ($this->selected as $slug) {
            if (in_array($slug, $known, true) && ! in_array($slug, $selected, true)) {
                $selected[] = $slug;
            }
        }

        $this->selected = $selected === [] ? array_slice($known, 0, self::MAX_SELECTED) : array_slice($selected, 0, self::MAX_SELECTED);
    }

    /**
     * Add or remove a car. Adding past the limit drops the oldest choice
     * rather than refusing, so a click always does something visible.
     */
    public function toggle(string $slug): void
    {
        if (! $this->allVehicles()->contains('slug', $slug)) {
            return;
        }

        if (in_array($slug, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$slug]));

            return;
        }

        $this->selected[] = $slug;

        if (count($this->selected) > self::MAX_SELECTED) {
            array_shift($this->selected);
        }
    }

    /**
     * Every published car, for the picker.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function allVehicles(): Collection
    {
        return Vehicle::query()->published()->ordered()->get();
    }

    /**
     * The chosen cars, in range order.
     *
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return $this->allVehicles()->whereIn('slug', $this->selected)->values();
    }

    /**
     * Every equipment item any compared car lists, so each row can show a
     * tick or a dash per car.
     *
     * @return list<string>
     */
    #[Computed]
    public function equipment(): array
    {
        return array_values($this->vehicles()
            ->flatMap(fn (Vehicle $vehicle): array => $vehicle->equipment ?? [])
            ->unique()
            ->sort()
            ->all());
    }

    public function render(): View
    {
        return view('livewire.compare-cars')
            ->layout('layouts::public', [
                'title' => __('Compare electric cars'),
                'description' => __('Compare Voltiva electric cars side by side: price, range, speed, battery, charging, seats and equipment.'),
            ]);
    }
}
