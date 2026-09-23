<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The header's language switcher: remembers the choice and returns the
 * visitor to the page they were reading.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, (array) config('voltiva.locales')), 404);

        $request->session()->put('locale', $locale);

        // Back to the referring page only when it is on this host, so the
        // switcher cannot be used as an open redirect. Compared by parsed
        // host rather than a string prefix, which "example.com.evil.test"
        // would pass.
        $previous = url()->previous();
        $isSameHost = parse_url($previous, PHP_URL_HOST) === $request->getHost();

        return redirect()->to($isSameHost ? $previous : route('home'));
    }
}
