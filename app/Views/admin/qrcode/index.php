<?php

declare(strict_types=1);

use App\Core\View;

/**
 * QR Code de acesso ao site (painel admin).
 *
 * @var array  $app
 * @var string $targetUrl   URL completa para onde o QR aponta
 * @var string $targetPath  Caminho relativo (ex.: /confirmacao-interna)
 * @var string $baseUrl     base_url configurada
 * @var string $imageBase   nome-base dos arquivos (qr-confirmacao)
 * @var bool   $pngExists
 * @var bool   $svgExists
 */

$pngUrl = View::assetVersioned('img/' . $imageBase . '.png', $baseUrl);
$svgUrl = View::assetVersioned('img/' . $imageBase . '.svg', $baseUrl);

// Alerta quando a base_url ainda é a de homologação (.plesk.page): o QR fixo
// aponta para esse endereço e precisará ser regerado ao publicar no domínio.
$isStagingUrl = str_contains($baseUrl, 'plesk.page');
?>

<div class="qr-page">

    <?php if ($baseUrl === ''): ?>
        <div class="flash flash--error">
            A <strong>base_url</strong> do site não está configurada
            (<code>app/Config/config.php</code>). Sem ela, não é possível montar
            o endereço do QR Code.
        </div>
    <?php endif; ?>

    <!-- ============ O que é esta tela ============ -->
    <div class="admin-card">
        <h2>QR Code de acesso ao site</h2>
        <p>
            Este é o <strong>QR Code único</strong> que você compartilha para dar
            acesso ao site. Ao apontar a câmera do celular para ele, o convidado é
            levado direto à página de <strong>confirmação de presença</strong> —
            sem precisar digitar nenhum endereço.
        </p>
        <p class="qr-page__muted">
            Ideal para o cartão do casamento, o convite impresso, placas na recepção
            ou mensagens. É sempre o mesmo código: imprima uma vez e use onde quiser.
        </p>
    </div>

    <!-- ============ O QR Code + download ============ -->
    <div class="admin-card qr-card">
        <div class="qr-card__visual">
            <?php if ($pngExists): ?>
                <div class="qr-frame">
                    <img src="<?= View::e($pngUrl) ?>" alt="QR Code de acesso ao site" width="260" height="260">
                </div>
            <?php else: ?>
                <div class="qr-frame qr-frame--empty">
                    <span>Imagem do QR Code não encontrada.</span>
                </div>
            <?php endif; ?>

            <div class="qr-card__downloads">
                <?php if ($pngExists): ?>
                    <a class="btn-admin" href="<?= View::e($pngUrl) ?>" download="qr-confirmacao.png">
                        Baixar PNG
                    </a>
                <?php endif; ?>
                <?php if ($svgExists): ?>
                    <a class="btn-admin btn-admin--ghost" href="<?= View::e($svgUrl) ?>" download="qr-confirmacao.svg">
                        Baixar SVG (impressão)
                    </a>
                <?php endif; ?>
            </div>
            <p class="qr-page__hint">
                Use o <strong>PNG</strong> para telas e mensagens. Use o
                <strong>SVG</strong> para impressão: ele mantém a nitidez em
                qualquer tamanho, do cartão a um banner.
            </p>
        </div>

        <div class="qr-card__info">
            <h3 class="qr-page__subtitle">Para onde este QR Code leva</h3>
            <div class="qr-url" title="Endereço de destino">
                <code><?= View::e($targetUrl) ?></code>
            </div>
            <p class="qr-page__muted">
                <a href="<?= View::e($targetUrl) ?>" target="_blank" rel="noopener">
                    Abrir a página em uma nova aba &rarr;
                </a>
                para conferir como o convidado vê.
            </p>

            <h3 class="qr-page__subtitle">Como usar em 3 passos</h3>
            <ol class="qr-steps">
                <li><strong>Baixe</strong> a imagem (PNG para digital, SVG para impressão).</li>
                <li><strong>Coloque</strong> no cartão, convite ou onde for compartilhar.</li>
                <li><strong>Teste</strong> com a câmera do seu celular antes de imprimir.</li>
            </ol>
        </div>
    </div>

    <!-- ============ Convites individuais (QR por convidado) ============ -->
    <div class="admin-card">
        <div class="qr-individual__head">
            <h2>QR Codes individuais</h2>
            <a class="btn-admin btn-admin--sm" href="<?= View::e($createInviteUrl) ?>">
                + Criar convite individual
            </a>
        </div>
        <p class="qr-page__muted">
            Cada convite individual tem um <strong>link</strong> e um
            <strong>QR Code próprios</strong>, ligados a um único convidado. Use
            para enviar convites online. A confirmação feita por esse link é
            identificada pelo convidado correspondente — sem precisar de CPF.
        </p>

        <?php if (!empty($invitesError)): ?>
            <div class="flash flash--error"><?= View::e($invitesError) ?></div>
        <?php endif; ?>

        <?php if (empty($qrAvailable)): ?>
            <div class="flash flash--error">
                A biblioteca de geração de QR Code ainda não está instalada no
                servidor. Os <strong>links</strong> abaixo já funcionam; as
                imagens dos QR Codes aparecerão após rodar
                <code>composer install</code> no servidor.
            </div>
        <?php endif; ?>

        <?php if (empty($individualInvites)): ?>
            <p class="qr-page__muted">
                Nenhum convite individual criado ainda.
                <a href="<?= View::e($createInviteUrl) ?>">Criar o primeiro &rarr;</a>
            </p>
        <?php else: ?>
            <div class="qr-individual__list">
                <?php foreach ($individualInvites as $inv): ?>
                    <?php
                    $statusLabel = $inv['confirmed']
                        ? ['yes', 'Confirmou']
                        : ($inv['declined'] ? ['no', 'Não vai'] : ['pending', 'Sem resposta']);
                    ?>
                    <div class="qr-individual__item">
                        <div class="qr-individual__visual">
                            <?php if ($inv['qr_url'] !== null): ?>
                                <img src="<?= View::e($inv['qr_url']) ?>" width="150" height="150"
                                     alt="QR Code do convite de <?= View::e($inv['name']) ?>">
                                <a class="qr-individual__download" href="<?= View::e($inv['qr_url']) ?>"
                                   download="convite-<?= View::e($inv['token']) ?>.png">Baixar QR</a>
                            <?php else: ?>
                                <div class="qr-individual__empty">QR indisponível</div>
                            <?php endif; ?>
                        </div>
                        <div class="qr-individual__body">
                            <div class="qr-individual__name-row">
                                <h3 class="qr-individual__name"><?= View::e($inv['name']) ?></h3>
                                <span class="qr-badge qr-badge--<?= $statusLabel[0] ?>">
                                    <?= View::e($statusLabel[1]) ?>
                                </span>
                            </div>
                            <label class="qr-individual__label">Link exclusivo</label>
                            <div class="qr-copy">
                                <input type="text" readonly value="<?= View::e($inv['link']) ?>"
                                       data-copy-src aria-label="Link do convite">
                                <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm"
                                        data-copy-btn>Copiar</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============ Avisos importantes ============ -->
    <div class="admin-card">
        <h3 class="qr-page__subtitle">Antes de imprimir, confira</h3>
        <ul class="qr-notes">
            <li>
                <strong>Teste sempre.</strong> Aponte a câmera do celular para a
                imagem acima e confirme que ela abre a página de confirmação.
            </li>
            <li>
                <strong>Contraste e tamanho.</strong> Imprima em fundo claro e com
                pelo menos 2,5&nbsp;cm de lado. Mantenha a margem branca ao redor —
                ela faz parte do código.
            </li>
            <?php if ($isStagingUrl): ?>
            <li class="qr-notes__warn">
                <strong>Atenção ao endereço.</strong> Este QR Code aponta para o
                endereço provisório <code><?= View::e($baseUrl) ?></code>. Quando o
                site for publicado no domínio oficial, o QR Code precisará ser
                <strong>gerado novamente</strong> com o novo endereço — caso
                contrário ele deixará de funcionar.
            </li>
            <?php endif; ?>
        </ul>
    </div>

</div>

<script>
// Copiar link do convite individual (com fallback para navegadores antigos).
document.querySelectorAll('[data-copy-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = btn.closest('.qr-copy');
        var input = wrap ? wrap.querySelector('[data-copy-src]') : null;
        if (!input) { return; }
        input.select();
        input.setSelectionRange(0, 99999);
        var done = function () {
            var old = btn.textContent;
            btn.textContent = 'Copiado!';
            setTimeout(function () { btn.textContent = old; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done, function () {
                try { document.execCommand('copy'); done(); } catch (e) {}
            });
        } else {
            try { document.execCommand('copy'); done(); } catch (e) {}
        }
    });
});
</script>

<style>
/* Estilos específicos da tela de QR Code (paleta do admin). */
.qr-page { max-width: 820px; }
.qr-page__subtitle {
    font-family: var(--font-serif);
    font-size: 1.15rem;
    margin: 1.4rem 0 0.6rem;
    color: var(--c-ink);
}
.qr-page__subtitle:first-child { margin-top: 0; }
.qr-page__muted { color: var(--c-muted); font-size: 0.92rem; }
.qr-page__hint { color: var(--c-muted); font-size: 0.85rem; margin-top: 0.9rem; }

.qr-card {
    display: grid;
    grid-template-columns: minmax(0, 300px) 1fr;
    gap: clamp(1.25rem, 4vw, 2.5rem);
    align-items: start;
}
.qr-card__visual { text-align: center; }

.qr-frame {
    display: inline-block;
    padding: 1rem;
    background: var(--c-white);
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}
.qr-frame img { display: block; width: 260px; height: 260px; }
.qr-frame--empty {
    width: 260px; height: 260px;
    display: grid; place-items: center;
    color: var(--c-muted); font-size: 0.9rem; text-align: center;
    box-shadow: none; background: var(--c-cream);
}

.qr-card__downloads {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    justify-content: center;
    margin-top: 1.1rem;
}

.qr-url {
    background: var(--c-cream);
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    padding: 0.7rem 0.85rem;
    overflow-wrap: anywhere;
    word-break: break-all;
}
.qr-url code { font-size: 0.9rem; color: var(--c-sage-dark); }

.qr-steps, .qr-notes { margin: 0.4rem 0 0; padding-left: 1.2rem; color: var(--c-muted); }
.qr-steps li, .qr-notes li { margin-bottom: 0.5rem; }
.qr-steps strong, .qr-notes strong { color: var(--c-ink); }
.qr-notes { list-style: none; padding-left: 0; }
.qr-notes li { position: relative; padding-left: 1.4rem; }
.qr-notes li::before {
    content: "✓"; position: absolute; left: 0; top: 0;
    color: var(--c-sage-dark); font-weight: 600;
}
.qr-notes__warn::before { content: "!"; color: var(--c-error-fg); }
.qr-notes__warn { color: var(--c-error-fg); }
.qr-notes__warn code { color: var(--c-error-fg); }

@media (max-width: 640px) {
    .qr-card { grid-template-columns: 1fr; }
}

/* ---- Convites individuais ---- */
.qr-individual__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
    margin-bottom: 0.3rem;
}
.qr-individual__head h2 { margin: 0; }

.qr-individual__list { display: grid; gap: 1rem; margin-top: 1rem; }
.qr-individual__item {
    display: grid;
    grid-template-columns: 170px 1fr;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: var(--c-white);
}
.qr-individual__visual { text-align: center; }
.qr-individual__visual img {
    display: block;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: #fff;
}
.qr-individual__empty {
    width: 150px; height: 150px;
    display: grid; place-items: center;
    color: var(--c-muted); font-size: 0.8rem; text-align: center;
    background: var(--c-cream); border-radius: var(--radius);
}
.qr-individual__download { display: inline-block; margin-top: 0.5rem; font-size: 0.82rem; }

.qr-individual__name-row { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.qr-individual__name { margin: 0; font-family: var(--font-serif); font-size: 1.1rem; }
.qr-individual__label { display: block; margin: 0.8rem 0 0.3rem; font-size: 0.82rem; color: var(--c-muted); }

.qr-copy { display: flex; gap: 0.5rem; }
.qr-copy input {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: var(--c-cream);
    font-size: 0.85rem;
    overflow-wrap: anywhere;
}

.qr-badge {
    font-size: 0.72rem;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-weight: 500;
}
.qr-badge--yes { background: #e4f1e4; color: #2f6b36; }
.qr-badge--no { background: #f6e3e3; color: #9a3b3b; }
.qr-badge--pending { background: var(--c-cream); color: var(--c-muted); }

@media (max-width: 600px) {
    .qr-individual__item { grid-template-columns: 1fr; }
    .qr-individual__visual { margin: 0 auto; }
}
</style>
