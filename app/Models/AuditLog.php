<?php

namespace App\Models;

use App\Audit\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One entry in the trail of who changed what.
 *
 * Append-only by construction: the model has no $fillable (entries are written
 * by App\Audit\AuditLogger through forceFill, never mass-assigned from a
 * request), and UPDATED_AT is null so the table carries a creation time alone.
 * An audit entry that can be edited is worth very little.
 *
 * The actor is stored twice on purpose -- as a foreign key AND as a name and
 * email copied at write time. The key goes null when the account is deleted,
 * which is precisely when the trail is being read.
 *
 * @property int $id
 * @property AuditEvent $event
 * @property int|null $user_id
 * @property string|null $user_name
 * @property string|null $user_email
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable|null $created_at
 */
class AuditLog extends Model
{
    /**
     * Entries are stamped once and never touched again.
     *
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'context' => 'array',
        ];
    }

    /**
     * The account that performed the action, while it still exists.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit the query to entries recorded for the given event.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function ofEvent(Builder $query, AuditEvent $event): void
    {
        $query->where('event', $event->value);
    }

    /**
     * Limit the query to entries describing the given record.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forRecord(Builder $query, Model $record): void
    {
        $query
            ->where('auditable_type', $record->getMorphClass())
            ->where('auditable_id', $record->getKey());
    }

    /**
     * How the actor is described when the entry is displayed.
     *
     * Falls back through the copied name, the copied email and finally a
     * generic label, because all three are nullable: a console command or a
     * queued job acts with no authenticated user at all.
     */
    public function actorName(): string
    {
        return $this->user_name
            ?? $this->user_email
            ?? 'System';
    }

    /**
     * A human-readable name for the record this entry describes.
     *
     * Derived from the stored class name rather than by resolving the record,
     * which is routinely gone -- a delete event's subject no longer exists,
     * and that is the entry most worth reading.
     */
    public function subjectLabel(): ?string
    {
        if ($this->auditable_type === null) {
            return null;
        }

        return Str::headline(class_basename($this->auditable_type));
    }

    /**
     * The attribute names this entry records a change to.
     *
     * @return list<string>
     */
    public function changedAttributes(): array
    {
        return array_values(array_unique([
            ...array_keys($this->new_values ?? []),
            ...array_keys($this->old_values ?? []),
        ]));
    }
}
