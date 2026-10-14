<?php

declare(strict_types=1);

namespace App\Core;

use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Exportação do relatório financeiro de presentes (ponto 11) em Excel (.xlsx)
 * e PDF, incluindo valores bruto, taxas e líquido, datas, status, e totais
 * consolidados ao final. As linhas chegam já filtradas pelo controller.
 */
final class FinanceExport
{
    private const STATUS_LABELS = [
        'pending'  => 'Pendente',
        'paid'     => 'Pago',
        'failed'   => 'Recusado',
        'refunded' => 'Estornado',
    ];

    private const BILLING_LABELS = [
        'PIX'         => 'PIX',
        'CREDIT_CARD' => 'Cartão',
        'BOLETO'      => 'Boleto',
        'UNDEFINED'   => '—',
    ];

    /** Centavos -> "1.234,56" (sem o prefixo R$, para a planilha). */
    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    private static function statusLabel(string $s): string
    {
        return self::STATUS_LABELS[$s] ?? $s;
    }

    private static function billingLabel(string $b): string
    {
        return self::BILLING_LABELS[$b] ?? $b;
    }

    private static function date(?string $v): string
    {
        if ($v === null || $v === '' || str_starts_with($v, '0000')) {
            return '';
        }
        $ts = strtotime($v);
        return $ts !== false ? date('d/m/Y H:i', $ts) : (string) $v;
    }

    /**
     * Gera e envia o Excel (.xlsx). Encerra a execução.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int>               $summary
     */
    public static function downloadXlsx(array $rows, array $summary, string $coupleNames = ''): void
    {
        if (!class_exists(Spreadsheet::class)) {
            throw new RuntimeException('Biblioteca de Excel ausente. Rode "composer install" no servidor.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Financeiro');

        $sheet->setCellValue('A1', $coupleNames !== '' ? ('Casamento — ' . $coupleNames) : 'Relatório financeiro');
        $sheet->setCellValue('A2', 'Relatório financeiro de presentes');
        $sheet->setCellValue('A3', 'Gerado em ' . date('d/m/Y H:i'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A3')->getFont()->setSize(9);

        $headers = [
            'A' => 'Transação (ref.)',
            'B' => 'Convidado/Comprador',
            'C' => 'Presente',
            'D' => 'Forma',
            'E' => 'Status',
            'F' => 'Criada em',
            'G' => 'Pagamento',
            'H' => 'Valor bruto (R$)',
            'I' => 'Taxas (R$)',
            'J' => 'Líquido (R$)',
        ];
        $headerRow = 5;
        foreach ($headers as $col => $title) {
            $sheet->setCellValue($col . $headerRow, $title);
        }
        $headerStyle = $sheet->getStyle('A' . $headerRow . ':J' . $headerRow);
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('6E7256');

        $row = $headerRow + 1;
        foreach ($rows as $p) {
            $buyer = (string) ($p['guest_name'] ?? ($p['buyer_name'] ?? '—'));
            $sheet->setCellValue('A' . $row, (string) ($p['payment_ref'] ?? '—'));
            $sheet->setCellValue('B' . $row, $buyer !== '' ? $buyer : '—');
            $sheet->setCellValue('C' . $row, (string) ($p['gift_name'] ?? '—'));
            $sheet->setCellValue('D' . $row, self::billingLabel((string) ($p['billing_type'] ?? 'UNDEFINED')));
            $sheet->setCellValue('E' . $row, self::statusLabel((string) ($p['payment_status'] ?? 'pending')));
            $sheet->setCellValue('F' . $row, self::date($p['created_at'] ?? null));
            $sheet->setCellValue('G' . $row, self::date($p['paid_at'] ?? null));
            $sheet->setCellValue('H' . $row, self::money((int) ($p['amount_cents'] ?? 0)));
            $sheet->setCellValue('I' . $row, self::money((int) ($p['fee_cents'] ?? 0)));
            $sheet->setCellValue('J' . $row, self::money((int) ($p['net_cents'] ?? 0)));
            $row++;
        }

        $lastDataRow = max($headerRow, $row - 1);
        $sheet->getStyle('A' . $headerRow . ':J' . $lastDataRow)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->setAutoFilter('A' . $headerRow . ':J' . $lastDataRow);

        // Totais consolidados.
        $t = $row + 1;
        $totals = [
            ['Total vendido (não falhas)',        $summary['sold_cents'] ?? 0],
            ['Total aprovado (pago)',             $summary['approved_cents'] ?? 0],
            ['Total de taxas',                    $summary['fee_cents'] ?? 0],
            ['Total líquido recebido',            $summary['net_cents'] ?? 0],
            ['Total pendente',                    $summary['pending_cents'] ?? 0],
            ['Total estornado/reembolsado',       $summary['refunded_cents'] ?? 0],
        ];
        foreach ($totals as $pair) {
            $sheet->setCellValue('A' . $t, $pair[0]);
            $sheet->setCellValue('J' . $t, self::money((int) $pair[1]));
            $sheet->mergeCells('A' . $t . ':I' . $t);
            $sheet->getStyle('A' . $t . ':J' . $t)->getFont()->setBold(true);
            $sheet->getStyle('J' . $t)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $t++;
        }

        foreach (['A' => 22, 'B' => 24, 'C' => 24, 'D' => 10, 'E' => 12, 'F' => 16, 'G' => 16, 'H' => 15, 'I' => 12, 'J' => 15] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="financeiro-' . date('Ymd-His') . '.xlsx"');
        header('Cache-Control: max-age=0');
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    /**
     * Gera e envia o PDF. Encerra a execução.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, int>               $summary
     */
    public static function downloadPdf(array $rows, array $summary, string $coupleNames = ''): void
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('Biblioteca de PDF ausente. Rode "composer install" no servidor.');
        }

        $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $body = '';
        if ($rows === []) {
            $body = '<tr><td colspan="7" style="text-align:center;color:#777;padding:12px;">Nenhuma transação neste filtro.</td></tr>';
        } else {
            foreach ($rows as $p) {
                $buyer = (string) ($p['guest_name'] ?? ($p['buyer_name'] ?? '—'));
                $body .= '<tr>'
                    . '<td>' . $esc((string) ($p['gift_name'] ?? '—')) . '</td>'
                    . '<td>' . $esc($buyer !== '' ? $buyer : '—') . '</td>'
                    . '<td>' . $esc(self::billingLabel((string) ($p['billing_type'] ?? 'UNDEFINED'))) . '</td>'
                    . '<td>' . $esc(self::statusLabel((string) ($p['payment_status'] ?? 'pending'))) . '</td>'
                    . '<td style="text-align:right;">' . self::money((int) ($p['amount_cents'] ?? 0)) . '</td>'
                    . '<td style="text-align:right;">' . self::money((int) ($p['fee_cents'] ?? 0)) . '</td>'
                    . '<td style="text-align:right;">' . self::money((int) ($p['net_cents'] ?? 0)) . '</td>'
                    . '</tr>';
            }
        }

        $heading = $coupleNames !== '' ? ('Casamento — ' . $esc($coupleNames)) : 'Relatório financeiro';
        $totalsHtml = '<table class="totals"><tbody>'
            . self::totalRow('Total vendido (não falhas)', (int) ($summary['sold_cents'] ?? 0))
            . self::totalRow('Total aprovado (pago)', (int) ($summary['approved_cents'] ?? 0))
            . self::totalRow('Total de taxas', (int) ($summary['fee_cents'] ?? 0))
            . self::totalRow('Total líquido recebido', (int) ($summary['net_cents'] ?? 0))
            . self::totalRow('Total pendente', (int) ($summary['pending_cents'] ?? 0))
            . self::totalRow('Total estornado/reembolsado', (int) ($summary['refunded_cents'] ?? 0))
            . '</tbody></table>';

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><style>'
            . 'body { font-family: sans-serif; color: #2c2a26; }'
            . 'h1 { font-size: 15pt; margin: 0 0 2px; }'
            . 'h2 { font-size: 11pt; margin: 0 0 2px; color: #6e7256; font-weight: normal; }'
            . '.meta { font-size: 8pt; color: #888; margin-bottom: 10px; }'
            . 'table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }'
            . 'th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }'
            . 'th { background: #6e7256; color: #fff; }'
            . 'tr:nth-child(even) td { background: #f6f4ef; }'
            . '.totals { width: 48%; margin-top: 14px; font-size: 9.5pt; }'
            . '.totals td { border: 0; padding: 3px 6px; }'
            . '.totals td:last-child { text-align: right; font-weight: bold; }'
            . '</style></head><body>'
            . '<h1>' . $heading . '</h1>'
            . '<h2>Relatório financeiro de presentes</h2>'
            . '<div class="meta">Gerado em ' . date('d/m/Y H:i') . '</div>'
            . '<table><thead><tr>'
            . '<th>Presente</th><th>Convidado/Comprador</th><th>Forma</th><th>Status</th>'
            . '<th style="text-align:right;">Bruto</th><th style="text-align:right;">Taxas</th><th style="text-align:right;">Líquido</th>'
            . '</tr></thead><tbody>' . $body . '</tbody></table>'
            . $totalsHtml
            . '</body></html>';

        $tmp = BASE_PATH . '/storage/mpdf';
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $mpdf = new Mpdf([
            'mode'        => 'utf-8',
            'format'      => 'A4-L', // paisagem: cabem mais colunas
            'margin_top'  => 14,
            'margin_bottom' => 14,
            'tempDir'     => is_dir($tmp) ? $tmp : null,
        ]);
        $mpdf->SetTitle('Relatório financeiro');
        $mpdf->WriteHTML($html);
        $mpdf->Output('financeiro-' . date('Ymd-His') . '.pdf', 'D');
        exit;
    }

    /** Linha de total para o bloco de totais do PDF. */
    private static function totalRow(string $label, int $cents): string
    {
        return '<tr><td>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>R$ ' . self::money($cents) . '</td></tr>';
    }
}
