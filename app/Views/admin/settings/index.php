<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Configurações: SMTP e ASAAS (base — ainda sem envio/cobrança reais).
 *
 * @var array                      $app
 * @var array<string, string|null> $values
 * @var string|null                $error
 * @var bool                       $hasKey
 * @var string                     $csrfField
 * @var array{type:string,message:string}|null $flash
 */

$baseUrl = $app['base_url'] ?? '';
$v = static fn (string $k): string => View::e((string) ($values[$k] ?? ''));

$smtpSecurity   = (string) ($values['smtp_security'] ?? 'tls');
$asaasEnv       = (string) ($values['asaas_environment'] ?? 'sandbox');
$webhookEnabled = (string) ($values['webhook_rsvp_enabled'] ?? '') !== '';
?>
<?php if (!empty($flash)): ?>
    <div class="flash flash--<?= View::e($flash['type']) ?>"><?= View::e($flash['message']) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="flash flash--error"><?= View::e($error) ?></div>
<?php endif; ?>

<?php if (!$hasKey): ?>
    <div class="flash flash--error">
        A chave de aplicação ainda não foi gerada. Os campos sensíveis (senha SMTP
        e chave do ASAAS) não poderão ser salvos até você rodar, no servidor:
        <code>php database/generate_key.php</code>
    </div>
<?php endif; ?>

<!-- ===================== SMTP ===================== -->
<div class="admin-card">
    <h2>E-mail (SMTP)</h2>
    <p>Configuração do servidor de envio de e-mails. Com o SMTP configurado, o
    site envia automaticamente a confirmação de presença aos convidados e o
    agradecimento quando um presente é pago. Use o botão <em>Testar envio</em>
    para validar as credenciais.</p>

    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/configuracoes/smtp', $baseUrl)) ?>">
        <?= $csrfField ?>
        <div class="form-row">
            <div class="field">
                <label for="smtp_host">Servidor (host)</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?= $v('smtp_host') ?>" placeholder="smtp.exemplo.com">
            </div>
            <div class="field">
                <label for="smtp_port">Porta</label>
                <input type="text" id="smtp_port" name="smtp_port" value="<?= $v('smtp_port') ?>" placeholder="587">
            </div>
        </div>

        <div class="form-row">
            <div class="field">
                <label for="smtp_user">Usuário</label>
                <input type="text" id="smtp_user" name="smtp_user" value="<?= $v('smtp_user') ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="smtp_security">Segurança</label>
                <select id="smtp_security" name="smtp_security">
                    <option value="tls" <?= $smtpSecurity === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= $smtpSecurity === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    <option value="none" <?= $smtpSecurity === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                </select>
            </div>
        </div>

        <div class="field">
            <label for="smtp_password">Senha <?= ($values['smtp_password_set'] ?? '') ? '(já salva — preencha só para alterar)' : '' ?></label>
            <input type="password" id="smtp_password" name="smtp_password" autocomplete="new-password" placeholder="••••••••">
        </div>

        <div class="form-row">
            <div class="field">
                <label for="smtp_from_email">E-mail remetente</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?= $v('smtp_from_email') ?>">
            </div>
            <div class="field">
                <label for="smtp_from_name">Nome remetente</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?= $v('smtp_from_name') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin">Salvar SMTP</button>
        </div>
    </form>

    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/configuracoes/smtp/testar', $baseUrl)) ?>" style="margin-top:1rem">
        <?= $csrfField ?>
        <div class="field">
            <label for="smtp_test_to">Enviar e-mail de teste para</label>
            <input type="email" id="smtp_test_to" name="smtp_test_to" value="<?= $v('smtp_from_email') ?>" placeholder="destinatario@exemplo.com" autocomplete="off">
            <small>Em branco envia para o próprio e-mail remetente. Salve as
            configurações antes de testar.</small>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn-admin btn-admin--ghost">Testar envio</button>
        </div>
    </form>
</div>

<!-- ===================== ASAAS ===================== -->
<div class="admin-card">
    <h2>Pagamentos (ASAAS)</h2>
    <p>Credenciais do gateway de pagamento. Com a chave configurada, a lista de
    presentes passa a gerar cobranças reais (PIX, cartão e boleto). Comece sempre
    pelo ambiente <em>sandbox</em> e só mude para produção após homologar.</p>

    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/configuracoes/asaas', $baseUrl)) ?>">
        <?= $csrfField ?>
        <div class="field">
            <label for="asaas_environment">Ambiente</label>
            <select id="asaas_environment" name="asaas_environment">
                <option value="sandbox" <?= $asaasEnv === 'sandbox' ? 'selected' : '' ?>>Sandbox (teste)</option>
                <option value="production" <?= $asaasEnv === 'production' ? 'selected' : '' ?>>Produção</option>
            </select>
        </div>

        <?php $asaasKeySet = (string) ($values['asaas_api_key_set'] ?? '') !== ''; ?>
        <div class="field">
            <label for="asaas_api_key">Chave de API</label>
            <input type="password" id="asaas_api_key" name="asaas_api_key" autocomplete="off"
                   placeholder="<?= $asaasKeySet ? '••••••••••••••••' : '$aact_...' ?>">
            <small>
                <?php if ($asaasKeySet): ?>
                    Já existe uma chave salva (ocultada por segurança). Deixe em branco
                    para mantê-la ou digite uma nova para substituir.
                <?php else: ?>
                    Cole a chave gerada no painel do ASAAS (começa com <code>$aact_</code>).
                <?php endif; ?>
            </small>
        </div>

        <div class="field">
            <label for="asaas_webhook_token">Token do webhook (opcional)</label>
            <input type="text" id="asaas_webhook_token" name="asaas_webhook_token" value="<?= $v('asaas_webhook_token') ?>" autocomplete="off" placeholder="um valor secreto à sua escolha">
            <small>Defina o mesmo valor no cadastro do webhook no painel do ASAAS
            (campo <em>Token de autenticação</em>). O site valida o header
            <code>asaas-access-token</code> antes de processar.</small>
        </div>

        <?php
        // Monta a URL ABSOLUTA do webhook. Com base_url configurada (produção),
        // usa-a direto. Sem base_url (dev local), deriva do host da requisição,
        // evitando mostrar um caminho relativo que o ASAAS não consegue chamar.
        if ($baseUrl !== '') {
            $webhookUrl = View::url('/asaas/webhook', $baseUrl);
        } else {
            $scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host       = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $webhookUrl = $scheme . '://' . $host . '/asaas/webhook';
        }
        ?>
        <div class="field">
            <label>URL do webhook (configure no painel do ASAAS)</label>
            <input type="text" readonly value="<?= View::e($webhookUrl) ?>" onclick="this.select()">
            <small>Cadastre esta URL no ASAAS para receber as confirmações de
            pagamento (eventos de cobrança). Em desenvolvimento local, use um
            túnel público (ex.: ngrok) para o ASAAS conseguir acessá-la.</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin">Salvar ASAAS</button>
        </div>
    </form>

    <form method="post" action="<?= View::e(View::url('/admin/configuracoes/asaas/testar', $baseUrl)) ?>">
        <div class="form-actions" style="margin-top:1rem">
            <button type="submit" class="btn-admin btn-admin--ghost">Testar conexão</button>
            <small>Usa a chave já salva para validar o acesso à API do ASAAS.</small>
        </div>
        <?= $csrfField ?>
    </form>
</div>

<!-- ===================== WEBHOOK (confirmações) ===================== -->
<div class="admin-card">
    <h2>Webhook (confirmações)</h2>
    <p>Quando um convidado envia a confirmação de presença (positiva ou negativa),
    o site faz um <code>POST</code> em <strong>JSON</strong> para a URL abaixo.
    Útil para integrar com automações (ex.: envio de mensagem no WhatsApp).
    Deixe em branco para desativar.</p>

    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/configuracoes/webhook', $baseUrl)) ?>">
        <?= $csrfField ?>
        <div class="field">
            <label for="webhook_rsvp_url">URL do webhook</label>
            <input type="url" id="webhook_rsvp_url" name="webhook_rsvp_url" value="<?= $v('webhook_rsvp_url') ?>" placeholder="https://exemplo.com/webhook" autocomplete="off">
        </div>

        <div class="field">
            <label class="checkbox-field" for="webhook_rsvp_enabled">
                <input type="checkbox" id="webhook_rsvp_enabled" name="webhook_rsvp_enabled" value="1" <?= $webhookEnabled ? 'checked' : '' ?>>
                <span>Ativar o envio automático ao receber uma confirmação</span>
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin">Salvar Webhook</button>
        </div>
    </form>
</div>
