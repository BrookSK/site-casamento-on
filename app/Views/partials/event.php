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

/**
 * Resolve os dados de mapa para um endereço REAL.
 *
 * Só retorna algo quando há endereço de fato: ignora vazio e os
 * placeholders padrão ("Endereço a definir" / "Local a definir"),
 * evitando apontar para uma localização inexistente ou incorreta.
 * Quando há também o nome do local, ele é usado junto do endereço
 * para melhorar a precisão da busca no mapa.
 *
 * Devolve ['link' => URL do Maps, 'embed' => URL do iframe de embed]
 * ou null quando não há endereço configurado.
 *
 * @return array{link:string,embed:string}|null
 */
$mapData = static function (string $place, string $address): ?array {
    $placeholders = ['', 'Endereço a definir', 'Local a definir'];
    $place   = trim($place);
    $address = trim($address);

    if (in_array($address, $placeholders, true)) {
        return null;
    }

    $query = in_array($place, $placeholders, true)
        ? $address
        : $place . ', ' . $address;

    $encoded = rawurlencode($query);

    return [
        // Link para abrir o Google Maps completo (rota, app nativo no mobile).
        'link'  => 'https://www.google.com/maps/search/?api=1&query=' . $encoded,
        // Embed sem chave de API — exibe o minimapa dentro do site.
        'embed' => 'https://maps.google.com/maps?q=' . $encoded . '&z=15&output=embed',
    ];
};

$ceremonyMap  = $mapData($ceremonyPlace, $ceremonyAddress);
$receptionMap = $mapData($receptionPlace, $receptionAddress);
?>
<section class="section section--event" id="evento" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">O grande dia</p>
            <h2 class="section__title">Informações do evento</h2>
            <p class="section__intro">
                Datas e locais do nosso grande dia.
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
                <div class="invite__row<?= $ceremonyMap !== null ? ' invite__row--has-map' : '' ?>">
                    <span class="invite__emoji" aria-hidden="true">&#9962;</span>
                    <div class="invite__info">
                        <h3 class="invite__title">Cerimônia</h3>
                        <p class="invite__place"><?= View::e($ceremonyPlace) ?></p>
                        <p class="invite__address"><?= View::e($ceremonyAddress) ?></p>
                        <?php if ($ceremonyDetails !== ''): ?>
                            <p class="invite__details"><?= View::e($ceremonyDetails) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($ceremonyMap !== null): ?>
                        <div class="invite__map">
                            <div class="invite__map-frame">
                                <iframe src="<?= View::e($ceremonyMap['embed']) ?>" title="Mapa da Cerimônia"
                                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                        tabindex="-1" aria-hidden="true"></iframe>
                                <a class="invite__map-overlay" href="<?= View::e($ceremonyMap['link']) ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   aria-label="Abrir a localização da Cerimônia no Google Maps"></a>
                            </div>
                            <a class="invite__map-cta" href="<?= View::e($ceremonyMap['link']) ?>"
                               target="_blank" rel="noopener noreferrer">
                                <span class="invite__map-icon" aria-hidden="true">&#128205;</span>
                                Abrir no Google Maps
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="invite__divider" aria-hidden="true"></div>

                <div class="invite__row<?= $receptionMap !== null ? ' invite__row--has-map' : '' ?>">
                    <span class="invite__emoji" aria-hidden="true">&#127870;</span>
                    <div class="invite__info">
                        <h3 class="invite__title">Recepção</h3>
                        <p class="invite__place"><?= View::e($receptionPlace) ?></p>
                        <p class="invite__address"><?= View::e($receptionAddress) ?></p>
                        <?php if ($receptionDetails !== ''): ?>
                            <p class="invite__details"><?= View::e($receptionDetails) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($receptionMap !== null): ?>
                        <div class="invite__map">
                            <div class="invite__map-frame">
                                <iframe src="<?= View::e($receptionMap['embed']) ?>" title="Mapa da Recepção"
                                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                                        tabindex="-1" aria-hidden="true"></iframe>
                                <a class="invite__map-overlay" href="<?= View::e($receptionMap['link']) ?>"
                                   target="_blank" rel="noopener noreferrer"
                                   aria-label="Abrir a localização da Recepção no Google Maps"></a>
                            </div>
                            <a class="invite__map-cta" href="<?= View::e($receptionMap['link']) ?>"
                               target="_blank" rel="noopener noreferrer">
                                <span class="invite__map-icon" aria-hidden="true">&#128205;</span>
                                Abrir no Google Maps
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
