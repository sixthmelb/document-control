<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Departments';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Department Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->maxLength(10)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('e.g., IT, HR, FIN')
                                    ->helperText('Department code used in document numbering'),
                                
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Information Technology'),
                            ]),
                        
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Only active departments can be selected for new documents'),
                    ]),

                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\KeyValue::make('settings')
                            ->keyLabel('Setting Name')
                            ->valueLabel('Setting Value')
                            ->helperText('Department-specific configuration settings')
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
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->badge()
                    ->color('primary'),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                
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
                
                Tables\Columns\TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->badge()
                    ->color('info'),
                
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
                Filter::make('is_active')
                    ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                    ->label('Active Only')
                    ->default(),
                
                Filter::make('has_sections')
                    ->query(fn(Builder $query): Builder => $query->has('sections'))
                    ->label('Has Sections'),
                
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
                        ->icon(fn(Department $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn(Department $record): string => $record->is_active ? 'danger' : 'success')
                        ->label(fn(Department $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->action(function (Department $record) {
                            $record->update(['is_active' => !$record->is_active]);
                        })
                        ->requiresConfirmation()
                        ->modalDescription(function (Department $record): string {
                            if ($record->is_active) {
                                return 'Deactivating this department will prevent it from being selected for new documents. Existing documents will not be affected.';
                            }
                            return 'Activating this department will allow it to be selected for new documents.';
                        }),
                    
                    Tables\Actions\Action::make('view_statistics')
                        ->icon('heroicon-o-chart-bar')
                        ->color('info')
                        ->action(function (Department $record) {
                            return redirect()->route('filament.admin.resources.departments.statistics', $record);
                        }),
                    
                    Tables\Actions\DeleteAction::make()
                        ->before(function (Department $record, Tables\Actions\DeleteAction $action) {
                            if (!$record->canBeDeleted()) {
                                $action->cancel();
                                throw new \Exception('Cannot delete department with existing documents or users.');
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
                                    throw new \Exception("Cannot delete department '{$record->name}' with existing documents or users.");
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
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Department Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('code')
                                    ->badge()
                                    ->weight(FontWeight::Bold),
                                
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Medium),
                                
                                Infolists\Components\IconEntry::make('is_active')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),
                                
                                Infolists\Components\TextEntry::make('created_at')
                                    ->dateTime(),
                            ]),
                        
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(4)
                            ->schema([
                                Infolists\Components\TextEntry::make('sections_count')
                                    ->label('Sections')
                                    ->state(fn(Department $record): int => $record->sections()->count())
                                    ->badge()
                                    ->color('info'),
                                
                                Infolists\Components\TextEntry::make('users_count')
                                    ->label('Users')
                                    ->state(fn(Department $record): int => $record->users()->count())
                                    ->badge()
                                    ->color('warning'),
                                
                                Infolists\Components\TextEntry::make('documents_count')
                                    ->label('Total Documents')
                                    ->state(fn(Department $record): int => $record->documents()->count())
                                    ->badge()
                                    ->color('success'),
                                
                                Infolists\Components\TextEntry::make('published_documents_count')
                                    ->label('Published Documents')
                                    ->state(fn(Department $record): int => $record->publishedDocuments()->count())
                                    ->badge()
                                    ->color('primary'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Document Statistics')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('document_stats')
                            ->state(function (Department $record): array {
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
                    ->visible(fn(Department $record): bool => !empty($record->settings)),
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            //'view' => Pages\ViewDepartment::route('/{record}'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
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
        $query->withCount(['sections', 'users', 'documents']);
        
        return $query;
    }
}