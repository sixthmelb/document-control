<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentViewController extends Controller
{
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    /**
     * Download document file (authenticated).
     */
    public function download(Document $document, Request $request)
    {
        // Check authorization
        if (!auth()->check() || !$this->canDownloadDocument($document)) {
            abort(403, 'You do not have permission to download this document.');
        }

        // Check if file exists on documents disk
        if (!$document->file_path || !Storage::disk('documents')->exists($document->file_path)) {
            \Log::error("File not found for document {$document->id}: {$document->file_path}");
            abort(404, 'Document file not found');
        }

        try {
            // Record authenticated download
            DocumentDownload::createRecord($document, [
                'download_type' => 'download',
                'access_method' => 'web',
                'user_type' => 'authenticated',
            ]);

            // Increment download count
            $document->incrementDownloadCount();

            // Get file from documents disk
            $fileContent = Storage::disk('documents')->get($document->file_path);
            $mimeType = $this->getMimeType($document->file_type);

            // Return file download with proper headers
            return response($fileContent, 200, [
                'Content-Type' => $mimeType,
                'Content-Disposition' => 'attachment; filename="' . $document->original_filename . '"',
                'Content-Length' => strlen($fileContent),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            \Log::error("Download failed for document {$document->id}: " . $e->getMessage());
            abort(500, 'Failed to download document');
        }
    }

    /**
     * View document inline (authenticated).
     */
    public function view(Document $document, Request $request)
    {
        // Check if user can view this document
        if (!auth()->check() || !$this->canViewDocument($document)) {
            abort(403, 'You do not have permission to view this document.');
        }

        // Check if file exists on documents disk
        if (!$document->file_path || !Storage::disk('documents')->exists($document->file_path)) {
            \Log::error("File not found for document {$document->id}: {$document->file_path}");
            abort(404, 'Document file not found');
        }

        // Only allow inline viewing for PDFs
        if (strtolower($document->file_type) !== 'pdf') {
            return $this->download($document, $request);
        }

        try {
            // Record authenticated view
            DocumentDownload::createRecord($document, [
                'download_type' => 'view',
                'access_method' => 'web',
                'user_type' => 'authenticated',
            ]);

            // Increment view count
            $document->incrementViewCount();

            // Get PDF content from documents disk
            $fileContent = Storage::disk('documents')->get($document->file_path);

            // Return PDF for inline viewing
            return response($fileContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
                'Content-Length' => strlen($fileContent),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Frame-Options' => 'SAMEORIGIN',
            ]);

        } catch (\Exception $e) {
            \Log::error("View failed for document {$document->id}: " . $e->getMessage());
            abort(500, 'Failed to view document');
        }
    }

    /**
     * Check if user can download the document.
     */
    private function canDownloadDocument(Document $document): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Super admin can download any document
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Document creator can download their own documents
        if ($document->creator_id === $user->id) {
            return true;
        }

        // Admin can download documents in their department
        if ($user->isAdmin() && $user->department_id === $document->department_id) {
            return true;
        }

        // Users can download published non-confidential documents
        if ($document->isPublished() && !$document->is_confidential) {
            return true;
        }

        // Users in same department can download non-confidential documents
        if (!$document->is_confidential && $user->department_id === $document->department_id) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can view the document.
     */
    private function canViewDocument(Document $document): bool
    {
        return $this->canDownloadDocument($document);
    }

    /**
     * Get MIME type for file extension.
     */
    private function getMimeType(string $extension): string
    {
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
        ];

        return $mimeTypes[strtolower($extension)] ?? 'application/octet-stream';
    }
}