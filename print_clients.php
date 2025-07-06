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

// Calculate totals
$total_balance = 0;
$total_debt = 0;
foreach ($clients as $client) {
    $total_balance += $client['balance'];
    $total_debt += $client['debt'];
}

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

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: white;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #2d7d32;
        }
        
        .logo {
            font-size: 18px;
            font-weight: bold;
            color: #2d7d32;
            margin-bottom: 5px;
        }
        
        .subtitle {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .date-generated {
            font-size: 10px;
            color: #999;
        }
        
        h2 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #2d7d32;
        }
        
        .summary {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            border-left: 3px solid #2d7d32;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-label {
            font-weight: bold;
        }
        
        .summary-value {
            font-weight: bold;
        }
        
        .positive-value {
            color: #4caf50;
        }
        
        .negative-value {
            color: #f44336;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .client-type {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .type-primary {
            background: #e3f2fd;
            color: #0d47a1;
        }
        
        .type-secondary {
            background: #e8f5e9;
            color: #1b5e20;
        }
        
        .type-teacher-primary {
            background: #fff3e0;
            color: #e65100;
        }
        
        .type-teacher-secondary {
            background: #f3e5f5;
            color: #6a1b9a;
        }
        
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            background: #2d7d32;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #1b5e20;
        }
        
        .footer {
            text-align: center;
            font-size: 10px;
            color: #999;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
            .print-button { display: none !important; }
        }
    </style>
    <script>
        function printPage() {
            window.print();
        }
        
        window.onload = function() {
            document.getElementById('printBtn').focus();
        };
    </script>
</head>
<body>
    <!-- Print Button -->
    <button id="printBtn" class="print-button no-print" onclick="printPage()">
        🖨️ Imprimir Agora
    </button>
    
    <!-- Header -->
    <div class="header">
        <div class="logo">🍽️ SmartSales</div>
        <div class="subtitle">Sistema de Gestão de Cantina Escolar</div>
        <div class="date-generated">Relatório gerado em <?php echo date('d/m/Y H:i'); ?></div>
    </div>
    
    <!-- Title -->
    <h2><?php echo $title; ?></h2>
    
    <!-- Summary -->
    <div class="summary">
        <div class="summary-row">
            <span class="summary-label">Total de Clientes:</span>
            <span class="summary-value"><?php echo count($clients); ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total de Saldo:</span>
            <span class="summary-value positive-value">Mt <?php echo number_format($total_balance, 2); ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Total de Dívidas:</span>
            <span class="summary-value negative-value">Mt <?php echo number_format($total_debt, 2); ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Saldo Líquido:</span>
            <span class="summary-value <?php echo ($total_balance - $total_debt) >= 0 ? 'positive-value' : 'negative-value'; ?>">
                Mt <?php echo number_format($total_balance - $total_debt, 2); ?>
            </span>
        </div>
    </div>
    
    <!-- Clients Table -->
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Contacto</th>
                <th>Saldo (MT)</th>
                <th>Dívida (MT)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($clients)): ?>
            <tr>
                <td colspan="5" style="text-align: center; padding: 20px;">
                    Nenhum cliente encontrado com os filtros selecionados.
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($clients as $client): ?>
            <tr>
                <td><?php echo htmlspecialchars($client['name']); ?></td>
                <td>
                    <?php 
                    $type_names = [
                        'primary' => 'Aluno Primária',
                        'secondary' => 'Aluno Secundária',
                        'teacher_primary' => 'Professor Primária',
                        'teacher_secondary' => 'Professor Secundária',
                        'reservation' => 'Reserva'
                    ];
                    
                    $type_classes = [
                        'primary' => 'type-primary',
                        'secondary' => 'type-secondary',
                        'teacher_primary' => 'type-teacher-primary',
                        'teacher_secondary' => 'type-teacher-secondary',
                        'reservation' => 'type-reservation'
                    ];
                    
                    $type_display = $type_names[$client['type']] ?? ucfirst($client['type']);
                    $type_class = $type_classes[$client['type']] ?? '';
                    ?>
                    <span class="client-type <?php echo $type_class; ?>"><?php echo $type_display; ?></span>
                </td>
                <td><?php echo htmlspecialchars($client['contact'] ?? ''); ?></td>
                <td style="<?php echo $client['balance'] > 0 ? 'color: #4caf50; font-weight: bold;' : ''; ?>">
                    <?php echo number_format($client['balance'], 2); ?>
                </td>
                <td style="<?php echo $client['debt'] > 0 ? 'color: #f44336; font-weight: bold;' : ''; ?>">
                    <?php echo number_format($client['debt'], 2); ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Footer -->
    <div class="footer">
        SmartSales - Sistema de Gestão de Cantina Escolar | 
        Relatório gerado em <?php echo date('d/m/Y H:i:s'); ?> | 
        Usuário: <?php echo htmlspecialchars($_SESSION['name']); ?>
    </div>
</body>
</html>