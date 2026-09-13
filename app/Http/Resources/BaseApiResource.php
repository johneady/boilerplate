<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The base every API resource extends.
 *
 * Its job is to pin the response envelope. Laravel's default wrapper is
 * already "data", but it is a global, mutable setting -- one call to
 * JsonResource::withoutWrapping() anywhere, or a package that makes it,
 * silently reshapes every response this API has ever returned. Declaring it
 * here means the envelope is a property of these resources rather than of
 * global state, and a consumer parsing `data` keeps working.
 *
 * Subclasses implement toArray() as usual; nothing else is prescribed.
 */
abstract class BaseApiResource extends JsonResource
{
    /**
     * The key the resource's payload is nested under.
     *
     * @var string|null
     */
    public static $wrap = 'data';
}
