<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Enums\DocumentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentService
{
    /**
     * Submit document for review.
     */
    public function submitForReview(Document $document, User $submitter): bool
    {
        if (!$document->hasFile()) {
            throw new \InvalidArgumentException('Cannot submit document without file');
        }

        if (!$document->canBeSubmitted()) {
            throw new \InvalidArgumentException('Document cannot be submitted in current status');
        }

        $success = $document->updateStatus(DocumentStatus::UNDER_REVIEW, $submitter);

        if ($success) {
            // Move file from drafts to under_review folder
            $this->moveFile($document, 'drafts', 'under_review');
        }

        return $success;
    }

    /**
     * Verify document.
     */
    public function verifyDocument(Document $document, User $verifier): bool
    {
        if ($document->status !== DocumentStatus::UNDER_REVIEW) {
            throw new \InvalidArgumentException('Only documents under review can be verified');
        }

        $success = $document->updateStatus(DocumentStatus::VERIFIED, $verifier);

        if ($success) {
            // Move file from under_review to verified folder
            $this->moveFile($document, 'under_review', 'verified');
        }

        return $success;
    }

    /**
     * Approve document.
     */
    public function approveDocument(Document $document, User $approver): bool
    {
        if ($document->status !== DocumentStatus::VERIFIED) {
            throw new \InvalidArgumentException('Only verified documents can be approved');
        }

        $success = $document->updateStatus(DocumentStatus::APPROVED, $approver);

        if ($success) {
            // Move file from verified to approved folder
            $this->moveFile($document, 'verified', 'approved');
        }

        return $success;
    }

    /**
     * Publish document.
     */
    public function publishDocument(Document $document, User $publisher): bool
    {
        if ($document->status !== DocumentStatus::APPROVED) {
            throw new \InvalidArgumentException('Only approved documents can be published');
        }

        $success = $document->updateStatus(DocumentStatus::PUBLISHED, $publisher);

        if ($success) {
            // Move file from approved to published folder
            $this->moveFile($document, 'approved', 'published');
            
            // Generate QR code for published document
            $this->generateQrCode($document);
        }

        return $success;
    }

    /**
     * Move file between folders when status changes.
     */
    protected function moveFile(Document $document, string $fromFolder, string $toFolder): bool
    {
        if (!$document->file_path || !Storage::disk('documents')->exists($document->file_path)) {
            \Log::warning("Cannot move file for document {$document->id}: file not found at {$document->file_path}");
            return false;
        }

        try {
            $currentPath = $document->file_path;
            $fileName = basename($currentPath);
            $newPath = "{$toFolder}/" . now()->format('Y/m') . "/{$fileName}";

            // Ensure destination directory exists
            Storage::disk('documents')->makeDirectory(dirname($newPath));

            // Move file to new location
            if (Storage::disk('documents')->move($currentPath, $newPath)) {
                // Update document file path
                $document->update(['file_path' => $newPath]);
                
                \Log::info("File moved for document {$document->id}: {$currentPath} -> {$newPath}");
                return true;
            } else {
                \Log::error("Failed to move file for document {$document->id}: {$currentPath} -> {$newPath}");
                return false;
            }
        } catch (\Exception $e) {
            \Log::error("Error moving file for document {$document->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store uploaded file.
     */
    public function storeFile(UploadedFile $file, string $folder = 'drafts'): array
    {
        try {
            $fileName = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $folderPath = $folder . '/' . now()->format('Y/m');
            $filePath = $folderPath . '/' . $fileName;
            
            // Store file using documents disk
            $storedPath = Storage::disk('documents')->putFileAs(
                $folderPath,
                $file,
                $fileName
            );
            
            if (!$storedPath) {
                throw new \Exception('Failed to store file');
            }
            
            // Get file information
            $fileSize = Storage::disk('documents')->size($storedPath);
            $fileContent = Storage::disk('documents')->get($storedPath);
            
            return [
                'path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'type' => strtolower($file->getClientOriginalExtension()),
                'size' => $fileSize,
                'hash' => hash('sha256', $fileContent),
            ];
        } catch (\Exception $e) {
            \Log::error('File storage failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate QR code for published document.
     */
    protected function generateQrCode(Document $document): void
    {
        try {
            $qrCodeService = app(\App\Services\QrCodeService::class);
            $qrCodeService->generateForDocument($document);
        } catch (\Exception $e) {
            \Log::error("QR code generation failed for document {$document->id}: " . $e->getMessage());
        }
    }

    /**
     * Delete document and its file.
     */
    public function deleteDocument(Document $document, User $deleter): bool
    {
        if ($document->isPublished()) {
            throw new \InvalidArgumentException('Published documents cannot be deleted');
        }

        return DB::transaction(function () use ($document) {
            try {
                // Delete file if exists
                if ($document->file_path && Storage::disk('documents')->exists($document->file_path)) {
                    Storage::disk('documents')->delete($document->file_path);
                }

                // Delete QR code if exists
                if ($document->qr_code_path && Storage::disk('qrcodes')->exists($document->qr_code_path)) {
                    Storage::disk('qrcodes')->delete($document->qr_code_path);
                }

                // Soft delete document
                return $document->delete();
            } catch (\Exception $e) {
                \Log::error("Document deletion failed for document {$document->id}: " . $e->getMessage());
                return false;
            }
        });
    }
}