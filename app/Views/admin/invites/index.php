<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Convites individuais (painel admin): criação manual + lista.
 *
 * @var array                         $app
 * @var array<int,array<string,mixed>> $invites
 * @var string|null                   $error
 * @var bool                          $qrAvailable
 * @var string|null                   $csrfField
 */

$baseUrl = $app['base_url'] ?? '';
$storeUrl = View::url('/admin/convites/novo', $baseUrl);
?>

<div class="invite-page">

    <?php if ($error !== null): ?>
        <div class="flash flash--error"><?= View::e($error) ?></div>
    <?php endif; ?>

    <?php if (!$qrAvailable): ?>
        <div class="flash flash--error">
            A biblioteca de geração de QR Code ainda não está instalada no
            servidor. Os <strong>links</strong> dos convites funcionam
            normalmente; o <strong>QR Code</strong> aparecerá após rodar
            <code>composer install</code> no servidor.
        </div>
    <?php endif; ?>

    <!-- ============ Criar convite ============ -->
    <div class="admin-card">
        <h2>Criar convite individual</h2>
        <p class="invite-page__muted">
            Informe o nome do convidado. O sistema gera um <strong>link
            exclusivo</strong> e um <strong>QR Code</strong> próprios, com um
            token único usado para identificar a confirmação de presença dessa
            pessoa — sem precisar de CPF.
        </p>

        <form method="post" action="<?= View::e($storeUrl) ?>" class="invite-form">
            <?= $csrfField ?? '' ?>
            <div class="invite-form__row">
                <div class="invite-field">
                    <label for="invite-name">Nome do convidado *</label>
                    <input type="text" id="invite-name" name="name" required
                           maxlength="150" placeholder="Ex.: Maria Silva" autofocus>
                </div>
                <button class="btn-admin" type="submit">Gerar convite</button>
            </div>
        </form>
    </div>

    <!-- ============ Lista dos convites ============ -->
    <div class="admin-card">
        <h2>Convites gerados</h2>

        <?php if ($invites === []): ?>
            <p class="invite-page__muted">Nenhum convite individual criado ainda.</p>
        <?php else: ?>
            <div class="invite-list">
                <?php foreach ($invites as $inv): ?>
                    <?php
                    $statusLabel = $inv['confirmed']
                        ? ['yes', 'Confirmou']
                        : ($inv['declined'] ? ['no', 'Não vai'] : ['pending', 'Sem resposta']);
                    ?>
                    <div class="invite-item<?= $inv['is_new'] ? ' invite-item--new' : '' ?>">
                        <div class="invite-item__qr">
                            <?php if ($inv['qr_url'] !== null): ?>
                                <img src="<?= View::e($inv['qr_url']) ?>" width="150" height="150"
                                     alt="QR Code do convite de <?= View::e($inv['name']) ?>">
                                <a class="invite-item__download" href="<?= View::e($inv['qr_url']) ?>"
                                   download="convite-<?= View::e($inv['token']) ?>.png">Baixar QR</a>
                            <?php else: ?>
                                <div class="invite-item__qr-empty">QR indisponível</div>
                            <?php endif; ?>
                        </div>

                        <div class="invite-item__body">
                            <div class="invite-item__head">
                                <h3 class="invite-item__name"><?= View::e($inv['name']) ?></h3>
                                <span class="invite-badge invite-badge--<?= $statusLabel[0] ?>">
                                    <?= View::e($statusLabel[1]) ?>
                                </span>
                                <?php if ($inv['is_new']): ?>
                                    <span class="invite-badge invite-badge--new">Novo</span>
                                <?php endif; ?>
                            </div>

                            <label class="invite-item__label">Link exclusivo</label>
                            <div class="invite-copy">
                                <input type="text" readonly value="<?= View::e($inv['link']) ?>"
                                       data-copy-src aria-label="Link do convite">
                                <button type="button" class="btn-admin btn-admin--ghost"
                                        data-copy-btn>Copiar</button>
                            </div>

                            <p class="invite-item__token">
                                <span>Token:</span> <code><?= View::e($inv['token']) ?></code>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Copiar link do convite para a área de transferência (com fallback).
document.querySelectorAll('[data-copy-btn]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var wrap = btn.closest('.invite-copy');
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
.invite-page { max-width: 860px; }
.invite-page__muted { color: var(--c-muted); font-size: 0.92rem; }

.invite-form__row {
    display: flex;
    gap: 0.8rem;
    align-items: flex-end;
    flex-wrap: wrap;
}
.invite-field { flex: 1 1 260px; }
.invite-field label { display: block; margin-bottom: 0.35rem; font-size: 0.9rem; }
.invite-field input {
    width: 100%;
    padding: 0.6rem 0.7rem;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: var(--c-white);
}

.invite-list { display: grid; gap: 1rem; }
.invite-item {
    display: grid;
    grid-template-columns: 170px 1fr;
    gap: 1rem;
    padding: 1rem;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: var(--c-white);
}
.invite-item--new { border-color: var(--c-sage-dark); box-shadow: var(--shadow); }
.invite-item__qr { text-align: center; }
.invite-item__qr img {
    display: block;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: #fff;
}
.invite-item__qr-empty {
    width: 150px; height: 150px;
    display: grid; place-items: center;
    color: var(--c-muted); font-size: 0.8rem; text-align: center;
    background: var(--c-cream); border-radius: var(--radius);
}
.invite-item__download { display: inline-block; margin-top: 0.5rem; font-size: 0.82rem; }

.invite-item__head { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; }
.invite-item__name { margin: 0; font-family: var(--font-serif); font-size: 1.15rem; }
.invite-item__label { display: block; margin: 0.8rem 0 0.3rem; font-size: 0.82rem; color: var(--c-muted); }

.invite-copy { display: flex; gap: 0.5rem; }
.invite-copy input {
    flex: 1;
    padding: 0.5rem 0.6rem;
    border: 1px solid var(--c-line);
    border-radius: var(--radius);
    background: var(--c-cream);
    font-size: 0.85rem;
    overflow-wrap: anywhere;
}
.invite-item__token { margin: 0.7rem 0 0; font-size: 0.82rem; color: var(--c-muted); overflow-wrap: anywhere; }
.invite-item__token code { color: var(--c-sage-dark); }

.invite-badge {
    font-size: 0.72rem;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-weight: 500;
}
.invite-badge--yes { background: #e4f1e4; color: #2f6b36; }
.invite-badge--no { background: #f6e3e3; color: #9a3b3b; }
.invite-badge--pending { background: var(--c-cream); color: var(--c-muted); }
.invite-badge--new { background: var(--c-sage-dark); color: #fff; }

@media (max-width: 600px) {
    .invite-item { grid-template-columns: 1fr; }
    .invite-item__qr { margin: 0 auto; }
}
</style>
