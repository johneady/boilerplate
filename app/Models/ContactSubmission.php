<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Database\Factories\ContactSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A message sent through the public contact form.
 *
 * Stored as well as emailed, and stored FIRST. The mailer's unsaved default is
 * 'log' (see SettingKey::MailMailer), so a fresh instance sends nothing anywhere
 * -- an email-only contact form would discard every message a visitor sent until
 * somebody noticed and configured SMTP. The row is the record; the notification
 * is a courtesy on top of it.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $subject
 * @property string $message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $handled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'subject', 'message', 'ip_address', 'user_agent'])]
class ContactSubmission extends Model
{
    /** @use HasFactory<ContactSubmissionFactory> */
    use Auditable, HasFactory;

    /**
     * The submitter's own details, kept out of the audit trail.
     *
     * These are not noise, they are somebody else's personal data, and they are
     * the one case on this model where the trail must record less than it can.
     * An audit entry outlives the row it describes and nobody -- administrators
     * included -- may delete one, so copying the message body here would mean
     * deleting a submission no longer erases the person who sent it: their name,
     * address and words would survive in a table every administrator can read
     * until retention expires. Exercising the existing delete permission would
     * quietly stop doing what it says.
     *
     * What is left is what the trail is actually for: that submission #12 was
     * marked handled, or deleted, by a named administrator at a given time.
     *
     * Declared here rather than in config('audit.never_record'), which is
     * global: `name` and `email` are ordinary auditable columns on User, and
     * blanking them everywhere would gut the trail that matters most.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        // updated_at rides along as noise on the handled-toggle updates this
        // model exists to record.
        return ['name', 'email', 'subject', 'message', 'ip_address', 'user_agent', 'updated_at'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    /**
     * Limit the query to submissions nobody has dealt with yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function unhandled(Builder $query): void
    {
        $query->whereNull('handled_at');
    }

    /**
     * Whether this submission has been dealt with.
     */
    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }
}
