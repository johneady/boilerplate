<?php

namespace App\Concerns;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;

/**
 * Lets a settings component render with or without the Flux page chrome.
 *
 * The same component backs two pages: /settings/* in the Flux layout, which
 * needs the settings heading and its navlist, and the Account cluster inside
 * the Filament panel, which supplies its own heading and sub-navigation. A
 * component mounted with `bare` skips the chrome and renders only the shared
 * partial, so neither copy duplicates the other's markup.
 */
trait RendersSettingsChrome
{
    /**
     * Whether to render without the Flux settings chrome.
     *
     * Locked: the chrome a page renders is decided by the host page, never by
     * a value round-tripped through the browser.
     */
    #[Locked]
    public bool $bare = false;

    public function render(): View
    {
        return view($this->bare ? $this->bareView() : $this->chromedView());
    }

    /**
     * The view rendering this component's content alone.
     *
     * Returned as a literal by each component rather than built from a name
     * here: a concatenated view name is an unverifiable string, so a typo
     * would surface as a runtime "view not found" instead of a static error.
     *
     * @return view-string
     */
    abstract protected function bareView(): string;

    /**
     * The view wrapping that same content in the Flux settings chrome.
     *
     * @return view-string
     */
    abstract protected function chromedView(): string;
}
