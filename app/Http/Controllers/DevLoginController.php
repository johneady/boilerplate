<?php

namespace App\Http\Controllers;

use App\Auth\DevLoginAccounts;
use App\Http\Responses\ResolvesLoginRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DevLoginController extends Controller
{
    use ResolvesLoginRedirect;

    public function __construct(private DevLoginAccounts $accounts) {}

    /**
     * Log in one of the configured dev accounts without a password.
     *
     * The request names a position in the server-side account list rather than
     * an email address, so it cannot reach an account the application did not
     * offer. An unrecognised position 404s, as does any request at all outside
     * the allowed environments, where the route is never registered.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'integer', 'min:0'],
        ]);

        $user = $this->accounts->find((int) $validated['account']);

        if ($user === null) {
            throw new NotFoundHttpException;
        }

        Auth::login($user);

        $request->session()->regenerate();

        return $this->loginRedirect($user);
    }
}
