
<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

$report_date = $_GET['date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

// Get the same data as the main report
include_once 'daily_closure_report.php';

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fecho_contas_' . $report_date . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, ['RELATÓRIO DE FECHO DE CONTAS', date('d/m/Y', strtotime($report_date))], ';');
    fputcsv($output, [], ';'); // Empty line
    
    // Payment methods summary
    fputcsv($output, ['MÉTODOS DE PAGAMENTO'], ';');
    fputcsv($output, ['Método', 'Primária Trans.', 'Primária Valor', 'Secundária Trans.', 'Secundária Valor', 'Total Trans.', 'Total Valor'], ';');
    
    $payment_method_names = [
        'cash' => 'Dinheiro',
        'mpesa' => 'M-Pesa', 
        'emola' => 'E-Mola',
        'account' => 'Conta',
        'voucher' => 'Voucher',
        'debt' => 'Dívida'
    ];
    
    foreach ($payment_method_names as $method => $name) {
        fputcsv($output, [
            $name,
            $primary_data['payments'][$method]['transactions'] ?? 0,
            number_format($primary_data['payments'][$method]['amount'] ?? 0, 2),
            $secondary_data['payments'][$method]['transactions'] ?? 0, 
            number_format($secondary_data['payments'][$method]['amount'] ?? 0, 2),
            $totals['payments'][$method]['transactions'],
            number_format($totals['payments'][$method]['amount'], 2)
        ], ';');
    }
    
    fputcsv($output, [], ';'); // Empty line
    
    // Cash flow summary
    fputcsv($output, ['FLUXO DE CAIXA'], ';');
    fputcsv($output, ['', 'Primária', 'Secundária', 'Total'], ';');
    fputcsv($output, ['Dinheiro Recebido', number_format($primary_cash_flow['cash_received'], 2), number_format($secondary_cash_flow['cash_received'], 2), number_format($total_cash_flow['cash_received'], 2)], ';');
    fputcsv($output, ['Contas Debitadas', number_format($primary_cash_flow['account_deducted'], 2), number_format($secondary_cash_flow['account_deducted'], 2), number_format($total_cash_flow['account_deducted'], 2)], ';');
    fputcsv($output, ['Dívidas Criadas', number_format($primary_cash_flow['debt_created'], 2), number_format($secondary_cash_flow['debt_created'], 2), number_format($total_cash_flow['debt_created'], 2)], ';');
    fputcsv($output, ['Total Vendas', number_format($primary_cash_flow['total_sales'], 2), number_format($secondary_cash_flow['total_sales'], 2), number_format($total_cash_flow['total_sales'], 2)], ';');
    fputcsv($output, ['Diferença', number_format($primary_cash_flow['balance'], 2), number_format($secondary_cash_flow['balance'], 2), number_format($total_cash_flow['balance'], 2)], ';');
    
    fclose($output);
}
?>
