<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-chart-line"></i>
            Monitor de Vendas Diárias
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" onclick="refreshData()">
                <i class="fas fa-sync-alt"></i>
                Atualizar
            </button>
            <button type="button" class="btn btn-secondary" onclick="executeReset()">
                <i class="fas fa-redo"></i>
                Reset Manual
            </button>
        </div>
    </div>
    
    <!-- Status do Sistema -->
    <div class="card">
        <div class="card-header">
            <h3>📊 Status do Sistema de Reset</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--info-color);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value" id="last-reset">Carregando...</div>
                    <div class="stat-label">Último Reset</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--primary-color);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value" id="current-time">Carregando...</div>
                    <div class="stat-label">Hora Atual</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--success-color);">
                        <i class="fas fa-hamburger"></i>
                    </div>
                    <div class="stat-value" id="total-vendidos">0</div>
                    <div class="stat-label">Total Vendidos Hoje</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--warning-color);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value">5:00 AM</div>
                    <div class="stat-label">Próximo Reset</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Vendas por Categoria -->
    <div class="card">
        <div class="card-header">
            <h3>🍽️ Vendas por Categoria</h3>
        </div>
        <div class="card-body">
            <div id="category-totals" class="grid grid-4">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Detalhes por Produto -->
    <div class="card">
        <div class="card-header">
            <h3>📋 Detalhes por Produto</h3>
        </div>
        <div class="table-container">
            <table class="table" id="products-table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Vendidos Primária</th>
                        <th>Vendidos Secundária</th>
                        <th>Total Vendidos</th>
                        <th>Stock Atual</th>
                    </tr>
                </thead>
                <tbody id="products-tbody">
                    <!-- Será preenchido via JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Instruções -->
    <div class="card">
        <div class="card-header">
            <h3>📖 Como Funciona</h3>
        </div>
        <div class="card-body">
            <div class="instructions-grid">
                <div class="instruction-item">
                    <div class="instruction-icon">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="instruction-content">
                        <h4>1. Reset Automático</h4>
                        <p>Todos os dias às <strong>5:00 AM</strong>, o sistema reseta hambúrgueres, tostas, sandes e pregos para <strong>0</strong></p>
                    </div>
                </div>
                
                <div class="instruction-item">
                    <div class="instruction-icon">
                        <i class="fas fa-minus"></i>
                    </div>
                    <div class="instruction-content">
                        <h4>2. Contagem Negativa</h4>
                        <p>A cada venda, o número fica negativo: <strong>-1, -2, -3...</strong></p>
                    </div>
                </div>
                
                <div class="instruction-item">
                    <div class="instruction-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="instruction-content">
                        <h4>3. Controle Visual</h4>
                        <p>O número negativo mostra <strong>quantos foram vendidos</strong> no dia</p>
                    </div>
                </div>
                
                <div class="instruction-item">
                    <div class="instruction-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="instruction-content">
                        <h4>4. Relatórios</h4>
                        <p>Os relatórios calculam automaticamente as vendas baseadas nos números negativos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let refreshInterval;

document.addEventListener('DOMContentLoaded', function() {
    refreshData();
    
    // Atualizar a cada 30 segundos
    refreshInterval = setInterval(refreshData, 30000);
});

function refreshData() {
    fetch('api/check_negative_stock.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDisplay(data);
            } else {
                console.error('Erro ao carregar dados:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
        });
}

function updateDisplay(data) {
    // Atualizar informações gerais
    document.getElementById('last-reset').textContent = data.last_reset;
    document.getElementById('current-time').textContent = new Date(data.current_time).toLocaleString('pt-PT');
    
    // Calcular total vendidos
    let totalVendidos = 0;
    Object.values(data.totals).forEach(category => {
        totalVendidos += category.total;
    });
    document.getElementById('total-vendidos').textContent = totalVendidos;
    
    // Atualizar totais por categoria
    const categoryTotalsDiv = document.getElementById('category-totals');
    categoryTotalsDiv.innerHTML = '';
    
    Object.entries(data.totals).forEach(([category, totals]) => {
        const categoryCard = document.createElement('div');
        categoryCard.className = 'stat-card';
        categoryCard.innerHTML = `
            <div class="stat-icon" style="color: var(--primary-color);">
                <i class="fas fa-${getCategoryIcon(category)}"></i>
            </div>
            <div class="stat-value">${totals.total}</div>
            <div class="stat-label">${category}</div>
            <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 0.5rem;">
                Primária: ${totals.primaria} | Secundária: ${totals.secundaria}
            </div>
        `;
        categoryTotalsDiv.appendChild(categoryCard);
    });
    
    // Atualizar tabela de produtos
    const tbody = document.getElementById('products-tbody');
    tbody.innerHTML = '';
    
    if (data.products.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">
                    <div class="empty-state">
                        <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                        <h4>Nenhuma venda registrada hoje</h4>
                        <p>Os produtos serão listados aqui conforme as vendas acontecem</p>
                    </div>
                </td>
            </tr>
        `;
    } else {
        data.products.forEach(product => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${product.name}</td>
                <td>
                    <span class="badge badge-${product.category_name.toLowerCase()}">
                        ${product.category_name}
                    </span>
                </td>
                <td class="text-center">
                    <span class="vendidos-count">${product.vendidos_primaria}</span>
                </td>
                <td class="text-center">
                    <span class="vendidos-count">${product.vendidos_secundaria}</span>
                </td>
                <td class="text-center">
                    <strong class="total-vendidos">${product.vendidos_primaria + product.vendidos_secundaria}</strong>
                </td>
                <td class="text-center">
                    <span class="stock-negative">
                        P: ${product.stock_primary} | S: ${product.stock_secondary}
                    </span>
                </td>
            `;
            tbody.appendChild(row);
        });
    }
}

function getCategoryIcon(category) {
    const icons = {
        'Hamburguer': 'hamburger',
        'Tosta': 'bread-slice',
        'Sandes': 'sandwich',
        'Prego': 'drumstick-bite'
    };
    return icons[category] || 'utensils';
}

function executeReset() {
    if (!confirm('Executar reset manual agora? Isso irá zerar todos os contadores.')) {
        return;
    }
    
    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executando...';
    button.disabled = true;
    
    fetch('api/manual_reset.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(`Reset executado com sucesso!\nProdutos resetados: ${data.products_reset}`);
                refreshData();
            } else {
                alert(`Erro no reset: ${data.message}`);
            }
        })
        .catch(error => {
            alert(`Erro: ${error.message}`);
        })
        .finally(() => {
            button.innerHTML = originalText;
            button.disabled = false;
        });
}

// Limpar interval quando sair da página
window.addEventListener('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<style>
.vendidos-count {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.total-vendidos {
    font-weight: 700;
    color: var(--success-color);
    font-size: 1.2rem;
}

.stock-negative {
    font-family: monospace;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.badge-hamburguer { background: #ff6b35; color: white; }
.badge-tosta { background: #ffc107; color: #000; }
.badge-sandes { background: #28a745; color: white; }
.badge-prego { background: #dc3545; color: white; }

.instructions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.instruction-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--background-color);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--primary-color);
}

.instruction-icon {
    flex-shrink: 0;
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.instruction-content h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 1rem;
}

.instruction-content p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}
</style>

<?php include 'includes/footer.php'; ?>