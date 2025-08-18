<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'user_id',
        'download_type',
        'user_type',
        'session_id',
        'ip_address',
        'user_agent',
        'referer',
        'request_headers',
        'access_method',
        'device_type',
        'browser',
        'platform',
        'country_code',
        'city',
        'is_successful',
        'error_message',
        'bytes_served',
        'duration_ms',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'is_successful' => 'boolean',
        'bytes_served' => 'integer',
        'duration_ms' => 'integer',
    ];

    /**
     * Get the document that was downloaded.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the user who downloaded (if authenticated).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get download type icon.
     */
    public function getDownloadTypeIconAttribute(): string
    {
        return match ($this->download_type) {
            'view' => 'heroicon-o-eye',
            'download' => 'heroicon-o-arrow-down-tray',
            'print' => 'heroicon-o-printer',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Get download type color.
     */
    public function getDownloadTypeColorAttribute(): string
    {
        return match ($this->download_type) {
            'view' => 'info',
            'download' => 'success',
            'print' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Get access method icon.
     */
    public function getAccessMethodIconAttribute(): string
    {
        return match ($this->access_method) {
            'web' => 'heroicon-o-computer-desktop',
            'api' => 'heroicon-o-code-bracket',
            'qr_code' => 'heroicon-o-qr-code',
            'direct_link' => 'heroicon-o-link',
            default => 'heroicon-o-globe-alt',
        };
    }

    /**
     * Get formatted bytes served.
     */
    public function getFormattedBytesSizeAttribute(): ?string
    {
        if (!$this->bytes_served) {
            return null;
        }

        $bytes = $this->bytes_served;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration_ms) {
            return null;
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }

        $seconds = round($this->duration_ms / 1000, 2);
        return $seconds . 's';
    }

    /**
     * Check if download was from mobile device.
     */
    public function isMobile(): bool
    {
        return $this->device_type === 'mobile';
    }

    /**
     * Check if download was from authenticated user.
     */
    public function isAuthenticated(): bool
    {
        return $this->user_type === 'authenticated' && $this->user_id !== null;
    }

    /**
     * Check if download was via QR code.
     */
    public function isViaQrCode(): bool
    {
        return $this->access_method === 'qr_code';
    }

    /**
     * Create download record.
     */
    public static function createRecord(Document $document, array $data = []): self
    {
        $request = request();
        $userAgent = $request->userAgent();
        
        // Parse user agent for device info
        $deviceInfo = self::parseUserAgent($userAgent);
        
        $defaultData = [
            'document_id' => $document->id,
            'user_id' => auth()->id(),
            'download_type' => 'view',
            'user_type' => auth()->check() ? 'authenticated' : 'guest',
            'session_id' => session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent,
            'referer' => $request->header('referer'),
            'request_headers' => $request->headers->all(),
            'access_method' => 'web',
            'device_type' => $deviceInfo['device_type'],
            'browser' => $deviceInfo['browser'],
            'platform' => $deviceInfo['platform'],
            'is_successful' => true,
        ];

        return self::create(array_merge($defaultData, $data));
    }

    /**
     * Parse user agent for device information.
     */
    private static function parseUserAgent(?string $userAgent): array
    {
        if (!$userAgent) {
            return [
                'device_type' => 'unknown',
                'browser' => 'unknown',
                'platform' => 'unknown',
            ];
        }

        // Simple user agent parsing (you might want to use a library like jenssegers/agent)
        $isMobile = preg_match('/Mobile|Android|iPhone|iPad/', $userAgent);
        $isTablet = preg_match('/iPad|Tablet/', $userAgent);
        
        $deviceType = $isMobile ? ($isTablet ? 'tablet' : 'mobile') : 'desktop';
        
        // Simple browser detection
        $browser = 'unknown';
        if (preg_match('/Chrome/', $userAgent)) $browser = 'Chrome';
        elseif (preg_match('/Firefox/', $userAgent)) $browser = 'Firefox';
        elseif (preg_match('/Safari/', $userAgent)) $browser = 'Safari';
        elseif (preg_match('/Edge/', $userAgent)) $browser = 'Edge';
        
        // Simple platform detection
        $platform = 'unknown';
        if (preg_match('/Windows/', $userAgent)) $platform = 'Windows';
        elseif (preg_match('/Mac/', $userAgent)) $platform = 'macOS';
        elseif (preg_match('/Linux/', $userAgent)) $platform = 'Linux';
        elseif (preg_match('/Android/', $userAgent)) $platform = 'Android';
        elseif (preg_match('/iOS/', $userAgent)) $platform = 'iOS';

        return [
            'device_type' => $deviceType,
            'browser' => $browser,
            'platform' => $platform,
        ];
    }

    /**
     * Scope for specific document.
     */
    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    /**
     * Scope for authenticated users only.
     */
    public function scopeAuthenticated($query)
    {
        return $query->where('user_type', 'authenticated')->whereNotNull('user_id');
    }

    /**
     * Scope for guest users only.
     */
    public function scopeGuest($query)
    {
        return $query->where('user_type', 'guest');
    }

    /**
     * Scope for specific download type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('download_type', $type);
    }

    /**
     * Scope for successful downloads only.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('is_successful', true);
    }

    /**
     * Scope for mobile devices.
     */
    public function scopeMobile($query)
    {
        return $query->where('device_type', 'mobile');
    }

    /**
     * Scope for QR code access.
     */
    public function scopeViaQrCode($query)
    {
        return $query->where('access_method', 'qr_code');
    }
}