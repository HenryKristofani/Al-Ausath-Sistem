<?php

namespace App\Support;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class KwitansiPdfGenerator
{
    public function generate(string $filePath, array $payload): string
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($filePath)) {
            $pdf = Pdf::loadView('pdf.kwitansi', $payload)->setPaper('a5', 'portrait');
            $disk->put($filePath, $pdf->output());
        }

        return Storage::url($filePath);
    }
}