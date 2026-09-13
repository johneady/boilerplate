---
paths:
  - 'app/Audit/**, app/Concerns/Auditable.php, app/Models/AuditLog.php, app/Policies/AuditLogPolicy.php, config/audit.php'
---

# Policies

## The audit trail is append-only, opt-in per model, and redacted in two layers
Auditing is OPT-IN: add App\Concerns\Auditable to a model. Never make it global — a model added later may hold secrets or third-party personal data, and global auditing captures it into an admin-readable table with nobody having decided it should be.

Immutable by design. AuditLogPolicy denies create/update/delete/forceDelete to EVERYONE including admins, but a policy is never consulted for an admin unless Gate::before stands down first — that is what AuthServiceProvider::IMMUTABLE_RECORD_ABILITIES does (a second list beside SELF_PROTECTED_ABILITIES, covering every write ability, not just delete). Policy methods take `mixed $model = null` because the Gate calls them with no model when the ability is checked against the CLASS (`can('delete', AuditLog::class)`), which is the form Filament uses for bulk actions — requiring the arg makes that fatal rather than denied. Entries leave through retention alone; a prune that could target particular rows would be a way to edit the trail.

Redaction is TWO layers and both must stay: models exclude their own noise via auditExclude(), and AuditLogger applies config('audit.never_record') to every array on the way in regardless of source. The second is the backstop for a new write site that forgot. Settings are separate again — SettingKey::isSecret() (a match, so a new case must be classified) makes MailPassword record only ['redacted' => true].

AuditLogger swallows its own write failures on purpose: an audit insert must never turn a successful password change into a 500. Rethrow there if a deployment needs writes to fail closed.
