<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Content;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Setting;
use Throwable;

/**
 * "Textos" — edição dos textos das telas públicas.
 *
 * Organizado por seção (nomes, nossa história, evento). Os textos são salvos
 * na tabela settings. Imagens NÃO são editáveis por aqui (são arquivos fixos
 * em assets/img, trocados diretamente no servidor).
 */
final class ContentController extends Controller
{
    private function guard(): void
    {
        if (!Auth::check()) {
            $this->redirect('/admin/login');
        }
    }

    public function index(): void
    {
        $this->guard();

        $content = new Content($this->config);

        $this->viewAdmin('admin/content/index', [
            'pageTitle' => 'Conteúdo',
            'active'    => 'content',
            'admin'     => Auth::user(),
            'content'   => $content,
            'csrfField' => Csrf::field(),
            'flash'     => $this->pullFlash(),
        ]);
    }

    public function save(): void
    {
        $this->guard();

        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->setFlash('error', 'Sessão expirada. Tente novamente.');
            $this->redirect('/admin/textos-e-imagens');
        }

        try {
            $settings = new Setting($this->config);

            $textPairs = [];
            foreach (Content::TEXT_KEYS as $key) {
                // date_label é derivado de event_date (não há campo próprio).
                if ($key === 'date_label') {
                    continue;
                }
                if (array_key_exists($key, $_POST)) {
                    $textPairs[$key] = trim((string) $_POST[$key]);
                }
            }

            // Data do evento: valida o formato ISO (YYYY-MM-DD) e gera o rótulo
            // legível exibido no site. Assim o texto da data e a contagem
            // regressiva usam SEMPRE a mesma data (ficam sincronizados).
            if (array_key_exists('event_date', $_POST)) {
                $eventDate = trim((string) $_POST['event_date']);
                if ($eventDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                    $textPairs['event_date'] = $eventDate;
                    $textPairs['date_label'] = $this->formatDateLabel($eventDate);
                } else {
                    // Data em branco/invalida: limpa ambos.
                    $textPairs['event_date'] = '';
                    $textPairs['date_label'] = '';
                }
            }

            if ($textPairs !== []) {
                $settings->setMany($textPairs);
            }

            $this->setFlash('success', 'Textos atualizados com sucesso.');
        } catch (Throwable $e) {
            error_log('[Content] save: ' . $e->getMessage());
            $this->setFlash('error', 'Não foi possível salvar: ' . $e->getMessage());
        }

        $this->redirect('/admin/textos-e-imagens');
    }

    /**
     * Gera o rótulo legível da data (ex.: "15 de agosto de 2026") a partir de
     * uma data ISO (YYYY-MM-DD). Usado para exibir no site a mesma data que
     * alimenta a contagem regressiva.
     */
    private function formatDateLabel(string $iso): string
    {
        $ts = strtotime($iso);
        if ($ts === false) {
            return '';
        }
        $meses = [
            1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
            'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
        ];
        $dia = (int) date('j', $ts);
        $mes = $meses[(int) date('n', $ts)] ?? '';
        $ano = date('Y', $ts);
        return $dia . ' de ' . $mes . ' de ' . $ano;
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
}
