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
        <?php
        // Mensagem de retorno após enviar (vinda de ?rsvp=...).
        $rsvpStatus = $rsvpStatus ?? null;
        $feedback = [
            'ok'          => ['success', 'Obrigado! Sua resposta foi registrada com carinho.'],
            'erro'        => ['error', 'Não foi possível registrar agora. Tente novamente.'],
            'erro-nome'   => ['error', 'Por favor, informe seu nome.'],
            'erro-email'  => ['error', 'Este e-mail já foi usado em uma confirmação.'],
            'erro-acompanhantes' => ['error', 'Por favor, informe o nome de cada acompanhante.'],
            'erro-sessao' => ['error', 'A sessão expirou. Recarregue a página e tente de novo.'],
        ];
        // Imagem decorativa da coluna esquerda (uma das fotos do ensaio).
        $rsvpImg = View::image('gallery-2', $app['base_url'] ?? '');
        ?>
        <div class="rsvp" id="rsvp">
            <div class="rsvp__aside" <?= $rsvpImg['exists'] ? 'style="background-image:url(\'' . View::e($rsvpImg['url']) . '\')"' : '' ?>>
                <div class="rsvp__aside-inner">
                    <p class="rsvp__aside-eyebrow">Faça parte deste dia</p>
                    <h3 class="rsvp__aside-title">Confirme sua presença</h3>
                    <p class="rsvp__aside-text">Sua presença é o presente mais especial. Conte para nós se poderá celebrar ao nosso lado.</p>
                </div>
            </div>

            <div class="rsvp__form-wrap">
                <?php if ($rsvpStatus !== null && isset($feedback[$rsvpStatus])): ?>
                    <p class="rsvp__feedback rsvp__feedback--<?= View::e($feedback[$rsvpStatus][0]) ?>">
                        <?= View::e($feedback[$rsvpStatus][1]) ?>
                    </p>
                <?php endif; ?>

                <form class="rsvp-form" method="post" data-rsvp-form
                      action="<?= View::e(View::url('/confirmar-presenca', $app['base_url'] ?? '')) ?>">
                    <?= $csrfField ?? '' ?>

                    <fieldset class="rsvp-choice">
                        <legend>Você vai comparecer? *</legend>
                        <div class="rsvp-choice__options">
                            <label class="rsvp-choice__card">
                                <input type="radio" name="attending" value="yes" required data-attending="yes">
                                <span class="rsvp-choice__icon" aria-hidden="true">&#10084;</span>
                                <span class="rsvp-choice__label">Sim, estarei lá</span>
                            </label>
                            <label class="rsvp-choice__card">
                                <input type="radio" name="attending" value="no" data-attending="no">
                                <span class="rsvp-choice__icon" aria-hidden="true">&#9815;</span>
                                <span class="rsvp-choice__label">Não poderei ir</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="rsvp-form__row">
                        <div class="rsvp-field">
                            <label for="rsvp-name">Nome completo *</label>
                            <input type="text" id="rsvp-name" name="name" required>
                        </div>
                        <div class="rsvp-field">
                            <label for="rsvp-phone">Telefone</label>
                            <input type="text" id="rsvp-phone" name="phone" inputmode="tel">
                        </div>
                    </div>

                    <div class="rsvp-field">
                        <label for="rsvp-email">E-mail</label>
                        <input type="email" id="rsvp-email" name="email">
                    </div>

                    <!-- Acompanhantes: só aparece ao escolher "Sim" (via JS). -->
                    <div class="rsvp-field rsvp-field--companions" data-companions hidden>
                        <label for="rsvp-companions">Quantos acompanhantes?</label>
                        <input type="number" id="rsvp-companions" name="companions"
                               min="0" max="20" value="0" data-companions-count>
                    </div>

                    <!-- Campos de nome gerados conforme a quantidade (via JS). -->
                    <div class="rsvp-companions-names" data-companions-names hidden>
                        <p class="rsvp-companions-names__hint">Informe o nome de cada acompanhante:</p>
                        <div data-companions-list></div>
                    </div>

                    <div class="rsvp-field">
                        <label for="rsvp-message">Deixe um recado (opcional)</label>
                        <textarea id="rsvp-message" name="message" rows="3"></textarea>
                    </div>

                    <button class="btn btn--primary rsvp-form__submit" type="submit">Enviar confirmação</button>
                </form>
            </div>
        </div>
    </div>
</section>
