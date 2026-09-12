<?php

namespace App\Filament\Pages;

use App\Concerns\ImageValidationRules;
use App\Jobs\ProcessUploadedImage;
use App\Mail\TestEmail;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use BackedEnum;
use Closure;
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
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Edit the application's settings.
 *
 * The form is built from App\Settings\SettingKey rather than a hand-written
 * field list, so adding a setting is a single enum case -- no migration, and
 * no edit here -- as long as its type has a field mapped in formComponent().
 * Settings are grouped onto the page's tabs by SettingKey::tab().
 *
 * The mailer with its SMTP connection and the site icon are the exceptions:
 * the mail tab and SEO & brand tab edit them through the modals their buttons
 * open, since they change as a unit and persist the moment the modal is
 * submitted.
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

    protected static ?int $navigationSort = 90;

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
        SettingKey::SiteIcon,
    ];

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
                                ->label('Save changes')
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
            ->title('Settings saved')
            ->send();
    }

    /**
     * Build the tab a group of settings is edited on.
     *
     * Tabs are rendered per SettingsTab case, each collecting the keys that
     * claim it, so a setting lands on a tab by its enum declaration alone.
     * The mail tab leads with the mailer button, whose modal edits the keys
     * MAILER_MODAL_KEYS holds instead of the form; the SEO & brand tab leads
     * with the site icon buttons, which edit SiteIcon the same way.
     */
    protected function tabComponent(SettingsTab $settingsTab): Tab
    {
        $keys = array_filter(
            SettingKey::cases(),
            fn (SettingKey $key): bool => $key->tab() === $settingsTab
                && ! in_array($key, [...self::MAILER_MODAL_KEYS, ...self::ACTION_EDITED_KEYS], true),
        );

        $components = array_map(
            fn (SettingKey $key): Field => $this->formComponent($key),
            $keys,
        );

        if ($settingsTab === SettingsTab::Mail) {
            array_unshift($components, Actions::make([$this->configureMailerAction()]));
        }

        if ($settingsTab === SettingsTab::SeoBrand) {
            array_unshift($components, Actions::make([
                $this->uploadSiteIconAction(),
                $this->removeSiteIconAction(),
            ]));
        }

        return Tab::make($settingsTab->label())
            ->icon($settingsTab->icon())
            ->schema($components);
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
     */
    protected function configureMailerAction(): Action
    {
        $mailerInUse = fn (): string => $this->settings()->effectiveMailer();

        return Action::make('configureMailer')
            ->label(fn (): string => 'Mailer: '.match ($mailerInUse()) {
                'smtp' => 'SMTP',
                'log' => 'Log',
                default => str($mailerInUse())->ucfirst()->toString(),
            })
            ->icon(Heroicon::OutlinedServerStack)
            ->color(fn (): string => $mailerInUse() === 'smtp' ? 'success' : 'gray')
            ->modalHeading('Change mailer')
            ->modalDescription(SettingKey::MailMailer->helperText())
            ->form(array_map(
                fn (SettingKey $key): Field => $this->formComponent($key),
                self::MAILER_MODAL_KEYS,
            ))
            ->fillForm(fn (): array => array_intersect_key(
                $this->settings()->toArray(),
                array_flip(array_map(fn (SettingKey $key): string => $key->value, self::MAILER_MODAL_KEYS)),
            ))
            ->action(function (array $data): void {
                $this->settings()->setMany($data);

                // Fail closed, the way the service applies it: an SMTP row
                // without a host delivers through the log mailer, and the
                // administrator needs to know that is what they saved.
                if ($this->settings()->string(SettingKey::MailMailer) === 'smtp'
                    && $this->settings()->string(SettingKey::MailHost) === '') {
                    Notification::make()
                        ->warning()
                        ->title('Mailer saved, sending through the log')
                        ->body('SMTP was chosen without a host, so messages are written to the application log until one is set.')
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Mailer updated')
                    ->send();
            });
    }

    /**
     * The button the SEO & brand tab uploads the site icon through.
     *
     * Like the mailer button, the effect is immediate: the upload is staged on
     * the private disk and queued for processing the moment the modal is
     * submitted, rather than riding on the form's Save -- the icon is not form
     * state, it is a file the processing job turns into the favicon, touch
     * icon and social image the page head renders.
     */
    protected function uploadSiteIconAction(): Action
    {
        $iconIsStored = fn (): bool => $this->settings()->string(SettingKey::SiteIcon) !== '';

        return Action::make('uploadSiteIcon')
            ->label(fn (): string => $iconIsStored() ? 'Replace site icon' : 'Upload site icon')
            ->icon(Heroicon::OutlinedPhoto)
            ->color(fn (): string => $iconIsStored() ? 'success' : 'gray')
            ->modalHeading('Site icon')
            ->modalDescription(SettingKey::SiteIcon->helperText())
            ->form([$this->formComponent(SettingKey::SiteIcon)])
            ->action(function (array $data): void {
                $sourcePath = (string) $data[SettingKey::SiteIcon->value];

                // FileUpload state is client-controllable once dehydrated: a
                // forged request can submit an arbitrary path string instead
                // of a fresh upload (see BaseFileUpload::saveUploadedFiles).
                // Only paths inside the staging directory are ever handed to
                // the job, so nothing else on the private disk can be read,
                // republished or deleted through this action.
                if (! static::isStagedUploadPath($sourcePath)) {
                    Notification::make()
                        ->danger()
                        ->title('Upload rejected')
                        ->body('Choose an icon file to upload.')
                        ->send();

                    return;
                }

                ProcessUploadedImage::dispatch(
                    sourcePath: $sourcePath,
                    conversionSet: 'site-icon',
                    targetDirectory: 'site-icon/'.Str::uuid()->toString(),
                    settingKey: SettingKey::SiteIcon,
                );

                Notification::make()
                    ->success()
                    ->title('Site icon uploaded')
                    ->body('It will appear in the browser tab and link previews once processed.')
                    ->send();
            });
    }

    /**
     * Whether a dehydrated FileUpload path may be handed to the job.
     *
     * Exactly "uploads/pending/<name>": a prefix match alone would admit
     * "uploads/pending/../../elsewhere", since the filesystem resolves the
     * dot segments after the prefix is checked. One bare filename -- no
     * further separators, no dot-prefixed segment -- is also exactly what
     * Filament stores there, a hashed name directly inside the directory.
     *
     * A pure predicate rather than an inline check so the rule is pinned by
     * a test of its own, independent of whichever layer rejects a forged
     * string first.
     */
    public static function isStagedUploadPath(string $path): bool
    {
        return (bool) preg_match('#^uploads/pending/[^/.\\\\][^/\\\\]*$#', $path);
    }

    /**
     * The button that removes the stored site icon.
     *
     * A marker is recorded first, so a replacement still queued for processing
     * is discarded by the job rather than resurrecting the icon being removed
     * -- the same guard the avatar's Remove button relies on.
     */
    protected function removeSiteIconAction(): Action
    {
        return Action::make('removeSiteIcon')
            ->label('Remove icon')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => $this->settings()->string(SettingKey::SiteIcon) !== '')
            ->requiresConfirmation()
            ->action(function (): void {
                Cache::put(
                    ProcessUploadedImage::settingRemovalKey(SettingKey::SiteIcon),
                    time(),
                    now()->addDay(),
                );

                $directory = $this->settings()->string(SettingKey::SiteIcon);

                if ($directory !== '') {
                    $this->settings()->set(SettingKey::SiteIcon, '');

                    /** @var string $disk */
                    $disk = config('images.disk');

                    Storage::disk($disk)->deleteDirectory($directory);
                }

                Notification::make()
                    ->success()
                    ->title('Site icon removed')
                    ->send();
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
            SettingKey::SiteIcon => FileUpload::make($key->value)
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
                ->placeholder('smtp.example.com')
                ->maxLength(255)
                ->visible($this->whenMailerIsSmtp()),
            SettingKey::MailPort => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder('587')
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
            SettingKey::MailFromAddress => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->required()
                ->email()
                ->notIn([(string) config('mail.from.address')])
                ->maxLength(255),
            SettingKey::MailFromName => TextInput::make($key->value)
                ->label($key->label())
                ->helperText($key->helperText())
                ->default($key->default())
                ->placeholder(fn (): string => $this->settings()->businessName())
                ->maxLength(255),
        };
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
                ->label('Send test email')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->modalDescription('Sends a short message through the saved mail settings, so delivery can be confirmed end to end.')
                ->form([
                    TextInput::make('recipient')
                        ->label('Deliver to')
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
                    ->title('Test email written to the log')
                    ->body('The mailer is set to log, so the message was written to the application log rather than delivered.')
                    ->send();

                return;
            }

            Notification::make()
                ->success()
                ->title('Test email sent')
                ->body("Delivered to {$recipient}. Check the inbox, and the spam folder if it does not arrive.")
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title('Test email failed')
                ->body($exception->getMessage())
                ->send();
        }
    }

    protected function settings(): Settings
    {
        return app(Settings::class);
    }
}
