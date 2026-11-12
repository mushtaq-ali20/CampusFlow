<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Clusters\Settings\SettingsCluster;
use App\Services\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * When the monthly fee is due, the late payment fine, and the month of the annual fee.
 */
class FeeSettings extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 5;

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
        return __('Fee settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Fee settings');
    }

    public function mount(): void
    {
        $settings = app(Settings::class);

        $this->form->fill([
            'fees.due_day' => $settings->get('fees.due_day', 10),
            'fees.late_fine' => $settings->get('fees.late_fine', 0),
            'fees.annual_fee_month' => $settings->get('fees.annual_fee_month'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fees.due_day')
                    ->label(__('Due day of the month'))
                    ->helperText(__('Monthly fees are due on this day.'))
                    ->integer()
                    ->minValue(1)
                    ->maxValue(28)
                    ->required(),
                TextInput::make('fees.late_fine')
                    ->label(__('Late payment fine'))
                    ->helperText(__('Added once to a voucher that is not paid by its due date. Leave 0 for no fine.'))
                    ->prefix(fn (): string => (string) config('campusflow.currency_symbol'))
                    ->integer()
                    ->minValue(0)
                    ->required(),
                Select::make('fees.annual_fee_month')
                    ->label(__('Month of the annual fee'))
                    ->helperText(__('Fee heads charged annually are added to the vouchers of this month. Leave empty to not charge them.'))
                    ->options(collect(range(1, 12))->mapWithKeys(fn (int $month): array => [$month => now()->startOfYear()->month($month)->translatedFormat('F')])->all())
                    ->placeholder(__('No annual fee')),
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

        app(Settings::class)->set([
            'fees.due_day' => (int) data_get($state, 'fees.due_day'),
            'fees.late_fine' => (int) data_get($state, 'fees.late_fine'),
            'fees.annual_fee_month' => filled(data_get($state, 'fees.annual_fee_month')) ? (int) data_get($state, 'fees.annual_fee_month') : null,
        ]);

        Notification::make()
            ->title(__('Settings saved'))
            ->success()
            ->send();
    }
}
