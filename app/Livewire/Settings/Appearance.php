<?php

namespace App\Livewire\Settings;

use App\Concerns\RendersSettingsChrome;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Appearance settings')]
class Appearance extends Component
{
    use RendersSettingsChrome;

    /**
     * @return view-string
     */
    protected function bareView(): string
    {
        return 'partials.settings.appearance';
    }

    /**
     * @return view-string
     */
    protected function chromedView(): string
    {
        return 'livewire.settings.appearance';
    }
}
