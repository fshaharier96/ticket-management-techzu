<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment): StreamedResponse
    {
        // Files live on a private disk and are only reachable through this authorized route.
        $this->authorize('download', $attachment);

        $disk = Storage::disk(config('tickets.attachments.disk'));
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff', // never let the browser "guess" and execute the file
        ]);
    }
}