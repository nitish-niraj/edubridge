<?php

namespace App\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Facades\Image;

class UploadSecurity
{
    /**
     * @param array<int, string> $allowedMimes
     */
    public static function validate(
        UploadedFile $file,
        array $allowedMimes,
        string $field = 'file',
        ?int $maxBytes = null
    ): void
    {
        $detectedMime = mime_content_type($file->getRealPath()) ?: '';

        if (! in_array($detectedMime, $allowedMimes, true)) {
            throw ValidationException::withMessages([
                $field => 'Invalid file type',
            ]);
        }

        if (substr_count($file->getClientOriginalName(), '.') > 1) {
            throw ValidationException::withMessages([
                $field => 'Invalid filename',
            ]);
        }

        if ($maxBytes !== null && $file->getSize() && (int) $file->getSize() > $maxBytes) {
            throw ValidationException::withMessages([
                $field => 'File is too large',
            ]);
        }
    }

    public static function storeAvatarWebp(UploadedFile $file, string $disk, string $directory = 'avatars'): string
    {
        self::validate($file, ['image/jpeg', 'image/png', 'image/webp'], 'avatar', 2 * 1024 * 1024);

        $image = Image::make($file->getRealPath())
            ->fit(300, 300, function ($constraint): void {
                $constraint->upsize();
            })
            ->encode('webp', 85);

        $path = trim($directory, '/') . '/' . Str::uuid() . '.webp';
        Storage::disk($disk)->put($path, (string) $image);

        return $path;
    }

    public static function storeValidatedFile(
        UploadedFile $file,
        string $disk,
        string $directory,
        string $field,
        array $allowedMimes,
        ?int $maxBytes = null
    ): string {
        self::validate($file, $allowedMimes, $field, $maxBytes);

        $safeExtension = strtolower($file->extension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'bin');
        $filename = Str::uuid() . '.' . $safeExtension;
        $path = trim($directory, '/') . '/' . $filename;

        Storage::disk($disk)->putFileAs($directory, $file, $filename);

        return $path;
    }
}
