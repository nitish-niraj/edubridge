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
    public static function validate(UploadedFile $file, array $allowedMimes, string $field = 'file'): void
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
    }

    public static function storeAvatarWebp(UploadedFile $file, string $disk, string $directory = 'avatars'): string
    {
        self::validate($file, ['image/jpeg', 'image/png', 'image/webp'], 'avatar');

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
        array $allowedMimes
    ): string {
        self::validate($file, $allowedMimes, $field);

        $safeExtension = strtolower($file->extension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'bin');
        $filename = Str::uuid() . '.' . $safeExtension;
        $path = trim($directory, '/') . '/' . $filename;

        Storage::disk($disk)->putFileAs($directory, $file, $filename);

        return $path;
    }
}
