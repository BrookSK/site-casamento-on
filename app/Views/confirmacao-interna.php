<?php

declare(strict_types=1);

/**
 * Página exclusiva de confirmação de presença (/confirmacao-interna).
 *
 * Exibe o bloco de RSVP (extraído da home) numa rota própria. O endereço
 * desta página é o que, após a publicação no domínio oficial, poderá ser
 * usado para gerar o QR Code do cartão de casamento.
 *
 * Variáveis disponíveis (vindas do Controller::view):
 * @var array               $app
 * @var array               $wedding
 * @var string|null         $rsvpStatus
 * @var array<string,mixed>|null $myRsvp
 * @var array<string,mixed>|null $currentGuest
 * @var string|null         $csrfField
 * @var string|null         $rsvpReturnTo
 */
?>
<section class="section section--event" id="confirmacao-interna" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Faça parte deste dia</p>
            <h2 class="section__title">Confirmação de presença</h2>
            <p class="section__intro">
                Conte para nós se poderá celebrar ao nosso lado.
            </p>
        </div>

        <?php require APP_PATH . '/Views/partials/rsvp.php'; ?>
    </div>
</section>
