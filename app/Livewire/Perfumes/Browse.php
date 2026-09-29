<?php

namespace App\Livewire\Perfumes;

use App\Models\Brand;
use App\Models\Perfume;
use App\Perfumes\Enums\Audience;
use App\Perfumes\Enums\Family;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public catalogue: search by name, house, perfumer or note, narrowed by
 * family, audience and house. Every filter lives in the query string, so a
 * filtered view can be bookmarked and shared.
 */
class Browse extends Component
{
    use WithPagination;

    /**
     * The sort orders offered, keyed by query-string value.
     *
     * @var list<string>
     */
    public const array SORTS = ['popular', 'newest', 'oldest', 'name'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $family = '';

    #[Url(except: '')]
    public string $gender = '';

    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: 'popular')]
    public string $sort = 'popular';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'family', 'gender', 'brand', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'family', 'gender', 'brand', 'sort');
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Perfume>
     */
    #[Computed]
    public function perfumes(): LengthAwarePaginator
    {
        $family = Family::tryFrom($this->family);
        $gender = Audience::tryFrom($this->gender);

        $query = Perfume::query()
            ->with('brand')
            ->withCount('followers')
            ->when(trim($this->search) !== '', fn ($query) => $query->search($this->search))
            ->when($family, fn ($query) => $query->where('family', $family))
            ->when($gender, fn ($query) => $query->where('gender', $gender))
            ->when($this->brand !== '', fn ($query) => $query->whereHas('brand', fn ($brand) => $brand->where('slug', $this->brand)));

        match ($this->sort) {
            'newest' => $query->orderByDesc('release_year'),
            'oldest' => $query->orderBy('release_year'),
            'name' => $query->orderBy('name'),
            default => $query->orderByDesc('followers_count'),
        };

        return $query->orderBy('name')->paginate(12);
    }

    public function render(): View
    {
        return view('livewire.perfumes.browse', [
            'families' => Family::cases(),
            'audiences' => Audience::cases(),
            'brands' => Brand::query()->orderBy('name')->get(['name', 'slug']),
        ])->layout('layouts::public', [
            'title' => __('Browse perfumes'),
            'description' => __('Search the open perfume database by name, house, perfumer or note.'),
        ]);
    }
}
