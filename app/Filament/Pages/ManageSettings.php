<?php

namespace App\Filament\Pages;

use App\Mail\TestEmail;
use App\Models\User;
use App\Settings\SettingKey;
use App\Settings\Settings;
use App\Settings\SettingsTab;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
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
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Edit the application's settings.
 *
 * The form is built from App\Settings\SettingKey rather than a hand-written
 * field list, so adding a setting is a single enum case -- no migration, and
 * no edit here -- as long as its type has a field mapped in formComponent().
 * Settings are grouped onto the page's tabs by SettingKey::tab().
 *
 * The mailer and its SMTP connection are the exception: the mail tab edits
 * them through the modal the mailer button opens, since they change as one
 * unit and persist the moment the modal is submitted.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
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
     * MAILER_MODAL_KEYS holds instead of the form.
     */
    protected function tabComponent(SettingsTab $settingsTab): Tab
    {
        $keys = array_filter(
            SettingKey::cases(),
            fn (SettingKey $key): bool => $key->tab() === $settingsTab && ! in_array($key, self::MAILER_MODAL_KEYS, true),
        );

        $components = array_map(
            fn (SettingKey $key): Field => $this->formComponent($key),
            $keys,
        );

        if ($settingsTab === SettingsTab::Mail) {
            array_unshift($components, Actions::make([$this->configureMailerAction()]));
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
