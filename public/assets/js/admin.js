/* =============================================================
   Área administrativa — máscaras de entrada (JavaScript vanilla)
   Formata campos enquanto o usuário digita, com base em data-mask:
     data-mask="date"  -> xx/xx/xx
     data-mask="time"  -> xx:xx
     data-mask="money" -> xxx.xxx,xx (formato brasileiro)
   ============================================================= */
(function () {
    'use strict';

    var onlyDigits = function (s) { return (s || '').replace(/\D/g, ''); };

    // Data: dd/mm/aa (2 dígitos de ano)
    var maskDate = function (value) {
        var d = onlyDigits(value).slice(0, 6);
        var out = d.slice(0, 2);
        if (d.length >= 3) { out += '/' + d.slice(2, 4); }
        if (d.length >= 5) { out += '/' + d.slice(4, 6); }
        return out;
    };

    // Horário: hh:mm
    var maskTime = function (value) {
        var d = onlyDigits(value).slice(0, 4);
        var out = d.slice(0, 2);
        if (d.length >= 3) { out += ':' + d.slice(2, 4); }
        return out;
    };

    // Dinheiro: trata os dígitos como centavos e formata pt-BR (1.234,56).
    var maskMoney = function (value) {
        var d = onlyDigits(value);
        if (d === '') { return ''; }
        // Limita a um valor razoável (até 9 dígitos = R$ 9.999.999,99).
        d = d.slice(0, 9);
        var cents = parseInt(d, 10);
        return (cents / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    var masks = { date: maskDate, time: maskTime, money: maskMoney };

    var fields = document.querySelectorAll('[data-mask]');
    Array.prototype.forEach.call(fields, function (field) {
        var type = field.getAttribute('data-mask');
        var fn = masks[type];
        if (!fn) { return; }

        // Formata o valor inicial (ex.: ao editar um registro existente).
        if (field.value) { field.value = fn(field.value); }

        field.addEventListener('input', function () {
            var start = field.selectionStart;
            var before = field.value;
            field.value = fn(field.value);
            // Mantém o cursor próximo do fim quando possível (simples e estável).
            if (start !== null && start >= before.length) {
                field.selectionStart = field.selectionEnd = field.value.length;
            }
        });
    });

    /* ---- Pré-visualização da imagem antes de salvar ---- */
    var uploads = document.querySelectorAll('[data-image-upload]');
    Array.prototype.forEach.call(uploads, function (box) {
        var input = box.querySelector('[data-image-input]');
        var preview = box.querySelector('[data-image-preview]');
        var img = box.querySelector('[data-image-preview-img]');
        var removeChk = box.querySelector('input[name="remove_image"]');
        var nameEl = box.querySelector('[data-image-name]');
        if (!input || !preview || !img) { return; }

        // Mostra o nome do arquivo escolhido ao lado do botão estilizado.
        var defaultName = nameEl ? nameEl.textContent : '';
        var showFileName = function () {
            if (!nameEl) { return; }
            var f = input.files && input.files[0];
            if (f) {
                nameEl.textContent = f.name;
                nameEl.classList.add('has-file');
            } else {
                nameEl.textContent = defaultName;
                nameEl.classList.remove('has-file');
            }
        };
        input.addEventListener('change', showFileName);

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { return; }
            // Só aceita imagem; evita gerar preview de arquivo inválido.
            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                preview.hidden = true;
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                img.src = e.target.result;
                preview.hidden = false;
                // Se o usuário escolheu uma nova imagem, desmarca "remover".
                if (removeChk) { removeChk.checked = false; }
            };
            reader.readAsDataURL(file);
        });

        // Marcar "remover imagem" esconde o preview atual.
        if (removeChk) {
            removeChk.addEventListener('change', function () {
                if (removeChk.checked) { preview.hidden = true; }
                else if (img.getAttribute('src')) { preview.hidden = false; }
            });
        }
    });

    /* ---- Pré-visualização de MÚLTIPLAS imagens (upload de álbuns) ---- */
    var multiInputs = document.querySelectorAll('[data-multi-image-input]');
    Array.prototype.forEach.call(multiInputs, function (input) {
        // Encontra o container de preview associado (irmão marcado no mesmo form).
        var form = input.form || input.closest('form');
        var box = form ? form.querySelector('[data-multi-image-preview]') : null;
        if (!box) { return; }

        input.addEventListener('change', function () {
            box.innerHTML = '';
            var files = input.files ? Array.prototype.slice.call(input.files) : [];
            var imgs = files.filter(function (f) {
                return /^image\/(jpeg|png|webp)$/.test(f.type);
            });
            if (imgs.length === 0) { box.hidden = true; return; }

            box.hidden = false;
            imgs.forEach(function (file) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    var thumb = document.createElement('img');
                    thumb.src = e.target.result;
                    thumb.className = 'album-upload-preview__thumb';
                    thumb.alt = file.name;
                    box.appendChild(thumb);
                };
                reader.readAsDataURL(file);
            });
        });
    });
})();

/* =============================================================
   Reordenação de presentes por arrastar-e-soltar (apenas DESKTOP).
   - O arraste é iniciado SOMENTE pela alça (≡) de cada linha.
   - Ao soltar, a nova ordem é renumerada e salva automaticamente
     via fetch (POST) para data-reorder-url, com feedback discreto.
   - Mobile fica para uma etapa futura (layout será replanejado).
   ============================================================= */
(function () {
    'use strict';

    // Ativa a reordenação por arraste em QUALQUER tabela marcada com
    // [data-reorder] (presentes, álbuns, etc.). Seletores genéricos de linha
    // (data-reorder-row) e de número de ordem (data-reorder-num).
    var tables = document.querySelectorAll('[data-reorder]');
    Array.prototype.forEach.call(tables, function (table) {
        initReorder(table);
    });

    function initReorder(table) {
        var body = table.querySelector('[data-reorder-body]');
        if (!body) { return; }

        // O status pode ser específico da tabela (data-reorder-status dentro
        // do mesmo card) ou global; procura primeiro no documento.
        var statusEl = document.querySelector('[data-reorder-status]');
        var url      = table.getAttribute('data-reorder-url') || '';
        var csrfName = table.getAttribute('data-csrf-name') || '';
        var csrfTok  = table.getAttribute('data-csrf-token') || '';

        var dragRow = null;        // linha sendo arrastada
        var statusTimer = null;

        var setStatus = function (text, kind) {
            if (!statusEl) { return; }
            statusEl.textContent = text;
            statusEl.className = 'gift-reorder-status' + (kind ? ' is-' + kind : '');
            if (statusTimer) { clearTimeout(statusTimer); }
            if (kind === 'ok') {
                statusTimer = setTimeout(function () {
                    statusEl.textContent = '';
                    statusEl.className = 'gift-reorder-status';
                }, 2500);
            }
        };

        var rowSel = '[data-reorder-row]';

        // Renumera a coluna "Ordem" (1..N) conforme a ordem atual do DOM.
        var renumber = function () {
            var rows = body.querySelectorAll(rowSel);
            Array.prototype.forEach.call(rows, function (row, i) {
                var cell = row.querySelector('[data-reorder-num]');
                if (cell) { cell.textContent = String(i + 1); }
            });
        };

        // Coleta os IDs na ordem atual do DOM.
        var currentOrder = function () {
            var rows = body.querySelectorAll(rowSel);
            return Array.prototype.map.call(rows, function (row) {
                return row.getAttribute('data-id');
            });
        };

        // Persiste a ordem no servidor.
        var save = function () {
            if (!url) { return; }
            setStatus('Salvando…', 'saving');

            var form = new FormData();
            if (csrfName) { form.append(csrfName, csrfTok); }
            currentOrder().forEach(function (id) { form.append('order[]', id); });

            fetch(url, {
                method: 'POST',
                body: form,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (r) {
                return r.json().catch(function () { return { ok: r.ok }; });
            }).then(function (data) {
                if (data && data.ok) { setStatus('Ordem salva \u2713', 'ok'); }
                else { setStatus((data && data.error) || 'Falha ao salvar a ordem.', 'error'); }
            }).catch(function () {
                setStatus('Falha de conexão ao salvar a ordem.', 'error');
            });
        };

        // Dado o ponteiro Y, encontra a linha após a qual soltar.
        var rowAfter = function (y) {
            var rows = Array.prototype.slice.call(
                body.querySelectorAll(rowSel + ':not(.is-dragging)')
            );
            var closest = { offset: Number.NEGATIVE_INFINITY, el: null };
            rows.forEach(function (row) {
                var box = row.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    closest = { offset: offset, el: row };
                }
            });
            return closest.el;
        };

        var handles = table.querySelectorAll('[data-drag-handle]');
        Array.prototype.forEach.call(handles, function (handle) {
            var row = handle.closest(rowSel);
            if (!row) { return; }

            handle.addEventListener('dragstart', function (e) {
                dragRow = row;
                row.classList.add('is-dragging');
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    try { e.dataTransfer.setData('text/plain', row.getAttribute('data-id') || ''); } catch (err) {}
                }
            });

            handle.addEventListener('dragend', function () {
                if (dragRow) { dragRow.classList.remove('is-dragging'); }
                dragRow = null;
            });
        });

        body.addEventListener('dragover', function (e) {
            if (!dragRow) { return; }
            e.preventDefault();                 // permite o drop
            if (e.dataTransfer) { e.dataTransfer.dropEffect = 'move'; }
            var after = rowAfter(e.clientY);
            if (after == null) {
                body.appendChild(dragRow);
            } else if (after !== dragRow) {
                body.insertBefore(dragRow, after);
            }
        });

        body.addEventListener('drop', function (e) {
            if (!dragRow) { return; }
            e.preventDefault();
            renumber();
            save();
        });
    }
})();


/* =============================================================
   Mobile: clicar na linha/cartão abre o popup de detalhes.
   Reaproveita o botão de detalhes já existente dentro da linha
   (data-rsvp-open / data-fin-open / data-row-trigger). Não altera
   nada no desktop — a linha só vira "clicável" via CSS no mobile,
   e aqui apenas encaminhamos o clique para o botão existente.
   ============================================================= */
(function () {
    'use strict';
    // Só vale no mobile: mesma faixa do CSS (.admin-table--cards vira cartões
    // em telas <= 640px). No desktop o comportamento continua intacto.
    var mql = window.matchMedia('(max-width: 640px)');

    document.querySelectorAll('tr[data-row-open]').forEach(function (row) {
        row.addEventListener('click', function (ev) {
            if (!mql.matches) { return; } // desktop: não faz nada
            // Ignora cliques em controles reais (links, botões, inputs, forms).
            if (ev.target.closest('a, button, input, select, textarea, form, label')) {
                return;
            }
            var trigger = row.querySelector('[data-row-trigger]');
            if (trigger) { trigger.click(); }
        });
    });
})();
