<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#111827">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Estoque Fácil">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
<link rel="icon" href="/icons/icon-192.png">

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch(() => {
                // PWA é um extra, não um requisito pra usar o app — se o
                // registro falhar (ex.: navegador sem suporte), segue o
                // baile sem service worker, sem quebrar a página.
            });
        });
    }
</script>
