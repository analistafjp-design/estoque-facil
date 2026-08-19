<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway, Render e afins terminam o TLS no proxy deles e repassam
        // a requisição pro container em HTTP puro, sinalizando o esquema
        // original via X-Forwarded-Proto. Sem confiar nesse cabeçalho, o
        // Laravel acha que está servindo em http:// e gera URL absoluta
        // http:// para os assets do Vite — que o navegador BLOQUEIA como
        // mixed content numa página https, sem nem chegar a pedir o
        // arquivo (a página responde 200, mas renderiza sem CSS nenhum).
        //
        // '*' porque o IP do proxy é interno da plataforma e muda sem
        // aviso, então não dá pra fixar uma lista — é o padrão para app
        // que roda atrás do balanceador gerenciado da hospedagem, onde
        // o container só recebe tráfego por esse proxy.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
