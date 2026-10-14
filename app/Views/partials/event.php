<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Informações do evento — data, horário, cerimônia e recepção.
 * Conteúdo editável via painel (Content), com fallback para a config.
 *
 * @var array          $wedding
 * @var array          $app
 * @var \App\Core\Content $content
 */

$dateLabel = $content->text('date_label', (string) ($wedding['date_label'] ?? 'Data a definir'));
$timeLabel = $content->text('time_label', (string) ($wedding['time_label'] ?? 'Horário a definir'));

$ceremonyPlace   = $content->text('ceremony_place',   (string) ($wedding['ceremony']['place'] ?? 'Local a definir'));
$ceremonyAddress = $content->text('ceremony_address', (string) ($wedding['ceremony']['address'] ?? 'Endereço a definir'));
$ceremonyDetails = $content->text('ceremony_details', (string) ($wedding['ceremony']['details'] ?? ''));
$receptionPlace   = $content->text('reception_place',   (string) ($wedding['reception']['place'] ?? 'Local a definir'));
$receptionAddress = $content->text('reception_address', (string) ($wedding['reception']['address'] ?? 'Endereço a definir'));
$receptionDetails = $content->text('reception_details', (string) ($wedding['reception']['details'] ?? ''));
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

        <!-- Bloco único tipo "convite": data/horário no topo e os dois
             locais (cerimônia e recepção) com o emoji à esquerda do título. -->
        <div class="invite">
            <div class="invite__header">
                <div class="invite__header-item">
                    <span class="invite__label">Data</span>
                    <span class="invite__value"><?= View::e($dateLabel) ?></span>
                </div>
                <div class="invite__header-divider" aria-hidden="true"></div>
                <div class="invite__header-item">
                    <span class="invite__label">Horário</span>
                    <span class="invite__value"><?= View::e($timeLabel) ?></span>
                </div>
            </div>

            <div class="invite__body">
                <div class="invite__row">
                    <span class="invite__emoji" aria-hidden="true">&#9962;</span>
                    <div class="invite__info">
                        <h3 class="invite__title">Cerimônia</h3>
                        <p class="invite__place"><?= View::e($ceremonyPlace) ?></p>
                        <p class="invite__address"><?= View::e($ceremonyAddress) ?></p>
                        <?php if ($ceremonyDetails !== ''): ?>
                            <p class="invite__details"><?= View::e($ceremonyDetails) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="invite__divider" aria-hidden="true"></div>

                <div class="invite__row">
                    <span class="invite__emoji" aria-hidden="true">&#127870;</span>
                    <div class="invite__info">
                        <h3 class="invite__title">Recepção</h3>
                        <p class="invite__place"><?= View::e($receptionPlace) ?></p>
                        <p class="invite__address"><?= View::e($receptionAddress) ?></p>
                        <?php if ($receptionDetails !== ''): ?>
                            <p class="invite__details"><?= View::e($receptionDetails) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
        // Imagem decorativa da coluna esquerda (fixa: gallery-2 em assets/img).
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
                                <span class="rsvp-choice__icon heart" aria-hidden="true">&#10084;</span>
                                <span class="rsvp-choice__label">Sim, estarei lá</span>
                            </label>
                            <label class="rsvp-choice__card">
                                <input type="radio" name="attending" value="no" data-attending="no">
                                <span class="rsvp-choice__icon rsvp-choice__icon--away" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none"
                                         stroke="currentColor" stroke-width="1.6"
                                         stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 14.5A8 8 0 1 1 9.5 4a6.3 6.3 0 0 0 10.5 10.5z"/>
                                    </svg>
                                </span>
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
                    <div class="rsvp-companions" data-companions hidden>
                        <p class="rsvp-companions__hint">Possui algum acompanhante?</p>
                        <div class="rsvp-companions__list" data-companions-list></div>
                        <button type="button" class="rsvp-companions__add" data-companions-add>
                            + Adicionar acompanhante
                        </button>
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
