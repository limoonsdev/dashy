<?php

namespace App\Http\Controllers;

use App\Services\Cli\CliBinaryBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a CLI binary behind a temporary signed URL. The signature is the
 * credential: anyone holding the link can download for two hours, and nothing
 * else on the instance becomes reachable.
 */
class CliDownloadController extends Controller
{
    public function __invoke(Request $request, string $platform, CliBinaryBuilder $builder): BinaryFileResponse
    {
        abort_unless(array_key_exists($platform, CliBinaryBuilder::PLATFORMS), 404);

        $path = $builder->ensure($platform);

        return response()
            ->download($path, $builder->downloadName($platform), [
                'Content-Type' => 'application/octet-stream',
                'Cache-Control' => 'no-store',
            ])
            ->deleteFileAfterSend(false);
    }
}
