<?php

namespace App\Http\Controllers;

use App\Exports\ProductImportTemplateExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductImportTemplateController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        return Excel::download(new ProductImportTemplateExport, 'modelo-importacao-produtos.xlsx');
    }
}
