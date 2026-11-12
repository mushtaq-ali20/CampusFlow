<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Enums\ClassAttendanceStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The daily attendance of the student, taken by the class teachers.
 */
class ClassAttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'classAttendances';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('attendance.view') ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Attendance');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereHas('admission', fn (Builder $admissions) => $admissions->visibleTo(auth()->user()))
                ->with('section.schoolClass'))
            ->defaultSort('class_attendances.date', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label(__('Date'))
                    ->date('D d M Y'),
                TextColumn::make('section.full_name')
                    ->label(__('Section')),
                TextColumn::make('status')
                    ->label(__('Attendance'))
                    ->badge(),
                TextColumn::make('remarks')
                    ->label(__('Remarks'))
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Attendance'))
                    ->options(ClassAttendanceStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['value'] ?? null),
                        fn (Builder $query): Builder => $query->where('class_attendances.status', $data['value']),
                    )),
            ]);
    }
}
