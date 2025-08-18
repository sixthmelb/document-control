<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentRevision;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn (Document $record): bool => 
                    !$record->isPublished() && 
                    (auth()->user()->isSuperAdmin() || $record->creator_id === auth()->id())
                ),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Add current file info for display
        if ($this->record && $this->record->file_path) {
            $data['current_file'] = $this->record->original_filename;
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle file upload if new file is provided
        if (isset($data['file_upload']) && $data['file_upload']) {
            $uploadedFile = $data['file_upload'];
            
            // Create revision if file is being updated
            if ($this->record->file_path) {
                DocumentRevision::createFromDocument(
                    $this->record,
                    auth()->user(),
                    'File updated via admin panel'
                );
            }
            
            // Move file from temp to drafts
            $originalName = Storage::path('public/' . $uploadedFile);
            $fileInfo = pathinfo($originalName);
            $extension = $fileInfo['extension'] ?? '';
            
            // Generate new filename
            $newFileName = Str::uuid() . '.' . $extension;
            $newPath = "documents/drafts/" . now()->format('Y/m') . "/{$newFileName}";
            
            // Ensure directory exists
            Storage::makeDirectory(dirname($newPath));
            
            // Copy file to new location
            if (Storage::exists('public/' . $uploadedFile)) {
                // Delete old file if exists
                if ($this->record->file_path && Storage::exists($this->record->file_path)) {
                    Storage::delete($this->record->file_path);
                }
                
                Storage::copy('public/' . $uploadedFile, $newPath);
                
                // Get file info
                $fileSize = Storage::size($newPath);
                $fileContent = Storage::get($newPath);
                
                // Set file data
                $data['original_filename'] = basename($uploadedFile);
                $data['file_path'] = $newPath;
                $data['file_type'] = $extension;
                $data['file_size'] = $fileSize;
                $data['file_hash'] = hash('sha256', $fileContent);
                
                // Clean up temp file
                Storage::delete('public/' . $uploadedFile);
            }
        }
        
        // Remove file_upload from data as it's not a database field
        unset($data['file_upload']);
        
        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Document updated successfully';
    }
}