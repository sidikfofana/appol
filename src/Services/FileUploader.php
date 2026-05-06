<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Illuminate\Support\Str;

class FileUploader
{
    public function upload(UploadedFile $file, $path = 'images')
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $originalFilename;
        $fileName = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($path, $fileName);
        } catch (FileException $e) {
            return $e->getMessage();
        }

        return $fileName;
    }
}
