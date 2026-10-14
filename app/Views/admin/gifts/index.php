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
        <div class="spacer"></div>
        <a class="btn-admin" href="<?= View::e(View::url('/admin/presentes/novo', $baseUrl)) ?>">+ Novo presente</a>
    </div>

    <?php if ($rows === []): ?>
        <p class="admin-empty">Nenhum presente cadastrado. Clique em “Novo presente”.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Ordem</th>
                    <th>Foto</th>
                    <th>Nome</th>
                    <th>Preço</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $g): ?>
                    <?php
                    [$label, $cls] = $statusLabels[$g['status']] ?? ['—', ''];
                    $thumb = $imageUrl((string) ($g['image'] ?? ''));
                    ?>
                    <tr>
                        <td><?= (int) $g['sort_order'] ?></td>
                        <td>
                            <?php if ($thumb !== ''): ?>
                                <img class="gift-thumb" src="<?= View::e($thumb) ?>"
                                     alt="<?= View::e((string) $g['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="gift-thumb" aria-hidden="true"
                                      style="display:grid;place-items:center;">&#127873;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong><?= View::e((string) $g['name']) ?></strong>
                            <?php if (!empty($g['description'])): ?>
                                <br><small><?= View::e(mb_strimwidth((string) $g['description'], 0, 70, '…')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= $money((int) $g['price_cents']) ?></td>
                        <td><span class="badge <?= $cls ?>"><?= View::e($label) ?></span></td>
                        <td style="white-space:nowrap;">
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
