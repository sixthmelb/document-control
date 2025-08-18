<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use App\Services\DocumentService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn (Document $record): bool => $record->canBeEditedBy(auth()->user())),
            
            // Document workflow actions
            Actions\Action::make('submit_review')
                ->label('Submit for Review')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->action(function (Document $record, DocumentService $documentService) {
                    $documentService->submitForReview($record, auth()->user());
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation()
                ->visible(fn (Document $record): bool => 
                    in_array($record->status, [\App\Enums\DocumentStatus::DRAFT, \App\Enums\DocumentStatus::NEEDS_REVISION]) &&
                    $record->creator_id === auth()->id()
                ),

            Actions\Action::make('start_review')
                ->label('Start Review')
                ->icon('heroicon-o-eye')
                ->color('warning')
                ->action(function (Document $record, DocumentService $documentService) {
                    $documentService->startReview($record, auth()->user());
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation()
                ->visible(fn (Document $record): bool => 
                    $record->status === \App\Enums\DocumentStatus::SUBMITTED &&
                    auth()->user()->canReview()
                ),

            Actions\Action::make('verify')
                ->label('Verify Document')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->action(function (Document $record, DocumentService $documentService) {
                    $documentService->verifyDocument($record, auth()->user());
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation()
                ->visible(fn (Document $record): bool => 
                    $record->status === \App\Enums\DocumentStatus::UNDER_REVIEW &&
                    auth()->user()->canReview()
                ),

            Actions\Action::make('approve')
                ->label('Approve Document')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->action(function (Document $record, DocumentService $documentService) {
                    $documentService->approveDocument($record, auth()->user());
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation()
                ->visible(fn (Document $record): bool => 
                    $record->status === \App\Enums\DocumentStatus::VERIFIED &&
                    auth()->user()->canApprove()
                ),

            Actions\Action::make('publish')
                ->label('Publish Document')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->action(function (Document $record, DocumentService $documentService) {
                    $documentService->publishDocument($record, auth()->user());
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation()
                ->visible(fn (Document $record): bool => 
                    $record->status === \App\Enums\DocumentStatus::APPROVED &&
                    auth()->user()->canApprove()
                ),

            Actions\Action::make('request_revision')
                ->label('Request Revision')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('revision_notes')
                        ->label('Revision Notes')
                        ->required()
                        ->maxLength(1000)
                        ->placeholder('Please specify what needs to be revised...'),
                ])
                ->action(function (array $data, Document $record, DocumentService $documentService) {
                    $documentService->requestRevision($record, auth()->user(), $data['revision_notes']);
                    $this->refreshFormData(['status']);
                })
                ->visible(fn (Document $record): bool => 
                    $record->status === \App\Enums\DocumentStatus::UNDER_REVIEW &&
                    auth()->user()->canReview()
                ),

            Actions\Action::make('reject')
                ->label('Reject Document')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->maxLength(1000)
                        ->placeholder('Please specify the reason for rejection...'),
                ])
                ->action(function (array $data, Document $record, DocumentService $documentService) {
                    $documentService->rejectDocument($record, auth()->user(), $data['rejection_reason']);
                    $this->refreshFormData(['status']);
                })
                ->visible(fn (Document $record): bool => 
                    in_array($record->status, [
                        \App\Enums\DocumentStatus::UNDER_REVIEW, 
                        \App\Enums\DocumentStatus::VERIFIED
                    ]) &&
                    auth()->user()->canReview()
                ),

            // File actions
            Actions\Action::make('download')
                ->label('Download File')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Document $record): string => route('documents.download', $record))
                ->openUrlInNewTab(),

            Actions\Action::make('view_file')
                ->label('View File')
                ->icon('heroicon-o-eye')
                ->url(fn (Document $record): string => route('documents.view', $record))
                ->openUrlInNewTab()
                ->visible(fn (Document $record): bool => strtolower($record->file_type ?? '') === 'pdf'),
        ];
    }
}