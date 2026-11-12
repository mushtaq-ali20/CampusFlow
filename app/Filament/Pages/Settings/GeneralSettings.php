<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Services\CertificateService;
use App\Services\Settings;
use BackedEnum;
use DateTimeImmutable;
use DateTimeZone;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;

/**
 * Institution settings saved in the database. Fields are named after their setting key.
 */
class GeneralSettings extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = -1;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return SettingsCluster::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('General settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('General settings');
    }

    public function mount(): void
    {
        $state = [];

        foreach (Settings::CONFIG_KEYS as $key) {
            data_set($state, $key, config($key));
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->persistTabInQueryString()
                    ->tabs([
                        $this->institutionTab(),
                        $this->regionalTab(),
                        $this->notificationsTab(),
                        $this->featuresTab(),
                        $this->registrationTab(),
                        $this->invoicingTab(),
                        $this->certificatesTab(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label(__('Save'))
                                ->submit('save'),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $values = [];

        foreach (Settings::CONFIG_KEYS as $key) {
            if (Arr::has($state, $key)) {
                $value = data_get($state, $key);
                $values[$key] = $value === '' ? null : $value;
            }
        }

        app(Settings::class)->set($values);

        Notification::make()
            ->title(__('Settings saved'))
            ->success()
            ->send();
    }

    private function institutionTab(): Tab
    {
        return Tab::make(__('Institution'))
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->columns(2)
            ->schema([
                TextInput::make('campusflow.company_name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('campusflow.company_id')
                    ->label(__('Tax ID'))
                    ->maxLength(255),
                TextInput::make('campusflow.company_address')
                    ->label(__('Address'))
                    ->maxLength(255),
                TextInput::make('campusflow.company_phone')
                    ->label(__('Phone'))
                    ->tel()
                    ->maxLength(255),
                TextInput::make('campusflow.company_email')
                    ->label(__('Email'))
                    ->email()
                    ->maxLength(255),
                FileUpload::make('campusflow.logo')
                    ->label(__('Logo'))
                    ->image()
                    ->disk('public')
                    ->directory('settings')
                    ->visibility('public')
                    ->maxSize(2048),
            ]);
    }

    /**
     * Timezones labelled with their UTC offset and, where useful, the cities they cover, so that
     * a search for a capital or country without its own identifier (such as Islamabad) finds it.
     *
     * @return array<string, string>
     */
    public static function timezoneOptions(): array
    {
        $aliases = [
            'Asia/Karachi' => 'Pakistan: Karachi, Islamabad, Lahore',
        ];

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $offset = (new DateTimeZone($identifier))->getOffset($now);
            $label = sprintf('(UTC%s%02d:%02d) %s', $offset < 0 ? '-' : '+', intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60), $identifier);

            $options[$identifier] = isset($aliases[$identifier]) ? "{$label} ({$aliases[$identifier]})" : $label;
        }

        return $options;
    }

    private function regionalTab(): Tab
    {
        return Tab::make(__('Regional settings'))
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->columns(2)
            ->schema([
                Select::make('settings.courses_timezone')
                    ->label(__('Timezone'))
                    ->options(self::timezoneOptions())
                    ->searchable()
                    ->required(),
                TextInput::make('campusflow.currency_code')
                    ->label(__('Currency code'))
                    ->placeholder('PKR')
                    ->required()
                    ->maxLength(3),
                TextInput::make('campusflow.currency_symbol')
                    ->label(__('Currency symbol'))
                    ->required()
                    ->maxLength(5),
                Select::make('campusflow.currency_position')
                    ->label(__('Currency symbol position'))
                    ->options([
                        'before' => __('Before the amount'),
                        'after' => __('After the amount'),
                    ])
                    ->required(),
                TimePicker::make('campusflow.calendar_start')
                    ->label(__('Calendars start at'))
                    ->seconds(false)
                    ->required(),
            ]);
    }

    private function notificationsTab(): Tab
    {
        return Tab::make(__('Notifications'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                Section::make(__('Students'))
                    ->schema([
                        Toggle::make('campusflow.send_emails_for_absences')
                            ->label(__('Email students and their contacts when they are absent')),
                        TextInput::make('settings.manager_email')
                            ->label(__('Also send absence emails to'))
                            ->helperText(__('Separate several addresses with commas.'))
                            ->maxLength(255),
                        Toggle::make('campusflow.send_emails_for_results')
                            ->label(__('Email students when their result is published')),
                    ]),
                Section::make(__('Staff'))
                    ->schema([
                        Toggle::make('campusflow.send_attendance_reminders')
                            ->label(__('Remind teachers by email of their classes without attendance')),
                        Toggle::make('settings.monthly_report')
                            ->label(__('Send a monthly report')),
                        TextInput::make('settings.reports_email')
                            ->label(__('Monthly report recipients'))
                            ->helperText(__('Separate several addresses with commas.'))
                            ->maxLength(255),
                        Toggle::make('settings.external_courses_report')
                            ->label(__('Send a report about external courses')),
                        TextInput::make('settings.secretary_email')
                            ->label(__('External courses report recipient'))
                            ->email()
                            ->maxLength(255),
                    ]),
            ]);
    }

    private function featuresTab(): Tab
    {
        return Tab::make(__('Features'))
            ->icon(Heroicon::OutlinedSquares2x2)
            ->schema([
                Section::make(__('Modules'))
                    ->schema([
                        Toggle::make('settings.members_enabled')
                            ->label(__('Members')),
                        Toggle::make('settings.external_courses_enabled')
                            ->label(__('External courses and partners')),
                        Toggle::make('settings.collect_student_banking_info')
                            ->label(__('Collect student banking information')),
                    ]),
                Section::make(__('Teachers'))
                    ->schema([
                        Toggle::make('settings.teachers_can_edit_result')
                            ->label(__('Teachers can edit the results of their students')),
                        Toggle::make('settings.teachers_can_view_calendars')
                            ->label(__('Teachers can view room calendars')),
                    ]),
                Section::make(__('Reports'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('settings.attendance_reports_enabled')
                            ->label(__('Attendance reports'))
                            ->columnSpanFull(),
                        Toggle::make('campusflow.include_takings_in_reports')
                            ->label(__('Include takings in reports'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private function registrationTab(): Tab
    {
        return Tab::make(__('Registration'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->schema([
                Toggle::make('registration.picture.enabled')
                    ->label(__('Students can upload a picture')),
                Toggle::make('registration.picture.mandatory')
                    ->label(__('The picture is mandatory')),
                TextInput::make('registration.terms_url')
                    ->label(__('Terms and conditions URL'))
                    ->url()
                    ->maxLength(255),
                TextInput::make('registration.rules_url')
                    ->label(__('Institution rules URL'))
                    ->url()
                    ->maxLength(255),
            ]);
    }

    private function invoicingTab(): Tab
    {
        return Tab::make(__('Invoicing'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->schema([
                Toggle::make('invoicing.accounting_enabled')
                    ->label(__('Show invoices and payments')),
                Select::make('invoicing.invoice_numbering')
                    ->label(__('Invoice numbering'))
                    ->options([
                        'auto' => __('Automatic, per invoice type and year'),
                        'manual' => __('Manual, with the receipt number entered at checkout'),
                    ])
                    ->required(),
                Toggle::make('invoicing.allow_scheduled_payments')
                    ->label(__('Scheduled payments')),
                Toggle::make('invoicing.price_categories_enabled')
                    ->label(__('Price categories')),
                Toggle::make('invoicing.add_books_to_invoices')
                    ->label(__('Add the course books to the cart at checkout')),
                Toggle::make('invoicing.invoices_contain_enrollments_only')
                    ->label(__('Invoices only contain enrollments')),
                Toggle::make('invoicing.allow_empty_payment_methods')
                    ->label(__('Allow payments without a payment method')),
                Toggle::make('invoicing.adding_scholarship_marks_as_paid')
                    ->label(__('Adding a scholarship marks the enrollment as paid')),
                Toggle::make('invoicing.skip_invoice_data_step')
                    ->label(__('Collapse the invoice data at checkout')),
                Textarea::make('invoicing.invoice_footer')
                    ->label(__('Invoice footer'))
                    ->rows(3),
            ]);
    }

    private function certificatesTab(): Tab
    {
        return Tab::make(__('Certificates'))
            ->icon(Heroicon::OutlinedDocumentCheck)
            ->columns(2)
            ->schema([
                Toggle::make('certificates.enabled')
                    ->label(__('Certificates'))
                    ->helperText(__('Staff can download a certificate for enrollments that have a result.'))
                    ->columnSpanFull(),
                TextInput::make('certificates.title')
                    ->label(__('Title'))
                    ->placeholder(__('Certificate of completion'))
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('certificates.body')
                    ->label(__('Text'))
                    ->placeholder(__('This certifies that {student} has completed the course {course}, a total of {hours} hours.'))
                    ->helperText(__('Available placeholders: :placeholders', ['placeholders' => implode(' ', CertificateService::PLACEHOLDERS)]))
                    ->rows(4)
                    ->columnSpanFull(),
                TextInput::make('certificates.city')
                    ->label(__('Place of issue'))
                    ->helperText(__('Printed before the date.'))
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('certificates.signer_name')
                    ->label(__('Signer name'))
                    ->maxLength(255),
                TextInput::make('certificates.signer_function')
                    ->label(__('Signer position'))
                    ->maxLength(255),
                FileUpload::make('certificates.signature')
                    ->label(__('Signature'))
                    ->image()
                    ->disk('local')
                    ->directory('settings')
                    ->visibility('private')
                    ->maxSize(2048),
                FileUpload::make('certificates.background')
                    ->label(__('Background image'))
                    ->helperText(__('Covers the whole page, in landscape A4 format.'))
                    ->image()
                    ->disk('local')
                    ->directory('settings')
                    ->visibility('private')
                    ->maxSize(5120),
            ]);
    }
}
