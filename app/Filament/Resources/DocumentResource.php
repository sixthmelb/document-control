<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\Department;
use App\Models\Section;
use App\Enums\DocumentStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Document Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                
                                Forms\Components\Textarea::make('description')
                                    ->maxLength(1000)
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('department_id')
                                    ->label('Department')
                                    ->options(Department::pluck('name', 'id'))
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('section_id', null)),
                                
                                Forms\Components\Select::make('section_id')
                                    ->label('Section')
                                    ->options(function (callable $get) {
                                        if (!$get('department_id')) {
                                            return [];
                                        }
                                        return Section::where('department_id', $get('department_id'))
                                            ->pluck('name', 'id');
                                    })
                                    ->required()
                                    ->reactive(),
                                
                                Forms\Components\Select::make('status')
                                    ->options([
                                        DocumentStatus::DRAFT->value => 'Draft',
                                        DocumentStatus::UNDER_REVIEW->value => 'Under Review',
                                        DocumentStatus::VERIFIED->value => 'Verified',
                                        DocumentStatus::APPROVED->value => 'Approved',
                                        DocumentStatus::PUBLISHED->value => 'Published',
                                    ])
                                    ->default(DocumentStatus::DRAFT->value)
                                    ->required()
                                    ->disabled(fn($livewire) => $livewire instanceof Pages\CreateDocument),
                            ]),
                    ]),

                Forms\Components\Section::make('Document Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('version')
                                    ->default('1.0')
                                    ->disabled(fn($livewire) => $livewire instanceof Pages\CreateDocument),
                                
                                Forms\Components\Toggle::make('is_confidential')
                                    ->default(false),
                            ]),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('effective_date'),
                                Forms\Components\DatePicker::make('expiry_date'),
                            ]),
                        
                        Forms\Components\TagsInput::make('tags')
                            ->placeholder('Add tags for categorization')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('File Upload')
                    ->schema([
                        // File Upload menggunakan custom handling
                        Forms\Components\FileUpload::make('file_upload')
                            ->label('Document File')
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(10240) // 10MB
                            ->disk('temp') // Use temp disk for initial upload
                            ->directory('uploads')
                            ->visibility('private')
                            ->preserveFilenames(false) // Let system generate names
                            ->required(false) // Optional for draft
                            ->columnSpanFull()
                            ->helperText('Supported formats: PDF, DOC, DOCX. Maximum size: 10MB.')
                            ->hint('File will be moved to secure storage after saving.'),
                        
                        // Display current file info if exists (for edit mode)
                        Forms\Components\Placeholder::make('current_file_display')
                            ->label('Current File')
                            ->content(function ($record) {
                                if (!$record || !$record->hasFile()) {
                                    return 'No file uploaded yet';
                                }
                                
                                $fileIcon = match(strtolower($record->file_type)) {
                                    'pdf' => '📄',
                                    'doc', 'docx' => '📝',
                                    default => '📎'
                                };
                                
                                return $fileIcon . ' ' . $record->original_filename . 
                                       ' (' . $record->formatted_file_size . ')' .
                                       ' - Status: ' . ucfirst($record->file_type);
                            })
                            ->visible(fn($livewire) => !($livewire instanceof Pages\CreateDocument))
                            ->columnSpanFull(),
                        
                        // File Information Section (read-only)
                        Forms\Components\Group::make([
                            Forms\Components\TextInput::make('original_filename')
                                ->label('Original Filename')
                                ->disabled()
                                ->dehydrated(false),
                            
                            Forms\Components\TextInput::make('file_type')
                                ->label('File Type')
                                ->disabled()
                                ->dehydrated(false),
                            
                            Forms\Components\TextInput::make('formatted_file_size')
                                ->label('File Size')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->columns(3)
                        ->visible(fn($record) => $record && $record->hasFile()),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Document #')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Document $record): string {
                        return $record->title;
                    }),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => DocumentStatus::DRAFT->value,
                        'warning' => DocumentStatus::UNDER_REVIEW->value,
                        'info' => DocumentStatus::VERIFIED->value,
                        'success' => DocumentStatus::APPROVED->value,
                        'primary' => DocumentStatus::PUBLISHED->value,
                        'danger' => DocumentStatus::NEEDS_REVISION->value,
                    ]),
                
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->searchable(),
                
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By')
                    ->searchable(),
                
                // File status indicator
                Tables\Columns\IconColumn::make('has_file')
                    ->label('File')
                    ->boolean()
                    ->getStateUsing(fn (Document $record): bool => $record->hasFile())
                    ->trueIcon('heroicon-o-document-check')
                    ->falseIcon('heroicon-o-document-check')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('version')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        DocumentStatus::DRAFT->value => 'Draft',
                        DocumentStatus::UNDER_REVIEW->value => 'Under Review',
                        DocumentStatus::VERIFIED->value => 'Verified',
                        DocumentStatus::APPROVED->value => 'Approved',
                        DocumentStatus::PUBLISHED->value => 'Published',
                        DocumentStatus::NEEDS_REVISION->value => 'Needs Revision',
                    ]),
                
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('department', 'name'),
                
                Tables\Filters\Filter::make('has_file')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('file_path'))
                    ->label('Has File'),
                
                Tables\Filters\Filter::make('no_file')
                    ->query(fn (Builder $query): Builder => $query->whereNull('file_path'))
                    ->label('No File'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn(Document $record): bool => $record->canBeEditedBy(auth()->user())),
                    
                    Tables\Actions\Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn(Document $record): string => route('documents.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn(Document $record): bool => $record->hasFile()),
                    
                    Tables\Actions\Action::make('view_file')
                        ->icon('heroicon-o-eye')
                        ->url(fn(Document $record): string => route('documents.view', $record))
                        ->openUrlInNewTab()
                        ->visible(fn(Document $record): bool => 
                            $record->hasFile() && strtolower($record->file_type) === 'pdf'
                        ),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'view' => Pages\ViewDocument::route('/{record}'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }
}