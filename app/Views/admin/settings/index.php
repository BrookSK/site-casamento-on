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

$smtpSecurity = (string) ($values['smtp_security'] ?? 'tls');
$asaasEnv     = (string) ($values['asaas_environment'] ?? 'sandbox');
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
    <p>Configuração do servidor de envio de e-mails. <strong>Base apenas</strong> —
    o envio ainda não está ativo; será implementado em outra etapa.</p>

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
</div>

<!-- ===================== ASAAS ===================== -->
<div class="admin-card">
    <h2>Pagamentos (ASAAS)</h2>
    <p>Credenciais do gateway de pagamento. <strong>Base apenas</strong> — nenhuma
    cobrança é gerada ainda. Comece sempre pelo ambiente <em>sandbox</em>.</p>

    <form class="form-grid" method="post" action="<?= View::e(View::url('/admin/configuracoes/asaas', $baseUrl)) ?>">
        <?= $csrfField ?>
        <div class="field">
            <label for="asaas_environment">Ambiente</label>
            <select id="asaas_environment" name="asaas_environment">
                <option value="sandbox" <?= $asaasEnv === 'sandbox' ? 'selected' : '' ?>>Sandbox (teste)</option>
                <option value="production" <?= $asaasEnv === 'production' ? 'selected' : '' ?>>Produção</option>
            </select>
        </div>

        <div class="field">
            <label for="asaas_api_key">Chave de API <?= ($values['asaas_api_key_set'] ?? '') ? '(já salva — preencha só para alterar)' : '' ?></label>
            <input type="password" id="asaas_api_key" name="asaas_api_key" autocomplete="off" placeholder="$aact_...">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-admin">Salvar ASAAS</button>
        </div>
    </form>
</div>
