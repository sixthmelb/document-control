<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentResource\Pages;
use App\Models\Document;
use App\Models\Department;
use App\Models\Section;
use App\Enums\DocumentStatus;
use App\Services\DocumentService;
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
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Documents';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Document Information')
                    ->schema([
                        // Document number will be auto-generated, so we hide it in create
                        Forms\Components\TextInput::make('document_number')
                            ->label('Document Number')
                            ->disabled()
                            ->visible(fn ($livewire) => !($livewire instanceof Pages\CreateDocument))
                            ->dehydrated(false),
                        
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('document_type')
                                    ->options([
                                        'general' => 'General',
                                        'policy' => 'Policy',
                                        'sop' => 'Standard Operating Procedure',
                                        'manual' => 'Manual',
                                        'form' => 'Form',
                                        'guideline' => 'Guideline',
                                        'procedure' => 'Procedure',
                                    ])
                                    ->default('general')
                                    ->required(),
                                
                                Forms\Components\Select::make('status')
                                    ->options(collect(DocumentStatus::cases())->mapWithKeys(
                                        fn($status) => [$status->value => $status->getLabel()]
                                    ))
                                    ->default(DocumentStatus::DRAFT)
                                    ->disabled(fn($livewire) => $livewire instanceof Pages\CreateDocument)
                                    ->dehydrated(),
                            ]),
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
                                    ->required()
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
                                    ->preload()
                                    ->required(),
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
                        Forms\Components\FileUpload::make('file_upload')
                            ->label('Document File')
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                            ->maxSize(10240) // 10MB
                            ->directory('documents/temp')
                            ->preserveFilenames()
                            ->required(false) // Optional for draft
                            ->columnSpanFull()
                            ->helperText('Supported formats: PDF, DOC, DOCX. Maximum size: 10MB. File is optional for draft documents.'),
                        
                        // Show current file info if exists
                        Forms\Components\Placeholder::make('current_file_info')
                            ->label('Current File')
                            ->content(function ($record) {
                                if (!$record || !$record->hasFile()) {
                                    return 'No file uploaded yet';
                                }
                                return $record->original_filename . ' (' . $record->formatted_file_size . ')';
                            })
                            ->visible(fn($livewire) => !($livewire instanceof Pages\CreateDocument)),
                    ])
                    ->visible(fn($livewire) => $livewire instanceof Pages\CreateDocument || $livewire instanceof Pages\EditDocument),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->label('Document Number')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::Bold),
                
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->formatStateUsing(fn(DocumentStatus $state): string => $state->getLabel())
                    ->colors([
                        'gray' => DocumentStatus::DRAFT,
                        'info' => DocumentStatus::SUBMITTED,
                        'warning' => DocumentStatus::UNDER_REVIEW,
                        'danger' => DocumentStatus::NEEDS_REVISION,
                        'success' => DocumentStatus::VERIFIED,
                        'primary' => DocumentStatus::APPROVED,
                        'success' => DocumentStatus::PUBLISHED,
                        'danger' => DocumentStatus::REJECTED,
                        'gray' => DocumentStatus::ARCHIVED,
                    ])
                    ->icons([
                        'heroicon-o-document' => DocumentStatus::DRAFT,
                        'heroicon-o-paper-airplane' => DocumentStatus::SUBMITTED,
                        'heroicon-o-eye' => DocumentStatus::UNDER_REVIEW,
                        'heroicon-o-exclamation-triangle' => DocumentStatus::NEEDS_REVISION,
                        'heroicon-o-check-badge' => DocumentStatus::VERIFIED,
                        'heroicon-o-shield-check' => DocumentStatus::APPROVED,
                        'heroicon-o-globe-alt' => DocumentStatus::PUBLISHED,
                        'heroicon-o-x-circle' => DocumentStatus::REJECTED,
                        'heroicon-o-archive-box' => DocumentStatus::ARCHIVED,
                    ]),
                
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Department')
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('section.name')
                    ->label('Section')
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Creator')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(string $state): string => ucfirst($state)),
                
                Tables\Columns\TextColumn::make('version')
                    ->badge()
                    ->color('warning'),
                
                Tables\Columns\IconColumn::make('is_confidential')
                    ->label('Confidential')
                    ->boolean()
                    ->trueIcon('heroicon-o-eye-slash')
                    ->falseIcon('heroicon-o-eye'),
                
                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('download_count')
                    ->label('Downloads')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(DocumentStatus::cases())->mapWithKeys(
                        fn($status) => [$status->value => $status->getLabel()]
                    ))
                    ->multiple(),
                
                SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('section_id')
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                
                SelectFilter::make('document_type')
                    ->options([
                        'general' => 'General',
                        'policy' => 'Policy',
                        'sop' => 'SOP',
                        'manual' => 'Manual',
                        'form' => 'Form',
                        'guideline' => 'Guideline',
                        'procedure' => 'Procedure',
                    ])
                    ->multiple(),
                
                SelectFilter::make('creator_id')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
                
                Filter::make('is_confidential')
                    ->query(fn(Builder $query): Builder => $query->where('is_confidential', true))
                    ->label('Confidential Only'),
                
                Filter::make('published_documents')
                    ->query(fn(Builder $query): Builder => $query->where('status', DocumentStatus::PUBLISHED))
                    ->label('Published Only'),
                
                Filter::make('my_documents')
                    ->query(fn(Builder $query): Builder => $query->where('creator_id', auth()->id()))
                    ->label('My Documents'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn(Document $record): bool => $record->canBeEditedBy(auth()->user())),
                    
                    Tables\Actions\Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn(Document $record): string => route('documents.download', $record))
                        ->openUrlInNewTab(),
                    
                    Tables\Actions\Action::make('view_file')
                        ->icon('heroicon-o-eye')
                        ->url(fn(Document $record): string => route('documents.view', $record))
                        ->openUrlInNewTab()
                        ->visible(fn(Document $record): bool => strtolower($record->file_type) === 'pdf'),
                    
                    Tables\Actions\Action::make('submit_review')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->action(function (Document $record, DocumentService $documentService) {
                            if (!$record->hasFile()) {
                                throw new \Exception('Cannot submit document without file. Please upload a file first.');
                            }
                            $documentService->submitForReview($record, auth()->user());
                        })
                        ->requiresConfirmation()
                        ->modalDescription(fn(Document $record): string => 
                            $record->hasFile() 
                                ? 'Are you sure you want to submit this document for review?' 
                                : 'This document does not have a file attached. Please upload a file before submitting.'
                        )
                        ->disabled(fn(Document $record): bool => !$record->hasFile())
                        ->visible(fn(Document $record): bool => 
                            in_array($record->status, [DocumentStatus::DRAFT, DocumentStatus::NEEDS_REVISION]) &&
                            $record->creator_id === auth()->id()
                        ),
                    
                    Tables\Actions\Action::make('start_review')
                        ->icon('heroicon-o-eye')
                        ->color('warning')
                        ->action(function (Document $record, DocumentService $documentService) {
                            $documentService->startReview($record, auth()->user());
                        })
                        ->requiresConfirmation()
                        ->visible(fn(Document $record): bool => 
                            $record->status === DocumentStatus::SUBMITTED &&
                            auth()->user()->canReview()
                        ),
                    
                    Tables\Actions\Action::make('verify')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(function (Document $record, DocumentService $documentService) {
                            $documentService->verifyDocument($record, auth()->user());
                        })
                        ->requiresConfirmation()
                        ->visible(fn(Document $record): bool => 
                            $record->status === DocumentStatus::UNDER_REVIEW &&
                            auth()->user()->canReview()
                        ),
                    
                    Tables\Actions\Action::make('approve')
                        ->icon('heroicon-o-shield-check')
                        ->color('primary')
                        ->action(function (Document $record, DocumentService $documentService) {
                            $documentService->approveDocument($record, auth()->user());
                        })
                        ->requiresConfirmation()
                        ->visible(fn(Document $record): bool => 
                            $record->status === DocumentStatus::VERIFIED &&
                            auth()->user()->canApprove()
                        ),
                    
                    Tables\Actions\Action::make('publish')
                        ->icon('heroicon-o-globe-alt')
                        ->color('success')
                        ->action(function (Document $record, DocumentService $documentService) {
                            $documentService->publishDocument($record, auth()->user());
                        })
                        ->requiresConfirmation()
                        ->visible(fn(Document $record): bool => 
                            $record->status === DocumentStatus::APPROVED &&
                            auth()->user()->canApprove()
                        ),
                    
                    Tables\Actions\Action::make('request_revision')
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('revision_notes')
                                ->label('Revision Notes')
                                ->required()
                                ->maxLength(1000),
                        ])
                        ->action(function (array $data, Document $record, DocumentService $documentService) {
                            $documentService->requestRevision($record, auth()->user(), $data['revision_notes']);
                        })
                        ->visible(fn(Document $record): bool => 
                            $record->status === DocumentStatus::UNDER_REVIEW &&
                            auth()->user()->canReview()
                        ),
                    
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn(Document $record): bool => 
                            !$record->isPublished() && 
                            (auth()->user()->isSuperAdmin() || $record->creator_id === auth()->id())
                        ),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn(): bool => auth()->user()->isSuperAdmin()),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Document Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('document_number')
                                    ->label('Document Number')
                                    ->copyable(),
                                
                                Infolists\Components\TextEntry::make('title')
                                    ->weight(FontWeight::Bold),
                                
                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn(DocumentStatus $state): string => $state->getLabel()),
                                
                                Infolists\Components\TextEntry::make('version')
                                    ->badge(),
                            ]),
                        
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Organization')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('department.name')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('section.name')
                                    ->badge(),
                            ]),
                    ]),

                Infolists\Components\Section::make('File Information')
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('original_filename')
                                    ->label('File Name'),
                                
                                Infolists\Components\TextEntry::make('file_type')
                                    ->label('File Type')
                                    ->badge(),
                                
                                Infolists\Components\TextEntry::make('formatted_file_size')
                                    ->label('File Size'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Workflow Information')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('creator.name')
                                    ->label('Created By'),
                                
                                Infolists\Components\TextEntry::make('currentReviewer.name')
                                    ->label('Current Reviewer')
                                    ->placeholder('Not assigned'),
                                
                                Infolists\Components\TextEntry::make('approver.name')
                                    ->label('Approved By')
                                    ->placeholder('Not approved yet'),
                                
                                Infolists\Components\TextEntry::make('published_at')
                                    ->label('Published Date')
                                    ->dateTime()
                                    ->placeholder('Not published yet'),
                            ]),
                    ]),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('view_count')
                                    ->label('Views')
                                    ->numeric(),
                                
                                Infolists\Components\TextEntry::make('download_count')
                                    ->label('Downloads')
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
            'index' => Pages\ListDocuments::route('/'),
            'create' => Pages\CreateDocument::route('/create'),
            'view' => Pages\ViewDocument::route('/{record}'),
            'edit' => Pages\EditDocument::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Filter documents based on user role
        if (!auth()->user()->isSuperAdmin()) {
            if (auth()->user()->isAdmin()) {
                // Admin can see documents in their department
                $query->where('department_id', auth()->user()->department_id);
            } else {
                // Regular users can only see their own documents
                $query->where('creator_id', auth()->id());
            }
        }
        
        return $query;
    }
}