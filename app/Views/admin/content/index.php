<?php

declare(strict_types=1);

use App\Core\View;

/**
 * "Textos" — edição dos textos públicos, organizado por seção.
 *
 * @var array                 $app
 * @var \App\Core\Content      $content
 * @var string                 $csrfField
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';

/** Helper: campo de texto. */
$textField = function (string $key, string $label, string $default = '', bool $area = false) use ($content): void {
    $val = $content->text($key, $default);
    ?>
    <div class="field">
        <label for="f-<?= View::e($key) ?>"><?= View::e($label) ?></label>
        <?php if ($area): ?>
            <textarea id="f-<?= View::e($key) ?>" name="<?= View::e($key) ?>" rows="3"><?= View::e($val) ?></textarea>
        <?php else: ?>
            <input type="text" id="f-<?= View::e($key) ?>" name="<?= View::e($key) ?>" value="<?= View::e($val) ?>">
        <?php endif; ?>
    </div>
    <?php
};
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<p style="color:var(--c-muted);margin-bottom:1.25rem;">
    Edite os textos das telas públicas. As alterações aparecem no site após salvar.
</p>

<form method="post" action="<?= View::e(View::url('/admin/textos-e-imagens/salvar', $baseUrl)) ?>">
    <?= $csrfField ?>

    <!-- ===== Nomes ===== -->
    <div class="admin-card">
        <h2>Nomes</h2>
        <div class="form-grid">
            <div class="form-row">
                <?php $textField('bride', 'Nome da noiva', 'Noiva'); ?>
                <?php $textField('groom', 'Nome do noivo', 'Noivo'); ?>
            </div>
            <?php $textField('hashtag', 'Hashtag', '#NossoCasamento'); ?>
        </div>
    </div>

    <!-- ===== Nossa história ===== -->
    <div class="admin-card">
        <h2>Nossa história</h2>
        <div class="form-grid">
            <?php $textField('history_title', 'Título', 'Dois caminhos, uma só direção'); ?>
            <?php $textField('history_text', 'Texto de introdução', '', true); ?>
            <div class="form-row">
                <?php $textField('fact1_label', 'Fato 1 — rótulo', 'Onde tudo começou'); ?>
                <?php $textField('fact1_value', 'Fato 1 — valor', 'A definir'); ?>
            </div>
            <div class="form-row">
                <?php $textField('fact2_label', 'Fato 2 — rótulo', 'O pedido'); ?>
                <?php $textField('fact2_value', 'Fato 2 — valor', 'A definir'); ?>
            </div>
            <div class="form-row">
                <?php $textField('fact3_label', 'Fato 3 — rótulo', 'Nosso lema'); ?>
                <?php $textField('fact3_value', 'Fato 3 — valor', 'A definir'); ?>
            </div>
        </div>
    </div>

    <!-- ===== Informações do evento ===== -->
    <div class="admin-card">
        <h2>Informações do evento</h2>
        <div class="form-grid">
            <div class="form-row">
                <div class="field">
                    <label for="f-date_label">Data</label>
                    <input type="text" id="f-date_label" name="date_label"
                           value="<?= View::e($content->text('date_label', '')) ?>"
                           placeholder="dd/mm/aa" inputmode="numeric" data-mask="date">
                </div>
                <div class="field">
                    <label for="f-time_label">Horário</label>
                    <input type="text" id="f-time_label" name="time_label"
                           value="<?= View::e($content->text('time_label', '')) ?>"
                           placeholder="hh:mm" inputmode="numeric" data-mask="time">
                </div>
            </div>
            <div class="form-row">
                <?php $textField('ceremony_place', 'Cerimônia — local', 'Local a definir'); ?>
                <?php $textField('ceremony_address', 'Cerimônia — endereço', 'Endereço a definir'); ?>
            </div>
            <?php $textField('ceremony_details', 'Cerimônia — detalhes', 'Detalhes serão divulgados em breve.'); ?>
            <div class="form-row">
                <?php $textField('reception_place', 'Recepção — local', 'Local a definir'); ?>
                <?php $textField('reception_address', 'Recepção — endereço', 'Endereço a definir'); ?>
            </div>
            <?php $textField('reception_details', 'Recepção — detalhes', 'Detalhes serão divulgados em breve.'); ?>
        </div>
    </div>

    <div class="admin-card" style="position:sticky;bottom:0;">
        <button type="submit" class="btn-admin">Salvar alterações</button>
    </div>
</form>
