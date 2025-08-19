<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use App\Models\Document;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set creator_id
        $data['creator_id'] = auth()->id();
        
        Log::info('CreateDocument - Form data before processing:', [
            'has_file_upload' => isset($data['file_upload']),
            'file_upload_value' => $data['file_upload'] ?? 'not set',
            'file_upload_type' => isset($data['file_upload']) ? gettype($data['file_upload']) : 'N/A'
        ]);
        
        // Handle file upload if provided
        if (isset($data['file_upload']) && !empty($data['file_upload'])) {
            try {
                // Filament menyimpan sebagai array path atau string path
                $uploadedFilePath = is_array($data['file_upload']) ? $data['file_upload'][0] : $data['file_upload'];
                
                Log::info('Processing file upload:', ['path' => $uploadedFilePath]);
                
                // Check if file exists in temp storage
                if (Storage::disk('temp')->exists($uploadedFilePath)) {
                    // Get file info from temp storage
                    $fileContent = Storage::disk('temp')->get($uploadedFilePath);
                    $originalName = basename($uploadedFilePath);
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    
                    // Generate new secure filename
                    $newFileName = Str::uuid() . '.' . strtolower($extension);
                    $folderPath = 'drafts/' . now()->format('Y/m');
                    $newPath = $folderPath . '/' . $newFileName;
                    
                    // Store in documents disk
                    Storage::disk('documents')->makeDirectory($folderPath);
                    
                    if (Storage::disk('documents')->put($newPath, $fileContent)) {
                        // Set document file data
                        $data['original_filename'] = $originalName;
                        $data['file_path'] = $newPath;
                        $data['file_type'] = strtolower($extension);
                        $data['file_size'] = strlen($fileContent);
                        $data['file_hash'] = hash('sha256', $fileContent);
                        
                        // Clean up temp file
                        Storage::disk('temp')->delete($uploadedFilePath);
                        
                        Log::info('File upload successful:', [
                            'original_filename' => $data['original_filename'],
                            'file_path' => $data['file_path'],
                            'file_size' => $data['file_size']
                        ]);
                    } else {
                        throw new \Exception('Failed to store file in documents storage');
                    }
                } else {
                    // Try to find in public temp folder (fallback)
                    $publicTempPath = 'documents/temp/' . $uploadedFilePath;
                    if (Storage::disk('public')->exists($publicTempPath)) {
                        Log::info('File found in public temp, moving to documents storage');
                        
                        $fileContent = Storage::disk('public')->get($publicTempPath);
                        $originalName = basename($uploadedFilePath);
                        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        
                        $newFileName = Str::uuid() . '.' . strtolower($extension);
                        $folderPath = 'drafts/' . now()->format('Y/m');
                        $newPath = $folderPath . '/' . $newFileName;
                        
                        Storage::disk('documents')->makeDirectory($folderPath);
                        
                        if (Storage::disk('documents')->put($newPath, $fileContent)) {
                            $data['original_filename'] = $originalName;
                            $data['file_path'] = $newPath;
                            $data['file_type'] = strtolower($extension);
                            $data['file_size'] = strlen($fileContent);
                            $data['file_hash'] = hash('sha256', $fileContent);
                            
                            Storage::disk('public')->delete($publicTempPath);
                            
                            Log::info('File moved from public temp to documents storage');
                        } else {
                            throw new \Exception('Failed to move file from public temp to documents storage');
                        }
                    } else {
                        throw new \Exception('File not found in any temp storage: ' . $uploadedFilePath);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Document file upload failed:', [
                    'error' => $e->getMessage(),
                    'file_data' => $data['file_upload'] ?? 'not set'
                ]);
                
                // Set file fields to null on error
                $data['original_filename'] = null;
                $data['file_path'] = null;
                $data['file_type'] = null;
                $data['file_size'] = null;
                $data['file_hash'] = null;
                
                // Add validation error
                $this->addError('file_upload', 'Failed to upload file: ' . $e->getMessage());
            }
        } else {
            // No file uploaded - set fields to null
            $data['original_filename'] = null;
            $data['file_path'] = null;
            $data['file_type'] = null;
            $data['file_size'] = null;
            $data['file_hash'] = null;
            
            Log::info('No file uploaded, setting file fields to null');
        }
        
        // Remove file_upload from data as it's not a database field
        unset($data['file_upload']);
        
        Log::info('CreateDocument - Final data:', [
            'has_file' => !empty($data['file_path']),
            'file_path' => $data['file_path'] ?? 'null',
            'original_filename' => $data['original_filename'] ?? 'null'
        ]);
        
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

    protected function afterCreate(): void
    {
        // Log the created document
        $record = $this->record;
        Log::info('Document created:', [
            'id' => $record->id,
            'title' => $record->title,
            'has_file' => $record->hasFile(),
            'file_path' => $record->file_path
        ]);
    }
}