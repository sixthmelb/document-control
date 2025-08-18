<?php

namespace App\Filament\Resources\DocumentResource\Pages;

use App\Filament\Resources\DocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Document')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTitle(): string
    {
        $user = auth()->user();
        
        if ($user->isSuperAdmin()) {
            return 'All Documents';
        } elseif ($user->isAdmin()) {
            return $user->department->name . ' Documents';
        } else {
            return 'My Documents';
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here for statistics
        ];
    }
}