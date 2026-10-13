<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Gift;
use Throwable;

/**
 * Controlador da página inicial.
 *
 * Lê os presentes do banco (com fallback para a config caso o banco não
 * esteja disponível) e passa o token CSRF para o formulário de confirmação.
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        $gifts   = $this->loadGifts();
        $content = new Content($this->config);

        // Nomes para o título vêm do conteúdo editável (fallback para config).
        $bride = $content->text('bride', (string) ($this->config['wedding']['bride'] ?? 'Noiva'));
        $groom = $content->text('groom', (string) ($this->config['wedding']['groom'] ?? 'Noivo'));

        $this->view('home', [
            'pageTitle' => $bride . ' & ' . $groom,
            'gifts'     => $gifts,
            'content'   => $content,
            'csrfField' => Csrf::field(),
            'rsvpStatus'=> isset($_GET['rsvp']) ? (string) $_GET['rsvp'] : null,
        ]);
    }

    /**
     * Carrega presentes disponíveis do banco. Se o banco falhar ou estiver
     * vazio, usa a lista provisória da config (mantém a home sempre bonita).
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadGifts(): array
    {
        try {
            $rows = (new Gift($this->config))->listAll(true);
            if ($rows !== []) {
                return array_map(static function (array $g): array {
                    return [
                        'name'  => (string) $g['name'],
                        'price' => 'R$ ' . number_format(((int) $g['price_cents']) / 100, 2, ',', '.'),
                    ];
                }, $rows);
            }
        } catch (Throwable $e) {
            error_log('[Home] loadGifts fallback: ' . $e->getMessage());
        }

        return $this->config['gifts'] ?? [];
    }
}
