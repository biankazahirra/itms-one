<?php

namespace App\Http\Controllers;

use App\Models\GeneratedDocument;
use App\Services\PrivateFileService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GeneratedDocumentController extends Controller
{
    /**
     * Download a generated document through an authorized route.
     */
    public function __invoke(GeneratedDocument $document, PrivateFileService $files): StreamedResponse
    {
        abort_unless(in_array($document->entity_type, [
            GeneratedDocument::ENTITY_PURCHASE_REQUEST,
            GeneratedDocument::ENTITY_BUDGET,
            GeneratedDocument::ENTITY_LHSTP,
        ], true), 404);

        return $files->download($document->storage_path, $document->original_filename);
    }
}
