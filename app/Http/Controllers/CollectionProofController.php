<?php

namespace App\Http\Controllers;

use App\Models\CollectionEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CollectionProofController extends Controller
{
    public function __invoke(string $collectionEvent): StreamedResponse
    {
        $event = CollectionEvent::query()
            ->withoutGlobalScope('tenant')
            ->findOrFail($collectionEvent);

        Gate::authorize('view', $event);

        abort_if($event->proof_path === null, 404);

        $disk = Storage::disk($event->proof_disk ?? 'local');

        abort_if(! $disk->exists($event->proof_path), 404);

        $name = $event->proof_original_name ?? basename($event->proof_path);

        return $disk->response($event->proof_path, $name, [
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ], 'inline');
    }
}
