<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FixExistingDocuments extends Command
{
    protected $signature = 'documents:fix-existing';
    protected $description = 'Fix existing documents with incorrect file paths';

    public function handle()
    {
        $this->info('Fixing existing documents...');
        
        $documents = Document::whereNotNull('file_path')->get();
        $fixed = 0;
        $errors = 0;
        $notFound = 0;

        foreach ($documents as $document) {
            $this->line("Processing: {$document->title}");
            
            try {
                $oldPath = $document->file_path;
                
                // Skip if already in correct location
                if (Storage::disk('documents')->exists($oldPath)) {
                    $this->info("  ✓ Already in correct location");
                    continue;
                }

                $fileContent = null;
                $sourceLocation = '';

                // Try to find file in various locations
                $possiblePaths = [
                    ['disk' => 'public', 'path' => $oldPath],
                    ['disk' => 'public', 'path' => 'documents/temp/' . basename($oldPath)],
                    ['disk' => 'public', 'path' => 'documents/samples/' . basename($oldPath)],
                    ['disk' => 'local', 'path' => $oldPath],
                    ['disk' => 'temp', 'path' => basename($oldPath)],
                ];

                foreach ($possiblePaths as $location) {
                    if (Storage::disk($location['disk'])->exists($location['path'])) {
                        $fileContent = Storage::disk($location['disk'])->get($location['path']);
                        $sourceLocation = $location['disk'] . ':' . $location['path'];
                        break;
                    }
                }

                if (!$fileContent) {
                    $this->error("  ✗ File not found in any location");
                    $notFound++;
                    continue;
                }

                // Create new path in documents disk
                $extension = pathinfo($document->original_filename ?? 'file.pdf', PATHINFO_EXTENSION);
                $newFileName = Str::uuid() . '.' . strtolower($extension);
                $folderPath = 'drafts/' . now()->format('Y/m');
                $newPath = $folderPath . '/' . $newFileName;

                // Store in documents disk
                Storage::disk('documents')->makeDirectory($folderPath);
                
                if (Storage::disk('documents')->put($newPath, $fileContent)) {
                    // Update document record
                    $document->update([
                        'file_path' => $newPath,
                        'file_type' => strtolower($extension),
                        'file_size' => strlen($fileContent),
                        'file_hash' => hash('sha256', $fileContent),
                    ]);
                    
                    $fixed++;
                    $this->info("  ✓ Fixed: {$sourceLocation} -> {$newPath}");
                    
                    // Clean up old file (optional)
                    // Storage::disk($location['disk'])->delete($location['path']);
                } else {
                    throw new \Exception('Failed to store file in documents disk');
                }
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("  ✗ Error: " . $e->getMessage());
            }
        }

        $this->info("\nSummary:");
        $this->info("Fixed: {$fixed}");
        $this->info("Errors: {$errors}");
        $this->info("Not found: {$notFound}");
        
        return 0;
    }
}