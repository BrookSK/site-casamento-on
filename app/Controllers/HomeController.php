<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Asaas;
use App\Core\Content;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\GuestAuth;
use App\Models\Gift;
use App\Models\Rsvp;
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

        // Convidado logado: busca a confirmação já registrada (se houver), para
        // mostrar o resumo com edição de acompanhantes em vez do formulário.
        $currentGuest = GuestAuth::user();
        $myRsvp       = null;
        if ($currentGuest !== null) {
            try {
                $myRsvp = (new Rsvp($this->config))->findLatestForGuest((int) $currentGuest['id']);
            } catch (Throwable $e) {
                error_log('[Home] findLatestForGuest: ' . $e->getMessage());
            }
        }

        $this->view('home', [
            'pageTitle'       => $bride . ' & ' . $groom,
            'gifts'           => $gifts,
            'content'         => $content,
            'csrfField'       => Csrf::field(),
            'rsvpStatus'      => isset($_GET['rsvp']) ? (string) $_GET['rsvp'] : null,
            'giftStatus'      => isset($_GET['gift']) ? (string) $_GET['gift'] : null,
            'paymentsEnabled' => Asaas::isConfigured($this->config),
            'currentGuest'    => $currentGuest,
            'myRsvp'          => $myRsvp,
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
            // Traz TODOS os presentes (inclusive os já comprados), para que os
            // adquiridos permaneçam visíveis na lista, porém indisponíveis.
            $rows = (new Gift($this->config))->listAll(false);
            if ($rows !== []) {
                return array_map(static function (array $g): array {
                    $cents  = (int) $g['price_cents'];
                    $status = (string) ($g['status'] ?? 'available');
                    return [
                        'id'          => (int) $g['id'],
                        'name'        => (string) $g['name'],
                        'description' => (string) ($g['description'] ?? ''),
                        'image'       => (string) ($g['image'] ?? ''),
                        'status'      => $status,
                        // Indisponível para compra quando reservado ou comprado.
                        'taken'       => in_array($status, ['purchased', 'reserved'], true),
                        'price'       => 'R$ ' . number_format($cents / 100, 2, ',', '.'),
                        'price_cents' => $cents,
                    ];
                }, $rows);
            }
        } catch (Throwable $e) {
            error_log('[Home] loadGifts fallback: ' . $e->getMessage());
        }

        return $this->config['gifts'] ?? [];
    }
}
