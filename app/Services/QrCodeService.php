<?php

namespace App\Services;

use App\Models\Document;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;
use Barryvdh\DomPDF\Facade\Pdf;

class QrCodeService
{
    /**
     * Generate QR code for document.
     */
    public function generateQrCode(Document $document): string
    {
        // Generate validation token if not exists
        if (!$document->validation_token) {
            $document->generateValidationToken();
        }

        // Create QR code data
        $qrData = [
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'validation_token' => $document->validation_token,
            'validation_url' => route('qr.validate', [
                'document' => $document->id,
                'token' => $document->validation_token
            ])
        ];

        // Generate QR code
        $qrCodeContent = QrCode::format('png')
            ->size(200)
            ->margin(2)
            ->errorCorrection('M')
            ->generate(json_encode($qrData));

        // Create filename
        $fileName = "qr_code_{$document->id}_{$document->validation_token}.png";
        $qrCodePath = "qrcodes/{$fileName}";

        // Store QR code
        Storage::put($qrCodePath, $qrCodeContent);

        // Update document with QR code path
        $document->update(['qr_code_path' => $qrCodePath]);

        return $qrCodePath;
    }

    /**
     * Generate QR code with logo (company logo).
     */
    public function generateQrCodeWithLogo(Document $document, ?string $logoPath = null): string
    {
        // Generate basic QR code first
        $qrCodePath = $this->generateQrCode($document);

        if (!$logoPath || !Storage::exists($logoPath)) {
            return $qrCodePath;
        }

        try {
            // Load QR code image
            $qrCodeFullPath = Storage::path($qrCodePath);
            $qrImage = Image::make($qrCodeFullPath);

            // Load logo
            $logoFullPath = Storage::path($logoPath);
            $logo = Image::make($logoFullPath);

            // Resize logo to fit in center (about 20% of QR code size)
            $logoSize = min($qrImage->width(), $qrImage->height()) * 0.2;
            $logo->resize($logoSize, $logoSize, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Add white background to logo for better visibility
            $logoWithBg = Image::canvas($logoSize + 10, $logoSize + 10, '#ffffff');
            $logoWithBg->insert($logo, 'center');

            // Insert logo into QR code center
            $qrImage->insert($logoWithBg, 'center');

            // Save the modified QR code
            $qrImage->save($qrCodeFullPath);

            return $qrCodePath;
        } catch (\Exception $e) {
            // If logo processing fails, return basic QR code
            return $qrCodePath;
        }
    }

    /**
     * Validate QR code data.
     */
    public function validateQrCode(string $qrData): array
    {
        try {
            $data = json_decode($qrData, true);
            
            if (!$data || !isset($data['document_id']) || !isset($data['validation_token'])) {
                return ['valid' => false, 'message' => 'Invalid QR code format'];
            }

            $document = Document::find($data['document_id']);
            
            if (!$document) {
                return ['valid' => false, 'message' => 'Document not found'];
            }

            if ($document->validation_token !== $data['validation_token']) {
                return ['valid' => false, 'message' => 'Invalid validation token'];
            }

            if (!$document->isPublished()) {
                return ['valid' => false, 'message' => 'Document is not published'];
            }

            return [
                'valid' => true,
                'document' => $document,
                'message' => 'Document is valid and authentic'
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Invalid QR code data'];
        }
    }

    /**
     * Validate QR code by document ID and token.
     */
    public function validateByDocumentAndToken(int $documentId, string $token): array
    {
        $document = Document::find($documentId);
        
        if (!$document) {
            return ['valid' => false, 'message' => 'Document not found'];
        }

        if ($document->validation_token !== $token) {
            return ['valid' => false, 'message' => 'Invalid validation token'];
        }

        if (!$document->isPublished()) {
            return ['valid' => false, 'message' => 'Document is not published'];
        }

        return [
            'valid' => true,
            'document' => $document,
            'message' => 'Document is valid and authentic',
            'validated_at' => now(),
            'validation_details' => [
                'document_number' => $document->document_number,
                'title' => $document->title,
                'published_at' => $document->published_at,
                'department' => $document->department->name,
                'section' => $document->section->name,
            ]
        ];
    }

    /**
     * Inject QR code into PDF document.
     */
    public function injectQrCodeIntoPdf(Document $document): bool
    {
        if (!$document->qr_code_path || !Storage::exists($document->qr_code_path)) {
            // Generate QR code if not exists
            $this->generateQrCode($document);
        }

        if (!Storage::exists($document->file_path)) {
            return false;
        }

        try {
            // For PDF files, we'll create a new page with QR code
            // This is a simplified version - you might want to use a more sophisticated PDF library
            
            $qrCodePath = Storage::path($document->qr_code_path);
            $originalPdfPath = Storage::path($document->file_path);
            
            // Create HTML template for QR code page
            $qrHtml = $this->createQrCodePageHtml($document, $qrCodePath);
            
            // Generate PDF with QR code page
            $qrPdf = Pdf::loadHTML($qrHtml);
            
            // Save QR code page as separate PDF
            $qrPdfPath = str_replace('.pdf', '_with_qr.pdf', $document->file_path);
            $qrPdf->save(Storage::path($qrPdfPath));
            
            // Update document file path
            $document->update(['file_path' => $qrPdfPath]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create HTML for QR code page.
     */
    protected function createQrCodePageHtml(Document $document, string $qrCodePath): string
    {
        $qrCodeBase64 = base64_encode(file_get_contents($qrCodePath));
        
        return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .qr-container { text-align: center; margin-top: 50px; }
                .qr-code { margin: 20px 0; }
                .document-info { margin-top: 30px; font-size: 12px; color: #666; }
                .validation-info { margin-top: 20px; font-size: 10px; color: #999; }
            </style>
        </head>
        <body>
            <div class='qr-container'>
                <h2>Document Verification</h2>
                <div class='qr-code'>
                    <img src='data:image/png;base64,{$qrCodeBase64}' alt='QR Code' style='width: 150px; height: 150px;'>
                </div>
                <div class='document-info'>
                    <p><strong>Document:</strong> {$document->title}</p>
                    <p><strong>Document Number:</strong> {$document->document_number}</p>
                    <p><strong>Department:</strong> {$document->department->name}</p>
                    <p><strong>Section:</strong> {$document->section->name}</p>
                    <p><strong>Published:</strong> {$document->published_at->format('d M Y')}</p>
                </div>
                <div class='validation-info'>
                    <p>Scan this QR code to validate the authenticity of this document.</p>
                    <p>Validation URL: " . route('qr.validate', ['document' => $document->id, 'token' => $document->validation_token]) . "</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Generate batch QR codes for multiple documents.
     */
    public function generateBatchQrCodes(array $documentIds): array
    {
        $results = [];
        
        foreach ($documentIds as $documentId) {
            $document = Document::find($documentId);
            
            if ($document && $document->isPublished()) {
                try {
                    $qrCodePath = $this->generateQrCode($document);
                    $results[$documentId] = ['success' => true, 'path' => $qrCodePath];
                } catch (\Exception $e) {
                    $results[$documentId] = ['success' => false, 'error' => $e->getMessage()];
                }
            } else {
                $results[$documentId] = ['success' => false, 'error' => 'Document not found or not published'];
            }
        }
        
        return $results;
    }

    /**
     * Regenerate QR code (useful when document is updated).
     */
    public function regenerateQrCode(Document $document): string
    {
        // Delete old QR code if exists
        if ($document->qr_code_path && Storage::exists($document->qr_code_path)) {
            Storage::delete($document->qr_code_path);
        }

        // Generate new validation token
        $document->generateValidationToken();

        // Generate new QR code
        return $this->generateQrCode($document);
    }

    /**
     * Get QR code statistics.
     */
    public function getQrCodeStatistics(): array
    {
        $documentsWithQr = Document::whereNotNull('qr_code_path')->count();
        $publishedDocuments = Document::published()->count();
        $totalValidations = \DB::table('document_downloads')
            ->where('access_method', 'qr_code')
            ->count();
        
        return [
            'documents_with_qr' => $documentsWithQr,
            'published_documents' => $publishedDocuments,
            'qr_coverage_percentage' => $publishedDocuments > 0 ? round(($documentsWithQr / $publishedDocuments) * 100, 2) : 0,
            'total_validations' => $totalValidations,
            'recent_validations' => \DB::table('document_downloads')
                ->where('access_method', 'qr_code')
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ];
    }
}