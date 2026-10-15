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
    'erro-telefone'   => 'Informe o seu telefone para criarmos a sua conta.',
    'erro-cpf'        => 'Informe um CPF/CNPJ válido para concluir o pagamento.',
    'erro-cartao'     => 'Verifique os dados do cartão e tente novamente.',
    'erro-cartao-recusado' => 'O pagamento com cartão foi recusado. Confira os '
                       . 'dados ou tente outro cartão/forma de pagamento.',
    'erro-conflito'   => 'O e-mail e o telefone informados pertencem a cadastros '
                       . 'diferentes. Verifique os dados ou faça login.',
    'erro-indisponivel' => 'Este presente não está mais disponível.',
    'erro-valor'      => 'Este presente ainda não tem um valor definido.',
    'login'           => 'Você ainda não possui uma conta ou não está conectado. '
                       . 'Crie uma conta ou faça login para continuar com seu presente.',
];
$giftMsg = $giftStatus !== null ? ($giftMessages[$giftStatus] ?? null) : null;

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
            <?php if ($paymentsEnabled): ?>
                <p class="section__intro">
                    Preparamos uma seleção especial de presentes para
                    compartilhar esse momento tão importante com vocês.
                </p>
            <?php else: ?>
                <p class="section__intro">
                    Prévia visual da futura lista de presentes. As compras ainda
                    <strong>não estão disponíveis</strong> nesta etapa.
                </p>
            <?php endif; ?>
        </div>

        <?php if ($giftMsg !== null): ?>
            <p class="flash flash--error" style="text-align:center"><?= View::e($giftMsg) ?></p>
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
                       value="<?= (int) $maxCents ?>" step="100"
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
                // Presente já escolhido (pago) ou reservado durante um pagamento.
                $taken      = (bool) ($gift['taken'] ?? false);
                $name       = (string) ($gift['name'] ?? 'Presente');
                $desc       = trim((string) ($gift['description'] ?? ''));
                $priceText  = (string) ($gift['price'] ?? 'R$ ---');
                $imageUrl   = $resolveImage((string) ($gift['image'] ?? ''));
                // Só pode comprar se há pagamento ativo, id/preço válidos E não
                // estiver indisponível (reservado/comprado).
                $canBuy     = $paymentsEnabled && $giftId > 0 && $priceCents > 0 && !$taken;
                // Card clicável (abre o popup de detalhes) sempre que há
                // pagamento ativo — inclusive os já escolhidos, que abrem o
                // popup só para ver a descrição (sem a opção de presentear).
                $clickable  = $paymentsEnabled;
                ?>
                <article class="gift-card<?= $taken ? ' gift-card--taken' : '' ?><?= $clickable ? ' gift-card--clickable' : '' ?>"
                         data-gift-card data-price="<?= $priceCents ?>"
                         <?= $taken ? 'data-taken="1"' : '' ?>
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

                    <?php if ($taken): ?>
                        <!-- Já escolhido: notificação discreta no lugar do "Presentear". -->
                        <span class="gift-status gift-status--taken">
                            <span class="gift-status__dot" aria-hidden="true"></span>
                            Presente já escolhido
                        </span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($showFilter): ?>
            <p class="gift-empty" data-gift-empty hidden>Nenhum presente dentro desse valor.</p>
        <?php endif; ?>

        <?php if (!$paymentsEnabled): ?>
            <p class="gift-note">
                <em>Os presentes acima são provisórios e meramente ilustrativos.</em>
            </p>
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
                Este presente já foi escolhido. Que tal escolher outro?
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
    </div>
</div>

<!-- Popup 2: dados de pagamento (reutilizado para qualquer presente). -->
<div class="gift-modal" data-gift-modal hidden>
    <div class="gift-modal__overlay" data-gift-close></div>
    <div class="gift-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="gift-modal-title">
        <button class="gift-modal__close" type="button" data-gift-close aria-label="Fechar">&times;</button>

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
                Preencha os dados ao lado para contribuir. O pagamento é
                processado com segurança &mdash; você escolhe entre PIX, cartão
                de crédito ou boleto.
            </p>
        </div>

        <form class="gift-modal__form" method="post"
              action="<?= View::e(View::url('/presentear', $baseUrl)) ?>">
            <?= $csrfField ?>
            <input type="hidden" name="gift_id" value="" data-gift-modal-id>

            <div class="field field--full">
                <label for="gm_name">Seu nome</label>
                <input type="text" id="gm_name" name="buyer_name"
                       value="<?= View::e((string) ($currentGuest['name'] ?? '')) ?>" required>
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
                    <input type="email" id="gm_email" name="buyer_email"
                           value="<?= View::e((string) ($currentGuest['email'] ?? '')) ?>"
                           <?= $guestLogged ? '' : 'required' ?>>
                </div>

                <div class="field field--full">
                    <label for="gm_phone">Telefone/WhatsApp</label>
                    <input type="tel" id="gm_phone" name="buyer_phone" inputmode="numeric"
                           placeholder="(DDD) número"
                           <?= $guestLogged ? '' : 'required' ?>>
                </div>
            </div>

            <div class="field field--full">
                <label for="gm_billing">Forma de pagamento</label>
                <select id="gm_billing" name="billing_type" data-gift-modal-billing>
                    <option value="PIX">PIX (aprovação na hora)</option>
                    <option value="CREDIT_CARD">Cartão de crédito</option>
                    <option value="BOLETO">Boleto bancário</option>
                </select>
            </div>

            <div class="field field--full" data-gift-modal-cpf hidden>
                <label for="gm_cpf">CPF/CNPJ</label>
                <input type="text" id="gm_cpf" name="buyer_cpf" inputmode="numeric"
                       placeholder="Somente números">
            </div>

            <!-- Campos do cartão (checkout transparente). Só aparecem quando a
                 forma escolhida é "Cartão de crédito". Nada é armazenado: os
                 dados vão por HTTPS ao servidor e são repassados ao ASAAS. -->
            <div class="gift-modal__card" data-gift-modal-card hidden>
                <p class="gift-modal__card-note">
                    Pagamento processado com segurança. Seus dados do cartão não
                    são armazenados pelo site.
                </p>

                <div class="field field--full">
                    <label for="gm_card_holder">Nome impresso no cartão</label>
                    <input type="text" id="gm_card_holder" name="card_holder"
                           autocomplete="cc-name" placeholder="Como está no cartão">
                </div>

                <div class="field field--full">
                    <label for="gm_card_number">Número do cartão</label>
                    <input type="text" id="gm_card_number" name="card_number"
                           inputmode="numeric" autocomplete="cc-number"
                           maxlength="23" placeholder="0000 0000 0000 0000"
                           data-card-number>
                </div>

                <div class="gift-modal__card-row">
                    <div class="field">
                        <label for="gm_card_expiry">Validade</label>
                        <input type="text" id="gm_card_expiry" name="card_expiry"
                               inputmode="numeric" autocomplete="cc-exp"
                               maxlength="7" placeholder="MM/AA" data-card-expiry>
                    </div>
                    <div class="field">
                        <label for="gm_card_ccv">CVV</label>
                        <input type="text" id="gm_card_ccv" name="card_ccv"
                               inputmode="numeric" autocomplete="cc-csc"
                               maxlength="4" placeholder="000" data-card-ccv>
                    </div>
                </div>

                <div class="gift-modal__card-row">
                    <div class="field">
                        <label for="gm_card_cep">CEP</label>
                        <input type="text" id="gm_card_cep" name="card_cep"
                               inputmode="numeric" autocomplete="postal-code"
                               maxlength="9" placeholder="00000-000">
                    </div>
                    <div class="field">
                        <label for="gm_card_addr">Nº do endereço</label>
                        <input type="text" id="gm_card_addr" name="card_address_number"
                               inputmode="numeric" placeholder="123">
                    </div>
                </div>
            </div>

            <div class="field field--full">
                <label for="gm_msg">Mensagem (opcional)</label>
                <input type="text" id="gm_msg" name="message" maxlength="255"
                       placeholder="Uma mensagem carinhosa para o casal">
            </div>

            <button class="btn btn--primary gift-modal__submit" type="submit">
                Continuar para o pagamento
            </button>
        </form>
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
    var billing = modal.querySelector('[data-gift-modal-billing]');
    var cpf     = modal.querySelector('[data-gift-modal-cpf]');
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

        // Valida a disponibilidade antes de permitir a compra.
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

        // Aplica o estado da forma de pagamento (CPF e campos de cartão)
        // conforme a opção selecionada ao abrir o pagamento.
        if (typeof applyBilling === 'function') { applyBilling(); }
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

    // Bloco dos campos de cartão (checkout transparente).
    var cardBox = modal.querySelector('[data-gift-modal-card]');
    var cardInputs = cardBox
        ? cardBox.querySelectorAll('input[name^="card_"]')
        : [];

    // Ativa/desativa os campos de cartão: quando ocultos, remove 'required' e
    // 'name' para o navegador não bloquear o envio nem mandar campos vazios.
    var setCardActive = function (active) {
        if (!cardBox) { return; }
        cardBox.hidden = !active;
        Array.prototype.forEach.call(cardInputs, function (inp) {
            if (active) {
                inp.required = true;
                if (inp.dataset.name) { inp.name = inp.dataset.name; }
            } else {
                inp.required = false;
                // Guarda o name e remove, para não enviar dado de cartão quando
                // a forma escolhida não for cartão.
                if (!inp.dataset.name) { inp.dataset.name = inp.name; }
                inp.removeAttribute('name');
            }
        });
    };

    // Mostra/oculta CPF e cartão conforme a forma de pagamento. Para visitante
    // sem conta, o CPF é sempre exigido; para logado, aparece em boleto e cartão.
    var applyBilling = function () {
        var v = billing ? billing.value : 'PIX';
        var isCard = v === 'CREDIT_CARD';
        if (cpf) {
            cpf.hidden = isLogged ? (v !== 'BOLETO' && !isCard) : false;
        }
        setCardActive(isCard);
    };
    if (billing) {
        billing.addEventListener('change', applyBilling);
        applyBilling(); // estado inicial
    }

    // Máscaras dos campos de cartão.
    var onlyDigits = function (s) { return (s || '').replace(/\D/g, ''); };
    var cardNumber = modal.querySelector('[data-card-number]');
    if (cardNumber) {
        cardNumber.addEventListener('input', function () {
            var d = onlyDigits(cardNumber.value).slice(0, 19);
            cardNumber.value = d.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
        });
    }
    var cardExpiry = modal.querySelector('[data-card-expiry]');
    if (cardExpiry) {
        cardExpiry.addEventListener('input', function () {
            var d = onlyDigits(cardExpiry.value).slice(0, 4);
            cardExpiry.value = d.length >= 3 ? (d.slice(0, 2) + '/' + d.slice(2)) : d;
        });
    }
    var cardCcv = modal.querySelector('[data-card-ccv]');
    if (cardCcv) {
        cardCcv.addEventListener('input', function () {
            cardCcv.value = onlyDigits(cardCcv.value).slice(0, 4);
        });
    }
    var cardCep = modal.querySelector('#gm_card_cep');
    if (cardCep) {
        cardCep.addEventListener('input', function () {
            var d = onlyDigits(cardCep.value).slice(0, 8);
            cardCep.value = d.length >= 6 ? (d.slice(0, 5) + '-' + d.slice(5)) : d;
        });
    }

    // Máscara de CPF/CNPJ: formata automaticamente enquanto digita.
    // CPF: 000.000.000-00  |  CNPJ: 00.000.000/0000-00
    var cpfInput = modal.querySelector('#gm_cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function () {
            var d = cpfInput.value.replace(/\D/g, '').slice(0, 14);
            var out;
            if (d.length <= 11) {
                out = d.replace(/^(\d{3})(\d)/, '$1.$2')
                       .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
                       .replace(/\.(\d{3})(\d)/, '.$1-$2');
            } else {
                out = d.replace(/^(\d{2})(\d)/, '$1.$2')
                       .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                       .replace(/\.(\d{3})(\d)/, '.$1/$2')
                       .replace(/(\d{4})(\d)/, '$1-$2');
            }
            cpfInput.value = out;
        });
    }
})();
</script>
<?php endif; ?>
