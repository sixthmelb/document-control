<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Department;
use App\Models\Section;
use App\Enums\UserRole;
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
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?string $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255),
                                
                                Forms\Components\TextInput::make('employee_id')
                                    ->label('Employee ID')
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50),
                                
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(20),
                            ]),
                        
                        Forms\Components\Textarea::make('address')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Account Settings')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('role')
                                    ->options(UserRole::getSelectOptions())
                                    ->required()
                                    ->reactive()
                                    ->native(false),
                                
                                Forms\Components\Toggle::make('is_active')
                                    ->default(true),
                            ]),
                        
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Organization')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('department_id')
                                    ->relationship('department', 'name')
                                    ->options(Department::active()->pluck('name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(fn(callable $set) => $set('section_id', null)),
                                
                                Forms\Components\Select::make('section_id')
                                    ->relationship('section', 'name')
                                    ->options(function (callable $get) {
                                        $departmentId = $get('department_id');
                                        if (!$departmentId) {
                                            return [];
                                        }
                                        return Section::where('department_id', $departmentId)
                                            ->active()
                                            ->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->preload(),
                            ]),
                    ]),

                Forms\Components\Section::make('Preferences')
                    ->schema([
                        Forms\Components\KeyValue::make('preferences')
                            ->keyLabel('Setting')
                            ->valueLabel('Value')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\BadgeColumn::make('role')
                    ->formatStateUsing(fn(UserRole $state): string => $state->getLabel())
                    ->colors([
                        'danger' => UserRole::SUPERADMIN,
                        'warning' => UserRole::ADMIN,
                        'success' => UserRole::USER,
                    ])
                    ->icons([
                        'heroicon-o-shield-exclamation' => UserRole::SUPERADMIN,
                        'heroicon-o-user-group' => UserRole::ADMIN,
                        'heroicon-o-user' => UserRole::USER,
                    ]),
                
                Tables\Columns\TextColumn::make('department.name')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('section.name')
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(UserRole::getSelectOptions()),
                
                SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('section_id')
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('is_active')
                    ->query(fn(Builder $query): Builder => $query->where('is_active', true))
                    ->label('Active Only')
                    ->default(),
                
                Filter::make('has_logged_in')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('last_login_at'))
                    ->label('Has Logged In'),
                
                Filter::make('email_verified')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('email_verified_at'))
                    ->label('Email Verified'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    
                    Tables\Actions\Action::make('reset_password')
                        ->icon('heroicon-o-key')
                        ->color('warning')
                        ->form([
                            Forms\Components\TextInput::make('password')
                                ->password()
                                ->required()
                                ->minLength(8)
                                ->maxLength(255),
                        ])
                        ->action(function (array $data, User $record) {
                            $record->update([
                                'password' => Hash::make($data['password']),
                            ]);
                        })
                        ->requiresConfirmation()
                        ->visible(fn(): bool => auth()->user()->isSuperAdmin()),
                    
                    Tables\Actions\Action::make('toggle_status')
                        ->icon(fn(User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color(fn(User $record): string => $record->is_active ? 'danger' : 'success')
                        ->label(fn(User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                        ->action(function (User $record) {
                            $record->update(['is_active' => !$record->is_active]);
                        })
                        ->requiresConfirmation()
                        ->visible(fn(User $record): bool => 
                            auth()->user()->isSuperAdmin() && 
                            $record->id !== auth()->id()
                        ),
                    
                    Tables\Actions\Action::make('send_verification')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->action(function (User $record) {
                            $record->sendEmailVerificationNotification();
                        })
                        ->requiresConfirmation()
                        ->visible(fn(User $record): bool => 
                            is_null($record->email_verified_at) && 
                            auth()->user()->isSuperAdmin()
                        ),
                    
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn(User $record): bool => 
                            auth()->user()->isSuperAdmin() && 
                            $record->id !== auth()->id()
                        ),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn(): bool => auth()->user()->isSuperAdmin()),
                    
                    Tables\Actions\BulkAction::make('activate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                        })
                        ->requiresConfirmation()
                        ->visible(fn(): bool => auth()->user()->isSuperAdmin()),
                    
                    Tables\Actions\BulkAction::make('deactivate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->id !== auth()->id()) {
                                    $record->update(['is_active' => false]);
                                }
                            });
                        })
                        ->requiresConfirmation()
                        ->visible(fn(): bool => auth()->user()->isSuperAdmin()),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Personal Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->weight(FontWeight::Bold),
                                
                                Infolists\Components\TextEntry::make('email')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('employee_id')
                                    ->label('Employee ID')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('phone')
                                    ->copyable(),
                            ]),
                        
                        Infolists\Components\TextEntry::make('address')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Account Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('role')
                                    ->formatStateUsing(fn(UserRole $state): string => $state->getLabel())
                                    ->badge(),
                                
                                Infolists\Components\IconEntry::make('is_active')
                                    ->boolean()
                                    ->trueIcon('heroicon-o-check-circle')
                                    ->falseIcon('heroicon-o-x-circle'),
                                
                                Infolists\Components\TextEntry::make('email_verified_at')
                                    ->label('Email Verified')
                                    ->dateTime()
                                    ->placeholder('Not verified'),
                                
                                Infolists\Components\TextEntry::make('last_login_at')
                                    ->label('Last Login')
                                    ->dateTime()
                                    ->since()
                                    ->placeholder('Never logged in'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Organization')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('department.name')
                                    ->badge()
                                    ->placeholder('Not assigned'),
                                
                                Infolists\Components\TextEntry::make('section.name')
                                    ->badge()
                                    ->placeholder('Not assigned'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Document Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('documents_count')
                                    ->label('Documents Created')
                                    ->state(fn(User $record): int => $record->documents()->count())
                                    ->numeric(),
                                
                                Infolists\Components\TextEntry::make('reviews_count')
                                    ->label('Reviews Performed')
                                    ->state(fn(User $record): int => $record->documentApprovals()->count())
                                    ->numeric(),
                                
                                Infolists\Components\TextEntry::make('approvals_count')
                                    ->label('Documents Approved')
                                    ->state(fn(User $record): int => $record->approvedDocuments()->count())
                                    ->numeric(),
                            ]),
                    ]),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            //'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Only superadmins can see all users
        if (!auth()->user()->isSuperAdmin()) {
            // Admins can see users in their department
            if (auth()->user()->isAdmin() && auth()->user()->department_id) {
                $query->where('department_id', auth()->user()->department_id);
            } else {
                // Regular users can only see themselves
                $query->where('id', auth()->id());
            }
        }
        
        return $query;
    }

    public static function canCreate(): bool
    {
        return auth()->user()->isSuperAdmin();
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->isSuperAdmin();
    }
}