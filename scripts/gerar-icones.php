<?php
// Ícone do PWA: caixa de estoque simples sobre fundo sólido, na cor
// gray-900 (#111827) já usada nos botões primários e na nav ativa do app
// — mesma identidade visual, sem depender de nenhum arquivo de design
// externo.
function desenharIcone(int $tamanho, string $caminho, bool $maskable = false): void
{
    $img = imagecreatetruecolor($tamanho, $tamanho);
    imagesavealpha($img, true);
    $transparente = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparente);

    $fundo = imagecolorallocate($img, 0x11, 0x18, 0x27); // gray-900
    $linha = imagecolorallocate($img, 0xff, 0xff, 0xff);

    // Maskable precisa de "safe zone": conteúdo dentro de ~80% central,
    // porque o SO pode recortar em círculo. Ícone normal usa quase tudo.
    $margem = $maskable ? (int) ($tamanho * 0.18) : (int) ($tamanho * 0.08);
    $raio = (int) ($tamanho * 0.12);

    imagefilledrectangle($img, 0, 0, $tamanho - 1, $tamanho - 1, $maskable ? $fundo : $transparente);
    if (! $maskable) {
        // Cantos arredondados no ícone "normal" (o maskable fica quadrado
        // de propósito, o SO decide a máscara).
        imagefilledellipse($img, $raio, $raio, $raio * 2, $raio * 2, $fundo);
        imagefilledellipse($img, $tamanho - $raio, $raio, $raio * 2, $raio * 2, $fundo);
        imagefilledellipse($img, $raio, $tamanho - $raio, $raio * 2, $raio * 2, $fundo);
        imagefilledellipse($img, $tamanho - $raio, $tamanho - $raio, $raio * 2, $raio * 2, $fundo);
        imagefilledrectangle($img, $raio, 0, $tamanho - $raio, $tamanho - 1, $fundo);
        imagefilledrectangle($img, 0, $raio, $tamanho - 1, $tamanho - $raio, $fundo);
    }

    // Caixa (o "estoque"): um hexágono simples de caixa aberta, em traço.
    $cx = $tamanho / 2;
    $cy = $tamanho / 2;
    $l = $tamanho - $margem * 2;
    $espessura = max(2, (int) ($tamanho * 0.035));
    imagesetthickness($img, $espessura);

    $topo = $cy - $l * 0.30;
    $meio = $cy - $l * 0.08;
    $base = $cy + $l * 0.32;
    $esq = $cx - $l * 0.36;
    $dir = $cx + $l * 0.36;

    // Tampa (losango) + corpo da caixa + linha central, tudo em traço branco.
    imageline($img, (int) $esq, (int) $meio, (int) $cx, (int) $topo, $linha);
    imageline($img, (int) $cx, (int) $topo, (int) $dir, (int) $meio, $linha);
    imageline($img, (int) $esq, (int) $meio, (int) $esq, (int) $base, $linha);
    imageline($img, (int) $dir, (int) $meio, (int) $dir, (int) $base, $linha);
    imageline($img, (int) $esq, (int) $base, (int) $cx, (int) ($topo + ($base - $topo) * 0.42 + ($l * 0.12)), $linha);
    imageline($img, (int) $dir, (int) $base, (int) $cx, (int) ($topo + ($base - $topo) * 0.42 + ($l * 0.12)), $linha);
    imageline($img, (int) $cx, (int) $meio, (int) $cx, (int) $base, $linha);

    imagepng($img, $caminho);
    imagedestroy($img);
}

desenharIcone(192, __DIR__.'/../public/icons/icon-192.png');
desenharIcone(512, __DIR__.'/../public/icons/icon-512.png');
desenharIcone(512, __DIR__.'/../public/icons/icon-maskable-512.png', maskable: true);
desenharIcone(180, __DIR__.'/../public/icons/apple-touch-icon.png');

echo "Ícones gerados em public/icons/\n";
