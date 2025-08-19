<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentRevision;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

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
        // Add current file info for display in form
        if ($this->record && $this->record->hasFile()) {
            $data['current_file_display'] = [
                'name' => $this->record->original_filename,
                'size' => $this->record->formatted_file_size,
                'type' => strtoupper($this->record->file_type),
            ];
            
            // For read-only fields
            $data['original_filename'] = $this->record->original_filename;
            $data['file_type'] = strtoupper($this->record->file_type);
            $data['formatted_file_size'] = $this->record->formatted_file_size;
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::info('EditDocument - Form data before processing:', [
            'document_id' => $this->record->id,
            'has_file_upload' => isset($data['file_upload']),
            'file_upload_value' => $data['file_upload'] ?? 'not set',
            'current_file_path' => $this->record->file_path
        ]);
        
        // Handle file upload if new file is provided
        if (isset($data['file_upload']) && !empty($data['file_upload'])) {
            try {
                // Create revision if file is being updated
                if ($this->record->hasFile()) {
                    DocumentRevision::createFromDocument(
                        $this->record,
                        auth()->user(),
                        'File updated via admin panel'
                    );
                    
                    Log::info('Created document revision for file update');
                }
                
                // Process the new file upload
                $uploadedFilePath = is_array($data['file_upload']) ? $data['file_upload'][0] : $data['file_upload'];
                
                Log::info('Processing file update:', ['path' => $uploadedFilePath]);
                
                $fileContent = null;
                $originalName = '';
                
                // Check temp disk first
                if (Storage::disk('temp')->exists($uploadedFilePath)) {
                    $fileContent = Storage::disk('temp')->get($uploadedFilePath);
                    $originalName = basename($uploadedFilePath);
                    
                    Log::info('File found in temp disk');
                } 
                // Fallback to public temp
                elseif (Storage::disk('public')->exists('documents/temp/' . $uploadedFilePath)) {
                    $fileContent = Storage::disk('public')->get('documents/temp/' . $uploadedFilePath);
                    $originalName = basename($uploadedFilePath);
                    
                    Log::info('File found in public temp');
                } else {
                    throw new \Exception('Uploaded file not found: ' . $uploadedFilePath);
                }
                
                if ($fileContent) {
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    
                    // Delete old file if exists
                    if ($this->record->file_path && Storage::disk('documents')->exists($this->record->file_path)) {
                        Storage::disk('documents')->delete($this->record->file_path);
                        Log::info('Deleted old file:', ['path' => $this->record->file_path]);
                    }
                    
                    // Generate new secure filename
                    $newFileName = Str::uuid() . '.' . strtolower($extension);
                    $folderPath = 'drafts/' . now()->format('Y/m');
                    $newPath = $folderPath . '/' . $newFileName;
                    
                    // Store new file in documents disk
                    Storage::disk('documents')->makeDirectory($folderPath);
                    
                    if (Storage::disk('documents')->put($newPath, $fileContent)) {
                        // Update file data
                        $data['original_filename'] = $originalName;
                        $data['file_path'] = $newPath;
                        $data['file_type'] = strtolower($extension);
                        $data['file_size'] = strlen($fileContent);
                        $data['file_hash'] = hash('sha256', $fileContent);
                        
                        // Clean up temp file
                        if (Storage::disk('temp')->exists($uploadedFilePath)) {
                            Storage::disk('temp')->delete($uploadedFilePath);
                        } elseif (Storage::disk('public')->exists('documents/temp/' . $uploadedFilePath)) {
                            Storage::disk('public')->delete('documents/temp/' . $uploadedFilePath);
                        }
                        
                        Log::info('File update successful:', [
                            'new_path' => $data['file_path'],
                            'original_filename' => $data['original_filename'],
                            'file_size' => $data['file_size']
                        ]);
                    } else {
                        throw new \Exception('Failed to store updated file');
                    }
                }
            } catch (\Exception $e) {
                Log::error('Document file update failed:', [
                    'document_id' => $this->record->id,
                    'error' => $e->getMessage()
                ]);
                
                $this->addError('file_upload', 'Failed to update file: ' . $e->getMessage());
                
                // Don't change file data on error, keep existing
                unset($data['original_filename'], $data['file_path'], $data['file_type'], $data['file_size'], $data['file_hash']);
            }
        }
        
        // Remove file_upload and display fields from data
        unset($data['file_upload'], $data['current_file_display'], $data['formatted_file_size']);
        
        Log::info('EditDocument - Final data processed for document:', [
            'document_id' => $this->record->id,
            'file_updated' => isset($data['file_path'])
        ]);
        
        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Document updated successfully';
    }

    protected function afterSave(): void
    {
        // Log the updated document
        $record = $this->record;
        Log::info('Document updated:', [
            'id' => $record->id,
            'title' => $record->title,
            'has_file' => $record->hasFile(),
            'file_path' => $record->file_path
        ]);
    }
}