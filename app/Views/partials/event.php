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

        <!-- Confirmação de presença (RSVP) -->
        <div class="rsvp" id="rsvp">
            <h3 class="rsvp__title">Confirmação de presença</h3>

            <?php
            // Mensagem de retorno após enviar (vinda de ?rsvp=...).
            $rsvpStatus = $rsvpStatus ?? null;
            $feedback = [
                'ok'          => ['success', 'Obrigado! Sua resposta foi registrada.'],
                'erro'        => ['error', 'Não foi possível registrar agora. Tente novamente.'],
                'erro-nome'   => ['error', 'Por favor, informe seu nome.'],
                'erro-sessao' => ['error', 'A sessão expirou. Recarregue a página e tente de novo.'],
            ];
            ?>
            <?php if ($rsvpStatus !== null && isset($feedback[$rsvpStatus])): ?>
                <p class="rsvp__feedback rsvp__feedback--<?= View::e($feedback[$rsvpStatus][0]) ?>">
                    <?= View::e($feedback[$rsvpStatus][1]) ?>
                </p>
            <?php endif; ?>

            <p class="rsvp__text">Conte para nós se você poderá comemorar com a gente.</p>

            <form class="rsvp-form" method="post"
                  action="<?= View::e(View::url('/confirmar-presenca', $app['base_url'] ?? '')) ?>">
                <?= $csrfField ?? '' ?>

                <div class="rsvp-form__row">
                    <div class="rsvp-field">
                        <label for="rsvp-name">Nome *</label>
                        <input type="text" id="rsvp-name" name="name" required>
                    </div>
                    <div class="rsvp-field">
                        <label for="rsvp-phone">Telefone</label>
                        <input type="text" id="rsvp-phone" name="phone" inputmode="tel">
                    </div>
                </div>

                <div class="rsvp-form__row">
                    <div class="rsvp-field">
                        <label for="rsvp-email">E-mail</label>
                        <input type="email" id="rsvp-email" name="email">
                    </div>
                    <div class="rsvp-field">
                        <label for="rsvp-companions">Acompanhantes</label>
                        <input type="number" id="rsvp-companions" name="companions" min="0" max="20" value="0">
                    </div>
                </div>

                <fieldset class="rsvp-field rsvp-field--choice">
                    <legend>Você vai comparecer? *</legend>
                    <label><input type="radio" name="attending" value="yes" required> Sim, estarei lá</label>
                    <label><input type="radio" name="attending" value="no"> Infelizmente não poderei</label>
                </fieldset>

                <div class="rsvp-field">
                    <label for="rsvp-message">Mensagem (opcional)</label>
                    <textarea id="rsvp-message" name="message" rows="3"></textarea>
                </div>

                <button class="btn btn--primary" type="submit">Enviar confirmação</button>
            </form>
        </div>
    </div>
</section>
