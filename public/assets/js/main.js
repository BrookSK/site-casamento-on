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
        var setMenu = function (open) {
            nav.classList.toggle('is-open', open);
            document.body.classList.toggle('nav-open', open);
            toggle.setAttribute('aria-expanded', String(open));
            var sr = toggle.querySelector('.sr-only');
            if (sr) { sr.textContent = open ? 'Fechar menu' : 'Abrir menu'; }
        };

        toggle.addEventListener('click', function () {
            setMenu(!nav.classList.contains('is-open'));
        });

        // Fecha o menu ao clicar em um link
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { setMenu(false); });
        });

        // Fecha com a tecla Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && nav.classList.contains('is-open')) {
                setMenu(false);
                toggle.focus();
            }
        });

        // Fecha ao tocar fora do painel (na área escurecida), sem fechar quando
        // o toque é no próprio painel ou no botão de abrir/fechar.
        document.addEventListener('click', function (e) {
            if (!nav.classList.contains('is-open')) { return; }
            if (nav.contains(e.target) || toggle.contains(e.target)) { return; }
            setMenu(false);
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

    /* ---- Minimapa do evento: carrega o iframe só no clique ----
       Mantém a página leve e preserva a privacidade: o mapa (iframe de
       terceiros) só é injetado quando a pessoa pede "Ver mapa". O link
       "Abrir no Google Maps" continua funcionando independentemente. */
    var mapBlocks = document.querySelectorAll('[data-map]');
    Array.prototype.forEach.call(mapBlocks, function (block) {
        var loadBtn = block.querySelector('[data-map-load]');
        var src = block.getAttribute('data-map-src');
        if (!loadBtn || !src) { return; }

        loadBtn.addEventListener('click', function () {
            if (block.classList.contains('is-loaded')) { return; }

            var frame = document.createElement('div');
            frame.className = 'invite__map-frame';

            var iframe = document.createElement('iframe');
            iframe.src = src;
            iframe.loading = 'lazy';
            iframe.referrerPolicy = 'no-referrer-when-downgrade';
            iframe.setAttribute('allowfullscreen', '');
            iframe.title = 'Mapa do local do evento';

            frame.appendChild(iframe);
            // Insere o mapa antes do link, logo após o botão.
            block.insertBefore(frame, loadBtn.nextSibling);
            block.classList.add('is-loaded');
        });
    });

    /* ---- RSVP: acompanhantes adicionados um a um ---- */
    var MAX_COMPANIONS = 20;

    // Configura a lista dinâmica de acompanhantes de um formulário RSVP.
    // Funciona tanto para o formulário de nova confirmação (com o seletor
    // "vai/não vai") quanto para o formulário de edição (sempre visível).
    function setupCompanions(form) {
        var companionsBox = form.querySelector('[data-companions]');
        var companionsList = form.querySelector('[data-companions-list]');
        var addBtn = form.querySelector('[data-companions-add]');
        var attendingRadios = form.querySelectorAll('[data-attending]');
        if (!companionsList) { return; }

        var renumber = function () {
            var rows = companionsList.querySelectorAll('[data-companion-row]');
            Array.prototype.forEach.call(rows, function (row, i) {
                var label = row.querySelector('label');
                var input = row.querySelector('input');
                var id = 'rsvp-companion-' + (i + 1);
                if (label) { label.textContent = 'Acompanhante ' + (i + 1); label.setAttribute('for', id); }
                if (input) { input.id = id; }
            });
            if (addBtn) { addBtn.hidden = rows.length >= MAX_COMPANIONS; }
        };

        var addCompanion = function (focus) {
            if (companionsList.querySelectorAll('[data-companion-row]').length >= MAX_COMPANIONS) { return; }
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
            if (focus !== false) { input.focus(); }
        };

        // Liga os botões "remover" de linhas já presentes (formulário de edição).
        Array.prototype.forEach.call(companionsList.querySelectorAll('[data-companion-remove]'), function (btn) {
            btn.addEventListener('click', function () {
                var row = btn.closest('[data-companion-row]');
                if (row) { row.parentNode.removeChild(row); renumber(); }
            });
        });

        if (addBtn) {
            addBtn.addEventListener('click', function () { addCompanion(true); });
        }

        // Só o formulário de nova confirmação tem o seletor vai/não vai.
        if (attendingRadios.length) {
            var updateCompanions = function () {
                var checked = form.querySelector('[data-attending]:checked');
                var isYes = checked && checked.getAttribute('data-attending') === 'yes';
                if (companionsBox) { companionsBox.hidden = !isYes; }
                if (!isYes) { companionsList.innerHTML = ''; renumber(); }
            };
            attendingRadios.forEach(function (radio) {
                radio.addEventListener('change', updateCompanions);
            });
            updateCompanions();
        } else {
            renumber();
        }
    }

    var newRsvpForm = document.querySelector('[data-rsvp-form]');
    if (newRsvpForm) { setupCompanions(newRsvpForm); }

    var editRsvpForm = document.querySelector('[data-rsvp-edit]');
    if (editRsvpForm) { setupCompanions(editRsvpForm); }

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

    /* ---- Fotos sobrepostas ("Nossa História"): destaque por toque ---- */
    var photoStack = document.querySelector('[data-photo-stack]');
    if (photoStack) {
        var items = Array.prototype.slice.call(
            photoStack.querySelectorAll('[data-photo-stack-item]')
        );

        var clearActive = function (except) {
            items.forEach(function (el) {
                if (el !== except) { el.classList.remove('is-active'); }
            });
        };

        items.forEach(function (item) {
            // No toque, alterna o destaque da foto tocada (hover não existe).
            item.addEventListener('touchstart', function () {
                var willActivate = !item.classList.contains('is-active');
                clearActive(item);
                item.classList.toggle('is-active', willActivate);
            }, { passive: true });

            // Teclado: Enter/Espaço também destacam (acessibilidade).
            item.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var willActivate = !item.classList.contains('is-active');
                    clearActive(item);
                    item.classList.toggle('is-active', willActivate);
                }
            });
        });

        // Toque fora das fotos remove o destaque atual.
        document.addEventListener('touchstart', function (e) {
            if (!photoStack.contains(e.target)) { clearActive(null); }
        }, { passive: true });
    }

    /* ---- Galeria por álbuns: lightbox com navegação por álbum ---- */
    var lightbox = document.querySelector('[data-lightbox]');
    if (lightbox) {
        var lbImg = lightbox.querySelector('[data-lightbox-img]');
        var lbCaption = lightbox.querySelector('[data-lightbox-caption]');
        var lbCounter = lightbox.querySelector('[data-lightbox-counter]');
        var lbImgWrap = lightbox.querySelector('.lightbox__imgwrap');
        var prevBtn = lightbox.querySelector('[data-lightbox-prev]');
        var nextBtn = lightbox.querySelector('[data-lightbox-next]');
        var closeEls = lightbox.querySelectorAll('[data-lightbox-close]');

        var currentAlbum = null;  // lista de fotos do álbum atual
        var currentIndex = 0;
        var lastFocused = null;

        var render = function () {
            if (!currentAlbum || !currentAlbum[currentIndex]) { return; }
            var photo = currentAlbum[currentIndex];
            lbImg.setAttribute('src', photo.src);
            lbImg.setAttribute('alt', photo.alt);
            if (lbCaption) { lbCaption.textContent = photo.alt; }
            // Contador "atual / total".
            if (lbCounter) {
                lbCounter.textContent = (currentIndex + 1) + ' / ' + currentAlbum.length;
            }
            // Pequena animação de troca de imagem (fade suave).
            if (lbImgWrap) {
                lbImgWrap.classList.remove('is-swapping');
                // reflow para reiniciar a animação
                void lbImgWrap.offsetWidth;
                lbImgWrap.classList.add('is-swapping');
            }
            // Esconde as setas quando o álbum tem só uma foto.
            var multiple = currentAlbum.length > 1;
            if (prevBtn) { prevBtn.style.display = multiple ? '' : 'none'; }
            if (nextBtn) { nextBtn.style.display = multiple ? '' : 'none'; }
        };

        var openLightbox = function (albumEl, index) {
            var photoBtns = Array.prototype.slice.call(
                albumEl.querySelectorAll('[data-album-photo]')
            );
            currentAlbum = photoBtns.map(function (btn) {
                return {
                    src: btn.getAttribute('data-src'),
                    alt: btn.getAttribute('data-alt') || ''
                };
            });
            currentIndex = index;
            lastFocused = document.activeElement;
            render();
            lightbox.hidden = false;
            document.body.style.overflow = 'hidden';
            if (closeEls[0]) { closeEls[0].focus(); }
        };

        var closeLightbox = function () {
            lightbox.hidden = true;
            document.body.style.overflow = '';
            currentAlbum = null;
            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        };

        var showPrev = function () {
            if (!currentAlbum) { return; }
            currentIndex = (currentIndex - 1 + currentAlbum.length) % currentAlbum.length;
            render();
        };
        var showNext = function () {
            if (!currentAlbum) { return; }
            currentIndex = (currentIndex + 1) % currentAlbum.length;
            render();
        };

        // Abre ao clicar numa foto (delegação por álbum).
        document.querySelectorAll('[data-album]').forEach(function (albumEl) {
            albumEl.querySelectorAll('[data-album-photo]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var index = parseInt(btn.getAttribute('data-index'), 10) || 0;
                    openLightbox(albumEl, index);
                });
            });
        });

        if (prevBtn) { prevBtn.addEventListener('click', showPrev); }
        if (nextBtn) { nextBtn.addEventListener('click', showNext); }
        closeEls.forEach(function (el) { el.addEventListener('click', closeLightbox); });

        // Teclado: Esc fecha, setas navegam.
        document.addEventListener('keydown', function (e) {
            if (lightbox.hidden) { return; }
            if (e.key === 'Escape') { closeLightbox(); }
            else if (e.key === 'ArrowLeft') { showPrev(); }
            else if (e.key === 'ArrowRight') { showNext(); }
        });

        // Swipe no mobile (dentro do lightbox).
        var touchStartX = 0;
        lightbox.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        lightbox.addEventListener('touchend', function (e) {
            var delta = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(delta) > 50) {
                if (delta < 0) { showNext(); } else { showPrev(); }
            }
        }, { passive: true });
    }

    /* ---- Música ambiente ("Die With a Smile") ----
       Toca ao acessar o site respeitando o bloqueio de autoplay dos
       navegadores. Se o autoplay for bloqueado, oferece um controle
       discreto e inicia no primeiro gesto do usuário.

       O estado (tocando/pausado) e a posição são guardados em
       sessionStorage, de modo que a navegação entre páginas não
       reinicia a faixa — ela continua de onde parou. */
    var musicPlayer = document.querySelector('[data-music-player]');
    if (musicPlayer) {
        var audio = musicPlayer.querySelector('[data-music-audio]');
        var toggleBtn = musicPlayer.querySelector('[data-music-toggle]');
        var musicLabel = musicPlayer.querySelector('[data-music-label]');

        // Volume ambiente (0.0 a 1.0): mais baixo para não incomodar.
        audio.volume = 0.4;

        var KEY_STATE = 'wedding-music-state';   // 'playing' | 'paused'
        var KEY_TIME = 'wedding-music-time';     // posição em segundos

        // Lê/escreve o estado com tolerância a navegadores sem storage.
        var readStore = function (key) {
            try { return window.sessionStorage.getItem(key); } catch (e) { return null; }
        };
        var writeStore = function (key, value) {
            try { window.sessionStorage.setItem(key, value); } catch (e) { /* ignora */ }
        };

        // Usuário já desligou a música nesta sessão? Então não insistimos.
        var userPaused = readStore(KEY_STATE) === 'paused';

        var reflectState = function (isPlaying) {
            musicPlayer.classList.toggle('is-playing', isPlaying);
            toggleBtn.setAttribute('aria-pressed', String(isPlaying));
            toggleBtn.setAttribute('aria-label', isPlaying ? 'Pausar música do casamento' : 'Tocar música do casamento');
            if (musicLabel) { musicLabel.textContent = isPlaying ? 'Pausar música' : 'Tocar música'; }
        };

        // Restaura a posição salva (continuidade entre páginas).
        var savedTime = parseFloat(readStore(KEY_TIME) || '');
        if (!isNaN(savedTime) && savedTime > 0) {
            var applyTime = function () {
                try { audio.currentTime = savedTime; } catch (e) { /* ignora */ }
            };
            if (audio.readyState >= 1) { applyTime(); }
            else { audio.addEventListener('loadedmetadata', applyTime, { once: true }); }
        }

        // Mostra o controle (fica oculto por padrão para não "piscar").
        musicPlayer.hidden = false;

        // Mantém a posição salva periodicamente e nos eventos chave.
        var persistTime = function () {
            if (!isNaN(audio.currentTime)) { writeStore(KEY_TIME, String(audio.currentTime)); }
        };
        audio.addEventListener('timeupdate', persistTime);
        window.addEventListener('pagehide', persistTime);
        window.addEventListener('beforeunload', persistTime);

        audio.addEventListener('play', function () {
            writeStore(KEY_STATE, 'playing');
            reflectState(true);
        });
        audio.addEventListener('pause', function () {
            // Só marca como pausado pelo usuário quando a página segue ativa
            // (evita gravar 'paused' durante a troca de página).
            if (!document.hidden) { writeStore(KEY_STATE, 'paused'); }
            reflectState(false);
        });

        // Tenta iniciar a reprodução; retorna a promessa (ou null).
        var tryPlay = function () {
            var p = audio.play();
            if (p && typeof p.catch === 'function') {
                p.catch(function () {
                    // Autoplay bloqueado: deixa o controle visível e pausado.
                    reflectState(false);
                });
            }
            return p;
        };

        // Clique no controle alterna manualmente (sempre permitido).
        toggleBtn.addEventListener('click', function () {
            if (audio.paused) {
                writeStore(KEY_STATE, 'playing');
                tryPlay();
            } else {
                writeStore(KEY_STATE, 'paused');
                audio.pause();
            }
        });

        // Inicia automaticamente, a menos que o usuário tenha pausado antes.
        if (!userPaused) {
            tryPlay();

            // Fallback para autoplay bloqueado: começa no primeiro gesto.
            var resumeOnGesture = function () {
                if (readStore(KEY_STATE) === 'paused') { return; }
                if (audio.paused) { tryPlay(); }
            };
            var gestureEvents = ['click', 'keydown', 'touchstart', 'pointerdown'];
            var detach = function () {
                gestureEvents.forEach(function (evt) {
                    document.removeEventListener(evt, onGesture);
                });
            };
            var onGesture = function () {
                resumeOnGesture();
                // Uma vez que conseguiu tocar, não precisamos mais escutar.
                if (!audio.paused) { detach(); }
            };
            gestureEvents.forEach(function (evt) {
                document.addEventListener(evt, onGesture, { passive: true });
            });
        } else {
            reflectState(false);
        }
    }
})();
