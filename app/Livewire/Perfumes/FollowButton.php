<?php

namespace App\Livewire\Perfumes;

use App\Models\Perfume;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Follow or unfollow a perfume. Guests are sent to sign up first, since a
 * follow belongs to an account.
 */
class FollowButton extends Component
{
    public Perfume $perfume;

    public int $followers = 0;

    public bool $following = false;

    public function mount(Perfume $perfume): void
    {
        $this->perfume = $perfume;
        $this->followers = $perfume->followers()->count();
        $this->following = auth()->user()?->follows($perfume) ?? false;
    }

    public function toggle(): void
    {
        $user = auth()->user();

        if ($user === null) {
            $this->redirectRoute(Route::has('register') ? 'register' : 'login');

            return;
        }

        if ($user->follows($this->perfume)) {
            $user->followedPerfumes()->detach($this->perfume->id);
        } else {
            $user->followedPerfumes()->attach($this->perfume->id, ['created_at' => now()]);
        }

        $this->following = $user->follows($this->perfume);
        $this->followers = $this->perfume->followers()->count();
    }

    public function render(): View
    {
        return view('livewire.perfumes.follow-button');
    }
}
