<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\Rsvp;
use PDOException;
use Throwable;

/**
 * Confirmação de presença (RSVP) — lado público.
 *
 * Formulário aberto: o convidado informa nome, contato, se vai, nº de
 * acompanhantes e uma mensagem. Grava em guests + rsvps. Sem login
 * (login de convidado fica para etapa futura).
 */
final class RsvpController extends Controller
{
    public function store(): void
    {
        // Proteção CSRF (token exibido no formulário da home).
        if (!Csrf::validate($_POST[Csrf::fieldName()] ?? null)) {
            $this->redirectHome('rsvp', 'erro-sessao');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->redirectHome('rsvp', 'erro-nome');
        }

        $attending = ($_POST['attending'] ?? '') === 'yes';

        // Acompanhantes: a quantidade é derivada dos nomes informados.
        // Cada campo "Acompanhante N" deve ter um nome (não pode ficar vazio).
        $companionNames = [];
        if ($attending) {
            $raw = $_POST['companion_names'] ?? [];
            if (is_array($raw)) {
                $hasEmpty = false;
                foreach ($raw as $cn) {
                    $cn = trim((string) $cn);
                    if ($cn === '') {
                        $hasEmpty = true;
                    } else {
                        $companionNames[] = $cn;
                    }
                }
                // Se o convidado adicionou um campo e deixou em branco, exige o nome.
                if ($hasEmpty) {
                    $this->redirectHome('rsvp', 'erro-acompanhantes');
                }
            }
            $companionNames = array_slice($companionNames, 0, 20);
        }
        $companions = count($companionNames);

        $data = [
            'name'  => mb_substr($name, 0, 150),
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
        ];

        $rsvp = [
            'status'     => $attending ? 'confirmed' : 'declined',
            'companions' => $attending ? $companions : 0,
            'message'    => trim((string) ($_POST['message'] ?? '')) ?: null,
        ];

        try {
            (new Rsvp($this->config))->createWithGuest($data, $rsvp, $companionNames);
        } catch (PDOException $e) {
            // 23000 / 1062 = violação de chave única (e-mail já cadastrado).
            if ($e->getCode() === '23000' || ($e->errorInfo[1] ?? null) === 1062) {
                $this->redirectHome('rsvp', 'erro-email');
            }
            error_log('[Rsvp] store (pdo): ' . $e->getMessage());
            $this->redirectHome('rsvp', 'erro');
        } catch (Throwable $e) {
            error_log('[Rsvp] store: ' . $e->getMessage());
            $this->redirectHome('rsvp', 'erro');
        }

        $this->redirectHome('rsvp', 'ok');
    }

    /** Redireciona para a home, numa âncora, com um status de retorno. */
    private function redirectHome(string $anchor, string $status): void
    {
        $baseUrl = rtrim((string) ($this->config['app']['base_url'] ?? ''), '/');
        header('Location: ' . $baseUrl . '/?rsvp=' . urlencode($status) . '#' . $anchor);
        exit;
    }
}
