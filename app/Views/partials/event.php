<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Informações do evento — data, horário, cerimônia e recepção.
 *
 * @var array $wedding
 */

$dateLabel = $wedding['date_label'] ?? 'Data a definir';
$timeLabel = $wedding['time_label'] ?? 'Horário a definir';
$ceremony  = $wedding['ceremony'] ?? [];
$reception = $wedding['reception'] ?? [];
?>
<section class="section section--event" id="evento" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">O grande dia</p>
            <h2 class="section__title">Informações do evento</h2>
            <p class="section__intro">
                Datas e locais provisórios. Os detalhes finais serão divulgados
                em breve nesta página.
            </p>
        </div>

        <div class="event-highlight">
            <div class="event-highlight__item">
                <span class="event-highlight__label">Data</span>
                <span class="event-highlight__value"><?= View::e($dateLabel) ?></span>
            </div>
            <div class="event-highlight__divider" aria-hidden="true"></div>
            <div class="event-highlight__item">
                <span class="event-highlight__label">Horário</span>
                <span class="event-highlight__value"><?= View::e($timeLabel) ?></span>
            </div>
        </div>

        <div class="event-cards">
            <article class="event-card">
                <div class="event-card__icon" aria-hidden="true">&#9962;</div>
                <h3 class="event-card__title"><?= View::e($ceremony['title'] ?? 'Cerimônia') ?></h3>
                <p class="event-card__place"><?= View::e($ceremony['place'] ?? 'Local a definir') ?></p>
                <p class="event-card__address"><?= View::e($ceremony['address'] ?? 'Endereço a definir') ?></p>
                <p class="event-card__details"><?= View::e($ceremony['details'] ?? '') ?></p>
            </article>

            <article class="event-card">
                <div class="event-card__icon" aria-hidden="true">&#127870;</div>
                <h3 class="event-card__title"><?= View::e($reception['title'] ?? 'Recepção') ?></h3>
                <p class="event-card__place"><?= View::e($reception['place'] ?? 'Local a definir') ?></p>
                <p class="event-card__address"><?= View::e($reception['address'] ?? 'Endereço a definir') ?></p>
                <p class="event-card__details"><?= View::e($reception['details'] ?? '') ?></p>
            </article>
        </div>

        <!-- Confirmação de presença (RSVP): APENAS VISUAL nesta etapa. -->
        <div class="rsvp" id="rsvp">
            <h3 class="rsvp__title">Confirmação de presença</h3>
            <p class="rsvp__text">
                Em breve você poderá confirmar sua presença por aqui.
            </p>
            <button class="btn btn--primary" type="button" disabled aria-disabled="true">
                Em breve
            </button>
        </div>
    </div>
</section>
