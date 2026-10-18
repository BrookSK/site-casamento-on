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
        <h3 style="margin-top:0;">Avisos da importação (<?= (int) count($report['errors']) ?>)</h3>
        <p style="color:var(--c-muted);font-size:0.9rem;">
            Veja abaixo as linhas que precisaram de atenção. Linhas com dados inválidos (ex.: sem
            nome) são ignoradas; presentes com imagem ausente ou inválida são importados sem foto.
            Os demais presentes válidos foram cadastrados normalmente.
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
    <p style="color:var(--c-muted);font-size:0.92rem;line-height:1.6;max-width:68ch;">
        Cadastre vários presentes de uma só vez enviando uma planilha. Você pode enviar:
    </p>
    <ul style="color:var(--c-muted);font-size:0.92rem;line-height:1.7;max-width:68ch;margin-top:0;">
        <li>um arquivo <strong>.csv</strong> — cadastra os presentes <em>sem</em> foto; ou</li>
        <li>um arquivo <strong>.zip</strong> — contendo o CSV <em>e</em> as imagens, associando cada
            foto ao presente pela coluna <code>imagem</code>.</li>
    </ul>
    <p style="color:var(--c-muted);font-size:0.92rem;line-height:1.6;max-width:68ch;">
        A primeira linha do CSV deve conter os cabeçalhos. O único campo obrigatório é
        <strong>nome</strong>. Os presentes importados são <strong>adicionados ao final da lista</strong>,
        sem substituir os já existentes.
    </p>

    <h4 style="margin:1.25rem 0 0.25rem;">Campos do CSV</h4>
    <table class="admin-table" style="margin:0.5rem 0 1rem;max-width:720px;">
        <thead>
            <tr>
                <th>Coluna</th>
                <th>Obrigatório?</th>
                <th>Regras de preenchimento</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><code>nome</code></td>
                <td>Sim</td>
                <td>Nome do presente. Não pode ficar vazio (linha sem nome é ignorada).</td>
            </tr>
            <tr>
                <td><code>descricao</code></td>
                <td>Não</td>
                <td>Texto livre. Pode ficar vazio.</td>
            </tr>
            <tr>
                <td><code>preco</code></td>
                <td>Não</td>
                <td>Formato brasileiro (<code>1.500,00</code>) ou simples (<code>1500.00</code>).
                    Vazio equivale a <strong>R$ 0,00</strong>.</td>
            </tr>
            <tr>
                <td><code>status</code></td>
                <td>Não</td>
                <td><code>disponivel</code>, <code>reservado</code> ou <code>presenteado</code>.
                    Vazio equivale a <strong>disponivel</strong>.</td>
            </tr>
            <tr>
                <td><code>imagem</code></td>
                <td>Não</td>
                <td>Nome exato do arquivo de imagem dentro do ZIP (ex.: <code>jogo-de-panelas.jpg</code>).
                    Usado apenas na importação por ZIP. Vazio = presente sem foto.</td>
            </tr>
        </tbody>
    </table>

    <h4 style="margin:1.25rem 0 0.25rem;">Formato e regras</h4>
    <ul style="color:var(--c-muted);font-size:0.9rem;line-height:1.7;max-width:68ch;margin-top:0.25rem;">
        <li><strong>Codificação:</strong> UTF-8 (acentos preservados).</li>
        <li><strong>Separador do CSV:</strong> vírgula (<code>,</code>) ou ponto e vírgula (<code>;</code>) —
            detectado automaticamente.</li>
        <li><strong>Imagens aceitas:</strong> JPG, JPEG, PNG e WebP (até 5 MB cada). São recortadas e
            padronizadas automaticamente, igual ao cadastro manual.</li>
        <li><strong>Imagem ausente ou inválida:</strong> o presente é importado <em>sem foto</em> e um
            aviso indica a linha afetada — os demais presentes não são comprometidos.</li>
        <li><strong>Estrutura do ZIP:</strong> coloque o CSV e as imagens dentro do pacote. As imagens
            são localizadas pelo nome do arquivo (a pasta interna não importa).</li>
    </ul>

    <pre style="background:var(--c-bg,#f6f4ef);padding:0.75rem 1rem;border-radius:6px;font-size:0.82rem;line-height:1.5;overflow:auto;max-width:720px;">presentes.zip
├── presentes.csv
├── jogo-de-panelas.jpg
└── lua-de-mel.png</pre>

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
            <label for="sheet">Arquivo CSV ou ZIP *</label>
            <input type="file" id="sheet" name="sheet" accept=".csv,.zip,text/csv,application/zip" required>
            <small style="color:var(--c-muted);font-size:0.8rem;">
                Envie um <code>.csv</code> (sem imagens) ou um <code>.zip</code> (CSV + imagens).
            </small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin">Importar presentes</button>
            <a class="btn-admin btn-admin--ghost"
               href="<?= View::e(View::url('/admin/presentes', $baseUrl)) ?>">Voltar</a>
        </div>
    </form>
</div>
