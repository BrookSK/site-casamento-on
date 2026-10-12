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

    /* ---- RSVP: mostra "acompanhantes" só quando escolhe "Sim" ---- */
    var rsvpForm = document.querySelector('[data-rsvp-form]');
    if (rsvpForm) {
        var companionsField = rsvpForm.querySelector('[data-companions]');
        var companionsInput = companionsField ? companionsField.querySelector('input') : null;
        var attendingRadios = rsvpForm.querySelectorAll('[data-attending]');

        var updateCompanions = function () {
            var checked = rsvpForm.querySelector('[data-attending]:checked');
            var isYes = checked && checked.getAttribute('data-attending') === 'yes';
            if (companionsField) {
                companionsField.hidden = !isYes;
            }
            // Zera o campo quando a pessoa escolhe "não".
            if (!isYes && companionsInput) {
                companionsInput.value = '0';
            }
        };

        attendingRadios.forEach(function (radio) {
            radio.addEventListener('change', updateCompanions);
        });
        updateCompanions();
    }
})();
