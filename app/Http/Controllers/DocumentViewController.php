<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;

class DocumentViewController extends Controller
{
    protected DocumentService $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
        // Remove middleware from constructor - will be handled in routes
    }

    /**
     * Display authenticated user's documents.
     */
    public function index(Request $request)
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        
        $query = Document::with(['department', 'section', 'currentReviewer', 'approver'])
            ->where('creator_id', $user->id)
            ->orderBy('updated_at', 'desc');

        // Apply status filter
        if ($request->filled('status')) {
            $query->byStatus($request->get('status'));
        }

        // Apply search filter
        if ($request->filled('search')) {
            $query->search($request->get('search'));
        }

        $documents = $query->paginate(10);

        // Get status counts for user's documents
        $statusCounts = Document::where('creator_id', $user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('documents.index', compact('documents', 'statusCounts'));
    }

    /**
     * Show document details.
     */
    public function show(Document $document, Request $request)
    {
        // Check authorization
        if (!auth()->check() || !$this->canViewDocument($document)) {
            abort(403, 'You do not have permission to view this document.');
        }

        // Record authenticated view
        DocumentDownload::createRecord($document, [
            'download_type' => 'view',
            'access_method' => 'web',
            'user_type' => 'authenticated',
        ]);

        // Increment view count
        $document->incrementViewCount();

        // Load relationships
        $document->load([
            'creator',
            'department',
            'section',
            'currentReviewer',
            'approver',
            'approvals.user',
            'revisions.creator'
        ]);

        // Get workflow history
        $workflowHistory = $document->approvals()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('documents.show', compact('document', 'workflowHistory'));
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

        // Check if file exists
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            abort(404, 'File not found');
        }

        // Record authenticated download
        DocumentDownload::createRecord($document, [
            'download_type' => 'download',
            'access_method' => 'web',
            'user_type' => 'authenticated',
        ]);

        // Increment download count
        $document->incrementDownloadCount();

        // Return file download
        return Storage::download(
            $document->file_path,
            $document->original_filename,
            [
                'Content-Type' => $this->getMimeType($document->file_type),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]
        );
    }

    /**
     * View document inline (authenticated).
     */
    public function view(Document $document, Request $request)
    {
        // Check if user can view this document
        if (!$this->canViewDocument($document)) {
            abort(403, 'You do not have permission to view this document.');
        }

        // Check if file exists
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            abort(404, 'File not found');
        }

        // Only allow inline viewing for PDFs
        if (strtolower($document->file_type) !== 'pdf') {
            return $this->download($document, $request);
        }

        // Record authenticated view
        DocumentDownload::createRecord($document, [
            'download_type' => 'view',
            'access_method' => 'web',
            'user_type' => 'authenticated',
        ]);

        // Increment view count
        $document->incrementViewCount();

        // Return PDF for inline viewing
        return response(Storage::get($document->file_path), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Submit document for review.
     */
    public function submit(Document $document, Request $request)
    {
        // Check if user can submit this document
        if (!$this->canSubmitDocument($document)) {
            abort(403, 'You cannot submit this document.');
        }

        try {
            $this->documentService->submitForReview($document, auth()->user());
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Document has been submitted for review.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to submit document: ' . $e->getMessage());
        }
    }

    /**
     * Start review process (admin only).
     */
    public function startReview(Document $document, Request $request)
    {
        if (!auth()->user()->canReview()) {
            abort(403, 'You do not have permission to review documents.');
        }

        try {
            $this->documentService->startReview($document, auth()->user());
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Review process has been started.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to start review: ' . $e->getMessage());
        }
    }

    /**
     * Request revision (admin only).
     */
    public function requestRevision(Document $document, Request $request)
    {
        if (!auth()->user()->canReview()) {
            abort(403, 'You do not have permission to review documents.');
        }

        $request->validate([
            'revision_notes' => 'required|string|max:1000',
        ]);

        try {
            $this->documentService->requestRevision(
                $document,
                auth()->user(),
                $request->input('revision_notes')
            );
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Revision has been requested.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to request revision: ' . $e->getMessage());
        }
    }

    /**
     * Verify document (admin only).
     */
    public function verify(Document $document, Request $request)
    {
        if (!auth()->user()->canReview()) {
            abort(403, 'You do not have permission to verify documents.');
        }

        try {
            $this->documentService->verifyDocument($document, auth()->user());
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Document has been verified.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to verify document: ' . $e->getMessage());
        }
    }

    /**
     * Approve document (superadmin only).
     */
    public function approve(Document $document, Request $request)
    {
        if (!auth()->user()->canApprove()) {
            abort(403, 'You do not have permission to approve documents.');
        }

        try {
            $this->documentService->approveDocument($document, auth()->user());
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Document has been approved.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to approve document: ' . $e->getMessage());
        }
    }

    /**
     * Publish document (superadmin only).
     */
    public function publish(Document $document, Request $request)
    {
        if (!auth()->user()->canApprove()) {
            abort(403, 'You do not have permission to publish documents.');
        }

        try {
            $this->documentService->publishDocument($document, auth()->user());
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Document has been published.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to publish document: ' . $e->getMessage());
        }
    }

    /**
     * Reject document (admin only).
     */
    public function reject(Document $document, Request $request)
    {
        if (!auth()->user()->canReview()) {
            abort(403, 'You do not have permission to reject documents.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        try {
            $this->documentService->rejectDocument(
                $document,
                auth()->user(),
                $request->input('rejection_reason')
            );
            
            return redirect()
                ->route('documents.show', $document)
                ->with('success', 'Document has been rejected.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', 'Failed to reject document: ' . $e->getMessage());
        }
    }

    /**
     * Show documents pending review (admin only).
     */
    public function pendingReview(Request $request)
    {
        if (!auth()->user()->canReview()) {
            abort(403, 'You do not have permission to review documents.');
        }

        $query = Document::with(['creator', 'department', 'section'])
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderBy('submitted_at', 'asc');

        // Apply department filter for admins (not superadmins)
        if (auth()->user()->isAdmin() && auth()->user()->department_id) {
            $query->where('department_id', auth()->user()->department_id);
        }

        $documents = $query->paginate(10);

        return view('documents.pending-review', compact('documents'));
    }

    /**
     * Show documents pending approval (superadmin only).
     */
    public function pendingApproval(Request $request)
    {
        if (!auth()->user()->canApprove()) {
            abort(403, 'You do not have permission to approve documents.');
        }

        $documents = Document::with(['creator', 'department', 'section'])
            ->where('status', 'verified')
            ->orderBy('verified_at', 'asc')
            ->paginate(10);

        return view('documents.pending-approval', compact('documents'));
    }

    /**
     * Check if user can view document.
     */
    protected function canViewDocument(Document $document): bool
    {
        $user = auth()->user();

        // Superadmin can view all documents
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Admin can view documents in their department
        if ($user->isAdmin() && $document->department_id === $user->department_id) {
            return true;
        }

        // Users can view their own documents
        if ($document->creator_id === $user->id) {
            return true;
        }

        // Users can view published documents
        if ($document->isPublished()) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can download document.
     */
    protected function canDownloadDocument(Document $document): bool
    {
        // Same logic as view for now
        return $this->canViewDocument($document);
    }

    /**
     * Check if user can submit document.
     */
    protected function canSubmitDocument(Document $document): bool
    {
        $user = auth()->user();

        // Only document creator can submit
        if ($document->creator_id !== $user->id) {
            return false;
        }

        // Document must be in draft or needs revision status
        return in_array($document->status->value, ['draft', 'needs_revision']);
    }

    /**
     * Get MIME type based on file extension.
     */
    protected function getMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}