<?php

namespace App\Http\Middleware;

use App\Auth\Permission;
use App\Settings\SettingKey;
use App\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve the "coming soon" holding page in place of the public site while the
 * ComingSoon setting is on.
 *
 * Applied to the public routes only (routes/web.php), never the web group: the
 * login page, the admin panel and the health checks must keep working behind
 * the holding page, or nobody could sign in to turn it off.
 *
 * Anyone who can reach the admin panel sees the real site instead, so the
 * owner builds and checks it on the live domain while visitors see the holding
 * page. Checked as a permission rather than is_admin, like every other access
 * rule here -- see .ai/rules/providers.md.
 *
 * Answered with 200 rather than 503: a new domain has no site for a search
 * engine to keep, and the page itself asks not to be indexed.
 */
class ShowComingSoonPage
{
    public function __construct(private Settings $settings) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->boolean(SettingKey::ComingSoon)) {
            return $next($request);
        }

        if ($request->user()?->hasPermission(Permission::AccessAdminPanel)) {
            return $next($request);
        }

        return response()->view('coming-soon', [
            'message' => $this->settings->string(SettingKey::ComingSoonMessage),
        ]);
    }
}
