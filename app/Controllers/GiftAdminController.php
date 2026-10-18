<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\ImageUploader;
use App\Models\Gift;
use App\Models\GiftPurchase;
use Throwable;

/**
 * Gerenciamento da lista de presentes (CRUD, área protegida).
 */
final class GiftAdminController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    /** Lista todos os presentes. */
    public function index(): void
    {
        $this->guard();

        $rows  = [];
        $error = null;
        try {
            $model = new Gift($this->config);
            // Normaliza a sequência (1..N) para a coluna "Ordem" fazer sentido
            // mesmo em bases antigas com vários sort_order iguais a 0.
            $model->normalizeOrder();
            $rows = $model->listAll();
        } catch (Throwable $e) {
            error_log('[GiftAdmin] ' . $e->getMessage());
            $error = 'Não foi possível carregar os presentes.';
        }

        $this->viewAdmin('admin/gifts/index', [
            'pageTitle' => 'Presentes',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'rows'      => $rows,
            'error'     => $error,
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
        ]);
    }

    /**
     * Rota legada: "Contribuições" foi consolidada no painel Financeiro, que é
     * um superconjunto (mesmos dados + taxas, líquido, filtros, detalhe,
     * histórico e exportação). Mantida apenas para não quebrar links antigos:
     * redireciona para /admin/financeiro.
     */
    public function purchases(): void
    {
        $this->guard();
        $this->redirect('/admin/financeiro');
    }

    /** Formulário de novo presente. */
    public function create(): void
    {
        $this->guard();
        $this->viewAdmin('admin/gifts/form', [
            'pageTitle' => 'Novo presente',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'gift'      => null,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Formulário de edição. */
    public function edit(): void
    {
        $this->guard();
        $id = (int) ($_GET['id'] ?? 0);

        $gift = null;
        try {
            $gift = (new Gift($this->config))->find($id);
        } catch (Throwable $e) {
            error_log('[GiftAdmin] edit: ' . $e->getMessage());
        }

        if ($gift === null) {
            $this->setFlash('error', 'Presente não encontrado.');
            $this->redirect('/admin/presentes');
        }

        $this->viewAdmin('admin/gifts/form', [
            'pageTitle' => 'Editar presente',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'gift'      => $gift,
            'csrfField' => Csrf::field(),
        ]);
    }

    /** Cria ou atualiza (um único endpoint de salvar). */
    public function save(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/presentes');
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($name === '') {
            $this->setFlash('error', 'O nome do presente é obrigatório.');
            $this->redirect($id > 0 ? '/admin/presentes/editar?id=' . $id : '/admin/presentes/novo');
        }

        // Preço: aceita "150,00" ou "150.00" e converte para centavos.
        $priceRaw   = str_replace(['.', ' '], '', (string) ($_POST['price'] ?? '0'));
        $priceRaw   = str_replace(',', '.', $priceRaw);
        $priceCents = (int) round(((float) $priceRaw) * 100);

        $allowedStatus = ['available', 'reserved', 'purchased'];
        $status = in_array($_POST['status'] ?? '', $allowedStatus, true)
            ? (string) $_POST['status'] : 'available';

        $model = new Gift($this->config);

        // Imagem atual (quando editando), para preservar/substituir/remover.
        $currentImage = null;
        if ($id > 0) {
            $existing = $model->find($id);
            $currentImage = $existing !== null ? ($existing['image'] ?? null) : null;

            // Trava de segurança: um presente efetivamente PAGO (purchased) não
            // pode ter o status alterado (nem voltar a disponível). Protege o
            // histórico financeiro e evita vender o mesmo presente duas vezes.
            // A view já esconde a opção; aqui garantimos no servidor.
            if ($existing !== null && (string) ($existing['status'] ?? '') === 'purchased') {
                $status = 'purchased';
            }
        }
        $image = $currentImage;

        // 1) Upload de arquivo (prioritário): padroniza e salva a nova imagem.
        $uploaded = $_FILES['image_file'] ?? null;
        if (is_array($uploaded) && (int) ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $newPath = ImageUploader::handle($uploaded, 'gifts');
                // Remove a antiga só depois de a nova ter sido salva com sucesso.
                if ($currentImage) {
                    ImageUploader::remove((string) $currentImage);
                }
                $image = $newPath;
            } catch (Throwable $e) {
                error_log('[GiftAdmin] upload: ' . $e->getMessage());
                $this->setFlash('error', 'Imagem: ' . $e->getMessage());
                $this->redirect($id > 0 ? '/admin/presentes/editar?id=' . $id : '/admin/presentes/novo');
            }
        } elseif (($_POST['remove_image'] ?? '') === '1') {
            // 2) Remoção explícita da imagem (checkbox "remover imagem").
            if ($currentImage) {
                ImageUploader::remove((string) $currentImage);
            }
            $image = null;
        } else {
            // 3) Sem upload: permite informar/editar um nome de arquivo manual
            //    (compatibilidade com o campo de texto antigo).
            $manual = trim((string) ($_POST['image'] ?? ''));
            if ($manual !== '') {
                $image = $manual;
            }
        }

        // Ordem de exibição: não vem mais do formulário. Ao criar, o presente
        // entra no FIM da fila; ao editar, preserva a ordem atual (a reordenação
        // é feita por arrastar-e-soltar na lista).
        if ($id > 0) {
            $sortOrder = isset($existing['sort_order']) ? (int) $existing['sort_order'] : $model->nextSortOrder();
        } else {
            $sortOrder = $model->nextSortOrder();
        }

        $data = [
            'name'        => $name,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'price_cents' => max(0, $priceCents),
            'image'       => $image ?: null,
            'status'      => $status,
            'sort_order'  => $sortOrder,
        ];

        try {
            if ($id > 0) {
                $model->updateGift($id, $data);
                $this->setFlash('success', 'Presente atualizado.');
            } else {
                $model->createGift($data);
                $this->setFlash('success', 'Presente criado.');
            }
        } catch (Throwable $e) {
            error_log('[GiftAdmin] save: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar o presente.');
        }

        $this->redirect('/admin/presentes');
    }

    /** Tela de importação de presentes via planilha (CSV). */
    public function importForm(): void
    {
        $this->guard();
        $this->viewAdmin('admin/gifts/import', [
            'pageTitle' => 'Importar presentes',
            'active'    => 'gifts',
            'admin'     => Auth::user(),
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
            'report'    => $this->pullImportReport(),
        ]);
    }

    /**
     * Baixa um CSV modelo com os cabeçalhos corretos e uma linha de exemplo,
     * para o usuário preencher e reenviar. UTF-8 com BOM para abrir acentuado
     * corretamente no Excel.
     */
    public function importTemplate(): void
    {
        $this->guard();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="modelo-presentes.csv"');

        $out = fopen('php://output', 'wb');
        // BOM para o Excel reconhecer UTF-8.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['nome', 'descricao', 'preco', 'status']);
        fputcsv($out, ['Jogo de panelas', 'Conjunto 5 peças antiaderente', '450,00', 'disponivel']);
        fputcsv($out, ['Lua de mel', 'Contribuição para a viagem', '1.500,00', 'disponivel']);
        fclose($out);
    }

    /**
     * Processa o upload da planilha, valida linha a linha e cadastra os
     * presentes válidos. Linhas inválidas são ignoradas (com relatório), sem
     * abortar a importação toda e sem criar presentes incompletos.
     */
    public function import(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/presentes/importar');
        }

        $file = $_FILES['sheet'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $this->setFlash('error', 'Selecione um arquivo CSV para importar.');
            $this->redirect('/admin/presentes/importar');
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->setFlash('error', 'Falha no envio do arquivo. Tente novamente.');
            $this->redirect('/admin/presentes/importar');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            $this->setFlash('error', 'Arquivo de upload inválido.');
            $this->redirect('/admin/presentes/importar');
        }

        // Valida a extensão (apenas .csv é aceito).
        $origName = (string) ($file['name'] ?? '');
        if (strtolower((string) pathinfo($origName, PATHINFO_EXTENSION)) !== 'csv') {
            $this->setFlash('error', 'Formato não suportado. Envie um arquivo .csv.');
            $this->redirect('/admin/presentes/importar');
        }

        try {
            $result = $this->parseCsv($tmp);
        } catch (Throwable $e) {
            error_log('[GiftAdmin] import parse: ' . $e->getMessage());
            $this->setFlash('error', $e->getMessage());
            $this->redirect('/admin/presentes/importar');
        }

        /** @var array{gifts: array<int, array<string, mixed>>, errors: array<int, string>} $result */
        $errors = $result['errors'];
        $valid  = $result['gifts'];

        $imported = 0;
        if ($valid !== []) {
            try {
                $model = new Gift($this->config);
                // Define a ordem sequencial a partir do fim da fila atual, para
                // os importados entrarem após os existentes, na ordem do arquivo.
                $next = $model->nextSortOrder();
                foreach ($valid as $i => $g) {
                    $valid[$i]['sort_order'] = $next + $i;
                }
                $imported = $model->createManyGifts($valid);
            } catch (Throwable $e) {
                error_log('[GiftAdmin] import save: ' . $e->getMessage());
                $this->setFlash('error', 'Não foi possível salvar os presentes importados.');
                $this->redirect('/admin/presentes/importar');
            }
        }

        $this->setImportReport([
            'imported' => $imported,
            'errors'   => $errors,
        ]);

        if ($imported > 0 && $errors === []) {
            $this->setFlash('success', $imported . ' presente(s) importado(s) com sucesso.');
            $this->redirect('/admin/presentes');
        }

        if ($imported > 0) {
            $this->setFlash(
                'success',
                $imported . ' presente(s) importado(s). '
                . count($errors) . ' linha(s) ignorada(s) por erro — veja os detalhes abaixo.'
            );
        } else {
            $this->setFlash('error', 'Nenhum presente foi importado. Corrija os erros abaixo e tente novamente.');
        }
        $this->redirect('/admin/presentes/importar');
    }

    /**
     * Lê e valida o CSV, devolvendo os presentes prontos para inserir e a
     * lista de erros (com número da linha). Não grava nada no banco.
     *
     * Cabeçalhos aceitos (1ª linha): nome, descricao, preco, status.
     * Único campo obrigatório por linha: nome.
     *
     * @return array{gifts: array<int, array<string, mixed>>, errors: array<int, string>}
     * @throws \RuntimeException em erro estrutural do arquivo (não abre, sem cabeçalho, etc.)
     */
    private function parseCsv(string $path): array
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        // Lê a primeira linha crua para remover BOM e detectar o separador.
        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            throw new \RuntimeException('O arquivo está vazio.');
        }
        // Remove BOM UTF-8, se houver.
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;

        // Detecta o separador: ";" (comum no Excel pt-BR) ou ",".
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $map    = $this->mapHeader($header);
        if (!isset($map['nome'])) {
            fclose($handle);
            throw new \RuntimeException(
                'Cabeçalho inválido. A primeira linha deve conter as colunas: nome, descricao, preco, status.'
            );
        }

        $allowedStatus = ['available', 'reserved', 'purchased'];
        $gifts  = [];
        $errors = [];
        $lineNo = 1; // o cabeçalho é a linha 1

        while (($cols = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNo++;

            // Pula linhas totalmente vazias (sem conteúdo em nenhuma célula).
            $joined = trim(implode('', array_map(static fn ($c) => (string) $c, $cols)));
            if ($joined === '') {
                continue;
            }

            $get = static function (string $key) use ($cols, $map): string {
                if (!isset($map[$key])) {
                    return '';
                }
                $idx = $map[$key];
                return isset($cols[$idx]) ? trim((string) $cols[$idx]) : '';
            };

            $name = $get('nome');
            if ($name === '') {
                $errors[] = 'Linha ' . $lineNo . ': o campo "nome" é obrigatório — linha ignorada.';
                continue;
            }

            // Preço: aceita "1.500,00", "1500.00" ou "1500". Vazio = 0.
            $priceRaw = $get('preco');
            $priceCents = 0;
            if ($priceRaw !== '') {
                $normalized = str_replace(['.', ' '], '', $priceRaw);
                $normalized = str_replace(',', '.', $normalized);
                if (!is_numeric($normalized)) {
                    $errors[] = 'Linha ' . $lineNo . ': preço inválido ("' . $priceRaw . '") — linha ignorada.';
                    continue;
                }
                $priceCents = (int) round(((float) $normalized) * 100);
                if ($priceCents < 0) {
                    $priceCents = 0;
                }
            }

            // Status: aceita rótulos pt-BR ou os valores internos. Vazio = disponível.
            $status = $this->normalizeStatus($get('status'));
            if ($status === null) {
                $errors[] = 'Linha ' . $lineNo . ': status inválido ("' . $get('status')
                    . '"). Use disponivel, reservado ou presenteado — linha ignorada.';
                continue;
            }
            if (!in_array($status, $allowedStatus, true)) {
                $status = 'available';
            }

            $gifts[] = [
                'name'        => $name,
                'description' => $get('descricao') !== '' ? $get('descricao') : null,
                'price_cents' => $priceCents,
                'image'       => null,
                'status'      => $status,
                'sort_order'  => 0, // definido ao salvar
            ];
        }

        fclose($handle);
        return ['gifts' => $gifts, 'errors' => $errors];
    }

    /**
     * Mapeia os nomes de coluna do cabeçalho para índices, aceitando variações
     * de acento/caixa (ex.: "Descrição" -> "descricao", "Preço" -> "preco").
     *
     * @param array<int, string> $header
     * @return array<string, int> chave canônica => índice da coluna
     */
    private function mapHeader(array $header): array
    {
        $aliases = [
            'nome'        => 'nome',
            'name'        => 'nome',
            'descricao'   => 'descricao',
            'descrição'   => 'descricao',
            'description' => 'descricao',
            'preco'       => 'preco',
            'preço'       => 'preco',
            'price'       => 'preco',
            'valor'       => 'preco',
            'status'      => 'status',
            'situacao'    => 'status',
            'situação'    => 'status',
        ];

        $map = [];
        foreach ($header as $idx => $title) {
            $key = mb_strtolower(trim((string) $title));
            if (isset($aliases[$key]) && !isset($map[$aliases[$key]])) {
                $map[$aliases[$key]] = $idx;
            }
        }
        return $map;
    }

    /**
     * Converte o status informado para o valor interno. Devolve null quando o
     * texto é inválido (para reportar erro). Vazio vira 'available'.
     */
    private function normalizeStatus(string $raw): ?string
    {
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return 'available';
        }
        $map = [
            'disponivel'  => 'available',
            'disponível'  => 'available',
            'available'   => 'available',
            'reservado'   => 'reserved',
            'reserved'    => 'reserved',
            'presenteado' => 'purchased',
            'comprado'    => 'purchased',
            'purchased'   => 'purchased',
        ];
        return $map[$raw] ?? null;
    }

    /** Remove um presente. */
    public function destroy(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/presentes');
        }

        $id = (int) ($_POST['id'] ?? 0);
        try {
            $model = new Gift($this->config);

            // Preserva o histórico de transações: não permite excluir um
            // presente que já tenha compras (exceto tentativas falhas).
            $relevant = (new GiftPurchase($this->config))->countRelevantByGift($id);
            if ($relevant > 0) {
                $this->setFlash(
                    'error',
                    'Este presente possui compras registradas e não pode ser excluído '
                    . '(para preservar o histórico financeiro). Marque-o como '
                    . '“Presenteado” se não quiser mais exibi-lo.'
                );
                $this->redirect('/admin/presentes');
            }

            // Remove a imagem do disco antes de apagar o registro.
            $gift = $model->find($id);
            $image = $gift !== null ? (string) ($gift['image'] ?? '') : '';

            $model->delete($id);

            if ($image !== '') {
                ImageUploader::remove($image);
            }
            $this->setFlash('success', 'Presente removido.');
        } catch (Throwable $e) {
            error_log('[GiftAdmin] destroy: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível remover.');
        }

        $this->redirect('/admin/presentes');
    }

    /**
     * Salva a nova ordenação (arrastar-e-soltar). Recebe os IDs na ordem
     * desejada e responde JSON. Chamado via fetch pela lista do admin.
     */
    public function reorder(): void
    {
        $this->guard();

        header('Content-Type: application/json; charset=utf-8');

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'Sessão expirada.']);
            return;
        }

        // Aceita order[]=1&order[]=2... ou um campo "order" com IDs separados
        // por vírgula (fallback). Converte tudo para inteiros.
        $raw = $_POST['order'] ?? [];
        if (is_string($raw)) {
            $raw = array_filter(array_map('trim', explode(',', $raw)), static fn ($s) => $s !== '');
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        $ids = array_map('intval', $raw);

        if ($ids === []) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nenhuma ordem recebida.']);
            return;
        }

        try {
            (new Gift($this->config))->reorder($ids);
            echo json_encode(['ok' => true]);
        } catch (Throwable $e) {
            error_log('[GiftAdmin] reorder: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar a ordem.']);
        }
    }

    // ---- Flash ----
    private function setFlash(string $type, string $message): void
    {
        Auth::start();
        $_SESSION['_flash'] = ['type' => $type, 'message' => $message];
    }

    /** @return array{type:string,message:string}|null */
    private function pullFlash(): ?array
    {
        Auth::start();
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $flash;
    }

    // ---- Relatório da importação (persistido na sessão até ser exibido) ----

    /** @param array{imported:int,errors:array<int,string>} $report */
    private function setImportReport(array $report): void
    {
        Auth::start();
        $_SESSION['_gift_import_report'] = $report;
    }

    /** @return array{imported:int,errors:array<int,string>}|null */
    private function pullImportReport(): ?array
    {
        Auth::start();
        $report = $_SESSION['_gift_import_report'] ?? null;
        unset($_SESSION['_gift_import_report']);
        return $report;
    }
}
