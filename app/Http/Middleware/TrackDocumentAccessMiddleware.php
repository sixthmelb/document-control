<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Document;
use App\Models\DocumentDownload;

class TrackDocumentAccessMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only track successful responses (200-299)
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->trackAccess($request, $response);
        }

        return $response;
    }

    /**
     * Track document access.
     */
    protected function trackAccess(Request $request, Response $response): void
    {
        try {
            // Extract document ID from route parameters
            $document = $request->route('document');
            
            if (!$document instanceof Document) {
                return;
            }

            // Determine access type based on route
            $routeName = $request->route()->getName();
            $accessType = $this->getAccessType($routeName);

            if (!$accessType) {
                return;
            }

            // Check if we should track this request (avoid duplicate tracking)
            if ($this->shouldSkipTracking($request)) {
                return;
            }

            // Create download record
            DocumentDownload::createRecord($document, [
                'download_type' => $accessType,
                'access_method' => $this->getAccessMethod($request),
                'bytes_served' => $this->getResponseSize($response),
                'duration_ms' => $this->getRequestDuration($request),
            ]);

        } catch (\Exception $e) {
            // Log error but don't break the request
            \Log::warning('Failed to track document access', [
                'error' => $e->getMessage(),
                'request_url' => $request->fullUrl(),
                'user_id' => auth()->id(),
            ]);
        }
    }

    /**
     * Get access type based on route name.
     */
    protected function getAccessType(string $routeName): ?string
    {
        return match ($routeName) {
            'documents.show', 'public.documents.show' => 'view',
            'documents.download', 'public.documents.download' => 'download',
            'documents.view', 'public.documents.view' => 'view',
            'qr.validate' => 'view',
            default => null,
        };
    }

    /**
     * Get access method based on request.
     */
    protected function getAccessMethod(Request $request): string
    {
        // Check if accessed via QR code
        if ($request->query('via') === 'qr' || 
            str_contains($request->route()->getName(), 'qr.')) {
            return 'qr_code';
        }

        // Check if it's an API request
        if ($request->expectsJson() || 
            str_contains($request->route()->getName(), 'api.')) {
            return 'api';
        }

        // Check referer for direct link access
        $referer = $request->header('referer');
        if (!$referer || !str_contains($referer, $request->getHost())) {
            return 'direct_link';
        }

        return 'web';
    }

    /**
     * Get response size in bytes.
     */
    protected function getResponseSize(Response $response): ?int
    {
        $content = $response->getContent();
        return $content ? strlen($content) : null;
    }

    /**
     * Get request duration in milliseconds.
     */
    protected function getRequestDuration(Request $request): ?int
    {
        if (!defined('LARAVEL_START')) {
            return null;
        }

        return (int) ((microtime(true) - LARAVEL_START) * 1000);
    }

    /**
     * Check if we should skip tracking this request.
     */
    protected function shouldSkipTracking(Request $request): bool
    {
        // Skip if this is a preflight request
        if ($request->isMethod('OPTIONS')) {
            return true;
        }

        // Skip if this is a bot/crawler request
        $userAgent = $request->userAgent();
        $botPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'googlebot', 'bingbot', 'slurp', 'duckduckbot'
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        // Skip if same user accessed same document in last 5 minutes (to avoid spam)
        $cacheKey = 'doc_access_' . auth()->id() . '_' . $request->route('document')->id;
        if (cache()->has($cacheKey)) {
            return true;
        }

        // Set cache to prevent duplicate tracking for 5 minutes
        cache()->put($cacheKey, true, now()->addMinutes(5));

        return false;
    }
}