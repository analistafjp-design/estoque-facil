/**
 * Service worker do Estoque Fácil PRO.
 *
 * O app é servido pelo Laravel/Livewire — a maioria das páginas é
 * renderizada no servidor e as interações (Livewire) exigem uma requisição
 * de verdade. Por isso este service worker não tenta prometer "funciona
 * offline" para o app inteiro: seria mentira, e pior, poderia deixar
 * alguém enxergando estoque desatualizado sem perceber. O que ele faz:
 *
 *   1. Deixa o app instalável (é o manifest.webmanifest que faz a maior
 *      parte disso, mas o navegador exige um service worker registrado).
 *   2. Cacheia estático (JS/CSS com hash, fontes, ícones) depois do
 *      primeiro carregamento, então a segunda visita abre mais rápido.
 *   3. Se cair a conexão bem na hora de navegar pra uma página nova,
 *      mostra um aviso claro (offline.html) em vez de erro de navegador.
 *
 * Nunca intercepta POST nem a rota /livewire/update — isso tem que
 * sempre ir pro servidor, ou o app mostraria estoque errado.
 */

const CACHE_VERSION = 'v1';
const CACHE_STATICO = `estoque-facil-estatico-${CACHE_VERSION}`;

const PRE_CACHE = [
  '/offline.html',
  '/manifest.webmanifest',
  '/icons/icon-192.png',
  '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_STATICO)
      .then((cache) => cache.addAll(PRE_CACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((chaves) => Promise.all(
        chaves
          .filter((chave) => chave.startsWith('estoque-facil-') && chave !== CACHE_STATICO)
          .map((chave) => caches.delete(chave))
      ))
      .then(() => self.clients.claim())
  );
});

// É estático "seguro pra cachear sem pensar" (nome com hash de conteúdo,
// ou recurso que não muda com o estoque de ninguém)?
function ehEstatico(url) {
  return (
    url.pathname.startsWith('/build/') ||
    url.pathname.startsWith('/icons/') ||
    url.pathname.endsWith('.woff2') ||
    url.pathname.endsWith('.woff')
  );
}

self.addEventListener('fetch', (event) => {
  const { request } = event;

  // Nunca mexe em não-GET (login, criar produto, lançar movimentação,
  // livewire/update...) — sempre direto pro servidor.
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Navegação de página inteira: tenta a rede primeiro (o dado tem que
  // estar atualizado); só cai pro aviso offline se a rede falhar mesmo.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Estático versionado por hash: cache-first, atualiza o cache em
  // segundo plano pra próxima vez.
  if (ehEstatico(url)) {
    event.respondWith(
      caches.match(request).then((cacheado) => {
        const buscaNaRede = fetch(request)
          .then((resposta) => {
            if (resposta.ok) {
              const clone = resposta.clone();
              caches.open(CACHE_STATICO).then((cache) => cache.put(request, clone));
            }
            return resposta;
          })
          .catch(() => cacheado);

        return cacheado || buscaNaRede;
      })
    );
  }
});
