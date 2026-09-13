<?php

namespace App\Models;

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
    use HasFactory;

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
