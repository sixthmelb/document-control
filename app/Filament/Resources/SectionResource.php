<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SectionResource\Pages;
use App\Models\Section;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class SectionResource extends Resource
{
    protected static ?string $model = Section::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationLabel = 'Sections';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Section Information')
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->options(Department::active()->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Select the department this section belongs to'),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(10)
                                    ->placeholder('e.g., DEV, SUPPORT, NETWORK')
                                    ->helperText('Section code used in document numbering'),
                                
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Development, Technical Support'),
                            ]),
                        
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Only active sections can be selected for new documents'),
                    ]),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->keyLabel('Setting Name')
                            ->valueLabel('Setting Value')
                            ->helperText('Section-specific configuration settings')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('department.name')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                
                Tables\Columns\TextColumn::make('full_path')
                    ->label('Full Path')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color('warning'),
                
                Tables\Columns\TextColumn::make('documents_count')
                    ->label('Documents')
                    ->counts('documents')
                    ->badge()
                    ->color('success'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('is_active')
                    ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                    ->label('Active Only')
                    ->default(),
                
                Filter::make('in_active_departments')
                    ->query(fn(Builder $query): Builder => $query->inActiveDepartments())
                    ->label('In Active Departments')
                    ->default(),
                
                Filter::make('has_users')
                    ->query(fn(Builder $query): Builder => $query->has('users'))
                    ->label('Has Users'),
                
                Filter::make('has_documents')
                    ->query(fn(Builder $query): Builder => $query->has('documents'))
                    ->label('Has Documents'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('toggle_status')
                        ->icon(fn(Section $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn(Section $record): string => $record->is_active ? 'danger' : 'success')
                        ->label(fn(Section $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->action(function (Section $record) {
                            $record->update(['is_active' => !$record->is_active]);
                        })
                        ->requiresConfirmation()
                        ->modalDescription(function (Section $record): string {
                            if ($record->is_active) {
                                return 'Deactivating this section will prevent it from being selected for new documents. Existing documents will not be affected.';
                            }
                            return 'Activating this section will allow it to be selected for new documents.';
                        }),
                    
                    Tables\Actions\Action::make('view_statistics')
                        ->icon('heroicon-o-chart-bar')
                        ->color('info')
                        ->action(function (Section $record) {
                            return redirect()->route('filament.admin.resources.sections.statistics', $record);
                        }),
                    
                    Tables\Actions\DeleteAction::make()
                        ->before(function (Section $record, Tables\Actions\DeleteAction $action) {
                            if (!$record->canBeDeleted()) {
                                $action->cancel();
                                throw new \Exception('Cannot delete section with existing documents or users.');
                            }
                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records, Tables\Actions\DeleteBulkAction $action) {
                            foreach ($records as $record) {
                                if (!$record->canBeDeleted()) {
                                    $action->cancel();
                                    throw new \Exception("Cannot delete section '{$record->name}' with existing documents or users.");
                                }
                            }
                        }),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                        })
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('department.name')
            ->groups([
                Tables\Grouping\Group::make('department.name')
                    ->label('Department')
                    ->collapsible(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Section Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('department.name')
                                    ->badge()
                                    ->color('primary'),
                                
                                Infolists\Components\TextEntry::make('code')
                                    ->badge()
                                    ->weight(FontWeight::Bold),
                                
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Medium),
                                
                                Infolists\Components\IconEntry::make('is_active')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),
                            ]),
                        
                        Infolists\Components\TextEntry::make('full_display_name')
                            ->label('Full Path')
                            ->badge()
                            ->columnSpanFull(),
                        
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Users')
                                    ->state(fn(Section $record): int => $record->users()->count())
                                    ->badge()
                                    ->color('warning'),
                                
                                Infolists\Components\TextEntry::make('documents_count')
                                    ->label('Total Documents')
                                    ->state(fn(Section $record): int => $record->documents()->count())
                                    ->badge()
                                    ->color('success'),
                                
                                Infolists\Components\TextEntry::make('published_documents_count')
                                    ->label('Published Documents')
                                    ->state(fn(Section $record): int => $record->publishedDocuments()->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Document Statistics')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('document_stats')
                            ->state(function (Section $record): array {
                                $stats = $record->getDocumentStats();
                                return collect($stats)->map(function ($count, $status) {
                                    return [
                                        'status' => ucfirst(str_replace('_', ' ', $status)),
                                        'count' => $count,
                                    ];
                                })->values()->toArray();
                            })
                            ->schema([
                                Infolists\Components\Grid::make(2)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('status')
                                            ->weight(FontWeight::Medium),
                                        Infolists\Components\TextEntry::make('count')
                                            ->badge(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('Settings')
                    ->schema([
                        Infolists\Components\KeyValueEntry::make('settings')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn(Section $record): bool => !empty($record->settings)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSections::route('/'),
            'create' => Pages\CreateSection::route('/create'),
            //'view' => Pages\ViewSection::route('/{record}'),
            'edit' => Pages\EditSection::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->isSuperAdmin() && $record->canBeDeleted();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Add counts for better performance
        $query->withCount(['users', 'documents'])
              ->with('department');
        
        // Filter based on user role
        if (!auth()->user()->isSuperAdmin()) {
            if (auth()->user()->isAdmin() && auth()->user()->department_id) {
                $query->where('department_id', auth()->user()->department_id);
            }
        }
        
        return $query;
    }
}