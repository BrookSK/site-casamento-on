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
        <div style="overflow-x:auto;">
        <table class="admin-table gift-reorder"
               data-reorder
               data-reorder-url="<?= View::e(View::url('/admin/galeria/ordenar', $baseUrl)) ?>"
               data-csrf-name="<?= View::e(\App\Core\Csrf::fieldName()) ?>"
               data-csrf-token="<?= View::e(\App\Core\Csrf::token()) ?>">
            <thead>
                <tr>
                    <th aria-label="Reordenar"></th>
                    <th>Ordem</th>
                    <th>Álbum</th>
                    <th>Fotos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-reorder-body>
                <?php foreach ($rows as $a): ?>
                    <tr data-reorder-row data-id="<?= (int) $a['id'] ?>">
                        <td class="gift-drag-cell">
                            <span class="gift-drag-handle" data-drag-handle
                                  title="Arraste para reordenar" aria-label="Arraste para reordenar"
                                  role="button" tabindex="0" draggable="true">&#9776;</span>
                        </td>
                        <td class="gift-order-num" data-reorder-num><?= (int) $a['sort_order'] ?></td>
                        <td>
                            <strong><?= View::e((string) $a['title']) ?></strong>
                            <?php if (!empty($a['subtitle'])): ?>
                                <br><small><?= View::e((string) $a['subtitle']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) ($a['photo_count'] ?? 0) ?></td>
                        <td style="white-space:nowrap;">
                            <a class="btn-admin btn-admin--ghost btn-admin--sm"
                               href="<?= View::e(View::url('/admin/galeria/editar?id=' . (int) $a['id'], $baseUrl)) ?>">Gerenciar</a>
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
