<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Galeria de fotos (admin) — lista de álbuns.
 *
 * @var array $app
 * @var array<int, array<string, mixed>> $rows
 * @var string|null $error
 * @var string $csrfField
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';

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

<div class="admin-card">
    <div class="admin-toolbar">
        <a class="btn-admin btn-admin--ghost btn-admin--sm" href="<?= View::e(View::url('/admin/textos-e-imagens', $baseUrl)) ?>">&larr; Conteúdo</a>
        <div class="spacer"></div>
        <a class="btn-admin" href="<?= View::e(View::url('/admin/galeria/novo', $baseUrl)) ?>">+ Novo álbum</a>
    </div>

    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhum álbum criado ainda. Clique em “Novo álbum”.</p>
    <?php else: ?>
        <div class="admin-toolbar admin-toolbar--tight">
            <span class="gift-reorder-status" data-reorder-status role="status" aria-live="polite"></span>
        </div>
        <div style="overflow-x:auto;" class="admin-table--cards-wrap">
        <table class="admin-table gift-reorder admin-table--cards admin-table--cards-inline"
               data-reorder
               data-reorder-url="<?= View::e(View::url('/admin/galeria/ordenar', $baseUrl)) ?>"
               data-csrf-name="<?= View::e(\App\Core\Csrf::fieldName()) ?>"
               data-csrf-token="<?= View::e(\App\Core\Csrf::token()) ?>">
            <thead>
                <tr>
                    <th aria-label="Reordenar"></th>
                    <th>Ordem</th>
                    <th>Foto</th>
                    <th>Álbum</th>
                    <th>Fotos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-reorder-body>
                <?php foreach ($rows as $a): ?>
                    <?php
                    $cover    = $imageUrl((string) ($a['cover_image'] ?? ''));
                    $editUrl  = View::url('/admin/galeria/editar?id=' . (int) $a['id'], $baseUrl);
                    // Dados para o popup (somente leitura; Gerenciar/Remover usam as rotas atuais).
                    $albumData = [
                        'id'          => (int) $a['id'],
                        'title'       => (string) $a['title'],
                        'subtitle'    => (string) ($a['subtitle'] ?? ''),
                        'description' => (string) ($a['description'] ?? ''),
                        'photo_count' => (int) ($a['photo_count'] ?? 0),
                        'cover'       => $cover,
                        'edit_url'    => $editUrl,
                    ];
                    ?>
                    <tr data-reorder-row data-id="<?= (int) $a['id'] ?>" data-row-open>
                        <td class="gift-drag-cell cell-hide-mobile">
                            <span class="gift-drag-handle" data-drag-handle
                                  title="Arraste para reordenar" aria-label="Arraste para reordenar"
                                  role="button" tabindex="0" draggable="true">&#9776;</span>
                        </td>
                        <td class="gift-order-num cell-hide-mobile" data-reorder-num><?= (int) $a['sort_order'] ?></td>
                        <td data-label="Foto">
                            <?php if ($cover !== ''): ?>
                                <img class="gift-thumb" src="<?= View::e($cover) ?>"
                                     alt="<?= View::e((string) $a['title']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="gift-thumb" aria-hidden="true"
                                      style="display:grid;place-items:center;">&#128247;</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Álbum">
                            <strong><?= View::e((string) $a['title']) ?></strong>
                            <?php if (!empty($a['subtitle'])): ?>
                                <br><small class="cell-hide-mobile"><?= View::e((string) $a['subtitle']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Fotos"><?= (int) ($a['photo_count'] ?? 0) ?></td>
                        <td style="white-space:nowrap;" class="cell-action-mobile-hide">
                            <?php /* Gatilho do popup usado só no mobile (linha clicável). Fica oculto. */ ?>
                            <button type="button" class="is-hidden-trigger"
                                    data-album-open data-row-trigger aria-hidden="true" tabindex="-1"
                                    data-album='<?= View::e(json_encode($albumData, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'></button>
                            <a class="btn-admin btn-admin--ghost btn-admin--sm"
                               href="<?= View::e($editUrl) ?>">Gerenciar</a>
                            <form method="post" style="display:inline;"
                                  action="<?= View::e(View::url('/admin/galeria/remover', $baseUrl)) ?>"
                                  onsubmit="return confirm('Remover este álbum e todas as suas fotos?');">
                                <?= $csrfField ?>
                                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
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

<!-- Modal: detalhe do álbum (aberto ao tocar na linha no mobile) -->
<div class="modal" data-album-modal hidden>
    <div class="modal__backdrop" data-album-close></div>
    <div class="modal__box" role="dialog" aria-modal="true" aria-labelledby="album-modal-title">
        <div class="modal__head">
            <h3 class="modal__title" id="album-modal-title">Álbum</h3>
            <button type="button" class="modal__close" data-album-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal__body">
            <div class="gift-detail__photo" data-album-photo hidden>
                <img src="" alt="" data-album-photo-img>
            </div>
            <div class="finance-detail" data-album-detail></div>
            <p class="finance-detail__msg" data-album-desc hidden></p>
        </div>
        <div class="modal__foot">
            <form method="post" data-album-remove-form
                  action="<?= View::e(View::url('/admin/galeria/remover', $baseUrl)) ?>"
                  onsubmit="return confirm('Remover este álbum e todas as suas fotos?');" style="margin-right:auto;">
                <?= $csrfField ?>
                <input type="hidden" name="id" value="" data-album-remove-id>
                <button type="submit" class="btn-admin btn-admin--danger btn-admin--sm">Remover</button>
            </form>
            <a class="btn-admin btn-admin--sm" href="#" data-album-edit>Gerenciar fotos</a>
            <button type="button" class="btn-admin btn-admin--ghost btn-admin--sm" data-album-close>Fechar</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var modal = document.querySelector('[data-album-modal]');
    if (!modal) { return; }

    var detailBox = modal.querySelector('[data-album-detail]');
    var photoBox  = modal.querySelector('[data-album-photo]');
    var photoImg  = modal.querySelector('[data-album-photo-img]');
    var descBox   = modal.querySelector('[data-album-desc]');
    var removeId  = modal.querySelector('[data-album-remove-id]');
    var editLink  = modal.querySelector('[data-album-edit]');

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
            row('Título', d.title)
          + row('Subtítulo', d.subtitle)
          + row('Fotos', d.photo_count);

        if (d.cover && d.cover !== '') {
            photoImg.src = d.cover;
            photoImg.alt = d.title || '';
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

    document.querySelectorAll('[data-album-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try { open(JSON.parse(btn.getAttribute('data-album'))); }
            catch (e) { /* ignora dado inválido */ }
        });
    });
    modal.querySelectorAll('[data-album-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) { close(); }
    });
})();
</script>
