<?php

use App\Http\Controllers\ProductExportController;
use App\Http\Controllers\ProductImportTemplateController;
use App\Livewire\Categories\CategoryIndex;
use App\Livewire\Dashboard;
use App\Livewire\Invitations\InvitationAccept;
use App\Livewire\Invitations\InvitationIndex;
use App\Livewire\Products\ProductIndex;
use App\Livewire\Products\ProductShow;
use Illuminate\Support\Facades\Route;

// A raiz não tem página de marketing: este é um app interno de empresa,
// quem chega aqui ou já usa o sistema ou vai logar. Manda direto pro
// lugar certo em vez de servir uma landing page que ninguém leria.
Route::get('/', fn () => redirect()->route(
    auth()->check() ? 'dashboard' : 'login'
));

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

    Route::get('categorias', CategoryIndex::class)->name('categories.index');
    Route::get('produtos-exportar', ProductExportController::class)->name('products.export');
    Route::get('produtos-modelo-planilha', ProductImportTemplateController::class)->name('products.import-template');

    Route::view('profile', 'profile')->name('profile');

    Route::middleware('admin')->group(function () {
        Route::get('convites', InvitationIndex::class)->name('invitations.index');
    });
});

require __DIR__.'/auth.php';
