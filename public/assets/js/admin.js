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
        if (!input || !preview || !img) { return; }

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
})();
