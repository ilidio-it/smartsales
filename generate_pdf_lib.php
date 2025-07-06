<?php
// Simple PDF generation library for client statements
// This is a basic implementation - for production, consider using TCPDF or FPDF

class SimplePDF {
    private $content;
    private $title;
    
    public function __construct($title = 'Document') {
        $this->title = $title;
        $this->content = '';
    }
    
    public function addHTML($html) {
        $this->content .= $html;
    }
    
    public function output($filename = 'document.pdf', $destination = 'D') {
        // For now, we'll output HTML that can be printed as PDF
        // In production, you would use a proper PDF library
        
        header('Content-Type: text/html; charset=UTF-8');
        if ($destination === 'D') {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        echo $this->generatePrintableHTML();
    }
    
    private function generatePrintableHTML() {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($this->title) . '</title>
            <style>
                @page { 
                    size: A4; 
                    margin: 15mm; 
                }
                @media print {
                    body { -webkit-print-color-adjust: exact; }
                    .no-print { display: none !important; }
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    margin: 0;
                    padding: 0;
                }
            </style>
            <script>
                window.onload = function() {
                    window.print();
                };
            </script>
        </head>
        <body>
            ' . $this->content . '
        </body>
        </html>';
    }
}

// Helper function to generate client PDF
function generateClientStatementPDF($client, $purchases, $transactions) {
    $pdf = new SimplePDF('Extrato - ' . $client['name']);
    
    // Generate the HTML content for the PDF
    $html = generateClientPDFContent($client, $purchases, $transactions);
    
    $pdf->addHTML($html);
    
    // Output the PDF
    $filename = 'extrato_' . preg_replace('/[^a-zA-Z0-9]/', '_', $client['name']) . '_' . date('Y-m-d') . '.pdf';
    $pdf->output($filename, 'D');
}

function generateClientPDFContent($client, $purchases, $transactions) {
    // This would contain the detailed PDF HTML content
    // Similar to what we have in the generate_client_pdf.php file
    
    $type_names = [
        'primary' => 'Aluno Primária',
        'secondary' => 'Aluno Secundária',
        'teacher_primary' => 'Professor Primária',
        'teacher_secondary' => 'Professor Secundária'
    ];
    
    ob_start();
    ?>
    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #2d7d32; padding-bottom: 10px;">
        <h1 style="color: #2d7d32; margin: 0; font-size: 24px;">🍽️ SmartSales</h1>
        <p style="margin: 5px 0; color: #666;">Sistema de Gestão de Cantina Escolar</p>
        <small style="color: #999;">Extrato gerado em <?php echo date('d/m/Y H:i'); ?></small>
    </div>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2d7d32;">
        <h3 style="margin: 0 0 10px 0; color: #2d7d32;">Informações do Cliente</h3>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
            <div>
                <strong>Nome:</strong><br>
                <?php echo htmlspecialchars($client['name']); ?>
            </div>
            <div>
                <strong>Tipo:</strong><br>
                <?php echo $type_names[$client['type']] ?? ucfirst($client['type']); ?>
            </div>
            <div>
                <strong>Contacto:</strong><br>
                <?php echo htmlspecialchars($client['contact'] ?? 'N/A'); ?>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-top: 15px;">
            <div>
                <strong>Saldo Atual:</strong><br>
                <span style="color: <?php echo $client['balance'] < 0 ? '#f44336' : '#4caf50'; ?>; font-weight: bold;">
                    Mt <?php echo number_format($client['balance'], 2); ?>
                </span>
            </div>
            <div>
                <strong>Dívida Atual:</strong><br>
                <span style="color: #f44336; font-weight: bold;">
                    Mt <?php echo number_format($client['debt'], 2); ?>
                </span>
            </div>
            <div>
                <strong>Cadastrado em:</strong><br>
                <?php echo date('d/m/Y', strtotime($client['created_at'])); ?>
            </div>
        </div>
    </div>
    
    <!-- Add more content sections here -->
    
    <?php
    return ob_get_clean();
}
?>