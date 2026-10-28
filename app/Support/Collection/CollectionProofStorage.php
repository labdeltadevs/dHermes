<?php

namespace App\Support\Collection;

use App\Models\CollectionEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class CollectionProofStorage
{
    public const DISK = 'local';

    /**
     * Store the proof file of a collection event on the private disk.
     *
     * @return string The relative path of the stored file.
     */
    public function store(CollectionEvent $collectionEvent, UploadedFile $file): string
    {
        $directory = $this->directory($collectionEvent);
        $name = $file->hashName();
        $path = $directory.'/'.$name;

        $stored = Storage::disk(self::DISK)->putFileAs($directory, $file, $name);

        if ($stored === false) {
            throw new RuntimeException('No se pudo almacenar el comprobante del cobro.');
        }

        $collectionEvent->forceFill([
            'proof_disk' => self::DISK,
            'proof_path' => $path,
            'proof_original_name' => $file->getClientOriginalName(),
            'proof_mime_type' => $file->getClientMimeType(),
            'proof_size' => $file->getSize(),
        ])->save();

        return $path;
    }

    public function delete(CollectionEvent $collectionEvent): void
    {
        if ($collectionEvent->proof_path === null) {
            return;
        }

        Storage::disk($collectionEvent->proof_disk ?? self::DISK)->delete($collectionEvent->proof_path);

        $collectionEvent->forceFill([
            'proof_disk' => null,
            'proof_path' => null,
            'proof_original_name' => null,
            'proof_mime_type' => null,
            'proof_size' => null,
        ])->save();
    }

    /**
     * Absolute path of the stored proof, or null when there is none.
     */
    public function absolutePath(CollectionEvent $collectionEvent): ?string
    {
        if ($collectionEvent->proof_path === null) {
            return null;
        }

        return Storage::disk($collectionEvent->proof_disk ?? self::DISK)->path($collectionEvent->proof_path);
    }

    private function directory(CollectionEvent $collectionEvent): string
    {
        return 'collection-proofs/'.$collectionEvent->tenant_id.'/'.$collectionEvent->id;
    }
}
