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
            // Dados do último envio do formulário de presente que voltou com
            // erro (uso único): reabre o popup preenchido com o motivo.
            'giftOld'         => $this->pullGiftOld(),
            'paymentsEnabled' => Asaas::isConfigured($this->config),
            'currentGuest'    => $currentGuest,
            'myRsvp'          => $myRsvp,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function pullGiftOld(): ?array
    {
        $old = $_SESSION['gift_old'] ?? null;
        unset($_SESSION['gift_old']);
        return is_array($old) ? $old : null;
    }

    /**
     * Carrega presentes disponíveis do banco. Se o banco falhar ou estiver
     * vazio, retorna uma lista vazia — nenhum card provisório é exibido.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadGifts(): array
    {
        try {
            // Limpeza oportunista das cobranças pendentes abandonadas.
            GiftController::expireStalePayments($this->config);

            // Todos os presentes ficam sempre disponíveis: o mesmo presente
            // pode ser dado por várias pessoas (o casal pediu para não mostrar
            // "já escolhido" — muita gente escolhe pelo valor).
            // Só os ocultos (definidos no painel) ficam de fora.
            $rows = (new Gift($this->config))->listAll(true);
            if ($rows !== []) {
                return array_map(static function (array $g): array {
                    $cents = (int) $g['price_cents'];
                    return [
                        'id'          => (int) $g['id'],
                        'name'        => (string) $g['name'],
                        'description' => (string) ($g['description'] ?? ''),
                        'image'       => (string) ($g['image'] ?? ''),
                        'price'       => 'R$ ' . number_format($cents / 100, 2, ',', '.'),
                        'price_cents' => $cents,
                    ];
                }, $rows);
            }
        } catch (Throwable $e) {
            error_log('[Home] loadGifts: ' . $e->getMessage());
        }

        // Sem presentes cadastrados (ou banco indisponível): não exibe cards
        // provisórios — a seção fica sem a grade de presentes.
        return [];
    }
}
