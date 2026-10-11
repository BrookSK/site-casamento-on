<?php

declare(strict_types=1);

/**
 * Página inicial — monta as seções a partir das partials.
 *
 * Variáveis disponíveis (vindas do Controller::view):
 * @var array $app
 * @var array $wedding
 * @var array $gifts
 */

require APP_PATH . '/Views/partials/hero.php';
require APP_PATH . '/Views/partials/couple.php';
require APP_PATH . '/Views/partials/event.php';
require APP_PATH . '/Views/partials/gallery.php';
require APP_PATH . '/Views/partials/gifts.php';
