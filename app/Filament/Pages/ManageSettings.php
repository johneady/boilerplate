<?php

namespace App\Filament\Pages;

use App\Auth\Permission;
use App\Concerns\ImageValidationRules;
use App\Mail\TestEmail;
use App\Media\MediaCollection;
use App\Media\MediaManager;
use App\Media\StagedUpload;
use App\Models\User;
use App\Notifications\Payments\PaymentCredentialsChanged;
use App\Payments\Actions\RegisterWebhooks;
use App\Payments\Enums\Currency;
use App\Payments\Enums\Gateway;
use App\Payments\Enums\GatewayMode;
use App\Payments\Exceptions\GatewayException;
use App\Payments\Exceptions\PaymentNotAllowed;
use App\Payments\OpsAlerts;
use App\Payments\PaymentCredentials;
use App\Payments\PaymentDiagnostics;
use App\Payments\PaymentManager;
use App\Settings\DiagnosticResult;
use App\Settings\DiagnosticSeverity;
use App\Settings\ProductionDiagnostics;
use App\Settings\ServerInformation;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * Edit the application's settings.
 *
 * The form is built from App\Settings\SettingKey rather than a hand-written
 * field list, so adding a setting is a single enum case -- no migration, and
 * no edit here -- as long as its type has a field mapped in formComponent().
 * Settings are grouped onto the page's tabs by SettingKey::tab().
 *
 * The mailer with its SMTP connection and the logo are the exceptions: the
 * mail tab and Brand tab edit them through the modals their buttons
 * open, since they change as a unit and persist the moment the modal is
 * submitted.
 *
 * The Diagnostics tab edits nothing at all. It reports on configuration that
 * lives in the environment rather than the settings table -- debug mode, the
 * database driver, session cookie flags -- which an administrator otherwise
 * has no way to inspect without shell access to the container. The Server
 * tab is the same idea for the machine itself: the PHP build, the database
 * server and the disk it lives on, reported read-only from
 * App\Settings\ServerInformation.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    use ImageValidationRules;

    protected string $view = 'filament.pages.manage-settings';

    protected static ?string $slug = 'settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 90;

    /**
     * Whether the current user may reach this page.
     *
     * Filament's default allows EVERY authenticated panel user, and panel
     * access alone is not authorisation to edit SMTP credentials, the
     * registration toggle or the mail password -- Permission::ManageSettings
     * exists for exactly this check. The log viewer plugin authorizes itself
     * the same way (see AdminPanelProvider); every model-backed resource gets
     * the equivalent from its policy.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission(Permission::ManageSettings) ?? false;
    }

    /**
     * The settings edited through the mailer button's modal rather than the
     * tab's form, in modal display order.
     *
     * @var array<int, SettingKey>
     */
    private const MAILER_MODAL_KEYS = [
        SettingKey::MailMailer,
        SettingKey::MailHost,
        SettingKey::MailPort,
        SettingKey::MailUsername,
        SettingKey::MailPassword,
        SettingKey::MailEncryption,
    ];

    /**
     * The settings edited through a button's modal rather than a tab's form.
     *
     * @var array<int, SettingKey>
     */
    private const ACTION_EDITED_KEYS = [
        SettingKey::Logo,
    ];

    /**
     * The Stripe credentials, edited through their own modal on the Payments tab.
     *
     * @var array<int, SettingKey>
     */
    private const STRIPE_CREDENTIAL_KEYS = [
        SettingKey::StripeSandboxSecretKey,
        SettingKey::StripeSandboxWebhookSecret,
        SettingKey::StripeLiveSecretKey,
        SettingKey::StripeLiveWebhookSecret,
    ];

    /**
     * The PayPal credentials, edited through their own modal on the Payments tab.
     *
     * @var array<int, SettingKey>
     */
    private const PAYPAL_CREDENTIAL_KEYS = [
        SettingKey::PayPalSandboxClientId,
        SettingKey::PayPalSandboxClientSecret,
        SettingKey::PayPalSandboxWebhookId,
        SettingKey::PayPalLiveClientId,
        SettingKey::PayPalLiveClientSecret,
        SettingKey::PayPalLiveWebhookId,
    ];

    /**
     * The checks that did not pass, memoized for the render that asked.
     *
     * @var list<DiagnosticResult>|null
     */
    protected ?array $diagnosticFailures = null;

    /**
     * The form's state, keyed by SettingKey value.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->settings()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Tabs::make('settings')
                        ->tabs(array_map(
                            fn (SettingsTab $tab): Tab => $this->tabComponent($tab),
                            SettingsTab::cases(),
                        )),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('settings.actions.save'))
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $this->settings()->setMany($this->form->getState());

        Notification::make()
            ->success()
            ->title(__('settings.actions.saved'))
            ->send();
    }

    /**
     * Build the tab a group of settings is edited on.
     *
     * Tabs are rendered per SettingsTab case, each collecting the keys that
     * claim it, so a setting lands on a tab by its enum declaration alone.
     * The mail tab leads with the mailer button, whose modal edits the keys
     * MAILER_MODAL_KEYS holds instead of the form; the Brand tab leads
     * with the logo preview and its buttons, which edit Logo the same way.
     *
     * The Email and Diagnostics tabs carry a badge flagging what cannot be
     * seen without opening them: the mailer actually sending while it is not
     * SMTP, and the number of failing checks -- red the moment an error is
     * among them, amber while only warnings are. Both resolve per render,
     * so they follow an out-of-band save made in this same request.
     */
    protected function tabComponent(SettingsTab $settingsTab): Tab
    {
        $keys = array_filter(
            SettingKey::cases(),
            fn (SettingKey $key): bool => $key->tab() === $settingsTab
                && ! in_array($key, [...self::MAILER_MODAL_KEYS, ...self::ACTION_EDITED_KEYS, ...self::STRIPE_CREDENTIAL_KEYS, ...self::PAYPAL_CREDENTIAL_KEYS], true),
        );

        /** @var array<int, Component> $components */
        $components = array_map(
            fn (SettingKey $key): Field => $this->formComponent($key),
            $keys,
        );

        if ($settingsTab === SettingsTab::Mail) {
            array_unshift($components, Actions::make([$this->configureMailerAction()]));
        }

        if ($settingsTab === SettingsTab::SeoBrand) {
            array_unshift(
                $components,
                $this->logoPreview(),
                Actions::make([
                    $this->uploadLogoAction(),
                    $this->removeLogoAction(),
                ]),
            );
        }

        if ($settingsTab === SettingsTab::Payments) {
            array_unshift($components, Actions::make([
                $this->stripeCredentialsAction(),
                $this->paypalCredentialsAction(),
                $this->connectWebhooksAction(),
            ]));
        }

        if ($settingsTab === SettingsTab::Diagnostics) {
            $components[] = $this->diagnosticsReport();
        }

        if ($settingsTab === SettingsTab::Server) {
            $components[] = $this->serverReport();
        }

        $tab = Tab::make($settingsTab->label())
            ->icon($settingsTab->icon())
            ->schema($components);

        // Payment settings carry their own permission: reaching the settings
        // page is not by itself authority over where money is paid out. A
        // hidden tab's fields are not dehydrated, so saving the form without
        // the permission cannot write them either.
        if ($settingsTab === SettingsTab::Payments) {
            $tab->visible(fn (): bool => auth()->user()?->hasPermission(Permission::ManagePaymentSettings) ?? false);
        }

        if ($settingsTab === SettingsTab::Mail) {
            $tab
                ->badge(fn (): ?string => $this->settings()->effectiveMailer() === 'smtp' ? null : $this->mailerLabel())
                ->badgeColor('warning')
                ->badgeTooltip(fn (): ?string => $this->settings()->effectiveMailer() === 'smtp'
                    ? null
                    : $this->mailerTooltip());
        }

        if ($settingsTab === SettingsTab::Diagnostics) {
            $tab
                ->badge(fn (): ?string => static::diagnosticsBadge($this->diagnosticFailures())['label'])
                ->badgeColor(fn (): string => static::diagnosticsBadge($this->diagnosticFailures())['color'])
                ->badgeTooltip(fn (): ?string => static::diagnosticsBadge($this->diagnosticFailures())['tooltip']);
        }

        return $tab;
    }

    /**
     * The button the mail tab changes the mailer through.
     *
     * It displays the mailer actually in use -- the environment's own until
     * a mailer has been saved, and log for an SMTP row the service fails
     * closed for lack of a host (Settings::effectiveMailer()) -- and opens a
     * modal editing that mailer together with its SMTP connection, since one
     * is meaningless without the other. Submitting persists immediately --
     * the same direct effect the test email action has -- while the from
     * fields stay on the form, because they apply to whichever mailer sends.
     *
     * The modal is stopped at the button itself until a from address is
     * stored: this is the only path to choosing SMTP, the modal cannot edit
     * the from fields, and a submitted-without-one row would send real mail
     * from an address nobody chose. Together with the form's
     * required-on-smtp rule this makes a live SMTP mailer without a from
     * address unreachable from the panel.
     */
    protected function configureMailerAction(): Action
    {
        return Action::make('configureMailer')
            ->label(fn (): string => __('settings.mailer.button', ['mailer' => $this->mailerLabel()]))
            ->icon(Heroicon::OutlinedServerStack)
            ->color(fn (): string => $this->settings()->effectiveMailer() === 'smtp' ? 'success' : 'gray')
            ->modalHeading(__('settings.mailer.heading'))
            ->modalDescription(SettingKey::MailMailer->helperText())
            ->form(array_map(
                fn (SettingKey $key): Field => $this->formComponent($key),
                self::MAILER_MODAL_KEYS,
            ))
            // mountUsing rather than fillForm: fillForm is itself just a
            // mountUsing callback, so the guard and the fill share the one
            // hook. Halting inside mount unmounts the action, so the mailer
            // modal never opens -- the administrator is told why instead of
            // being left to fail validation after it does.
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                if ($this->settings()->string(SettingKey::MailFromAddress) === '') {
                    Notification::make()
                        ->warning()
                        ->title(__('settings.mailer.needs_from_address.title'))
                        ->body(__('settings.mailer.needs_from_address.body'))
                        ->send();

                    $action->halt();
                }

                $schema?->fill(array_intersect_key(
                    $this->settings()->toArray(),
                    array_flip(array_map(fn (SettingKey $key): string => $key->value, self::MAILER_MODAL_KEYS)),
                ));
            })
            ->action(function (array $data): void {
                $this->settings()->setMany($data);

                // Fail closed, the way the service applies it: an SMTP row
                // without a host delivers through the log mailer, and the
                // administrator needs to know that is what they saved.
                if ($this->settings()->string(SettingKey::MailMailer) === 'smtp'
                    && $this->settings()->string(SettingKey::MailHost) === '') {
                    Notification::make()
                        ->warning()
                        ->title(__('settings.mailer.saved_to_log.title'))
                        ->body(__('settings.mailer.saved_to_log.body'))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('settings.mailer.updated'))
                    ->send();
            });
    }

    /**
     * The preview of the logo currently in use, shown above the buttons.
     *
     * It renders the same x-app-logo-icon the site chrome does, so what an
     * administrator sees here is what the sidebar, auth pages and public
     * header render -- including the bundled default when nothing has been
     * uploaded, which is the state the buttons alone could not convey.
     */
    protected function logoPreview(): View
    {
        // Resolved per render rather than once at schema-build time, like the
        // buttons' label/visible closures beside it: the upload and remove
        // actions persist out-of-band and re-render the page, so a value
        // frozen into viewData would still describe the logo just replaced.
        return View::make('filament.settings.logo-preview')
            ->viewData(fn (): array => [
                'hasUploadedLogo' => $this->settings()->logoMedia() !== null,
            ]);
    }

    /**
     * The mailer's display name, as the mailer button and the Email tab's
     * badge both show it.
     *
     * Keyed off effectiveMailer(), so the two always agree on which mailer
     * is described -- including the incomplete-SMTP fallback to log.
     */
    protected function mailerLabel(): string
    {
        $mailer = $this->settings()->effectiveMailer();

        return match ($mailer) {
            'smtp' => 'SMTP',
            'log' => 'Log',
            default => str($mailer)->ucfirst()->toString(),
        };
    }

    /**
     * The Email tab badge's tooltip, naming the mailer actually in use.
     *
     * A method rather than __() inline in the closure because the translator
     * returns string|array once replacements are passed -- the array arm is
     * unreachable for a string key, but the badgeTooltip() closure declares
     * ?string, so the cast belongs somewhere it is explained rather than
     * repeated at each call site.
     */
    protected function mailerTooltip(): string
    {
        return (string) __('settings.mailer.tooltip', ['mailer' => $this->mailerLabel()]);
    }

    /**
     * The read-only configuration report shown on the Diagnostics tab.
     *
     * Resolved per render rather than frozen into viewData at schema-build
     * time, so reopening the tab after changing a setting -- or after a deploy
     * that changed the environment -- reports the configuration in force now.
     */
    protected function diagnosticsReport(): View
    {
        return View::make('filament.settings.diagnostics-report')
            ->viewData(fn (): array => [
                'results' => [...app(ProductionDiagnostics::class)->run(), ...app(PaymentDiagnostics::class)->run()],
                'environment' => (string) config('app.env'),
            ]);
    }

    /**
     * The read-only server report shown on the Server tab.
     *
     * Resolved per render rather than frozen into viewData at schema-build
     * time, matching the diagnostics report beside it: the server it
     * describes is the one handling this request, not an earlier one.
     */
    protected function serverReport(): View
    {
        return View::make('filament.settings.server-report')
            ->viewData(fn (): array => [
                'report' => app(ServerInformation::class)->toArray(),
            ]);
    }

    /**
     * The checks that did not pass, run once per render.
     *
     * The Diagnostics tab badge's closures and the report beside them all
     * ask for the findings; memoizing keeps a render to a single run of
     * the checks.
     *
     * @return list<DiagnosticResult>
     */
    protected function diagnosticFailures(): array
    {
        if ($this->diagnosticFailures !== null) {
            return $this->diagnosticFailures;
        }

        $failures = [...app(ProductionDiagnostics::class)->failures(), ...app(PaymentDiagnostics::class)->failures()];

        usort(
            $failures,
            fn (DiagnosticResult $a, DiagnosticResult $b): int => ($a->severity === DiagnosticSeverity::Error ? 0 : 1)
                <=> ($b->severity === DiagnosticSeverity::Error ? 0 : 1),
        );

        return $this->diagnosticFailures = $failures;
    }

    /**
     * The Diagnostics tab's badge, from the checks that did not pass.
     *
     * A pure function of the findings so it can be pinned by a test of its
     * own, independent of the configuration that produced them: the label
     * is the number of failures, the colour the worst severity among them,
     * and the tooltip the breakdown the count alone does not give.
     *
     * @param  list<DiagnosticResult>  $failures
     * @return array{label: ?string, color: string, tooltip: ?string}
     */
    public static function diagnosticsBadge(array $failures): array
    {
        $errors = count(array_filter(
            $failures,
            fn (DiagnosticResult $result): bool => $result->severity === DiagnosticSeverity::Error,
        ));

        $warnings = count(array_filter(
            $failures,
            fn (DiagnosticResult $result): bool => $result->severity === DiagnosticSeverity::Warning,
        ));

        $breakdown = [];

        if ($errors > 0) {
            $breakdown[] = $errors === 1 ? '1 error' : "{$errors} errors";
        }

        if ($warnings > 0) {
            $breakdown[] = $warnings === 1 ? '1 warning' : "{$warnings} warnings";
        }

        return [
            'label' => $breakdown === [] ? null : (string) count($failures),
            'color' => $errors > 0 ? 'danger' : 'warning',
            'tooltip' => $breakdown === [] ? null : implode(', ', $breakdown),
        ];
    }

    /**
     * The button the Brand tab uploads the logo through.
     *
     * Like the mailer button, the effect is immediate: the upload is staged on
     * the private disk and queued for processing the moment the modal is
     * submitted, rather than riding on the form's Save -- the logo is not form
     * state, it is a file the processing job turns into the brand mark, the
     * favicon, the touch icon and the social image.
     */
    protected function uploadLogoAction(): Action
    {
        $logoIsStored = fn (): bool => $this->settings()->logoMedia() !== null;

        return Action::make('uploadLogo')
            ->label(fn (): string => $logoIsStored() ? 'Replace logo' : 'Upload logo')
            ->icon(Heroicon::OutlinedPhoto)
            ->color(fn (): string => $logoIsStored() ? 'success' : 'gray')
            ->modalHeading(__('settings.logo.heading'))
            ->modalDescription(SettingKey::Logo->helperText())
            ->form([$this->formComponent(SettingKey::Logo)])
            ->action(function (array $data): void {
                $sourcePath = (string) $data[SettingKey::Logo->value];

                // FileUpload state is client-controllable once dehydrated: a
                // forged request can submit an arbitrary path string instead
                // of a fresh upload (see BaseFileUpload::saveUploadedFiles).
                // Only paths inside the staging directory are ever handed to
                // the job, so nothing else on the private disk can be read,
                // republished or deleted through this action.
                if (! StagedUpload::isStagedPath($sourcePath)) {
                    Notification::make()
                        ->danger()
                        ->title(__('settings.logo.rejected.title'))
                        ->body(__('settings.logo.rejected.body'))
                        ->send();

                    return;
                }

                app(MediaManager::class)->attachStagedImage(
                    stagedPath: $sourcePath,
                    collection: MediaCollection::Logo,
                );

                $this->settings()->forgetLogo();

                Notification::make()
                    ->success()
                    ->title(__('settings.logo.uploaded.title'))
                    ->body(__('settings.logo.uploaded.body'))
                    ->send();
            });
    }

    /**
     * The button that removes the uploaded logo, restoring the bundled mark.
     *
     * Deleting the row is also what cancels a replacement still being
     * processed: the job looks its row up when it finishes and discards the
     * conversions when it is gone. That replaces the cache marker this action
     * used to record, from when nothing represented the logo until processing
     * had written it.
     */
    protected function removeLogoAction(): Action
    {
        return Action::make('removeLogo')
            ->label(__('settings.logo.remove'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->settings()->logoMedia() !== null)
            ->requiresConfirmation()
            ->modalDescription(__('settings.logo.remove_description'))
            ->action(function (): void {
                $this->settings()->logoMedia()?->delete();

                // The page re-renders in this same request, and Settings
                // memoises the row it just deleted.
                $this->settings()->forgetLogo();

                Notification::make()
                    ->success()
                    ->title(__('settings.logo.removed'))
                    ->send();
            });
    }

    /**
     * The Stripe credentials button. Named {name}Action so Filament can
     * resolve the action by name when it is mounted, like configureMailerAction().
     */
    protected function stripeCredentialsAction(): Action
    {
        return $this->gatewayCredentialsAction(Gateway::Stripe);
    }

    /**
     * The PayPal credentials button, resolved by name the same way.
     */
    protected function paypalCredentialsAction(): Action
    {
        return $this->gatewayCredentialsAction(Gateway::PayPal);
    }

    /**
     * The button a gateway's credentials are edited through.
     *
     * Credentials can redirect where money is paid, so this modal is held to
     * a higher standard than the rest of the page:
     *
     *   - secrets are write-only: never filled into the modal, never in the
     *     Livewire payload, shown only masked, and a blank field keeps what is
     *     stored (Settings::toArray() leaves encrypted keys out entirely);
     *   - the administrator's current password is required to save;
     *   - every change is announced to the operations address and audited,
     *     with the values redacted (SettingKey::isSecret()).
     */
    protected function gatewayCredentialsAction(Gateway $gateway): Action
    {
        $keys = $gateway === Gateway::Stripe ? self::STRIPE_CREDENTIAL_KEYS : self::PAYPAL_CREDENTIAL_KEYS;

        return Action::make($gateway->value.'Credentials')
            ->label(__($gateway === Gateway::Stripe ? 'payments.settings.stripe_button' : 'payments.settings.paypal_button'))
            ->icon(Heroicon::OutlinedKey)
            ->color(fn (): string => app(PaymentCredentials::class)->isConfigured($gateway, app(PaymentManager::class)->mode()) ? 'success' : 'gray')
            ->authorize(fn (): bool => auth()->user()?->hasPermission(Permission::ManagePaymentSettings) ?? false)
            ->modalHeading(__('payments.settings.credentials_heading', ['gateway' => $gateway->label()]))
            ->modalDescription($this->credentialsDescription($gateway))
            ->schema([
                ...array_map(fn (SettingKey $key): Field => $this->formComponent($key), $keys),
                TextInput::make('current_password')
                    ->label(__('payments.settings.current_password'))
                    ->helperText(__('payments.settings.current_password_help'))
                    ->password()
                    ->currentPassword()
                    ->required()
                    ->dehydrated(false),
            ])
            ->mountUsing(function (?Schema $schema) use ($keys): void {
                $schema?->fill(array_intersect_key(
                    $this->settings()->toArray(),
                    array_flip(array_map(fn (SettingKey $key): string => $key->value, $keys)),
                ));
            })
            ->action(function (array $data) use ($gateway, $keys): void {
                $changes = [];

                foreach ($keys as $key) {
                    $value = trim((string) ($data[$key->value] ?? ''));

                    // Write-only: a secret left blank keeps the stored one.
                    if ($key->isEncrypted() && $value === '') {
                        continue;
                    }

                    if ($value !== $this->settings()->string($key)) {
                        $changes[$key->value] = $value;
                    }
                }

                if ($changes === []) {
                    Notification::make()->title(__('payments.settings.credentials_unchanged'))->send();

                    return;
                }

                $this->settings()->setMany($changes);

                $user = auth()->user();

                app(OpsAlerts::class)->send(new PaymentCredentialsChanged(
                    gateway: $gateway->label(),
                    changedFields: array_map(fn (string $key): string => SettingKey::from($key)->label(), array_keys($changes)),
                    changedBy: $user instanceof User ? "{$user->name} <{$user->email}>" : 'Unknown',
                    ipAddress: request()->ip(),
                ));

                Notification::make()->success()->title(__('payments.settings.credentials_saved'))->send();
            });
    }

    /**
     * Register this site's webhook endpoint at a gateway, for the current
     * mode, and store the signing secret or webhook id it returns.
     *
     * Held to the credentials' standard -- password confirmation and an ops
     * alert -- since it replaces a stored credential.
     */
    public function connectWebhooksAction(): Action
    {
        return Action::make('connectWebhooks')
            ->label(__('payments.settings.connect_webhooks'))
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->authorize(fn (): bool => auth()->user()?->hasPermission(Permission::ManagePaymentSettings) ?? false)
            ->modalHeading(__('payments.settings.connect_webhooks'))
            ->modalDescription(fn (): string => $this->connectWebhooksDescription())
            ->schema([
                Select::make('gateway')
                    ->label(__('payments.fields.gateway'))
                    ->options(fn (): array => collect([Gateway::Stripe, Gateway::PayPal])
                        ->filter(fn (Gateway $gateway): bool => app(PaymentCredentials::class)->isConfigured($gateway, app(PaymentManager::class)->mode()))
                        ->mapWithKeys(fn (Gateway $gateway): array => [$gateway->value => $gateway->label()])
                        ->all())
                    ->required(),
                TextInput::make('current_password')
                    ->label(__('payments.settings.current_password'))
                    ->helperText(__('payments.settings.current_password_help'))
                    ->password()
                    ->currentPassword()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data): void {
                $gateway = Gateway::from((string) $data['gateway']);

                try {
                    $key = app(RegisterWebhooks::class)->handle($gateway, app(PaymentManager::class)->mode());
                } catch (PaymentNotAllowed|GatewayException $e) {
                    Notification::make()->danger()->title(__('payments.settings.connect_webhooks_failed'))->body($e->getMessage())->send();

                    return;
                }

                $user = auth()->user();

                app(OpsAlerts::class)->send(new PaymentCredentialsChanged(
                    gateway: $gateway->label(),
                    changedFields: [$key->label()],
                    changedBy: $user instanceof User ? "{$user->name} <{$user->email}>" : 'Unknown',
                    ipAddress: request()->ip(),
                ));

                Notification::make()->success()->title(__('payments.settings.webhooks_connected', ['gateway' => $gateway->label()]))->send();
            });
    }

    /**
     * The connect-webhooks modal's description, naming the current mode.
     * Cast here because __() widens to string|array once replacements are
     * passed; the array arm is unreachable for this key.
     */
    protected function connectWebhooksDescription(): string
    {
        return (string) __('payments.settings.connect_webhooks_help', ['mode' => app(PaymentManager::class)->mode()->label()]);
    }

    /**
     * The credentials modal's description: how secrets are handled, and the
     * webhook URL to register at the gateway for each mode.
     */
    protected function credentialsDescription(Gateway $gateway): HtmlString
    {
        $lines = [
            (string) __('payments.settings.credentials_description'),
            (string) __('payments.settings.webhook_urls_help'),
        ];

        foreach (GatewayMode::cases() as $mode) {
            $lines[] = $mode->label().': '.route('payments.webhook', ['gateway' => $gateway->value, 'mode' => $mode->value]);
        }

        // Every line escaped; only the line breaks are markup.
        return new HtmlString(implode('<br>', array_map(e(...), $lines)));
    }

    /**
     * The field for one gateway credential.
     *
     * An encrypted one is a password field that starts empty and says whether
     * a value is stored -- by its masked form only.
     */
    protected function credentialField(SettingKey $key): Field
    {
        $field = TextInput::make($key->value)
            ->label($key->label())
            ->maxLength(1024);

        if (! $key->isEncrypted()) {
            return $field->helperText($key->helperText());
        }

        return $field
            ->password()
            ->autocomplete('new-password')
            ->helperText(function () use ($key): string {
                $masked = $this->settings()->masked($key);

                return $key->helperText().' '.($masked === ''
                    ? __('payments.settings.secret_unset')
                    : __('payments.settings.secret_set', ['masked' => $masked]));
            });
    }

    /**
     * Build the field for a single setting.
     *
     * Every key's default is declared on the enum, so a setting that has never
     * been saved still renders with the value the application actually uses.
     */
    protected function formComponent(SettingKey $key): Field
    {
        return match ($key) {
            SettingKey::BusinessName => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->required()
                ->maxLength(255),
            SettingKey::BusinessAddress => Textarea::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(1024)
                ->rows(3),
            SettingKey::BusinessPhone => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(255),
            SettingKey::BusinessEmail => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->email()
                ->maxLength(255),
            SettingKey::SeoTitle => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(fn (): string => $this->settings()->businessName())
                ->maxLength(255),
            SettingKey::SeoDescription => Textarea::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(511)
                ->rows(3),
            SettingKey::AllowSearchIndexing => Toggle::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default()),
            SettingKey::Logo => FileUpload::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->rules($this->imageRules())
                ->acceptedFileTypes(static::acceptedIconMimeTypes())
                // Staged on the private disk, never the public one: the
                // unprocessed original must not be reachable over HTTP (see
                // the avatar upload in App\Livewire\Settings\Profile).
                ->disk('local')
                ->directory('uploads/pending'),
            SettingKey::AllowRegistration => Toggle::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default()),
            SettingKey::MailMailer => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options([
                    'log' => 'Log',
                    'smtp' => 'SMTP',
                ])
                ->required()
                ->selectablePlaceholder(false)
                ->live(),
            SettingKey::MailHost => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(__('settings.mailer.host_placeholder'))
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailPort => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(__('settings.mailer.port_placeholder'))
                ->numeric()
                ->minValue(1)
                ->maxValue(65535)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailUsername => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailPassword => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->password()
                ->revealable()
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailEncryption => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options([
                    '' => 'Default (TLS)',
                    'tls' => 'TLS',
                    'ssl' => 'SSL',
                    'none' => 'None',
                ])
                ->selectablePlaceholder(false)
                ->visible($this->whenMailerIsSmtp()),
            // Required only while the mailer actually sending is SMTP. The
            // mailer is chosen in the modal and persisted the moment it is
            // submitted, so the saved mailer -- what effectiveMailer()
            // reports, including the incomplete-SMTP fallback to log -- is
            // the condition, never form state this field sits beside. On the
            // log mailer a blank stands down to the deployment's own
            // MAIL_FROM_ADDRESS rather than being forced.
            SettingKey::MailFromAddress => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->required(fn (): bool => $this->settings()->effectiveMailer() === 'smtp')
                ->email()
                ->notIn([(string) config('mail.from.address')])
                ->maxLength(255),
            SettingKey::MailFromName => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(fn (): string => $this->settings()->businessName())
                ->maxLength(255),
            // Deliberately not required(): blank is the meaningful "send no
            // alerts" value, and an installation with no one to alert must be
            // able to save the mail tab.
            SettingKey::OpsAlertEmail => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->email()
                ->maxLength(255),
            SettingKey::Timezone => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options(static::timezoneOptions())
                ->searchable()
                ->selectablePlaceholder(false),
            // Live so the date-format examples re-render with the chosen
            // locale's month names the moment it changes -- the clearest way
            // to show the two settings interact before anything is saved.
            SettingKey::Locale => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options(SettingKey::LOCALES)
                ->selectablePlaceholder(false)
                ->live(),
            SettingKey::DateFormat => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options(fn (Get $get): array => static::formatExamples(SettingKey::DATE_FORMATS, $get))
                ->selectablePlaceholder(false),
            SettingKey::TimeFormat => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options(fn (): array => static::formatExamples(SettingKey::TIME_FORMATS))
                ->selectablePlaceholder(false),
            SettingKey::PaymentsEnabled, SettingKey::StripeEnabled, SettingKey::PayPalEnabled, SettingKey::ManualPaymentsEnabled => Toggle::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default()),
            // Shown only where the Demo gateway can run at all: in production
            // the toggle would switch on nothing.
            SettingKey::DemoGatewayEnabled => Toggle::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->visible(fn (): bool => app(PaymentManager::class)->demoAllowed()),
            SettingKey::PaymentsMode => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options(collect(GatewayMode::cases())->mapWithKeys(fn (GatewayMode $mode): array => [$mode->value => $mode->label()])->all())
                ->required()
                ->selectablePlaceholder(false),
            SettingKey::PaymentsCurrency => Select::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->options(Currency::options())
                ->required()
                ->selectablePlaceholder(false),
            SettingKey::PastDueGraceDays => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->integer()
                ->minValue(0)
                ->maxValue(60)
                ->required(),
            SettingKey::StripeSandboxSecretKey, SettingKey::StripeSandboxWebhookSecret, SettingKey::StripeLiveSecretKey,
            SettingKey::StripeLiveWebhookSecret, SettingKey::PayPalSandboxClientId, SettingKey::PayPalSandboxClientSecret,
            SettingKey::PayPalSandboxWebhookId, SettingKey::PayPalLiveClientId, SettingKey::PayPalLiveClientSecret,
            SettingKey::PayPalLiveWebhookId => $this->credentialField($key),
        };
    }

    /**
     * The timezone choices, grouped by region for the searchable select.
     *
     * Keys are the full identifiers the setting stores, and the cast
     * validates against the same identifier list, so a select value and a
     * stored row can never disagree about what a timezone is.
     *
     * @return array<string, string|array<string, string>>
     */
    protected static function timezoneOptions(): array
    {
        $regions = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            // The Etc/ region duplicates UTC offsets in a form nobody
            // searches for; leaving it out leaves the choice clearer.
            if (! str_contains($identifier, '/') || str_starts_with($identifier, 'Etc/')) {
                continue;
            }

            [$region, $city] = explode('/', $identifier, 2);

            $regions[$region][$identifier] = str_replace('_', ' ', $city);
        }

        return ['UTC' => 'UTC'] + $regions;
    }

    /**
     * Render each candidate format as the example shown in its select.
     *
     * The sample is chosen to distinguish every format character at a glance:
     * day 5 against 05, March as a translatable month name, 14:07 as a
     * 24-hour clock against 2:07 pm. Each example carries the format's note
     * naming the convention it belongs to, so an administrator chooses
     * "Month first — United States" rather than decoding format characters.
     * Date examples localise to the locale select's current value, which is
     * what demonstrates that locale drives the names while the format drives
     * the shape.
     *
     * @param  array<string, string>  $formats  Format string => note.
     * @return array<string, string>
     */
    protected static function formatExamples(array $formats, ?Get $get = null): array
    {
        $locale = $get !== null ? (string) ($get('locale') ?? 'en') : 'en';

        $examples = [];

        foreach ($formats as $format => $note) {
            $examples[$format] = static::sampleDate()->locale($locale)->translatedFormat($format).' — '.$note;
        }

        return $examples;
    }

    /**
     * The fixed date the format examples render with.
     *
     * Declared as the interface so callers chain the localising methods on
     * CarbonInterface rather than on a concrete class.
     */
    protected static function sampleDate(): CarbonInterface
    {
        return CarbonImmutable::parse('2021-03-05 14:07');
    }

    /**
     * The file-picker types matching config('images.accepted_extensions').
     *
     * Derived from the same config the server-side rules use, so the browser
     * picker and the validation never disagree about what is accepted.
     *
     * @return array<int, string>
     */
    protected static function acceptedIconMimeTypes(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = config('images.accepted_extensions');

        return collect($extensions)
            ->map(fn (string $extension): string => match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                default => 'image/'.$extension,
            })
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Show a field only while the SMTP connection is being configured.
     *
     * Live off the mailer select in the button's modal, so choosing SMTP
     * reveals the connection fields immediately without submitting. The
     * from-address fields stay on the form for both mailers -- the from
     * address is applied whichever one sends.
     */
    protected function whenMailerIsSmtp(): Closure
    {
        return fn (Get $get): bool => $get('mail_mailer') === 'smtp';
    }

    /**
     * The page-level actions shown beside the title.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label(__('settings.test_email.label'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalDescription(__('settings.test_email.description'))
                ->form([
                    TextInput::make('recipient')
                        ->label(__('settings.test_email.recipient'))
                        ->email()
                        ->required()
                        ->default(function (): string {
                            $businessEmail = $this->settings()->string(SettingKey::BusinessEmail);

                            if ($businessEmail !== '') {
                                return $businessEmail;
                            }

                            $user = auth()->user();

                            return $user instanceof User ? $user->email : '';
                        }),
                ])
                ->action(function (array $data): void {
                    $this->sendTestEmail($data['recipient']);
                }),
        ];
    }

    /**
     * Deliver the test message and report what actually happened.
     */
    protected function sendTestEmail(string $recipient): void
    {
        try {
            Mail::to($recipient)->send(new TestEmail);

            // mail.default now reflects the settings applied when the mailer
            // resolved, including the incomplete-SMTP fallback to log.
            if (config('mail.default') === 'log') {
                Notification::make()
                    ->warning()
                    ->title(__('settings.test_email.logged.title'))
                    ->body(__('settings.test_email.logged.body'))
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title(__('settings.test_email.sent'))
                ->body("Delivered to {$recipient}. Check the inbox, and the spam folder if it does not arrive.")
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(__('settings.test_email.failed'))
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function settings(): Settings
    {
        return app(Settings::class);
    }
}
