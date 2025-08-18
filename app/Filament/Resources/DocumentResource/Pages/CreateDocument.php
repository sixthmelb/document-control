<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set creator_id
        $data['creator_id'] = auth()->id();
        
        // Handle file upload if provided
        if (isset($data['file_upload']) && $data['file_upload']) {
            $uploadedFile = $data['file_upload'];
            
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
        } else {
            // No file uploaded - set fields to null explicitly
            $data['original_filename'] = null;
            $data['file_path'] = null;
            $data['file_type'] = null;
            $data['file_size'] = null;
            $data['file_hash'] = null;
        }
        
        // Remove file_upload from data as it's not a database field
        unset($data['file_upload']);
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Document created successfully';
    }
}