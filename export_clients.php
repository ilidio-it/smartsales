<?php
require_once 'config/database.php';
require_once 'config/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$conn = $database->connect();

// Get filter parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$client_type = isset($_GET['client_type']) ? $_GET['client_type'] : 'all';
$canteen_filter = isset($_GET['canteen']) ? $_GET['canteen'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Build query conditions
$conditions = ["status = 'active'"];
$params = [];

// Apply client type filter
if ($client_type !== 'all') {
    $conditions[] = "type = ?";
    $params[] = $client_type;
}

// Apply canteen filter
if ($canteen_filter !== 'all') {
    $conditions[] = "canteen_type = ?";
    $params[] = $canteen_filter;
}

// Apply search filter
if (!empty($search_term)) {
    $conditions[] = "(name LIKE ? OR contact LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Get canteen condition based on user's access
if ($_SESSION['canteen_type'] !== 'both') {
    $conditions[] = "(canteen_type = ? OR type LIKE ?)";
    $params[] = $_SESSION['canteen_type'];
    $params[] = "%{$_SESSION['canteen_type']}%";
}

// Apply type-specific conditions
if ($type === 'balance') {
    $conditions[] = "balance > 0";
} elseif ($type === 'debt') {
    $conditions[] = "debt > 0";
}

// Build the final query
$where_clause = implode(' AND ', $conditions);
$query = "SELECT * FROM clients WHERE $where_clause ORDER BY name";

// Get clients
$stmt = $conn->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determine title based on type
$title = "Lista de Clientes";
if ($type === 'balance') {
    $title = "Lista de Clientes com Saldo";
} elseif ($type === 'debt') {
    $title = "Lista de Clientes com Dívidas";
}

// Add filter information to title
$filter_info = [];
if ($client_type !== 'all') {
    $type_names = [
        'primary' => 'Alunos Primária',
        'secondary' => 'Alunos Secundária',
        'teacher_primary' => 'Professores Primária',
        'teacher_secondary' => 'Professores Secundária'
    ];
    $filter_info[] = $type_names[$client_type] ?? ucfirst($client_type);
}

if ($canteen_filter !== 'all') {
    $filter_info[] = $canteen_filter === 'primary' ? 'Cantina Primária' : 'Cantina Secundária';
}

if (!empty($search_term)) {
    $filter_info[] = 'Busca: "' . $search_term . '"';
}

if (!empty($filter_info)) {
    $title .= " - " . implode(", ", $filter_info);
}

// Generate Excel file
if ($format === 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Start output buffering
    ob_start();
    
    // Output Excel XML header
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
    
    // Add styles
    echo '<Styles>
        <Style ss:ID="Default" ss:Name="Normal">
            <Alignment ss:Vertical="Bottom"/>
            <Borders/>
            <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/>
            <Interior/>
            <NumberFormat/>
            <Protection/>
        </Style>
        <Style ss:ID="Header">
            <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/>
            <Interior ss:Color="#2d7d32" ss:Pattern="Solid"/>
            <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>
        </Style>
        <Style ss:ID="PositiveValue">
            <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#4caf50" ss:Bold="1"/>
        </Style>
        <Style ss:ID="NegativeValue">
            <Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#f44336" ss:Bold="1"/>
        </Style>
    </Styles>';
    
    // Start worksheet
    echo '<Worksheet ss:Name="Clientes">';
    echo '<Table>';
    
    // Add header row
    echo '<Row>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Nome</Data></Cell>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Tipo</Data></Cell>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Contacto</Data></Cell>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Saldo (MT)</Data></Cell>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Dívida (MT)</Data></Cell>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Cantina</Data></Cell>
        <Cell ss:StyleID="Header"><Data ss:Type="String">Data de Cadastro</Data></Cell>
    </Row>';
    
    // Add data rows
    foreach ($clients as $client) {
        $type_names = [
            'primary' => 'Aluno Primária',
            'secondary' => 'Aluno Secundária',
            'teacher_primary' => 'Professor Primária',
            'teacher_secondary' => 'Professor Secundária',
            'reservation' => 'Reserva'
        ];
        
        $canteen_names = [
            'primary' => 'Primária',
            'secondary' => 'Secundária'
        ];
        
        echo '<Row>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($client['name']) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . ($type_names[$client['type']] ?? ucfirst($client['type'])) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($client['contact'] ?? '') . '</Data></Cell>';
        
        // Balance with style
        $balance_style = $client['balance'] > 0 ? 'PositiveValue' : '';
        echo '<Cell ss:StyleID="' . $balance_style . '"><Data ss:Type="Number">' . $client['balance'] . '</Data></Cell>';
        
        // Debt with style
        $debt_style = $client['debt'] > 0 ? 'NegativeValue' : '';
        echo '<Cell ss:StyleID="' . $debt_style . '"><Data ss:Type="Number">' . $client['debt'] . '</Data></Cell>';
        
        echo '<Cell><Data ss:Type="String">' . ($canteen_names[$client['canteen_type']] ?? ucfirst($client['canteen_type'])) . '</Data></Cell>';
        echo '<Cell><Data ss:Type="String">' . date('d/m/Y', strtotime($client['created_at'])) . '</Data></Cell>';
        echo '</Row>';
    }
    
    // End worksheet
    echo '</Table>';
    echo '</Worksheet>';
    
    // End workbook
    echo '</Workbook>';
    
    // End output buffering and send to browser
    ob_end_flush();
    exit();
}

// Generate CSV file
elseif ($format === 'csv') {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="clientes_' . date('Y-m-d') . '.csv"');
    
    // Create a file pointer connected to the output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add header row
    fputcsv($output, ['Nome', 'Tipo', 'Contacto', 'Saldo (MT)', 'Dívida (MT)', 'Cantina', 'Data de Cadastro']);
    
    // Add data rows
    foreach ($clients as $client) {
        $type_names = [
            'primary' => 'Aluno Primária',
            'secondary' => 'Aluno Secundária',
            'teacher_primary' => 'Professor Primária',
            'teacher_secondary' => 'Professor Secundária',
            'reservation' => 'Reserva'
        ];
        
        $canteen_names = [
            'primary' => 'Primária',
            'secondary' => 'Secundária'
        ];
        
        fputcsv($output, [
            $client['name'],
            $type_names[$client['type']] ?? ucfirst($client['type']),
            $client['contact'] ?? '',
            $client['balance'],
            $client['debt'],
            $canteen_names[$client['canteen_type']] ?? ucfirst($client['canteen_type']),
            date('d/m/Y', strtotime($client['created_at']))
        ]);
    }
    
    fclose($output);
    exit();
}

// Fallback to JSON if format not supported
else {
    header('Content-Type: application/json');
    echo json_encode([
        'title' => $title,
        'clients' => $clients,
        'count' => count($clients)
    ]);
    exit();
}
?>