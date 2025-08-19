<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentDownload;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QrValidationController extends Controller
{
    protected QrCodeService $qrCodeService;

    public function __construct(QrCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Validate QR code and redirect to document.
     */
    public function validate(Document $document, string $token, Request $request)
    {
        try {
            // Validate QR code
            if (!$this->qrCodeService->validateQrCode($document, $token)) {
                abort(404, 'Invalid QR code or document not found');
            }

            // Record QR code scan
            DocumentDownload::createRecord($document, [
                'download_type' => 'view',
                'access_method' => 'qr_code',
                'user_type' => 'guest'
            ]);

            // Increment view count
            $document->incrementViewCount();

            // Redirect to public document page with QR flag
            return redirect()->route('public.documents.show', [
                'document' => $document,
                'via' => 'qr'
            ]);

        } catch (\Exception $e) {
            Log::error("QR validation failed: " . $e->getMessage());
            abort(404, 'Invalid QR code');
        }
    }

    /**
     * API endpoint for QR validation.
     */
    public function validateApi(Document $document, string $token, Request $request)
    {
        try {
            $isValid = $this->qrCodeService->validateQrCode($document, $token);

            if ($isValid) {
                // Record QR code scan
                DocumentDownload::createRecord($document, [
                    'download_type' => 'api_validation',
                    'access_method' => 'qr_code',
                    'user_type' => 'api'
                ]);

                return response()->json([
                    'valid' => true,
                    'document' => [
                        'id' => $document->id,
                        'title' => $document->title,
                        'document_number' => $document->document_number,
                        'published_at' => $document->published_at,
                        'url' => route('public.documents.show', $document)
                    ]
                ]);
            } else {
                return response()->json([
                    'valid' => false,
                    'message' => 'Invalid QR code or document not found'
                ], 404);
            }

        } catch (\Exception $e) {
            Log::error("QR API validation failed: " . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Validation failed'
            ], 500);
        }
    }

    /**
     * QR code scanner page.
     */
    public function scanner()
    {
        return view('qr.scanner');
    }

    /**
     * QR code help page.
     */
    public function help()
    {
        return view('qr.help');
    }

    /**
     * Generate QR code for document (admin only).
     */
    public function generate(Document $document)
    {
        try {
            if (!auth()->user()->canApprove()) {
                abort(403, 'Unauthorized');
            }

            if (!$document->isPublished()) {
                return back()->with('error', 'Only published documents can have QR codes generated');
            }

            if ($this->qrCodeService->generateForDocument($document)) {
                return back()->with('success', 'QR code generated successfully');
            } else {
                return back()->with('error', 'Failed to generate QR code');
            }

        } catch (\Exception $e) {
            Log::error("QR generation failed: " . $e->getMessage());
            return back()->with('error', 'QR code generation failed: ' . $e->getMessage());
        }
    }

    /**
     * Regenerate QR code for document (admin only).
     */
    public function regenerate(Document $document)
    {
        try {
            if (!auth()->user()->canApprove()) {
                abort(403, 'Unauthorized');
            }

            if (!$document->isPublished()) {
                return back()->with('error', 'Only published documents can have QR codes');
            }

            if ($this->qrCodeService->regenerateForDocument($document)) {
                return back()->with('success', 'QR code regenerated successfully');
            } else {
                return back()->with('error', 'Failed to regenerate QR code');
            }

        } catch (\Exception $e) {
            Log::error("QR regeneration failed: " . $e->getMessage());
            return back()->with('error', 'QR code regeneration failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate QR data from scanner.
     */
    public function validateQrData(Request $request)
    {
        $request->validate([
            'qr_data' => 'required|string'
        ]);

        try {
            $qrData = $request->input('qr_data');
            
            // Parse QR data to extract document ID and token
            if (preg_match('/\/qr\/validate\/(\d+)\/([a-f0-9]+)/', $qrData, $matches)) {
                $documentId = $matches[1];
                $token = $matches[2];

                $document = Document::find($documentId);
                if (!$document) {
                    return response()->json([
                        'valid' => false,
                        'message' => 'Document not found'
                    ], 404);
                }

                return $this->validateApi($document, $token, $request);
            } else {
                return response()->json([
                    'valid' => false,
                    'message' => 'Invalid QR code format'
                ], 400);
            }

        } catch (\Exception $e) {
            Log::error("QR data validation failed: " . $e->getMessage());
            return response()->json([
                'valid' => false,
                'message' => 'Validation failed'
            ], 500);
        }
    }

    /**
     * Get QR code statistics (admin only).
     */
    public function statistics()
    {
        try {
            if (!auth()->check()) {
                abort(403, 'Unauthorized');
            }

            $stats = $this->qrCodeService->getQrCodeStats();
            
            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error("QR statistics failed: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to get statistics'
            ], 500);
        }
    }
}