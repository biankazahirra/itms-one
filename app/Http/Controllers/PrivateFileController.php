<?php

namespace App\Http\Controllers;

use App\Services\PrivateFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileController extends Controller
{
    /**
     * Download a file from private storage through an authorized route.
     */
    public function __invoke(PrivateFileService $files, string $path): StreamedResponse
    {
        return $files->download($path);
    }
}
