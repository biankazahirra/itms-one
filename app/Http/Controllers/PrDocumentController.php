<?php

namespace App\Http\Controllers;

use App\Models\PrDocument;
use App\Models\PurchaseRequest;
use App\Services\PrDocumentArchiveService;
use App\Services\PrivateFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrDocumentController extends Controller
{
    public function __construct(
        private readonly PrDocumentArchiveService $archive,
        private readonly PrivateFileService $files,
    ) {}

    public function store(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(PrDocument::CATEGORIES)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.PrDocument::MAX_FILE_SIZE_KB],
        ], [
            'file.mimes' => 'Berkas harus PDF, JPG, JPEG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ], [
            'category' => 'Kategori',
            'notes' => 'Catatan',
            'file' => 'Berkas',
        ]);

        $this->archive->upload($purchaseRequest, $data, $request->file('file'), $request->user());

        return back()->with('status', 'Dokumen berhasil diunggah.');
    }

    public function preview(PurchaseRequest $purchaseRequest, PrDocument $prDocument): StreamedResponse
    {
        abort_unless($prDocument->canPreview(), 404);

        return $this->files->preview($prDocument->storage_path, $prDocument->original_filename);
    }

    public function download(PurchaseRequest $purchaseRequest, PrDocument $prDocument): StreamedResponse
    {
        return $this->files->download($prDocument->storage_path, $prDocument->original_filename);
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest, PrDocument $prDocument): RedirectResponse
    {
        $this->archive->delete($prDocument, $request->user());

        return back()->with('status', 'Dokumen dipindahkan ke Recycle Bin.');
    }
}
