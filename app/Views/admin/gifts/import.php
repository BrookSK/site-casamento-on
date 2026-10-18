<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Importação de presentes via planilha CSV (ou ZIP com imagens).
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

<div class="admin-card import-guide">
    <h3 style="margin-top:0;">Importar presentes por planilha</h3>
    <p style="color:var(--c-muted);font-size:0.92rem;line-height:1.6;max-width:68ch;margin-bottom:1.5rem;">
        Cadastre vários presentes de uma só vez a partir de uma planilha. Siga o passo a passo abaixo
        para montar o arquivo e enviar. Os presentes importados são
        <strong>adicionados ao final da lista</strong>, sem substituir os já cadastrados.
    </p>

    <!-- Passo 1: baixar o modelo -->
    <section class="import-step">
        <div class="import-step__num">1</div>
        <div class="import-step__body">
            <h4 class="import-step__title">Baixe a planilha modelo</h4>
            <p class="import-step__text">
                O modelo já vem com os cabeçalhos corretos e dois exemplos preenchidos. Abra-o no
                Excel, Google Sheets ou similar e substitua os exemplos pelos seus presentes.
            </p>
            <a class="btn-admin btn-admin--ghost btn-admin--sm" style="margin-top:0.25rem;"
               href="<?= View::e(View::url('/admin/presentes/importar/modelo', $baseUrl)) ?>">
                Baixar planilha modelo (CSV)
            </a>
        </div>
    </section>

    <!-- Passo 2: preencher as colunas -->
    <section class="import-step">
        <div class="import-step__num">2</div>
        <div class="import-step__body">
            <h4 class="import-step__title">Preencha uma linha por presente</h4>
            <p class="import-step__text">
                A primeira linha deve conter os cabeçalhos. Só <strong>nome</strong> é obrigatório;
                as demais colunas podem ficar em branco e assumem o valor padrão indicado abaixo.
            </p>
            <table class="admin-table import-fields" style="margin:0.75rem 0 0;">
                <thead>
                    <tr>
                        <th>Coluna</th>
                        <th>Obrigatório?</th>
                        <th>Como preencher</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>nome</code></td>
                        <td><span class="pill pill--req">Sim</span></td>
                        <td>Nome do presente. Se ficar vazio, a linha é ignorada.</td>
                    </tr>
                    <tr>
                        <td><code>descricao</code></td>
                        <td><span class="pill">Opcional</span></td>
                        <td>Texto livre. Pode ficar vazio.</td>
                    </tr>
                    <tr>
                        <td><code>preco</code></td>
                        <td><span class="pill">Opcional</span></td>
                        <td>Formato brasileiro (<code>1.500,00</code>) ou simples (<code>1500.00</code>).
                            <br>Vazio = <strong>R$ 0,00</strong>.</td>
                    </tr>
                    <tr>
                        <td><code>status</code></td>
                        <td><span class="pill">Opcional</span></td>
                        <td><code>disponivel</code>, <code>reservado</code> ou <code>presenteado</code>.
                            <br>Vazio = <strong>disponivel</strong>.</td>
                    </tr>
                    <tr>
                        <td><code>imagem</code></td>
                        <td><span class="pill">Opcional</span></td>
                        <td>Nome exato do arquivo da foto (ex.: <code>jogo-de-panelas.jpg</code>).
                            <br>Só tem efeito no envio por ZIP (ver passo 3). Vazio = presente sem foto.</td>
                    </tr>
                </tbody>
            </table>
            <p class="import-step__hint">
                <strong>Codificação:</strong> salve como UTF-8 (preserva os acentos).
                <strong>Separador:</strong> vírgula (<code>,</code>) ou ponto e vírgula (<code>;</code>) —
                reconhecemos os dois automaticamente.
            </p>
        </div>
    </section>

    <!-- Passo 3: fotos dos presentes (via ZIP) -->
    <section class="import-step">
        <div class="import-step__num">3</div>
        <div class="import-step__body">
            <h4 class="import-step__title">Envie as fotos dos presentes em um ZIP</h4>
            <p class="import-step__text">
                As imagens são as <strong>fotos dos presentes</strong> — a mesma imagem que aparece
                no card de cada presente no site. Para cadastrá-las junto, reúna a planilha e as fotos
                em um único arquivo <code>.zip</code> e envie esse ZIP no passo 4.
            </p>
            <p class="import-step__text" style="margin-top:0.6rem;">
                <strong>Quando usar cada formato:</strong> use o <code>.zip</code> quando os presentes
                tiverem foto (planilha + imagens juntas); use o <code>.csv</code> sozinho quando for
                cadastrar apenas os dados, sem foto. Em ambos os casos a planilha é a mesma.
            </p>
            <p class="import-step__text" style="margin-top:0.6rem;">
                Cada foto é ligada ao seu presente pelo <strong>nome do arquivo</strong> informado na
                coluna <code>imagem</code> da planilha. O nome precisa ser idêntico (ex.: a linha com
                <code>imagem = jogo-de-panelas.jpg</code> recebe o arquivo <code>jogo-de-panelas.jpg</code>
                do ZIP).
            </p>
            <ul class="import-step__list">
                <li><strong>Formatos aceitos:</strong> JPG, JPEG, PNG ou WebP, até 5 MB cada. As fotos
                    são recortadas e padronizadas automaticamente, igual ao cadastro manual.</li>
                <li><strong>Foto ausente ou inválida:</strong> se o arquivo indicado não estiver no ZIP
                    ou não for uma imagem válida, o presente é cadastrado mesmo assim (sem foto) e um
                    aviso indica a linha afetada — os demais presentes não são comprometidos.</li>
            </ul>
            <p class="import-step__hint" style="margin-bottom:0.35rem;">
                <strong>Estrutura esperada do ZIP:</strong> coloque a planilha e as fotos na raiz do
                pacote. Os nomes das fotos devem bater com a coluna <code>imagem</code>.
            </p>
<pre class="import-tree">presentes.zip
├── presentes.csv          (a planilha preenchida)
├── jogo-de-panelas.jpg    (foto do presente "Jogo de panelas")
└── lua-de-mel.png         (foto do presente "Lua de mel")</pre>
        </div>
    </section>

    <!-- Passo 4: enviar -->
    <section class="import-step import-step--last">
        <div class="import-step__num">4</div>
        <div class="import-step__body">
            <h4 class="import-step__title">Envie o arquivo</h4>
            <form method="post" enctype="multipart/form-data"
                  action="<?= View::e(View::url('/admin/presentes/importar', $baseUrl)) ?>">
                <?= $csrfField ?>

                <div class="field">
                    <label for="sheet">Arquivo CSV ou ZIP *</label>

                    <input type="file" id="sheet" name="sheet"
                           accept=".csv,.zip,text/csv,application/zip"
                           class="file-input__native" data-file-input required>

                    <div class="file-input">
                        <label for="sheet" class="file-input__button">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <span>Escolher arquivo</span>
                        </label>
                        <span class="file-input__name" data-file-name>Nenhum arquivo escolhido</span>
                    </div>

                    <small style="color:var(--c-muted);font-size:0.8rem;display:block;margin-top:0.5rem;">
                        Envie um <code>.csv</code> (sem imagens) ou um <code>.zip</code> (planilha + imagens).
                    </small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-admin">Importar presentes</button>
                    <a class="btn-admin btn-admin--ghost"
                       href="<?= View::e(View::url('/admin/presentes', $baseUrl)) ?>">Voltar</a>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
(function () {
    'use strict';
    // Mostra o nome do arquivo escolhido ao lado do botão estilizado.
    // Autônomo (não depende do fluxo de preview de imagem do cadastro manual).
    var input = document.querySelector('[data-file-input]');
    if (!input) { return; }
    var nameEl = document.querySelector('[data-file-name]');
    if (!nameEl) { return; }
    var defaultName = nameEl.textContent;
    input.addEventListener('change', function () {
        var f = input.files && input.files[0];
        if (f) {
            nameEl.textContent = f.name;
            nameEl.classList.add('has-file');
        } else {
            nameEl.textContent = defaultName;
            nameEl.classList.remove('has-file');
        }
    });
})();
</script>
