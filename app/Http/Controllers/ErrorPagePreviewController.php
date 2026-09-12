<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * Renders the custom error pages for design review.
 *
 * A 500 or a 503 cannot be looked at by causing one -- APP_DEBUG shows the
 * Whoops trace instead of the template, and `artisan down` takes the whole
 * site with it -- so the pages are otherwise only reachable by deliberately
 * breaking the application. This route renders them directly.
 *
 * Registered only outside production (routes/web.php gates it on
 * DevLoginAccounts::enabled(), the same switch as the quick logins), so the
 * route simply does not exist on a deployed instance rather than relying on
 * a runtime guard inside the controller.
 */
class ErrorPagePreviewController extends Controller
{
    /**
     * The status codes this application ships a template for.
     *
     * @var list<int>
     */
    public const array STATUSES = [403, 404, 419, 429, 500, 503];

    /**
     * Show one error page, or the index when no status is given.
     */
    public function __invoke(?int $status = null): Response
    {
        if ($status === null) {
            return response()->view('errors.preview-index', [
                'statuses' => self::STATUSES,
            ]);
        }

        abort_unless(in_array($status, self::STATUSES, true), 404);

        // The `errors::` namespace is registered lazily by the exception
        // handler, so it does not exist on an ordinary request like this one.
        (new RegisterErrorViewPaths)();

        // Rendered with a 200 rather than the status it depicts: this is a
        // preview, and returning a real 404 or 503 here would have the browser
        // (and any crawler) treat the preview itself as broken.
        return response()->view(View::exists('errors::'.$status) ? 'errors::'.$status : 'errors::500');
    }
}
