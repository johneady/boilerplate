<?php

namespace App\Http\Controllers;

use App\Http\Responses\ResolvesLoginRedirect;
use Spatie\LoginLink\Http\Controllers\LoginLinkController as BaseLoginLinkController;
use Spatie\LoginLink\Http\Requests\LoginLinkRequest;

class LoginLinkController extends BaseLoginLinkController
{
    use ResolvesLoginRedirect;

    /**
     * Send dev login links to the same destination as a normal login: the
     * intended URL if one was captured, otherwise the admin panel for admins
     * and the dashboard for everyone else.
     */
    protected function getRedirectUrl(LoginLinkRequest $request): string
    {
        if ($request->redirect_url) {
            return $request->redirect_url;
        }

        if ($routeName = config('login-link.redirect_route_name')) {
            return route($routeName);
        }

        return redirect()
            ->intended($this->defaultRedirect(auth($request->guard)->user()))
            ->getTargetUrl();
    }
}
