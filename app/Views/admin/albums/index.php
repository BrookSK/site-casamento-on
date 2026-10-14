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
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Álbum</th>
                    <th>Fotos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $a): ?>
                    <tr>
                        <td><?= (int) $a['sort_order'] ?></td>
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
    <?php endif; ?>
</div>
