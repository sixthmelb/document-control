<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class QrValidationController extends Controller
{
    protected QrCodeService $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Validate QR code and show document information.
     */
    public function validate(Document $document, string $token, Request $request): View
    {
        // Validate the document and token
        $validation = $this->qrCodeService->validateByDocumentAndToken($document->id, $token);

        // Record QR code access attempt
        DocumentDownload::createRecord($document, [
            'download_type' => 'view',
            'access_method' => 'qr_code',
            'is_successful' => $validation['valid'],
            'error_message' => $validation['valid'] ? null : $validation['message'],
            'additional_data' => [
                'validation_token' => $token,
                'validation_result' => $validation,
            ]
        ]);

        if (!$validation['valid']) {
            return view('qr.invalid', [
                'message' => $validation['message'],
                'document_id' => $document->id,
            ]);
        }

        // Increment view count for valid QR access
        $document->incrementViewCount();

        return view('qr.valid', [
            'document' => $validation['document'],
            'validation' => $validation,
        ]);
    }

    /**
     * API endpoint for QR code validation (JSON response).
     */
    public function validateApi(Document $document, string $token, Request $request): JsonResponse
    {
        // Validate the document and token
        $validation = $this->qrCodeService->validateByDocumentAndToken($document->id, $token);

        // Record API access attempt
        DocumentDownload::createRecord($document, [
            'download_type' => 'view',
            'access_method' => 'api',
            'user_type' => 'api',
            'is_successful' => $validation['valid'],
            'error_message' => $validation['valid'] ? null : $validation['message'],
            'additional_data' => [
                'validation_token' => $token,
                'api_endpoint' => $request->fullUrl(),
            ]
        ]);

        if (!$validation['valid']) {
            return response()->json([
                'valid' => false,
                'message' => $validation['message'],
                'timestamp' => now()->toISOString(),
            ], 400);
        }

        // Increment view count for valid API access
        $document->incrementViewCount();

        return response()->json([
            'valid' => true,
            'message' => $validation['message'],
            'document' => [
                'id' => $document->id,
                'document_number' => $document->document_number,
                'title' => $document->title,
                'description' => $document->description,
                'document_type' => $document->document_type,
                'version' => $document->version,
                'published_at' => $document->published_at->toISOString(),
                'department' => [
                    'name' => $document->department->name,
                    'code' => $document->department->code,
                ],
                'section' => [
                    'name' => $document->section->name,
                    'code' => $document->section->code,
                ],
                'creator' => [
                    'name' => $document->creator->name,
                    'department' => $document->creator->department->name ?? null,
                ],
                'file_size' => $document->formatted_file_size,
                'download_url' => route('public.documents.download', $document),
                'view_url' => route('public.documents.show', $document),
            ],
            'validation_details' => $validation['validation_details'],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Validate QR code data from JSON payload.
     */
    public function validateQrData(Request $request): JsonResponse
    {
        $request->validate([
            'qr_data' => 'required|string',
        ]);

        $qrData = $request->input('qr_data');
        $validation = $this->qrCodeService->validateQrCode($qrData);

        // If validation is successful, record the access
        if ($validation['valid'] && isset($validation['document'])) {
            $document = $validation['document'];
            
            DocumentDownload::createRecord($document, [
                'download_type' => 'view',
                'access_method' => 'qr_code',
                'user_type' => 'api',
                'is_successful' => true,
                'additional_data' => [
                    'qr_data_length' => strlen($qrData),
                    'validation_method' => 'qr_data_payload',
                ]
            ]);

            $document->incrementViewCount();

            return response()->json([
                'valid' => true,
                'message' => $validation['message'],
                'document' => [
                    'id' => $document->id,
                    'document_number' => $document->document_number,
                    'title' => $document->title,
                    'published_at' => $document->published_at->toISOString(),
                    'department' => $document->department->name,
                    'section' => $document->section->name,
                    'view_url' => route('public.documents.show', $document),
                    'download_url' => route('public.documents.download', $document),
                ],
                'timestamp' => now()->toISOString(),
            ]);
        }

        return response()->json([
            'valid' => false,
            'message' => $validation['message'],
            'timestamp' => now()->toISOString(),
        ], 400);
    }

    /**
     * Show QR code scanner page.
     */
    public function scanner(Request $request): View
    {
        return view('qr.scanner');
    }

    /**
     * Generate QR code for a specific document (admin only).
     */
    public function generate(Document $document, Request $request): JsonResponse
    {
        // Check if user is authenticated and has permission
        if (!auth()->check() || !auth()->user()->canReview()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Check if document is published
        if (!$document->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Document must be published to generate QR code',
            ], 400);
        }

        try {
            $qrCodePath = $this->qrCodeService->generateQrCode($document);
            
            return response()->json([
                'success' => true,
                'message' => 'QR code generated successfully',
                'qr_code_url' => $document->qr_code_url,
                'validation_url' => route('qr.validate', [
                    'document' => $document->id,
                    'token' => $document->validation_token
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate QR code for a document (admin only).
     */
    public function regenerate(Document $document, Request $request): JsonResponse
    {
        // Check if user is authenticated and has permission
        if (!auth()->check() || !auth()->user()->canReview()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        if (!$document->isPublished()) {
            return response()->json([
                'success' => false,
                'message' => 'Document must be published to regenerate QR code',
            ], 400);
        }

        try {
            $qrCodePath = $this->qrCodeService->regenerateQrCode($document);
            
            return response()->json([
                'success' => true,
                'message' => 'QR code regenerated successfully',
                'qr_code_url' => $document->qr_code_url,
                'validation_url' => route('qr.validate', [
                    'document' => $document->id,
                    'token' => $document->validation_token
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to regenerate QR code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get QR code validation statistics (admin only).
     */
    public function statistics(Request $request): JsonResponse
    {
        if (!auth()->check() || !auth()->user()->canReview()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $stats = $this->qrCodeService->getQrCodeStatistics();
        
        // Add recent validation activity
        $recentValidations = DocumentDownload::with(['document:id,title,document_number', 'user:id,name'])
            ->where('access_method', 'qr_code')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get()
            ->map(function ($download) {
                return [
                    'document_title' => $download->document->title,
                    'document_number' => $download->document->document_number,
                    'user_name' => $download->user->name ?? 'Anonymous',
                    'is_successful' => $download->is_successful,
                    'created_at' => $download->created_at->toISOString(),
                    'ip_address' => $download->ip_address,
                    'device_type' => $download->device_type,
                ];
            });

        return response()->json([
            'success' => true,
            'statistics' => $stats,
            'recent_validations' => $recentValidations,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Show help page for QR code usage.
     */
    public function help(): View
    {
        return view('qr.help');
    }
}