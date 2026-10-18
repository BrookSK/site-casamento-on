<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Importação de presentes via planilha CSV.
 *
 * @var array                                            $app
 * @var string                                           $csrfField
 * @var array{type:string,message:string}|null           $flash
 * @var array{imported:int,errors:array<int,string>}|null $report
 */

$baseUrl = $app['base_url'] ?? '';
$report  = $report ?? null;
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>

<?php if (!empty($report) && !empty($report['errors'])): ?>
    <div class="admin-card">
        <h3 style="margin-top:0;">Linhas ignoradas (<?= (int) count($report['errors']) ?>)</h3>
        <p style="color:var(--c-muted);font-size:0.9rem;">
            As linhas abaixo não foram importadas. As demais linhas válidas foram cadastradas normalmente.
        </p>
        <ul style="margin:0;padding-left:1.1rem;line-height:1.6;">
            <?php foreach ($report['errors'] as $err): ?>
                <li><?= View::e((string) $err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="admin-card">
    <h3 style="margin-top:0;">Importar presentes por planilha</h3>
    <p style="color:var(--c-muted);font-size:0.92rem;line-height:1.6;max-width:60ch;">
        Envie um arquivo <strong>.csv</strong> (UTF-8) para cadastrar vários presentes de uma só vez.
        A primeira linha deve conter os cabeçalhos. O único campo obrigatório é <strong>nome</strong>.
        Os presentes importados entram no fim da lista, sem foto (você pode adicionar a imagem depois,
        pela edição de cada presente).
    </p>

    <table class="admin-table" style="margin:1rem 0;max-width:640px;">
        <thead>
            <tr>
                <th>Coluna</th>
                <th>Obrigatório?</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>nome</code></td>
                <td>Sim</td>
                <td>Nome do presente.</td>
            </tr>
            <tr>
                <td><code>descricao</code></td>
                <td>Não</td>
                <td>Texto livre (pode ficar vazio).</td>
            </tr>
            <tr>
                <td><code>preco</code></td>
                <td>Não</td>
                <td>Formato brasileiro (<code>1.500,00</code>) ou simples (<code>1500.00</code>). Vazio = R$ 0,00.</td>
            </tr>
            <tr>
                <td><code>status</code></td>
                <td>Não</td>
                <td><code>disponivel</code>, <code>reservado</code> ou <code>presenteado</code>. Vazio = disponível.</td>
            </tr>
        </tbody>
    </table>

    <p style="margin:0 0 1.25rem;">
        <a class="btn-admin btn-admin--ghost btn-admin--sm"
           href="<?= View::e(View::url('/admin/presentes/importar/modelo', $baseUrl)) ?>">
            Baixar planilha modelo (CSV)
        </a>
    </p>

    <form method="post" enctype="multipart/form-data"
          action="<?= View::e(View::url('/admin/presentes/importar', $baseUrl)) ?>">
        <?= $csrfField ?>

        <div class="field">
            <label for="sheet">Arquivo CSV *</label>
            <input type="file" id="sheet" name="sheet" accept=".csv,text/csv" required>
            <small style="color:var(--c-muted);font-size:0.8rem;">
                Separador aceito: vírgula (,) ou ponto e vírgula (;). Codificação UTF-8.
            </small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin">Importar presentes</button>
            <a class="btn-admin btn-admin--ghost"
               href="<?= View::e(View::url('/admin/presentes', $baseUrl)) ?>">Voltar</a>
        </div>
    </form>
</div>
