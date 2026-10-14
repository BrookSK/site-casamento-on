<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Galeria de fotos (admin) — criar/editar álbum e gerenciar suas fotos.
 *
 * @var array $app
 * @var array<string, mixed>|null $album
 * @var array<int, array<string, mixed>> $photos
 * @var string $csrfField
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';
$isEdit  = $album !== null;

$title       = $isEdit ? (string) $album['title'] : '';
$subtitle    = $isEdit ? (string) ($album['subtitle'] ?? '') : '';
$description = $isEdit ? (string) ($album['description'] ?? '') : '';
$sortOrder   = $isEdit ? (int) $album['sort_order'] : 0;
$albumId     = $isEdit ? (int) $album['id'] : 0;

$imageUrl = static function (string $image) use ($baseUrl): string {
    $image = trim($image);
    if ($image === '') { return ''; }
    if (str_starts_with($image, 'http') || str_starts_with($image, '/')) { return $image; }
    return rtrim($baseUrl, '/') . '/assets/img/' . ltrim($image, '/');
};
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<!-- Dados do álbum -->
<div class="admin-card">
    <div class="admin-toolbar">
        <a class="btn-admin btn-admin--ghost btn-admin--sm" href="<?= View::e(View::url('/admin/galeria', $baseUrl)) ?>">&larr; Álbuns</a>
    </div>

    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/galeria/salvar', $baseUrl)) ?>">
        <?= $csrfField ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= $albumId ?>">
        <?php endif; ?>

        <div class="field">
            <label for="title">Título *</label>
            <input type="text" id="title" name="title" value="<?= View::e($title) ?>" required autofocus>
        </div>

        <div class="field">
            <label for="subtitle">Subtítulo (opcional)</label>
            <input type="text" id="subtitle" name="subtitle" value="<?= View::e($subtitle) ?>">
        </div>
        <!-- A ordem é definida por arraste na lista de álbuns; preserva o valor atual. -->
        <input type="hidden" name="sort_order" value="<?= $sortOrder ?>">

        <div class="field">
            <label for="description">Descrição (opcional)</label>
            <textarea id="description" name="description" rows="3"><?= View::e($description) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin"><?= $isEdit ? 'Salvar álbum' : 'Criar álbum' ?></button>
            <a class="btn-admin btn-admin--ghost" href="<?= View::e(View::url('/admin/galeria', $baseUrl)) ?>">Cancelar</a>
        </div>
    </form>
</div>

<?php if ($isEdit): ?>
<!-- Upload de fotos -->
<div class="admin-card">
    <h2 style="font-size:1.3rem;">Fotos do álbum</h2>
    <p style="color:var(--c-muted);">
        As fotos são padronizadas automaticamente (proporção 4:3) para a galeria
        ficar uniforme. Você pode selecionar várias de uma vez.
    </p>

    <form method="post" enctype="multipart/form-data"
          action="<?= View::e(View::url('/admin/galeria/fotos/upload', $baseUrl)) ?>">
        <?= $csrfField ?>
        <input type="hidden" name="album_id" value="<?= $albumId ?>">
        <div class="form-actions" style="align-items:center;">
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
            <button type="submit" class="btn-admin">Enviar fotos</button>
        </div>
    </form>

    <?php if ($photos === []): ?>
        <p class="admin-empty">Nenhuma foto neste álbum ainda.</p>
    <?php else: ?>
        <div class="album-admin-grid">
            <?php foreach ($photos as $p): ?>
                <figure class="album-admin-photo">
                    <img src="<?= View::e($imageUrl((string) $p['image'])) ?>"
                         alt="<?= View::e((string) ($p['caption'] ?? '')) ?>" loading="lazy">
                    <form method="post" action="<?= View::e(View::url('/admin/galeria/fotos/remover', $baseUrl)) ?>"
                          onsubmit="return confirm('Remover esta foto?');">
                        <?= $csrfField ?>
                        <input type="hidden" name="photo_id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="album_id" value="<?= $albumId ?>">
                        <button type="submit" class="album-admin-photo__remove" aria-label="Remover foto">&times;</button>
                    </form>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
