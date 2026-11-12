<?php

namespace App\Filament\Resources\FeeVouchers\Pages;

use App\Filament\Resources\FeeVouchers\FeeVoucherResource;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use App\Services\FeeService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class ListFeeVouchers extends ListRecords
{
    protected static string $resource = FeeVoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyLateFines')
                ->label(__('Apply late fines'))
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->can('fees.collect') ?? false)
                ->requiresConfirmation()
                ->modalDescription(fn (): string => app(FeeService::class)->lateFine() > 0
                    ? __('A fine of :fine is added once to every overdue voucher.', ['fine' => FeeService::money(app(FeeService::class)->lateFine())])
                    : __('No late fine is set. Set it in the fee settings.'))
                ->action(function (): void {
                    $fined = app(FeeService::class)->applyLateFines(null, auth()->user()->activeBranchId());

                    Notification::make()
                        ->title(__(':count overdue vouchers fined.', ['count' => $fined]))
                        ->success()
                        ->send();
                }),
            Action::make('generateVouchers')
                ->label(__('Generate monthly vouchers'))
                ->icon(Heroicon::OutlinedDocumentPlus)
                ->visible(fn (): bool => auth()->user()?->can('fees.collect') ?? false)
                ->schema([
                    DatePicker::make('month')
                        ->label(__('Fee month'))
                        ->native(false)
                        ->displayFormat('F Y')
                        ->default(now()->startOfMonth())
                        ->required(),
                    Select::make('campus_id')
                        ->label(__('Branch'))
                        ->options(fn (): array => SectionResource::branchOptions())
                        ->default(fn (): ?int => auth()->user()?->activeBranchId())
                        ->placeholder(__('All branches'))
                        ->disabled(fn (): bool => auth()->user()?->activeBranchId() !== null)
                        ->dehydrated()
                        ->live(),
                    Select::make('section_id')
                        ->label(__('Section'))
                        ->options(fn (Get $get): array => Section::query()
                            ->visibleTo(auth()->user())
                            ->when(filled($get('campus_id')), fn ($query) => $query->where('campus_id', $get('campus_id')))
                            ->with('schoolClass')
                            ->get()
                            ->mapWithKeys(fn (Section $section): array => [$section->id => $section->full_name])
                            ->all())
                        ->placeholder(__('All sections'))
                        ->searchable(),
                ])
                ->modalDescription(__('Creates the fee voucher of every active student for the month. Existing vouchers are not changed.'))
                ->action(function (array $data): void {
                    $campusId = auth()->user()->activeBranchId() ?? ($data['campus_id'] ?? null);

                    $created = app(FeeService::class)->generateForMonth(Carbon::parse($data['month']), $campusId, $data['section_id'] ?? null);

                    Notification::make()
                        ->title(__(':count vouchers created.', ['count' => $created]))
                        ->success()
                        ->send();
                }),
        ];
    }
}
