<?php

declare(strict_types=1);

/**
 * Convite individual (/convite?token=...).
 *
 * Mesmo bloco de RSVP da página pública, porém SEM o campo de CPF: a
 * identificação do convidado é feita pelo token do convite individual, que o
 * partial envia num campo oculto (variável $inviteToken).
 *
 * @var array                    $app
 * @var array                    $wedding
 * @var string|null              $rsvpStatus
 * @var array<string,mixed>|null $myRsvp
 * @var array<string,mixed>|null $currentGuest
 * @var string|null              $csrfField
 * @var string|null              $rsvpReturnTo
 * @var string                   $inviteToken
 */
?>
<section class="section section--event" id="confirmacao-interna" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Seu convite</p>
            <h2 class="section__title">Confirmação de presença</h2>
            <p class="section__intro">
                Este é o seu convite exclusivo. Conte para nós se poderá celebrar ao nosso lado.
            </p>
        </div>

        <?php require APP_PATH . '/Views/partials/rsvp.php'; ?>
    </div>
</section>
