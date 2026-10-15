<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Lista de presentes (admin).
 *
 * @var array        $app
 * @var array<int, array<string, mixed>> $rows
 * @var string|null  $error
 * @var string       $csrfField
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';

$statusLabels = [
    'available' => ['Disponível', 'badge--available'],
    'reserved'  => ['Reservado',  'badge--reserved'],
    'purchased' => ['Presenteado','badge--purchased'],
];

$money = static fn (int $cents): string => 'R$ ' . number_format($cents / 100, 2, ',', '.');

// Resumo do catálogo (calculado a partir das linhas já carregadas — não há
// consulta extra ao banco nem mudança de comportamento).
$rows        = $rows ?? [];
$countTotal  = count($rows);
$countAvail  = 0;
$countReserv = 0;
$countBought = 0;
$sumCents    = 0;
foreach ($rows as $g) {
    $sumCents += (int) ($g['price_cents'] ?? 0);
    switch ((string) ($g['status'] ?? '')) {
        case 'available':  $countAvail++;  break;
        case 'reserved':   $countReserv++; break;
        case 'purchased':  $countBought++; break;
    }
}
// Resolve a URL de uma imagem salva (http, absoluta, ou relativa a assets/img).
$imageUrl = static function (string $image) use ($baseUrl): string {
    $image = trim($image);
    if ($image === '') {
        return '';
    }
    if (str_starts_with($image, 'http') || str_starts_with($image, '/')) {
        return $image;
    }
    return rtrim($baseUrl, '/') . '/assets/img/' . ltrim($image, '/');
};
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<?php if ($rows !== []): ?>
    <!-- Indicadores independentes, soltos no topo (igual ao painel de Confirmações). -->
    <div class="stat-grid">
        <div class="stat"><span class="stat__value"><?= (int) $countTotal ?></span><span class="stat__label">Presentes</span></div>
        <div class="stat"><span class="stat__value"><?= (int) $countAvail ?></span><span class="stat__label">Disponíveis</span></div>
        <div class="stat"><span class="stat__value"><?= (int) $countReserv ?></span><span class="stat__label">Reservados</span></div>
        <div class="stat"><span class="stat__value"><?= (int) $countBought ?></span><span class="stat__label">Presenteados</span></div>
        <div class="stat"><span class="stat__value"><?= $money((int) $sumCents) ?></span><span class="stat__label">Valor da lista</span></div>
    </div>
<?php endif; ?>

<div class="admin-card">
    <?php if ($rows === []): ?>
        <div class="admin-toolbar">
            <div class="spacer"></div>
            <a class="btn-admin" href="<?= View::e(View::url('/admin/presentes/novo', $baseUrl)) ?>">+ Novo presente</a>
        </div>
        <p class="admin-empty">Nenhum presente cadastrado. Clique em “Novo presente”.</p>
    <?php else: ?>
        <!-- Ação "Novo presente" + feedback do salvamento da ordem (via JS). -->
        <div class="admin-toolbar admin-toolbar--tight">
            <span class="gift-reorder-status" data-reorder-status role="status" aria-live="polite"></span>
            <div class="spacer"></div>
            <a class="btn-admin" href="<?= View::e(View::url('/admin/presentes/novo', $baseUrl)) ?>">+ Novo presente</a>
        </div>

        <div style="overflow-x:auto;" class="admin-table--cards-wrap">
        <table class="admin-table gift-reorder admin-table--cards"
               data-reorder
               data-reorder-url="<?= View::e(View::url('/admin/presentes/ordenar', $baseUrl)) ?>"
               data-csrf-name="<?= View::e(\App\Core\Csrf::fieldName()) ?>"
               data-csrf-token="<?= View::e(\App\Core\Csrf::token()) ?>">
            <thead>
                <tr>
                    <th aria-label="Reordenar"></th>
                    <th>Ordem</th>
                    <th>Foto</th>
                    <th>Nome</th>
                    <th>Preço</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-reorder-body>
                <?php foreach ($rows as $g): ?>
                    <?php
                    [$label, $cls] = $statusLabels[$g['status']] ?? ['—', ''];
                    $thumb = $imageUrl((string) ($g['image'] ?? ''));
                    $st    = (string) ($g['status'] ?? '');
                    // Dados para o popup de detalhes do presente (somente leitura;
                    // Editar/Remover continuam usando as rotas existentes).
                    $giftData = [
                        'id'          => (int) $g['id'],
                        'name'        => (string) $g['name'],
                        'price'       => $money((int) $g['price_cents']),
                        'status'      => $label,
                        'status_cls'  => $cls,
                        'image'       => $thumb,
                        'description' => (string) ($g['description'] ?? ''),
                        'edit_url'    => View::url('/admin/presentes/editar?id=' . (int) $g['id'], $baseUrl),
                    ];
                    ?>
                    <tr data-reorder-row data-id="<?= (int) $g['id'] ?>" data-row-open>
                        <td class="gift-drag-cell cell-hide-mobile">
                            <span class="gift-drag-handle" data-drag-handle
                                  title="Arraste para reordenar" aria-label="Arraste para reordenar"
                                  role="button" tabindex="0" draggable="true">&#9776;</span>
                        </td>
                        <td class="gift-order-num cell-hide-mobile" data-reorder-num><?= (int) $g['sort_order'] ?></td>
                        <td class="cell-hide-mobile" data-label="Foto">
                            <?php if ($thumb !== ''): ?>
                                <img class="gift-thumb" src="<?= View::e($thumb) ?>"
                                     alt="<?= View::e((string) $g['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="gift-thumb" aria-hidden="true"
                                      style="display:grid;place-items:center;">&#127873;</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Nome">
                            <strong><?= View::e((string) $g['name']) ?></strong>
                            <span class="status-dot status-dot--<?= View::e($st) ?>" title="<?= View::e($label) ?>" aria-hidden="true"></span>
                            <?php if (!empty($g['description'])): ?>
                                <br><small class="cell-hide-mobile"><?= View::e(mb_strimwidth((string) $g['description'], 0, 70, '…')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Preço"><?= $money((int) $g['price_cents']) ?></td>
                        <td data-label="Status"><span class="badge cell-badge-dot <?= $cls ?>"><?= View::e($label) ?></span></td>
                        <td style="white-space:nowrap;" class="cell-action-mobile-hide">
                            <?php /* Gatilho do popup usado só no mobile (linha clicável). Fica oculto. */ ?>
                            <button type="button" class="is-hidden-trigger"
                                    data-gift-open data-row-trigger aria-hidden="true" tabindex="-1"
                                    data-gift='<?= View::e(json_encode($giftData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'></button>
                            <a class="btn-admin btn-admin--ghost btn-admin--sm"
                               href="<?= View::e(View::url('/admin/presentes/editar?id=' . (int) $g['id'], $baseUrl)) ?>">Editar</a>
                            <form method="post" style="display:inline;"
                                  action="<?= View::e(View::url('/admin/presentes/remover', $baseUrl)) ?>"
                                  onsubmit="return confirm('Remover este presente?');">
                                <?= $csrfField ?>
                                <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                                <button type="submit" class="btn-admin btn-admin--danger btn-admin--sm">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: detalhe do presente (aberto ao tocar na linha no mobile) -->
<div class="modal" data-gift-modal hidden>
    <div class="modal__backdrop" data-gift-close></div>
    <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="gift-modal-title">
        <div class="modal__head">
            <h3 class="modal__title" id="gift-modal-title">Presente</h3>
            <button type="button" class="modal__close" data-gift-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal__body">
            <div class="gift-detail__photo" data-gift-photo hidden>
                <img src="" alt="" data-gift-photo-img>
            </div>
            <div class="finance-detail" data-gift-detail></div>
            <p class="finance-detail__msg" data-gift-desc hidden></p>
        </div>
        <div class="modal__foot">
            <form method="post" data-gift-remove-form
                  action="<?= View::e(View::url('/admin/presentes/remover', $baseUrl)) ?>"
                  onsubmit="return confirm('Remover este presente?');" style="margin-right:auto;">
                <?= $csrfField ?>
                <input type="hidden" name="id" value="" data-gift-remove-id>
                <button type="submit" class="btn-admin btn-admin--danger btn-admin--sm">Remover</button>
            </form>
            <a class="btn-admin btn-admin--sm" href="#" data-gift-edit>Editar</a>
            <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm" data-gift-close>Fechar</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var modal = document.querySelector('[data-gift-modal]');
    if (!modal) { return; }

    var detailBox = modal.querySelector('[data-gift-detail]');
    var photoBox  = modal.querySelector('[data-gift-photo]');
    var photoImg  = modal.querySelector('[data-gift-photo-img]');
    var descBox   = modal.querySelector('[data-gift-desc]');
    var removeId  = modal.querySelector('[data-gift-remove-id]');
    var editLink  = modal.querySelector('[data-gift-edit]');

    var esc = function (s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    };
    var row = function (label, value) {
        return '<div class="finance-detail__item"><span>' + esc(label) + '</span>'
             + '<strong>' + esc(value || '—') + '</strong></div>';
    };

    var open = function (d) {
        detailBox.innerHTML =
            row('Nome', d.name)
          + row('Preço', d.price)
          + row('Status', d.status);

        if (d.image && d.image !== '') {
            photoImg.src = d.image;
            photoImg.alt = d.name || '';
            photoBox.hidden = false;
        } else {
            photoImg.removeAttribute('src');
            photoBox.hidden = true;
        }

        if (d.description && d.description.trim() !== '') {
            descBox.innerHTML = '<span style="color:var(--c-muted)">Descrição:</span> ' + esc(d.description);
            descBox.hidden = false;
        } else {
            descBox.hidden = true;
        }

        if (removeId) { removeId.value = d.id; }
        if (editLink) { editLink.setAttribute('href', d.edit_url || '#'); }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };
    var close = function () {
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    document.querySelectorAll('[data-gift-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { open(JSON.parse(btn.getAttribute('data-gift'))); }
            catch (e) { /* ignora dado inválido */ }
        });
    });
    modal.querySelectorAll('[data-gift-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
})();
</script>
