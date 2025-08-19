<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class FixDocumentPaths extends Command
{
    protected $signature = 'documents:fix-paths';
    protected $description = 'Fix document file paths for proper storage access';

    public function handle()
    {
        $this->info('Fixing document paths...');
        
        $documents = Document::whereNotNull('file_path')->get();
        $fixed = 0;
        $errors = 0;

        foreach ($documents as $document) {
            try {
                $oldPath = $document->file_path;
                
                // Skip if path is already correct
                if (Storage::disk('documents')->exists($oldPath)) {
                    continue;
                }

                // Try to find file in old location (public disk)
                if (Storage::disk('public')->exists($oldPath)) {
                    // Move from public to documents disk
                    $newPath = 'drafts/' . now()->format('Y/m') . '/' . basename($oldPath);
                    Storage::disk('documents')->makeDirectory(dirname($newPath));
                    
                    if (Storage::disk('public')->move($oldPath, 'temp_' . basename($oldPath))) {
                        $content = Storage::disk('public')->get('temp_' . basename($oldPath));
                        Storage::disk('documents')->put($newPath, $content);
                        Storage::disk('public')->delete('temp_' . basename($oldPath));
                        
                        $document->update(['file_path' => $newPath]);
                        $fixed++;
                        $this->line("Fixed: {$document->title} ({$oldPath} -> {$newPath})");
                    }
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error fixing {$document->title}: " . $e->getMessage());
            }
        }

        $this->info("Fixed {$fixed} documents, {$errors} errors");
        return 0;
    }
}