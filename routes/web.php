<?php

use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProductImportTemplateController;
use App\Livewire\Dashboard;
use App\Livewire\Invitations\InvitationAccept;
use App\Livewire\Invitations\InvitationIndex;
use App\Livewire\Products\ProductIndex;
use App\Livewire\Products\ProductShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// Aceitar convite é uma rota "guest": quem clica no link do e-mail ainda
// não tem sessão nenhuma. O token na URL é a própria autenticação desse
// fluxo — ver InvitationAccept.
Route::get('convite/{token}', InvitationAccept::class)
    ->middleware('guest')
    ->name('invitations.accept');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');

    Route::get('produtos', ProductIndex::class)->name('products.index');
    Route::get('produtos/{product}', ProductShow::class)->name('products.show');
    Route::get('produtos-exportar', ProductExportController::class)->name('products.export');
    Route::get('produtos-modelo-planilha', ProductImportTemplateController::class)->name('products.import-template');

    Route::view('profile', 'profile')->name('profile');

    Route::middleware('admin')->group(function () {
        Route::get('convites', InvitationIndex::class)->name('invitations.index');
    });
});

require __DIR__.'/auth.php';
