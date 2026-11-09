<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Lista de presentes.
 *
 * Quando o ASAAS está configurado ($paymentsEnabled), cada presente abre um
 * popup de detalhes (foto, nome, descrição e preço) com o botão "Presentear",
 * que por sua vez abre o popup de pagamento (PIX, cartão ou boleto). Caso
 * contrário, mantém a prévia visual sem compra.
 *
 * Inclui um filtro (slider) por valor máximo, aplicado no front-end.
 *
 * @var array        $gifts
 * @var bool         $paymentsEnabled
 * @var string       $csrfField
 * @var array|null   $currentGuest
 * @var string|null  $giftStatus
 * @var array        $app
 */

$gifts           = $gifts ?? [];
$paymentsEnabled = $paymentsEnabled ?? false;
$csrfField       = $csrfField ?? '';
$currentGuest    = $currentGuest ?? null;
$giftStatus      = $giftStatus ?? null;
$giftOld         = is_array($giftOld ?? null) ? $giftOld : null;
$baseUrl         = $app['base_url'] ?? '';

// Preços em centavos (para o filtro). Só habilita o slider se houver preços > 0.
$cents = array_map(static fn ($g): int => (int) ($g['price_cents'] ?? 0), $gifts);
$cents = array_filter($cents, static fn (int $c): int => $c);
$maxCents = $cents !== [] ? max($cents) : 0;
$minCents = $cents !== [] ? min($cents) : 0;
$showFilter = $maxCents > 0 && count($gifts) > 1;

// Mensagens de retorno do fluxo de pagamento.
$giftMessages = [
    'erro'            => 'Não foi possível iniciar o pagamento. Tente novamente.',
    'erro-sessao'     => 'Sua sessão expirou. Recarregue a página e tente novamente.',
    'erro-nome'       => 'Informe o seu nome para presentear.',
    'erro-email'      => 'Informe um e-mail válido para criarmos a sua conta.',
    'erro-telefone'   => 'Informe o seu telefone com DDD para criarmos a sua conta.',
    'erro-cpf'        => 'Informe um CPF/CNPJ válido para concluir o pagamento.',
    'erro-cartao'     => 'Verifique os dados do cartão e tente novamente.',
    'erro-cartao-recusado' => 'O pagamento com cartão foi recusado. Confira os '
                       . 'dados ou tente outro cartão/forma de pagamento.',
    'erro-conflito'   => 'O e-mail e o telefone informados pertencem a cadastros '
                       . 'diferentes. Verifique os dados ou faça login.',
    'erro-indisponivel' => 'Este presente não está mais na lista. Que tal escolher outro?',
    'erro-valor'      => 'Este presente ainda não tem um valor definido.',
    'login'           => 'Você ainda não possui uma conta ou não está conectado. '
                       . 'Crie uma conta ou faça login para continuar com seu presente.',
];
$giftMsg = $giftStatus !== null ? ($giftMessages[$giftStatus] ?? null) : null;

// Envio que voltou com erro: reabre o popup de pagamento com os dados
// digitados e o motivo dentro dele (o aviso da lista fica de reserva, caso o
// presente não possa mais ser aberto).
$reopenId  = 0;
$reopenMsg = '';
if ($giftOld !== null && (int) ($giftOld['gift_id'] ?? 0) > 0) {
    $oldStatus = (string) ($giftOld['status'] ?? '');
    if (!in_array($oldStatus, ['erro-indisponivel', 'erro-valor'], true)) {
        $reopenId  = (int) $giftOld['gift_id'];
        $reopenMsg = $giftMessages[$oldStatus] ?? $giftMessages['erro'];
        if ($oldStatus === 'erro-sessao') {
            $reopenMsg = 'Sua sessão expirou. Seus dados foram mantidos: confira e toque em continuar novamente.';
        }
        $detail = trim((string) ($giftOld['detail'] ?? ''));
        if ($detail !== '') {
            $reopenMsg = 'Não foi possível gerar o pagamento: ' . $detail;
        }
        if ($giftMsg === null) {
            $giftMsg = $reopenMsg;
        }
    }
}
// Valor inicial dos campos: o que foi digitado (se voltou com erro) ou os
// dados do convidado logado.
$oldVal = static function (string $key, string $fallback = '') use ($giftOld, $reopenId): string {
    if ($reopenId > 0 && isset($giftOld[$key]) && (string) $giftOld[$key] !== '') {
        return (string) $giftOld[$key];
    }
    return $fallback;
};
$oldBilling = $oldVal('billing_type', 'PIX');

/**
 * Resolve a URL da imagem de um presente (aceita caminho absoluto, http ou
 * nome relativo em assets/img).
 */
$resolveImage = static function (string $image) use ($baseUrl): string {
    $image = trim($image);
    if ($image === '') {
        return '';
    }
    return (str_starts_with($image, 'http') || str_starts_with($image, '/'))
        ? $image
        : rtrim($baseUrl, '/') . '/assets/img/' . ltrim($image, '/');
};
?>
<section class="section section--gifts" id="presentes" data-reveal>
    <div class="container">
        <div class="section__head section__head--center">
            <p class="section__eyebrow">Com carinho</p>
            <h2 class="section__title">Lista de presentes</h2>
            <p class="section__intro">
                Preparamos uma seleção especial de presentes para
                compartilhar esse momento tão importante com vocês.
            </p>
        </div>

        <?php if ($giftMsg !== null): ?>
            <p class="flash flash--error" style="text-align:center" role="alert" data-gift-flash
               <?= $reopenId > 0 ? 'hidden' : '' ?>><?= View::e($giftMsg) ?></p>
        <?php endif; ?>

        <?php if ($showFilter): ?>
            <div class="gift-filterbar" data-gift-filter
                 data-min="<?= (int) $minCents ?>" data-max="<?= (int) $maxCents ?>">
                <div class="gift-filter__head">
                    <label for="gift-range">Escolha pelo seu orçamento</label>
                    <span class="gift-filter__value" data-gift-filter-label>
                        Até R$ <?= number_format($maxCents / 100, 2, ',', '.') ?>
                    </span>
                </div>
                <input type="range" id="gift-range"
                       min="<?= (int) $minCents ?>" max="<?= (int) $maxCents ?>"
                       value="<?= (int) $maxCents ?>" step="1"
                       data-gift-range
                       aria-label="Valor máximo do presente">
                <div class="gift-filter__scale">
                    <span>R$ <?= number_format($minCents / 100, 2, ',', '.') ?></span>
                    <span>R$ <?= number_format($maxCents / 100, 2, ',', '.') ?></span>
                </div>
            </div>
        <?php endif; ?>

        <div class="gift-grid" data-gift-grid>
            <?php foreach ($gifts as $gift): ?>
                <?php
                $priceCents = (int) ($gift['price_cents'] ?? 0);
                $giftId     = (int) ($gift['id'] ?? 0);
                $name       = (string) ($gift['name'] ?? 'Presente');
                $desc       = trim((string) ($gift['description'] ?? ''));
                $priceText  = (string) ($gift['price'] ?? 'R$ ---');
                $imageUrl   = $resolveImage((string) ($gift['image'] ?? ''));
                // O mesmo presente pode ser dado por várias pessoas (pedido do
                // casal): nunca aparece como "já escolhido". Só precisa de
                // pagamento ativo e de id/preço válidos.
                $canBuy     = $paymentsEnabled && $giftId > 0 && $priceCents > 0;
                // Card clicável (abre o popup de detalhes) sempre que há
                // pagamento ativo.
                $clickable  = $paymentsEnabled;
                ?>
                <article class="gift-card<?= $clickable ? ' gift-card--clickable' : '' ?>"
                         data-gift-card data-price="<?= $priceCents ?>"
                         <?php if ($clickable): ?>
                         tabindex="0" role="button"
                         aria-label="Ver detalhes do presente <?= View::e($name) ?>"
                         data-gift-detail
                         data-gift-id="<?= $giftId ?>"
                         data-gift-name="<?= View::e($name) ?>"
                         data-gift-desc="<?= View::e($desc) ?>"
                         data-gift-price="<?= View::e($priceText) ?>"
                         data-gift-image="<?= View::e($imageUrl) ?>"
                         data-gift-canbuy="<?= $canBuy ? '1' : '0' ?>"
                         <?php endif; ?>>
                    <div class="gift-card__media">
                        <?php if ($imageUrl !== ''): ?>
                            <img class="gift-card__img" src="<?= View::e($imageUrl) ?>"
                                 alt="<?= View::e($name) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="gift-card__icon" aria-hidden="true">&#127873;</div>
                        <?php endif; ?>
                    </div>

                    <h3 class="gift-card__name"><?= View::e($name) ?></h3>
                    <p class="gift-card__price"><?= View::e($priceText) ?></p>

                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($showFilter): ?>
            <p class="gift-empty" data-gift-empty hidden>Nenhum presente dentro desse valor.</p>
        <?php endif; ?>
    </div>
</section>

<?php if ($paymentsEnabled): ?>
<?php $guestLogged = $currentGuest !== null; ?>
<!-- Popup 1: detalhes do presente (foto, nome, descrição, preço + Presentear) -->
<div class="gift-modal" data-gift-detail-modal data-logged="<?= $guestLogged ? '1' : '0' ?>" hidden>
    <div class="gift-modal__overlay" data-gift-detail-close></div>
    <div class="gift-modal__dialog gift-modal__dialog--detail" role="dialog" aria-modal="true"
         aria-labelledby="gift-detail-title">
        <button class="gift-modal__close" type="button" data-gift-detail-close aria-label="Fechar">&times;</button>

        <div class="gift-modal__scroll">
        <div class="gift-detail__media">
            <img class="gift-detail__img" src="" alt="" data-gift-detail-image hidden>
            <div class="gift-detail__icon" aria-hidden="true" data-gift-detail-icon>&#127873;</div>
        </div>

        <div class="gift-detail__body">
            <p class="gift-modal__eyebrow">Presente</p>
            <h3 class="gift-modal__title" id="gift-detail-title" data-gift-detail-name>Presente</h3>
            <p class="gift-modal__price" data-gift-detail-price>R$ ---</p>
            <p class="gift-detail__desc" data-gift-detail-desc></p>

            <button class="btn btn--primary gift-detail__cta" type="button" data-gift-detail-buy>
                Presentear
            </button>
            <p class="gift-detail__unavailable" data-gift-detail-unavailable hidden>
                Este presente ainda não está disponível para presentear. Que tal escolher outro?
            </p>

            <!-- Aviso de login: exibido quando um visitante NÃO autenticado
                 tenta presentear (ponto 6.5). -->
            <div class="gift-detail__authwall" data-gift-authwall hidden>
                <p class="gift-detail__authwall-msg">
                    Você ainda não possui uma conta ou não está conectado.
                    Crie uma conta ou faça login para continuar com seu presente.
                </p>
                <div class="gift-detail__authwall-actions">
                    <a class="btn btn--primary" href="<?= View::e(View::url('/entrar?tab=register', $baseUrl)) ?>">Criar conta</a>
                    <a class="btn btn--outline" href="<?= View::e(View::url('/entrar', $baseUrl)) ?>">Fazer login</a>
                </div>
            </div>
        </div>
        </div><!-- /.gift-modal__scroll -->
    </div>
</div>

<!-- Popup 2: dados de pagamento (reutilizado para qualquer presente). -->
<div class="gift-modal" data-gift-modal hidden
     <?php if ($reopenId > 0): ?>data-reopen-id="<?= $reopenId ?>"<?php endif; ?>>
    <div class="gift-modal__overlay" data-gift-close></div>
    <div class="gift-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gift-modal-title">
        <button class="gift-modal__close" type="button" data-gift-close aria-label="Fechar">&times;</button>

        <div class="gift-modal__scroll">
        <div class="gift-modal__aside">
            <div class="gift-modal__head">
                <p class="gift-modal__eyebrow">Presentear</p>
                <h3 class="gift-modal__title" id="gift-modal-title" data-gift-modal-name>Presente</h3>
                <p class="gift-modal__price" data-gift-modal-price>R$ ---</p>
            </div>

            <!-- Imagem e descrição do presente selecionado (mesmos dados da
                 1ª aba). Carregados dinamicamente conforme o presente. -->
            <div class="gift-modal__media">
                <img class="gift-modal__img" src="" alt="" data-gift-modal-image hidden>
                <div class="gift-modal__icon" aria-hidden="true" data-gift-modal-icon>&#127873;</div>
            </div>
            <p class="gift-modal__desc" data-gift-modal-desc hidden></p>

            <p class="gift-modal__intro">
                Preencha os seus dados para contribuir. O pagamento é
                processado com segurança &mdash; você escolhe entre PIX, cartão
                de crédito ou boleto.
            </p>
        </div>

        <form class="gift-modal__form" method="post" data-gift-modal-form
              action="<?= View::e(View::url('/presentear', $baseUrl)) ?>">
            <?= $csrfField ?>
            <input type="hidden" name="gift_id" value="" data-gift-modal-id>

            <?php if ($reopenId > 0): ?>
                <p class="gift-modal__error field--full" role="alert" data-gift-modal-error>
                    <?= View::e($reopenMsg) ?>
                </p>
            <?php endif; ?>

            <div class="field field--full">
                <label for="gm_name">Seu nome</label>
                <input type="text" id="gm_name" name="buyer_name" autocomplete="name"
                       value="<?= View::e($oldVal('buyer_name', (string) ($currentGuest['name'] ?? ''))) ?>" required>
            </div>

            <?php /* Visitante sem conta: cria automaticamente a conta com estes
                     dados (mesmo fluxo da confirmação de presença). Convidado
                     logado já tem conta — estes campos ficam ocultos. */ ?>
            <div class="gift-modal__extra" data-gift-modal-extra <?= $guestLogged ? 'hidden' : '' ?>>
                <?php if (!$guestLogged): ?>
                    <p class="gift-modal__hint">
                        Você ainda não tem conta. Informe os dados abaixo e nós a
                        criaremos automaticamente — depois você define a sua senha
                        pelo link que enviaremos.
                    </p>
                <?php endif; ?>

                <div class="field field--full">
                    <label for="gm_email">E-mail</label>
                    <input type="email" id="gm_email" name="buyer_email" autocomplete="email"
                           inputmode="email" autocapitalize="off" spellcheck="false"
                           value="<?= View::e($oldVal('buyer_email', (string) ($currentGuest['email'] ?? ''))) ?>"
                           <?= $guestLogged ? '' : 'required' ?>>
                </div>

                <div class="field field--full">
                    <label for="gm_phone">Telefone/WhatsApp</label>
                    <input type="tel" id="gm_phone" name="buyer_phone" inputmode="tel"
                           autocomplete="tel" placeholder="(11) 98888-7777" maxlength="20"
                           value="<?= View::e($oldVal('buyer_phone')) ?>" data-gift-phone
                           <?= $guestLogged ? '' : 'required' ?>>
                </div>
            </div>

            <div class="field field--full">
                <label for="gm_billing">Forma de pagamento</label>
                <select id="gm_billing" name="billing_type" data-gift-modal-billing>
                    <option value="PIX" <?= $oldBilling === 'PIX' ? 'selected' : '' ?>>PIX (aprovação na hora)</option>
                    <option value="CREDIT_CARD" <?= $oldBilling === 'CREDIT_CARD' ? 'selected' : '' ?>>Cartão de crédito</option>
                    <option value="BOLETO" <?= $oldBilling === 'BOLETO' ? 'selected' : '' ?>>Boleto bancário</option>
                </select>
            </div>

            <?php /* O ASAAS exige o CPF/CNPJ do pagador em qualquer forma de
                     pagamento (inclusive PIX), por isso o campo é sempre pedido. */ ?>
            <div class="field field--full">
                <label for="gm_cpf">CPF/CNPJ do pagador</label>
                <input type="text" id="gm_cpf" name="buyer_cpf" inputmode="numeric"
                       placeholder="000.000.000-00" maxlength="18" required
                       value="<?= View::e($oldVal('buyer_cpf')) ?>">
            </div>

            <div class="field field--full">
                <label for="gm_msg">Mensagem (opcional)</label>
                <input type="text" id="gm_msg" name="message" maxlength="255"
                       value="<?= View::e($oldVal('message')) ?>"
                       placeholder="Uma mensagem carinhosa para o casal">
            </div>

            <button class="btn btn--primary gift-modal__submit" type="submit" data-gift-modal-submit>
                Continuar para o pagamento
            </button>
        </form>
        </div><!-- /.gift-modal__scroll -->
    </div>
</div>

<script>
(function () {
    var detail = document.querySelector('[data-gift-detail-modal]');
    var modal  = document.querySelector('[data-gift-modal]');
    if (!modal || !detail) { return; }

    // ---- Elementos do popup de pagamento (popup 2) ----
    var nameEl  = modal.querySelector('[data-gift-modal-name]');
    var priceEl = modal.querySelector('[data-gift-modal-price]');
    var idEl    = modal.querySelector('[data-gift-modal-id]');
    var extraFields = modal.querySelector('[data-gift-modal-extra]');
    var payImg   = modal.querySelector('[data-gift-modal-image]');
    var payIcon  = modal.querySelector('[data-gift-modal-icon]');
    var payDesc  = modal.querySelector('[data-gift-modal-desc]');

    // ---- Elementos do popup de detalhes (popup 1) ----
    var dName   = detail.querySelector('[data-gift-detail-name]');
    var dPrice  = detail.querySelector('[data-gift-detail-price]');
    var dDesc   = detail.querySelector('[data-gift-detail-desc]');
    var dImg    = detail.querySelector('[data-gift-detail-image]');
    var dIcon   = detail.querySelector('[data-gift-detail-icon]');
    var dBuy    = detail.querySelector('[data-gift-detail-buy]');
    var dUnav   = detail.querySelector('[data-gift-detail-unavailable]');
    var dAuth   = detail.querySelector('[data-gift-authwall]');

    // Visitante autenticado? (definido no servidor via data-logged).
    var isLogged = detail.getAttribute('data-logged') === '1';

    // Guarda os dados do presente atualmente aberto no popup de detalhes.
    var current = null;

    var lockScroll   = function () { document.body.style.overflow = 'hidden'; };
    var unlockScroll = function () { document.body.style.overflow = ''; };

    // Abre o popup 1 (detalhes) a partir de um card.
    var openDetail = function (card) {
        var canBuy = card.getAttribute('data-gift-canbuy') === '1';
        current = {
            id:     card.getAttribute('data-gift-id') || '',
            name:   card.getAttribute('data-gift-name') || 'Presente',
            price:  card.getAttribute('data-gift-price') || '',
            desc:   card.getAttribute('data-gift-desc') || '',
            image:  card.getAttribute('data-gift-image') || '',
            canBuy: canBuy
        };

        dName.textContent  = current.name;
        dPrice.textContent = current.price;
        dDesc.textContent  = current.desc;
        dDesc.hidden = current.desc === '';

        if (current.image) {
            dImg.src = current.image;
            dImg.alt = current.name;
            dImg.hidden = false;
            if (dIcon) { dIcon.hidden = true; }
        } else {
            dImg.hidden = true;
            dImg.removeAttribute('src');
            if (dIcon) { dIcon.hidden = false; }
        }

        // Volta ao estado inicial: esconde o aviso de login a cada abertura.
        if (dAuth) { dAuth.hidden = true; }

        // Só bloqueia presente sem valor definido (nunca por já ter sido dado).
        if (canBuy) {
            dBuy.hidden = false;
            dUnav.hidden = true;
        } else {
            dBuy.hidden = true;
            dUnav.hidden = false;
        }

        detail.hidden = false;
        lockScroll();
    };
    var closeDetail = function () {
        detail.hidden = true;
        unlockScroll();
    };

    // Abre o popup 2 (pagamento) com os dados do presente atual.
    var openPayment = function () {
        if (!current || !current.canBuy) { return; }

        // Visitante sem conta pode prosseguir: o formulário de pagamento pede
        // CPF, e-mail e telefone e cria a conta automaticamente (mesmo fluxo da
        // confirmação de presença). Quando NÃO está logado, os campos de
        // contato ficam visíveis e obrigatórios.
        if (extraFields) { extraFields.hidden = isLogged; }

        idEl.value = current.id;
        nameEl.textContent = current.name;
        priceEl.textContent = current.price;

        // Reaproveita a imagem e a descrição do presente (mesmos dados da
        // 1ª aba), carregadas conforme o presente selecionado.
        if (payImg) {
            if (current.image) {
                payImg.src = current.image;
                payImg.alt = current.name;
                payImg.hidden = false;
                if (payIcon) { payIcon.hidden = true; }
            } else {
                payImg.hidden = true;
                payImg.removeAttribute('src');
                if (payIcon) { payIcon.hidden = false; }
            }
        }
        if (payDesc) {
            payDesc.textContent = current.desc;
            payDesc.hidden = current.desc === '';
        }

        detail.hidden = true;   // fecha o de detalhes
        modal.hidden = false;   // abre o de pagamento
        lockScroll();
    };
    var closePayment = function () {
        modal.hidden = true;
        unlockScroll();
    };

    // ---- Ligações: cards abrem o popup de detalhes ----
    document.querySelectorAll('[data-gift-detail]').forEach(function (card) {
        card.addEventListener('click', function () { openDetail(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openDetail(card);
            }
        });
    });

    // Botão "Presentear" dentro do popup de detalhes -> abre pagamento.
    if (dBuy) { dBuy.addEventListener('click', openPayment); }

    // Fechar popups.
    detail.querySelectorAll('[data-gift-detail-close]').forEach(function (el) {
        el.addEventListener('click', closeDetail);
    });
    modal.querySelectorAll('[data-gift-close]').forEach(function (el) {
        el.addEventListener('click', closePayment);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') { return; }
        if (!modal.hidden) { closePayment(); }
        else if (!detail.hidden) { closeDetail(); }
    });

    // Validação de CPF/CNPJ pelos dígitos verificadores (mesma regra do
    // servidor), para avisar no próprio campo antes de enviar.
    var validDoc = function (v) {
        var d = (v || '').replace(/\D/g, '');
        var i, t, sum, check;
        if (d.length === 11) {
            if (/^(\d)\1{10}$/.test(d)) { return false; }
            for (t = 9; t < 11; t++) {
                for (sum = 0, i = 0; i < t; i++) { sum += +d[i] * (t + 1 - i); }
                check = ((10 * sum) % 11) % 10;
                if (+d[t] !== check) { return false; }
            }
            return true;
        }
        if (d.length === 14) {
            if (/^(\d)\1{13}$/.test(d)) { return false; }
            var w = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            for (t = 12; t < 14; t++) {
                var ww = w.slice(13 - t);
                for (sum = 0, i = 0; i < t; i++) { sum += +d[i] * ww[i]; }
                check = sum % 11 < 2 ? 0 : 11 - (sum % 11);
                if (+d[t] !== check) { return false; }
            }
            return true;
        }
        return false;
    };

    // Máscara de CPF/CNPJ: formata automaticamente enquanto digita.
    // CPF: 000.000.000-00  |  CNPJ: 00.000.000/0000-00
    var cpfInput = modal.querySelector('#gm_cpf');
    var formatDoc = function (value) {
        var d = value.replace(/\D/g, '').slice(0, 14);
        if (d.length <= 11) {
            return d.replace(/^(\d{3})(\d)/, '$1.$2')
                    .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d)/, '.$1-$2');
        }
        return d.replace(/^(\d{2})(\d)/, '$1.$2')
                .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                .replace(/\.(\d{3})(\d)/, '.$1/$2')
                .replace(/(\d{4})(\d)/, '$1-$2');
    };
    if (cpfInput) {
        cpfInput.addEventListener('input', function () {
            cpfInput.value = formatDoc(cpfInput.value);
            cpfInput.setCustomValidity('');
        });
        if (cpfInput.value) { cpfInput.value = formatDoc(cpfInput.value); }
    }

    // Máscara do telefone: aceita o número colado/preenchido pelo celular com
    // +55 e deixa no formato (DDD) 00000-0000.
    var phoneInput = modal.querySelector('[data-gift-phone]');
    var formatPhone = function (value) {
        var d = value.replace(/\D/g, '');
        if (d.length >= 12 && d.indexOf('55') === 0) { d = d.slice(2); }
        d = d.slice(0, 11);
        if (d.length <= 2) { return d.length ? '(' + d : ''; }
        if (d.length <= 6) { return '(' + d.slice(0, 2) + ') ' + d.slice(2); }
        if (d.length <= 10) { return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6); }
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    };
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            // Não reformata enquanto apaga (deixa o Backspace fluir).
            if (e && e.inputType && e.inputType.indexOf('delete') === 0) { return; }
            phoneInput.value = formatPhone(phoneInput.value);
        });
        phoneInput.addEventListener('blur', function () {
            phoneInput.value = formatPhone(phoneInput.value);
        });
        if (phoneInput.value) { phoneInput.value = formatPhone(phoneInput.value); }
    }

    // Envio: valida o documento/telefone e trava o botão. Sem a trava, o toque
    // duplo no celular enviava duas vezes; a 2ª resposta ("indisponível")
    // substituía a tela do PIX gerado pela 1ª.
    var form   = modal.querySelector('[data-gift-modal-form]');
    var submit = modal.querySelector('[data-gift-modal-submit]');
    var submitLabel = submit ? submit.textContent : '';
    if (form) {
        form.addEventListener('submit', function (e) {
            if (form.getAttribute('data-sending') === '1') { e.preventDefault(); return; }
            if (cpfInput && !validDoc(cpfInput.value)) {
                e.preventDefault();
                cpfInput.setCustomValidity('Informe um CPF ou CNPJ válido.');
                cpfInput.reportValidity();
                return;
            }
            if (phoneInput && !isLogged && phoneInput.value.replace(/\D/g, '').replace(/^55(?=\d{10,11}$)/, '').length < 10) {
                e.preventDefault();
                phoneInput.setCustomValidity('Informe o telefone com DDD.');
                phoneInput.reportValidity();
                phoneInput.addEventListener('input', function clear() {
                    phoneInput.setCustomValidity('');
                    phoneInput.removeEventListener('input', clear);
                });
                return;
            }
            form.setAttribute('data-sending', '1');
            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Gerando pagamento…';
            }
        });
    }
    // Ao voltar para a página pelo botão "voltar" (cache do navegador), libera
    // o botão de novo.
    window.addEventListener('pageshow', function () {
        if (!form) { return; }
        form.removeAttribute('data-sending');
        if (submit) { submit.disabled = false; submit.textContent = submitLabel; }
    });

    // Voltou com erro: reabre o pagamento do mesmo presente, já preenchido,
    // com o motivo no topo do formulário.
    var reopenId = modal.getAttribute('data-reopen-id');
    if (reopenId) {
        var card = document.querySelector('[data-gift-detail][data-gift-id="' + reopenId + '"]');
        var flash = document.querySelector('[data-gift-flash]');
        if (card && card.getAttribute('data-gift-canbuy') === '1') {
            openDetail(card);
            openPayment();
            var err = modal.querySelector('[data-gift-modal-error]');
            // Rola o popup até o aviso (no celular o formulário fica abaixo da foto).
            if (err && err.scrollIntoView) { err.scrollIntoView({ block: 'center' }); }
        } else if (flash) {
            flash.hidden = false;
        }
    }

    // Remove o ?gift=... da URL: recarregar a página não repete o aviso.
    if (window.history && history.replaceState && window.URLSearchParams && /[?&]gift=/.test(location.search)) {
        var params = new URLSearchParams(location.search);
        params.delete('gift');
        var qs = params.toString();
        history.replaceState(null, '', location.pathname + (qs ? '?' + qs : '') + location.hash);
    }
})();
</script>
<?php endif; ?>
