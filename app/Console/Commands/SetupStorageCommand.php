<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SetupStorageCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'storage:setup';

    /**
     * The console command description.
     */
    protected $description = 'Setup storage directories for Document Control System';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Setting up storage directories...');

        $directories = [
            'documents/drafts',
            'documents/submitted',
            'documents/published',
            'documents/archived',
            'documents/rejected',
            'documents/temp',
            'documents/samples',
            'qrcodes',
            'backups/database',
            'backups/files',
            'archives/documents',
            'archives/logs',
        ];

        foreach ($directories as $directory) {
            Storage::makeDirectory($directory);
            $this->line("✓ Created directory: storage/app/{$directory}");
        }

        // Create public storage directories
        $publicDirectories = [
            'public/documents/published',
            'public/qrcodes',
        ];

        foreach ($publicDirectories as $directory) {
            Storage::makeDirectory($directory);
            $this->line("✓ Created directory: storage/app/{$directory}");
        }

        // Create sample readme files
        $readmeContent = "# Document Control System Storage\n\nThis directory contains documents for the Document Control System.\n\nGenerated on: " . now()->format('Y-m-d H:i:s');
        
        foreach ($directories as $directory) {
            if (strpos($directory, 'documents/') === 0) {
                Storage::put($directory . '/README.md', $readmeContent);
            }
        }

        $this->info('Storage directories setup completed successfully!');
        
        // Show current storage structure
        $this->newLine();
        $this->info('Storage structure:');
        $this->line('storage/app/');
        $this->line('├── documents/');
        $this->line('│   ├── drafts/');
        $this->line('│   ├── submitted/');
        $this->line('│   ├── published/');
        $this->line('│   ├── archived/');
        $this->line('│   ├── rejected/');
        $this->line('│   ├── temp/');
        $this->line('│   └── samples/');
        $this->line('├── qrcodes/');
        $this->line('├── backups/');
        $this->line('└── archives/');

        return 0;
    }
}