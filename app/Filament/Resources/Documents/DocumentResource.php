<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Concerns\AuthorizesWritesWithPermissions;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Filament\Resources\Sections\SectionResource;
use App\Models\Document;
use App\Models\DocumentFolder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Documents shared with the staff: everyone sees and downloads the documents shared with them; staff with the
 * documents permission upload and share them.
 */
class DocumentResource extends Resource
{
    use AuthorizesWritesWithPermissions;

    protected static string $editPermission = 'documents.manage';

    protected static string $deletePermission = 'documents.manage';

    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?int $navigationSort = 40;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Front desk');
    }

    public static function getModelLabel(): string
    {
        return __('Document');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Documents');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->availableTo(auth()->user());
    }

    /**
     * Who a document can be shared with: the roles, and the teachers.
     *
     * @return array<string, string>
     */
    public static function audienceOptions(): array
    {
        return [
            ...Role::query()->orderBy('name')->pluck('name')->mapWithKeys(fn (string $role): array => [$role => __(ucfirst($role))])->all(),
            Document::TEACHERS => __('Teachers'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('document_folder_id')
                    ->label(__('Folder'))
                    ->relationship('folder', 'name')
                    ->createOptionForm([
                        TextInput::make('name')->label(__('Name'))->required()->maxLength(255)->unique(DocumentFolder::class, 'name'),
                    ])
                    ->preload()
                    ->required(),
                TextInput::make('title')
                    ->label(__('Title'))
                    ->required()
                    ->maxLength(255),
                SpatieMediaLibraryFileUpload::make('file')
                    ->label(__('File'))
                    ->collection('file')
                    ->disk('local')
                    ->visibility('private')
                    ->preserveFilenames()
                    ->maxSize(20 * 1024)
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label(__('Description'))
                    ->columnSpanFull(),
                CheckboxList::make('audiences')
                    ->label(__('Shared with'))
                    ->options(fn (): array => static::audienceOptions())
                    ->columns(3)
                    ->required()
                    ->loadStateFromRelationshipsUsing(fn (CheckboxList $component, ?Document $record) => $component->state($record?->audiences()->pluck('audience')->all() ?? []))
                    ->saveRelationshipsUsing(fn (Document $record, ?array $state) => $record->shareWith(array_values($state ?? [])))
                    ->dehydrated(false)
                    ->columnSpanFull(),
                Select::make('campus_id')
                    ->label(__('Branch'))
                    ->options(fn (): array => SectionResource::branchOptions())
                    ->placeholder(__('All branches')),
                DatePicker::make('visible_from')
                    ->label(__('Visible from')),
                DatePicker::make('visible_until')
                    ->label(__('Visible until'))
                    ->afterOrEqual('visible_from'),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['folder', 'campus', 'audiences', 'uploadedBy', 'media']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->description(fn (Document $record): ?string => $record->getFirstMedia('file')?->file_name)
                    ->searchable(),
                TextColumn::make('folder.name')
                    ->label(__('Folder'))
                    ->sortable(),
                TextColumn::make('audiences.audience')
                    ->label(__('Shared with'))
                    ->formatStateUsing(fn (string $state): string => static::audienceOptions()[$state] ?? $state)
                    ->badge()
                    ->visible(fn (): bool => auth()->user()?->can('documents.manage') ?? false),
                TextColumn::make('campus.name')
                    ->label(__('Branch'))
                    ->placeholder(__('All branches')),
                TextColumn::make('visibility')
                    ->label(__('Visible'))
                    ->state(fn (Document $record): string => $record->visibility())
                    ->formatStateUsing(fn (string $state, Document $record): string => match ($state) {
                        'scheduled' => __('From :date', ['date' => $record->visible_from->format('d M Y')]),
                        'expired' => __('Expired'),
                        default => $record->visible_until ? __('Until :date', ['date' => $record->visible_until->format('d M Y')]) : __('Always'),
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'scheduled' => 'warning',
                        'expired' => 'gray',
                        default => 'success',
                    }),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('document_folder_id')
                    ->label(__('Folder'))
                    ->relationship('folder', 'name'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('Download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (Document $record): bool => $record->getFirstMedia('file') !== null)
                    ->action(function (Document $record): BinaryFileResponse {
                        $media = $record->getFirstMedia('file');

                        return response()->download($media->getPath(), $media->file_name);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocuments::route('/'),
        ];
    }
}
