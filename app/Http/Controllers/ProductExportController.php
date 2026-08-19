<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductExportController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $slug = str(Auth::user()->company->nome)->slug()->value() ?: 'empresa';

        return Excel::download(new ProductsExport, "estoque-{$slug}.xlsx");
    }
}
