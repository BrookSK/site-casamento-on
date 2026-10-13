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
<div class="admin-card">
    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/presentes/salvar', $baseUrl)) ?>">
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

        <div class="form-row">
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
                <label for="image">Imagem (nome do arquivo)</label>
                <input type="text" id="image" name="image" value="<?= View::e($image) ?>" placeholder="ex.: panelas.jpg">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin"><?= $isEdit ? 'Salvar alterações' : 'Criar presente' ?></button>
            <a class="btn-admin btn-admin--ghost" href="<?= View::e(View::url('/admin/presentes', $baseUrl)) ?>">Cancelar</a>
        </div>
    </form>
</div>
