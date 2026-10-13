/* =============================================================
   Conta do convidado — alternância de abas (entrar/criar) + máscara de telefone
   ============================================================= */
(function () {
    'use strict';

    // Alterna entre as abas "Entrar" e "Criar conta".
    var tabsWrap = document.querySelector('.account__tabs');
    var tabs = document.querySelectorAll('[data-account-tab]');
    var forms = document.querySelectorAll('[data-account-form]');

    var activate = function (name) {
        if (tabsWrap) {
            tabsWrap.setAttribute('data-active', name);
        }
        tabs.forEach(function (t) {
            t.classList.toggle('is-active', t.getAttribute('data-account-tab') === name);
        });
        forms.forEach(function (f) {
            f.classList.toggle('is-active', f.getAttribute('data-account-form') === name);
        });
    };

    tabs.forEach(function (t) {
        t.addEventListener('click', function () {
            activate(t.getAttribute('data-account-tab'));
        });
    });

    // Máscara de telefone: (11) 98888-7777
    var phone = document.querySelector('[data-mask-phone]');
    if (phone) {
        var maskPhone = function (v) {
            var d = (v || '').replace(/\D/g, '').slice(0, 11);
            if (d.length === 0) { return ''; }
            var out = '(' + d.slice(0, 2);
            if (d.length >= 3) { out += ') ' + d.slice(2, 7); }
            if (d.length >= 8) { out += '-' + d.slice(7, 11); }
            return out;
        };
        if (phone.value) { phone.value = maskPhone(phone.value); }
        phone.addEventListener('input', function () {
            phone.value = maskPhone(phone.value);
        });
    }
})();
