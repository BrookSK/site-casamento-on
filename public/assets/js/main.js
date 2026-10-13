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

    /* ---- RSVP: acompanhantes adicionados um a um ---- */
    var rsvpForm = document.querySelector('[data-rsvp-form]');
    if (rsvpForm) {
        var companionsBox = rsvpForm.querySelector('[data-companions]');
        var companionsList = rsvpForm.querySelector('[data-companions-list]');
        var addBtn = rsvpForm.querySelector('[data-companions-add]');
        var attendingRadios = rsvpForm.querySelectorAll('[data-attending]');

        var MAX = 20;

        // Renumera os rótulos (Acompanhante 1, 2, 3...) após adicionar/remover.
        var renumber = function () {
            var rows = companionsList.querySelectorAll('[data-companion-row]');
            Array.prototype.forEach.call(rows, function (row, i) {
                var label = row.querySelector('label');
                var input = row.querySelector('input');
                var id = 'rsvp-companion-' + (i + 1);
                if (label) { label.textContent = 'Acompanhante ' + (i + 1); label.setAttribute('for', id); }
                if (input) { input.id = id; }
            });
            // Esconde o botão ao atingir o máximo.
            if (addBtn) { addBtn.hidden = rows.length >= MAX; }
        };

        var addCompanion = function () {
            if (companionsList.querySelectorAll('[data-companion-row]').length >= MAX) { return; }

            var row = document.createElement('div');
            row.className = 'rsvp-companion-row';
            row.setAttribute('data-companion-row', '');

            var field = document.createElement('div');
            field.className = 'rsvp-field';
            var label = document.createElement('label');
            var input = document.createElement('input');
            input.type = 'text';
            input.name = 'companion_names[]';
            input.required = true;
            field.appendChild(label);
            field.appendChild(input);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'rsvp-companion-row__remove';
            remove.setAttribute('aria-label', 'Remover acompanhante');
            remove.innerHTML = '&times;';
            remove.addEventListener('click', function () {
                row.parentNode.removeChild(row);
                renumber();
            });

            row.appendChild(field);
            row.appendChild(remove);
            companionsList.appendChild(row);
            renumber();
            input.focus();
        };

        if (addBtn) {
            addBtn.addEventListener('click', addCompanion);
        }

        var updateCompanions = function () {
            var checked = rsvpForm.querySelector('[data-attending]:checked');
            var isYes = checked && checked.getAttribute('data-attending') === 'yes';
            if (companionsBox) { companionsBox.hidden = !isYes; }
            // Ao escolher "não", limpa todos os acompanhantes.
            if (!isYes && companionsList) {
                companionsList.innerHTML = '';
                renumber();
            }
        };

        attendingRadios.forEach(function (radio) {
            radio.addEventListener('change', updateCompanions);
        });
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

    /* ---- Carrossel infinito do ensaio ---- */
    var carousel = document.querySelector('[data-carousel]');
    if (carousel) {
        var viewport = carousel.querySelector('[data-carousel-viewport]');
        var track = carousel.querySelector('[data-carousel-track]');
        var originals = track ? Array.prototype.slice.call(track.children) : [];

        // Precisa de ao menos 2 slides para fazer sentido rolar.
        if (track && originals.length > 1) {
            // Duplica os slides para criar a sensação de rolagem infinita.
            originals.forEach(function (slide) {
                track.appendChild(slide.cloneNode(true));
            });

            var position = 0;          // deslocamento atual (px)
            var speed = 0.5;           // px por frame (~30px/s a 60fps)
            var paused = false;
            var rafId = null;
            var halfWidth = 0;         // largura do conjunto original (ponto de reset)

            var measure = function () {
                // Metade do scrollWidth = largura dos slides originais (sem os clones).
                halfWidth = track.scrollWidth / 2;
            };

            var step = function () {
                if (!paused) {
                    position += speed;
                    if (position >= halfWidth) { position -= halfWidth; }
                    track.style.transform = 'translateX(' + (-position) + 'px)';
                }
                rafId = window.requestAnimationFrame(step);
            };

            var start = function () {
                measure();
                if (rafId === null) { rafId = window.requestAnimationFrame(step); }
            };

            // Pausa no hover (desktop) e retoma ao sair.
            carousel.addEventListener('mouseenter', function () { paused = true; });
            carousel.addEventListener('mouseleave', function () { if (!dragging) { paused = false; } });

            // ---- Arraste / swipe (mouse e toque) ----
            var dragging = false;
            var startX = 0;
            var startPos = 0;

            var pointerDown = function (clientX) {
                dragging = true;
                paused = true;
                startX = clientX;
                startPos = position;
                viewport.classList.add('is-dragging');
            };
            var pointerMove = function (clientX) {
                if (!dragging) { return; }
                var delta = startX - clientX;
                position = startPos + delta;
                // Normaliza para manter o loop durante o arraste.
                if (halfWidth > 0) {
                    position = ((position % halfWidth) + halfWidth) % halfWidth;
                }
                track.style.transform = 'translateX(' + (-position) + 'px)';
            };
            var pointerUp = function () {
                if (!dragging) { return; }
                dragging = false;
                viewport.classList.remove('is-dragging');
                // Só retoma automaticamente se o mouse não estiver sobre o carrossel.
                if (!carousel.matches(':hover')) { paused = false; }
                else { paused = false; }
            };

            // Mouse
            viewport.addEventListener('mousedown', function (e) { e.preventDefault(); pointerDown(e.clientX); });
            window.addEventListener('mousemove', function (e) { pointerMove(e.clientX); });
            window.addEventListener('mouseup', pointerUp);

            // Toque
            viewport.addEventListener('touchstart', function (e) {
                pointerDown(e.touches[0].clientX);
            }, { passive: true });
            viewport.addEventListener('touchmove', function (e) {
                pointerMove(e.touches[0].clientX);
            }, { passive: true });
            viewport.addEventListener('touchend', pointerUp);

            // Recalcula no resize (larguras mudam entre breakpoints).
            var resizeTimer = null;
            window.addEventListener('resize', function () {
                window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(measure, 150);
            });

            // Respeita quem prefere menos movimento: sem rolagem automática,
            // mas o arraste manual continua funcionando.
            if (reduceMotion) {
                measure();
            } else {
                // Garante medição correta após o carregamento das imagens.
                if (document.readyState === 'complete') { start(); }
                else { window.addEventListener('load', start); start(); }
            }
        }
    }
})();
