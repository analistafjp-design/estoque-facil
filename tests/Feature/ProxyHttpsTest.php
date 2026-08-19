<?php

/**
 * Regressão de um bug real de produção: no Railway (e Render, e qualquer
 * hospedagem com balanceador gerenciado) o TLS termina no proxy da
 * plataforma, que repassa a requisição pro container em HTTP puro e
 * sinaliza o esquema original no cabeçalho X-Forwarded-Proto.
 *
 * Sem trustProxies configurado, o Laravel ignora esse cabeçalho, acha que
 * está servindo em http:// e gera URL absoluta http:// pros assets do
 * Vite. O navegador, numa página carregada por https, bloqueia isso como
 * mixed content — e nem chega a fazer a requisição. O sintoma é cruel de
 * diagnosticar: a página responde 200 normalmente, mas renderiza
 * completamente sem CSS, e o log do servidor não mostra erro nenhum
 * porque a requisição do CSS nunca sai do navegador.
 */

it('reconhece a requisição como segura quando o proxy sinaliza https', function () {
    $request = Illuminate\Http\Request::create('http://exemplo.test/', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'https');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');

    $middleware = app(Illuminate\Http\Middleware\TrustProxies::class);

    $middleware->handle($request, function (Illuminate\Http\Request $r) {
        expect($r->isSecure())->toBeTrue();
        expect($r->getScheme())->toBe('https');

        return new Illuminate\Http\Response();
    });
});

it('gera URL de asset em https quando atrás do proxy', function () {
    $request = Illuminate\Http\Request::create('http://exemplo.test/', 'GET');
    $request->headers->set('X-Forwarded-Proto', 'https');
    $request->server->set('REMOTE_ADDR', '10.0.0.1');

    $middleware = app(Illuminate\Http\Middleware\TrustProxies::class);

    $middleware->handle($request, function (Illuminate\Http\Request $r) {
        // É esta a URL que ia sair como http:// e ser bloqueada pelo
        // navegador como mixed content.
        app('url')->setRequest($r);

        expect(asset('build/assets/app.css'))->toStartWith('https://');

        return new Illuminate\Http\Response();
    });
});
