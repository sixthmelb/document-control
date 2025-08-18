<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Models\Department;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class PublicController extends Controller
{
    /**
     * Display the public landing page with published documents.
     */
    public function index(Request $request)
    {
        $query = Document::with(['department', 'section', 'creator'])
            ->publiclyAccessible()
            ->orderBy('published_at', 'desc');

        // Apply search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->search($search);
        }

        // Apply department filter
        if ($request->filled('department')) {
            $query->byDepartment($request->get('department'));
        }

        // Apply section filter
        if ($request->filled('section')) {
            $query->bySection($request->get('section'));
        }

        // Apply document type filter
        if ($request->filled('type')) {
            $query->where('document_type', $request->get('type'));
        }

        // Apply date range filter
        if ($request->filled('date_from')) {
            $query->where('published_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('published_at', '<=', $request->get('date_to'));
        }

        // Paginate results
        $documents = $query->paginate(12);

        // Get filter options (cached for performance)
        $departments = Cache::remember('public_departments', 3600, function () {
            return Department::active()
                ->has('documents')
                ->orderBy('name')
                ->get(['id', 'name', 'code']);
        });

        $sections = Cache::remember('public_sections', 3600, function () {
            return Section::active()
                ->inActiveDepartments()
                ->has('documents')
                ->with('department:id,name,code')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'department_id']);
        });

        $documentTypes = Cache::remember('public_document_types', 3600, function () {
            return Document::publiclyAccessible()
                ->distinct()
                ->pluck('document_type')
                ->filter()
                ->sort()
                ->values();
        });

        // Get statistics
        $stats = $this->getPublicStatistics();

        return view('public.index', compact(
            'documents',
            'departments',
            'sections',
            'documentTypes',
            'stats'
        ));
    }

    /**
     * Display a specific document.
     */
    public function show(Document $document, Request $request)
    {
        // Check if document is publicly accessible
        if (!$document->isPubliclyAccessible()) {
            abort(404);
        }

        // Record view
        DocumentDownload::createRecord($document, [
            'download_type' => 'view',
            'access_method' => $request->query('via') === 'qr' ? 'qr_code' : 'web',
        ]);

        // Increment view count
        $document->incrementViewCount();

        // Get related documents (same department/section)
        $relatedDocuments = Document::publiclyAccessible()
            ->where('id', '!=', $document->id)
            ->where(function ($query) use ($document) {
                $query->where('department_id', $document->department_id)
                      ->orWhere('section_id', $document->section_id);
            })
            ->orderBy('published_at', 'desc')
            ->take(5)
            ->get();

        return view('public.document', compact('document', 'relatedDocuments'));
    }

    /**
     * Download a document.
     */
    public function download(Document $document, Request $request)
    {
        // Check if document is publicly accessible
        if (!$document->isPubliclyAccessible()) {
            abort(404);
        }

        // Check if file exists
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            abort(404, 'File not found');
        }

        // Record download
        DocumentDownload::createRecord($document, [
            'download_type' => 'download',
            'access_method' => $request->query('via') === 'qr' ? 'qr_code' : 'web',
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
     * View document inline (for PDF viewer).
     */
    public function view(Document $document, Request $request)
    {
        // Check if document is publicly accessible
        if (!$document->isPubliclyAccessible()) {
            abort(404);
        }

        // Check if file exists
        if (!$document->file_path || !Storage::exists($document->file_path)) {
            abort(404, 'File not found');
        }

        // Only allow inline viewing for PDFs
        if (strtolower($document->file_type) !== 'pdf') {
            return $this->download($document, $request);
        }

        // Record view
        DocumentDownload::createRecord($document, [
            'download_type' => 'view',
            'access_method' => $request->query('via') === 'qr' ? 'qr_code' : 'web',
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
     * Search documents via AJAX.
     */
    public function search(Request $request)
    {
        $query = Document::with(['department', 'section'])
            ->publiclyAccessible();

        if ($request->filled('q')) {
            $query->search($request->get('q'));
        }

        if ($request->filled('department')) {
            $query->byDepartment($request->get('department'));
        }

        if ($request->filled('section')) {
            $query->bySection($request->get('section'));
        }

        if ($request->filled('type')) {
            $query->where('document_type', $request->get('type'));
        }

        $documents = $query->orderBy('published_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'documents' => $documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'document_number' => $document->document_number,
                    'department' => $document->department->name,
                    'section' => $document->section->name,
                    'published_at' => $document->published_at->format('d M Y'),
                    'url' => route('public.documents.show', $document),
                ];
            })
        ]);
    }

    /**
     * Get sections by department (AJAX).
     */
    public function getSectionsByDepartment(Department $department)
    {
        $sections = $department->activeSections()
            ->has('documents')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return response()->json($sections);
    }

    /**
     * Get document statistics for public display.
     */
    protected function getPublicStatistics(): array
    {
        return Cache::remember('public_statistics', 1800, function () {
            $totalDocuments = Document::publiclyAccessible()->count();
            $totalDepartments = Department::active()->has('documents')->count();
            $recentDocuments = Document::publiclyAccessible()
                ->where('published_at', '>=', now()->subDays(30))
                ->count();
            $totalDownloads = DocumentDownload::successful()->count();

            return [
                'total_documents' => $totalDocuments,
                'total_departments' => $totalDepartments,
                'recent_documents' => $recentDocuments,
                'total_downloads' => $totalDownloads,
            ];
        });
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

    /**
     * Show about page.
     */
    public function about()
    {
        return view('public.about');
    }

    /**
     * Show contact page.
     */
    public function contact()
    {
        return view('public.contact');
    }

    /**
     * Show document categories.
     */
    public function categories()
    {
        $departments = Department::active()
            ->has('documents')
            ->withCount(['documents' => function ($query) {
                $query->publiclyAccessible();
            }])
            ->with(['activeSections' => function ($query) {
                $query->has('documents')
                    ->withCount(['documents' => function ($q) {
                        $q->publiclyAccessible();
                    }]);
            }])
            ->orderBy('name')
            ->get();

        return view('public.categories', compact('departments'));
    }

    /**
     * Show documents by department.
     */
    public function departmentDocuments(Department $department, Request $request)
    {
        if (!$department->is_active) {
            abort(404);
        }

        $query = $department->documents()
            ->with(['section', 'creator'])
            ->publiclyAccessible()
            ->orderBy('published_at', 'desc');

        // Apply filters
        if ($request->filled('section')) {
            $query->bySection($request->get('section'));
        }

        if ($request->filled('search')) {
            $query->search($request->get('search'));
        }

        $documents = $query->paginate(12);

        return view('public.department-documents', compact('department', 'documents'));
    }
}