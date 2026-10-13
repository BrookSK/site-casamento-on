/* =============================================================
   Site de Casamento — Interações (JavaScript vanilla)
   Mantido leve: menu mobile, header no scroll, scroll reveal e
   contagem regressiva (apenas visual).
   ============================================================= */
(function () {
    'use strict';

    /* ---- Menu de navegação (mobile) ---- */
    var toggle = document.querySelector('[data-nav-toggle]');
    var nav = document.querySelector('[data-nav]');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var isOpen = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.querySelector('.sr-only').textContent = isOpen ? 'Fechar menu' : 'Abrir menu';
        });

        // Fecha o menu ao clicar em um link
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            });
        });

        // Fecha com a tecla Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                nav.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    }

    /* ---- Header muda de aparência ao rolar ---- */
    var header = document.querySelector('[data-header]');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 40);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* ---- Scroll reveal (IntersectionObserver) ---- */
    var revealEls = document.querySelectorAll('[data-reveal]');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (revealEls.length) {
        if (reduceMotion || !('IntersectionObserver' in window)) {
            revealEls.forEach(function (el) { el.classList.add('is-visible'); });
        } else {
            var observer = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12 });

            revealEls.forEach(function (el) { observer.observe(el); });
        }
    }

    /* ---- Contagem regressiva (apenas visual) ---- */
    var cd = document.querySelector('[data-countdown]');
    if (cd) {
        var target = new Date(cd.getAttribute('data-target')).getTime();

        // Só exibe se a data for válida e estiver no futuro.
        if (!isNaN(target) && target > Date.now()) {
            cd.hidden = false;

            var out = {
                days: cd.querySelector('[data-cd="days"]'),
                hours: cd.querySelector('[data-cd="hours"]'),
                minutes: cd.querySelector('[data-cd="minutes"]'),
                seconds: cd.querySelector('[data-cd="seconds"]')
            };

            var pad = function (n) { return String(n).padStart(2, '0'); };

            var tick = function () {
                var diff = target - Date.now();
                if (diff <= 0) {
                    out.days.textContent = out.hours.textContent =
                        out.minutes.textContent = out.seconds.textContent = '00';
                    clearInterval(timer);
                    return;
                }
                var s = Math.floor(diff / 1000);
                out.days.textContent = pad(Math.floor(s / 86400));
                out.hours.textContent = pad(Math.floor((s % 86400) / 3600));
                out.minutes.textContent = pad(Math.floor((s % 3600) / 60));
                out.seconds.textContent = pad(s % 60);
            };

            tick();
            var timer = setInterval(tick, 1000);
        }
    }

    /* ---- RSVP: acompanhantes + nomes dinâmicos ---- */
    var rsvpForm = document.querySelector('[data-rsvp-form]');
    if (rsvpForm) {
        var companionsField = rsvpForm.querySelector('[data-companions]');
        var companionsInput = rsvpForm.querySelector('[data-companions-count]');
        var namesWrap = rsvpForm.querySelector('[data-companions-names]');
        var namesList = rsvpForm.querySelector('[data-companions-list]');
        var attendingRadios = rsvpForm.querySelectorAll('[data-attending]');

        var MAX = 20;

        // Gera/ajusta os campos de nome conforme a quantidade informada.
        var renderNameFields = function () {
            if (!namesList || !namesWrap) { return; }

            var checked = rsvpForm.querySelector('[data-attending]:checked');
            var isYes = checked && checked.getAttribute('data-attending') === 'yes';
            var count = parseInt(companionsInput ? companionsInput.value : '0', 10);
            if (isNaN(count) || count < 0) { count = 0; }
            if (count > MAX) { count = MAX; }

            // Esconde a seção se não for "sim" ou não houver acompanhantes.
            namesWrap.hidden = !(isYes && count > 0);

            var current = namesList.children.length;

            // Adiciona campos faltantes (preservando o que o usuário já digitou).
            for (var i = current; i < count; i++) {
                var field = document.createElement('div');
                field.className = 'rsvp-field';
                var id = 'rsvp-companion-' + (i + 1);
                var label = document.createElement('label');
                label.setAttribute('for', id);
                label.textContent = 'Acompanhante ' + (i + 1);
                var input = document.createElement('input');
                input.type = 'text';
                input.id = id;
                input.name = 'companion_names[]';
                field.appendChild(label);
                field.appendChild(input);
                namesList.appendChild(field);
            }
            // Remove campos excedentes.
            while (namesList.children.length > count) {
                namesList.removeChild(namesList.lastChild);
            }

            // Nomes são obrigatórios quando visíveis.
            Array.prototype.forEach.call(namesList.querySelectorAll('input'), function (inp) {
                if (namesWrap.hidden) {
                    inp.removeAttribute('required');
                } else {
                    inp.setAttribute('required', 'required');
                }
            });
        };

        var updateCompanions = function () {
            var checked = rsvpForm.querySelector('[data-attending]:checked');
            var isYes = checked && checked.getAttribute('data-attending') === 'yes';
            if (companionsField) {
                companionsField.hidden = !isYes;
            }
            if (!isYes && companionsInput) {
                companionsInput.value = '0';
            }
            renderNameFields();
        };

        attendingRadios.forEach(function (radio) {
            radio.addEventListener('change', updateCompanions);
        });
        if (companionsInput) {
            companionsInput.addEventListener('input', renderNameFields);
            companionsInput.addEventListener('change', renderNameFields);
        }
        updateCompanions();
    }

    /* ---- Lista de presentes: filtro por valor máximo (slider) ---- */
    var giftFilter = document.querySelector('[data-gift-filter]');
    if (giftFilter) {
        var range = giftFilter.querySelector('[data-gift-range]');
        var label = giftFilter.querySelector('[data-gift-filter-label]');
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-gift-card]'));
        var emptyMsg = document.querySelector('[data-gift-empty]');

        var formatBRL = function (cents) {
            return (cents / 100).toLocaleString('pt-BR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        };

        var applyFilter = function () {
            var max = parseInt(range.value, 10);
            var visible = 0;

            cards.forEach(function (card) {
                var price = parseInt(card.getAttribute('data-price'), 10) || 0;
                var show = price <= max;
                card.classList.toggle('gift-card--hidden', !show);
                if (show) { visible++; }
            });

            if (label) { label.textContent = 'Até R$ ' + formatBRL(max); }
            if (emptyMsg) { emptyMsg.hidden = visible > 0; }
        };

        range.addEventListener('input', applyFilter);
        applyFilter();
    }
})();
