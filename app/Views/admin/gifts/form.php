<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Formulário de criar/editar presente.
 *
 * @var array                        $app
 * @var array<string, mixed>|null    $gift
 * @var string                       $csrfField
 */

$baseUrl = $app['base_url'] ?? '';
$isEdit  = $gift !== null;

$name        = $isEdit ? (string) $gift['name'] : '';
$description = $isEdit ? (string) ($gift['description'] ?? '') : '';
// Formato brasileiro: ponto para milhar, vírgula para decimal (ex.: 5.000,00).
$priceVal    = $isEdit ? number_format(((int) $gift['price_cents']) / 100, 2, ',', '.') : '';
$image       = $isEdit ? (string) ($gift['image'] ?? '') : '';
$statusCur   = $isEdit ? (string) $gift['status'] : 'available';
$sortOrder   = $isEdit ? (int) $gift['sort_order'] : 0;

$statuses = ['available' => 'Disponível', 'reserved' => 'Reservado', 'purchased' => 'Presenteado'];
?>
<?php
// Resolve a URL da imagem atual (aceita http, caminho absoluto ou nome
// relativo dentro de assets/img — inclusive a subpasta gifts/).
$currentImageUrl = '';
if ($image !== '') {
    $currentImageUrl = (str_starts_with($image, 'http') || str_starts_with($image, '/'))
        ? $image
        : rtrim($baseUrl, '/') . '/assets/img/' . ltrim($image, '/');
}
?>
<div class="admin-card">
    <form class="form-grid" method="post" enctype="multipart/form-data"
          action="<?= View::e(View::url('/admin/presentes/salvar', $baseUrl)) ?>">
        <?= $csrfField ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $gift['id'] ?>">
        <?php endif; ?>

        <div class="field">
            <label for="name">Nome *</label>
            <input type="text" id="name" name="name" value="<?= View::e($name) ?>" required autofocus>
        </div>

        <div class="field">
            <label for="description">Descrição</label>
            <textarea id="description" name="description"><?= View::e($description) ?></textarea>
        </div>

        <div class="form-row">
            <div class="field">
                <label for="price">Preço (R$)</label>
                <input type="text" id="price" name="price" value="<?= View::e($priceVal) ?>"
                       placeholder="5.000,00" inputmode="numeric" autocomplete="off" data-mask="money">
                <small style="color:var(--c-muted);font-size:0.8rem;">
                    Use vírgula para os centavos e ponto para o milhar. Ex.: 5.000,00
                </small>
            </div>
            <div class="field">
                <label for="sort_order">Ordem de exibição</label>
                <input type="number" id="sort_order" name="sort_order" value="<?= (int) $sortOrder ?>">
            </div>
        </div>

        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= View::e($value) ?>" <?= $statusCur === $value ? 'selected' : '' ?>>
                        <?= View::e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="field">
            <label for="image_file">Fotografia do presente</label>

            <div class="image-upload" data-image-upload>
                <div class="image-upload__preview" data-image-preview
                     <?= $currentImageUrl === '' ? 'hidden' : '' ?>>
                    <img data-image-preview-img
                         src="<?= View::e($currentImageUrl) ?>"
                         alt="Pré-visualização da fotografia do presente">
                </div>

                <input type="file" id="image_file" name="image_file"
                       accept="image/jpeg,image/png,image/webp"
                       data-image-input>

                <small style="color:var(--c-muted);font-size:0.8rem;">
                    JPG, PNG ou WebP (até 5 MB). A imagem é recortada e
                    padronizada automaticamente (proporção 4:3) para todos os
                    cards ficarem iguais.
                </small>

                <?php if ($isEdit && $currentImageUrl !== ''): ?>
                    <label class="checkbox-field" for="remove_image" style="margin-top:0.5rem;">
                        <input type="checkbox" id="remove_image" name="remove_image" value="1">
                        <span>Remover a fotografia atual</span>
                    </label>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin"><?= $isEdit ? 'Salvar alterações' : 'Criar presente' ?></button>
            <a class="btn-admin btn-admin--ghost" href="<?= View::e(View::url('/admin/presentes', $baseUrl)) ?>">Cancelar</a>
        </div>
    </form>
</div>
