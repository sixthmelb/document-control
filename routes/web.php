<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\QrValidationController;
use App\Http\Controllers\DocumentViewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Public Routes
Route::get('/', [PublicController::class, 'index'])->name('home');
Route::get('/about', [PublicController::class, 'about'])->name('about');
Route::get('/contact', [PublicController::class, 'contact'])->name('contact');
Route::get('/categories', [PublicController::class, 'categories'])->name('categories');

// Public Document Routes
Route::prefix('documents')->name('public.documents.')->group(function () {
    Route::get('/', [PublicController::class, 'index'])->name('index');
    Route::get('/search', [PublicController::class, 'search'])->name('search');
    Route::get('/{document}', [PublicController::class, 'show'])->name('show');
    Route::get('/{document}/download', [PublicController::class, 'download'])->name('download');
    Route::get('/{document}/view', [PublicController::class, 'view'])->name('view');
});

// Department Documents
Route::get('/departments/{department}/documents', [PublicController::class, 'departmentDocuments'])
    ->name('public.department.documents');

// AJAX Routes for Public
Route::get('/api/departments/{department}/sections', [PublicController::class, 'getSectionsByDepartment'])
    ->name('api.departments.sections');

// QR Code Routes
Route::prefix('qr')->name('qr.')->group(function () {
    // QR Code Validation
    Route::get('/validate/{document}/{token}', [QrValidationController::class, 'validate'])->name('validate');
    Route::get('/scanner', [QrValidationController::class, 'scanner'])->name('scanner');
    Route::get('/help', [QrValidationController::class, 'help'])->name('help');
    
    // API Routes for QR Validation
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/validate/{document}/{token}', [QrValidationController::class, 'validateApi'])->name('validate');
        Route::post('/validate', [QrValidationController::class, 'validateQrData'])->name('validate.data');
        Route::get('/statistics', [QrValidationController::class, 'statistics'])->name('statistics');
    });
});

// Authentication Routes (Laravel Breeze)
require __DIR__.'/auth.php';

// Authenticated Routes
Route::middleware('auth')->group(function () {
    // Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Dashboard
    Route::get('/dashboard', function () {
        $user = auth()->user();
        
        // Get user's documents statistics
        $documentStats = $user->getDocumentStats();
        
        // Get recent documents
        $recentDocuments = $user->documents()
            ->with(['department', 'section'])
            ->orderBy('updated_at', 'desc')
            ->take(5)
            ->get();
        
        // Get pending actions based on user role
        $pendingActions = [];
        
        if ($user->canReview()) {
            $pendingReviews = \App\Models\Document::whereIn('status', ['submitted', 'under_review'])
                ->when(!$user->isSuperAdmin() && $user->department_id, function ($query) use ($user) {
                    $query->where('department_id', $user->department_id);
                })
                ->count();
            
            $pendingActions['reviews'] = $pendingReviews;
        }
        
        if ($user->canApprove()) {
            $pendingApprovals = \App\Models\Document::where('status', 'verified')->count();
            $pendingActions['approvals'] = $pendingApprovals;
        }
        
        return view('dashboard', compact('documentStats', 'recentDocuments', 'pendingActions'));
    })->name('dashboard');
    
    // Document Management Routes
    Route::prefix('documents')->name('documents.')->group(function () {
        Route::get('/', [DocumentViewController::class, 'index'])->name('index');
        Route::get('/{document}', [DocumentViewController::class, 'show'])->name('show');
        Route::get('/{document}/download', [DocumentViewController::class, 'download'])->name('download');
        Route::get('/{document}/view', [DocumentViewController::class, 'view'])->name('view');
        
        // Document Workflow Actions
        Route::post('/{document}/submit', [DocumentViewController::class, 'submit'])->name('submit');
        Route::post('/{document}/start-review', [DocumentViewController::class, 'startReview'])->name('start-review');
        Route::post('/{document}/request-revision', [DocumentViewController::class, 'requestRevision'])->name('request-revision');
        Route::post('/{document}/verify', [DocumentViewController::class, 'verify'])->name('verify');
        Route::post('/{document}/approve', [DocumentViewController::class, 'approve'])->name('approve');
        Route::post('/{document}/publish', [DocumentViewController::class, 'publish'])->name('publish');
        Route::post('/{document}/reject', [DocumentViewController::class, 'reject'])->name('reject');
    });
    
    // Review and Approval Routes (Admin/SuperAdmin)
    Route::middleware(['can:review-documents'])->group(function () {
        Route::get('/pending-reviews', [DocumentViewController::class, 'pendingReview'])->name('documents.pending-review');
    });
    
    Route::middleware(['can:approve-documents'])->group(function () {
        Route::get('/pending-approvals', [DocumentViewController::class, 'pendingApproval'])->name('documents.pending-approval');
        
        // QR Code Management (SuperAdmin only)
        Route::prefix('qr')->name('qr.')->group(function () {
            Route::post('/generate/{document}', [QrValidationController::class, 'generate'])->name('generate');
            Route::post('/regenerate/{document}', [QrValidationController::class, 'regenerate'])->name('regenerate');
        });
    });
});

// Fallback route for 404
Route::fallback(function () {
    return view('errors.404');
});

/*
|--------------------------------------------------------------------------
| Custom Middleware Definitions
|--------------------------------------------------------------------------
*/

// Role-based middleware (you'll need to create this)
Route::middleware(['role:admin'])->group(function () {
    // Admin-only routes will be defined here if needed
});

Route::middleware(['role:superadmin'])->group(function () {
    // SuperAdmin-only routes will be defined here if needed
});

/*
|--------------------------------------------------------------------------
| API Routes for AJAX Requests
|--------------------------------------------------------------------------
*/

Route::prefix('api')->name('api.')->middleware('auth')->group(function () {
    // Get sections by department
    Route::get('/departments/{department}/sections', function (\App\Models\Department $department) {
        return response()->json(
            $department->activeSections()->orderBy('name')->get(['id', 'name', 'code'])
        );
    })->name('departments.sections');
    
    // Document search
    Route::get('/documents/search', function (\Illuminate\Http\Request $request) {
        $query = \App\Models\Document::with(['department', 'section']);
        
        // Apply user-based filters
        $user = auth()->user();
        if (!$user->isSuperAdmin()) {
            if ($user->isAdmin()) {
                $query->where('department_id', $user->department_id);
            } else {
                $query->where('creator_id', $user->id);
            }
        }
        
        if ($request->filled('q')) {
            $query->search($request->get('q'));
        }
        
        $documents = $query->orderBy('updated_at', 'desc')->take(10)->get();
        
        return response()->json([
            'documents' => $documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'title' => $document->title,
                    'document_number' => $document->document_number,
                    'status' => $document->status->getLabel(),
                    'department' => $document->department->name,
                    'section' => $document->section->name,
                    'url' => route('documents.show', $document),
                ];
            })
        ]);
    })->name('documents.search');
    
    // User notifications
    Route::get('/notifications', function () {
        $notifications = auth()->user()
            ->notifications()
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();
        
        return response()->json([
            'notifications' => $notifications,
            'unread_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    })->name('notifications.index');
    
    Route::post('/notifications/{id}/read', function (string $id) {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        
        return response()->json(['success' => true]);
    })->name('notifications.read');
    
    Route::post('/notifications/mark-all-read', function () {
        auth()->user()->unreadNotifications->markAsRead();
        
        return response()->json(['success' => true]);
    })->name('notifications.mark-all-read');
});