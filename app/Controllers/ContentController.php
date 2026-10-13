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
                if (array_key_exists($key, $_POST)) {
                    $textPairs[$key] = trim((string) $_POST[$key]);
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
