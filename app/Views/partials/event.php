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
 * Devolve a URL do Google Maps para o endereço, ou null quando não há
 * endereço configurado (vazio ou placeholder).
 *
 * @return string|null
 */
$mapLink = static function (string $place, string $address): ?string {
    $placeholders = ['', 'Endereço a definir', 'Local a definir'];
    $place   = trim($place);
    $address = trim($address);

    if (in_array($address, $placeholders, true)) {
        return null;
    }

    $query = in_array($place, $placeholders, true)
        ? $address
        : $place . ', ' . $address;

    // Link para abrir o Google Maps completo (rota, app nativo no mobile).
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($query);
};

$ceremonyMapLink  = $mapLink($ceremonyPlace, $ceremonyAddress);
$receptionMapLink = $mapLink($receptionPlace, $receptionAddress);

/**
 * Minimapa decorativo (SVG) no estilo do site. Não é um mapa real: é uma
 * ilustração que sinaliza "localização" e serve de botão para o Google Maps.
 * As cores vêm das variáveis do tema (via currentColor/classes no CSS).
 */
$mapArt = <<<'SVG'
<svg class="map-art" viewBox="0 0 320 220" preserveAspectRatio="xMidYMid slice"
     role="img" aria-hidden="true" focusable="false">
    <rect class="map-art__bg" x="0" y="0" width="320" height="220"/>
    <!-- "água" decorativa no canto -->
    <path class="map-art__water" d="M240 0 L320 0 L320 70 Q280 60 270 95 Q262 130 300 150 L320 160 L320 220 L210 220 Q235 170 220 120 Q208 70 240 0 Z"/>
    <!-- quarteirões (áreas verdes) -->
    <rect class="map-art__park" x="28" y="30" width="54" height="40" rx="4"/>
    <rect class="map-art__park" x="150" y="150" width="60" height="44" rx="4"/>
    <!-- malha de ruas -->
    <g class="map-art__roads">
        <path d="M-10 60 L330 90"/>
        <path d="M-10 130 L330 150"/>
        <path d="M60 -10 L90 230"/>
        <path d="M170 -10 L150 230"/>
    </g>
    <g class="map-art__roads map-art__roads--thin">
        <path d="M-10 95 L330 118"/>
        <path d="M115 -10 L120 230"/>
        <path d="M225 -10 L205 230"/>
    </g>
    <!-- pin central -->
    <g class="map-art__pin">
        <ellipse class="map-art__pin-shadow" cx="160" cy="150" rx="20" ry="6"/>
        <path class="map-art__pin-body" d="M160 54 c-22 0 -40 18 -40 40 c0 30 40 60 40 60 s40 -30 40 -60 c0 -22 -18 -40 -40 -40 Z"/>
        <circle class="map-art__pin-dot" cx="160" cy="94" r="14"/>
    </g>
</svg>
SVG;
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
                <div class="invite__row<?= $ceremonyMapLink !== null ? ' invite__row--has-map' : '' ?>">
                    <span class="invite__emoji" aria-hidden="true">&#9962;</span>
                    <div class="invite__info">
                        <h3 class="invite__title">Cerimônia</h3>
                        <p class="invite__place"><?= View::e($ceremonyPlace) ?></p>
                        <p class="invite__address"><?= View::e($ceremonyAddress) ?></p>
                        <?php if ($ceremonyDetails !== ''): ?>
                            <p class="invite__details"><?= View::e($ceremonyDetails) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($ceremonyMapLink !== null): ?>
                        <a class="invite__map" href="<?= View::e($ceremonyMapLink) ?>"
                           target="_blank" rel="noopener noreferrer"
                           aria-label="Abrir a localização da Cerimônia no Google Maps">
                            <span class="invite__map-frame">
                                <?= $mapArt ?>
                                <span class="invite__map-badge">
                                    <span class="invite__map-icon" aria-hidden="true">&#128205;</span>
                                    Ver no mapa
                                </span>
                            </span>
                            <span class="invite__map-cta">Abrir no Google Maps</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="invite__divider" aria-hidden="true"></div>

                <div class="invite__row<?= $receptionMapLink !== null ? ' invite__row--has-map' : '' ?>">
                    <span class="invite__emoji" aria-hidden="true">&#127870;</span>
                    <div class="invite__info">
                        <h3 class="invite__title">Recepção</h3>
                        <p class="invite__place"><?= View::e($receptionPlace) ?></p>
                        <p class="invite__address"><?= View::e($receptionAddress) ?></p>
                        <?php if ($receptionDetails !== ''): ?>
                            <p class="invite__details"><?= View::e($receptionDetails) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($receptionMapLink !== null): ?>
                        <a class="invite__map" href="<?= View::e($receptionMapLink) ?>"
                           target="_blank" rel="noopener noreferrer"
                           aria-label="Abrir a localização da Recepção no Google Maps">
                            <span class="invite__map-frame">
                                <?= $mapArt ?>
                                <span class="invite__map-badge">
                                    <span class="invite__map-icon" aria-hidden="true">&#128205;</span>
                                    Ver no mapa
                                </span>
                            </span>
                            <span class="invite__map-cta">Abrir no Google Maps</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
