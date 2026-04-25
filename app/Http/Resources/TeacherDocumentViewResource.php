<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherDocumentViewResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource['url'] ?? null,
            'expires_at' => $this->resource['expires_at'] ?? null,
        ];
    }
}
