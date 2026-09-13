<?php

namespace App\Concerns;

use App\Audit\AuditEvent;
use App\Audit\AuditLogger;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records create, update and delete events for the model that uses it.
 *
 * Opt-in per model, never global. A model added later may hold tokens, secrets
 * or somebody else's personal data, and auditing everything by default means
 * that model is captured into an administrator-readable table from the moment
 * it exists -- with nobody having decided that it should be. Adding the trait
 * is that decision, made once, in the model.
 *
 * A model may narrow what is captured with $auditExclude. Whatever it declares,
 * config('audit.never_record') is applied on top in AuditLogger, so forgetting
 * an exclusion cannot leak a password.
 *
 * See .ai/rules/audit.md.
 */
trait Auditable
{
    /**
     * Register the model event listeners that write the trail.
     *
     * Eloquent calls boot{TraitName} automatically when the model boots.
     *
     * `updated` rather than `saved`, and `created` rather than `saving`, so
     * each event fires once with the record in its final state -- and so a save
     * that changed nothing writes nothing, since wasChanged() is empty.
     */
    public static function bootAuditable(): void
    {
        static::created(function (self $model): void {
            $model->recordAuditEvent(AuditEvent::Created, [], $model->auditableAttributes());
        });

        static::updated(function (self $model): void {
            // getChanges() inside `updated` holds the attributes that actually
            // changed, already synced. An empty set means the save touched
            // nothing worth a trail entry -- a touch(), or a write of identical
            // values -- so nothing is recorded.
            $changed = $model->filterAuditable($model->getChanges());

            if ($changed === []) {
                return;
            }

            $model->recordAuditEvent(
                AuditEvent::Updated,
                // Only the previous values of attributes that changed. The full
                // original would make every entry a copy of the whole record,
                // and the question being asked is "what changed", not "what did
                // this row look like".
                array_intersect_key($model->filterAuditable($model->getRawOriginal()), $changed),
                $changed,
            );
        });

        static::deleted(function (self $model): void {
            // The attributes are captured on the way out: after this the record
            // is gone, so an entry naming only its id would be unreadable.
            $model->recordAuditEvent(AuditEvent::Deleted, $model->auditableAttributes(), []);
        });
    }

    /**
     * The audit entries describing this record.
     *
     * A morph relation with no inverse constraint -- entries outlive the record
     * they describe, so this resolves nothing for a deleted one. It exists for
     * reading history on a record that still exists.
     *
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')
            ->latest('created_at');
    }

    /**
     * Attribute names this model keeps out of the trail.
     *
     * Override in the model. Sensitive columns belong in
     * config('audit.never_record') instead when they are sensitive everywhere;
     * this is for attributes that are merely noise -- a counter, a cache
     * column -- which would otherwise make every entry look like a change.
     *
     * @return list<string>
     */
    protected function auditExclude(): array
    {
        return [];
    }

    /**
     * This record's attributes, filtered for the trail.
     *
     * @return array<string, mixed>
     */
    protected function auditableAttributes(): array
    {
        return $this->filterAuditable($this->getAttributes());
    }

    /**
     * Drop the attributes this model excludes.
     *
     * The global denylist is NOT applied here -- AuditLogger applies it to
     * everything on the way in, so it holds for callers that never touch this
     * trait.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function filterAuditable(array $values): array
    {
        return array_diff_key($values, array_flip($this->auditExclude()));
    }

    /**
     * Hand one event to the logger.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function recordAuditEvent(AuditEvent $event, array $oldValues, array $newValues): void
    {
        app(AuditLogger::class)->recordModelEvent($event, $this, $oldValues, $newValues);
    }
}
