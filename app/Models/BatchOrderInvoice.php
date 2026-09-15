<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchOrderInvoice extends Model
{
    protected $fillable = [
        'batch_order_id',
        'path',
        'original_name',
        'mime_type',
        'size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    // --- Relationships ---

    public function batchOrder(): BelongsTo
    {
        return $this->belongsTo(BatchOrder::class);
    }

    // --- Helpers ---

    /**
     * Friendly filename (falls back to the stored basename).
     */
    public function getFilenameAttribute(): string
    {
        return $this->original_name ?: basename((string) $this->path);
    }

    /**
     * File extension in lower case, e.g. "pdf".
     */
    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->getFilenameAttribute(), PATHINFO_EXTENSION));
    }

    /**
     * Whether this attachment is an image (so the gallery can show a thumbnail).
     */
    public function getIsImageAttribute(): bool
    {
        if ($this->mime_type) {
            return str_starts_with($this->mime_type, 'image/');
        }

        return in_array($this->getExtensionAttribute(), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    /**
     * Human readable file size, e.g. "1.2 MB".
     */
    public function getHumanSizeAttribute(): ?string
    {
        $bytes = $this->size;

        if (! $bytes || $bytes <= 0) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return round($value, $power === 0 ? 0 : 1).' '.$units[$power];
    }
}
