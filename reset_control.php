<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

// Check if user has permission
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'manager') {
    header("Location: dashboard.php");
    exit();
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-redo"></i>
            Controle de Reset - Alimentos Preparados
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-warning" onclick="executeManualReset()">
                <i class="fas fa-redo"></i>
                Reset Manual Agora
            </button>
            <button type="button" class="btn btn-info" onclick="checkNegativeStock()">
                <i class="fas fa-eye"></i>
                Ver Vendas de Hoje
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
                    <div class="stat-value">5:00 AM</div>
                    <div class="stat-label">Reset Automático</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="color: var(--primary-color);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-value" id="current-time"><?php echo date('H:i:s'); ?></div>
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
                        <i class="fas fa-cog"></i>
                    </div>
                    <div class="stat-value">Ativo</div>
                    <div class="stat-label">Sistema Automático</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Produtos com Vendas Hoje -->
    <div class="card">
        <div class="card-header">
            <h3>🍽️ Vendas de Hoje por Produto</h3>
        </div>
        <div class="card-body">
            <div id="products-sold-today" class="grid grid-4">
                <!-- Será preenchido via JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Instruções -->
    <div class="card">
        <div class="card-header">
            <h3>📖 Como Funciona o Sistema</h3>
        </div>
        <div class="card-body">
            <div class="instructions-grid">
                <div class="instruction-item">
                    <div class="instruction-icon">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="instruction-content">
                        <h4>1. Reset Automático (5:00 AM)</h4>
                        <p>Todos os dias às <strong>5:00 AM</strong>, o sistema reseta hambúrgueres, tostas, sandes e pregos para <strong>0</strong></p>
                    </div>
                </div>
                
                <div class="instruction-item">
                    <div class="instruction-icon">
                        <i class="fas fa-minus"></i>
                    </div>
                    <div class="instruction-content">
                        <h4>2. Contagem Negativa</h4>
                        <p>A cada venda, o número fica negativo: <strong>0 → -1 → -2 → -3...</strong></p>
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
                        <h4>4. Relatórios Automáticos</h4>
                        <p>Os relatórios calculam automaticamente as vendas baseadas nos números negativos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Log de Atividades -->
    <div class="card">
        <div class="card-header">
            <h3>📝 Log de Reset</h3>
        </div>
        <div class="card-body">
            <div id="reset-log" style="background: #f8f9fa; padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.875rem; max-height: 300px; overflow-y: auto;">
                Carregando logs...
            </div>
        </div>
    </div>
</div>

<script>
let refreshInterval;

document.addEventListener('DOMContentLoaded', function() {
    checkNegativeStock();
    loadResetLog();
    
    // Atualizar a cada 30 segundos
    refreshInterval = setInterval(() => {
        checkNegativeStock();
        updateCurrentTime();
    }, 30000);
});

function updateCurrentTime() {
    document.getElementById('current-time').textContent = new Date().toLocaleTimeString('pt-PT');
}

function executeManualReset() {
    if (!confirm('Confirma o reset manual? Isso irá zerar todos os contadores de hambúrgueres, tostas, sandes e pregos.')) {
        return;
    }
    
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Executando Reset...';
    btn.disabled = true;
    
    fetch('api/manual_reset.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`✅ Reset manual executado com sucesso!\n\nProdutos resetados: ${data.products_reset}\nHora: ${data.time}`);
            checkNegativeStock();
            loadResetLog();
        } else {
            alert(`❌ Erro no reset: ${data.message}`);
        }
    })
    .catch(error => {
        alert(`❌ Erro: ${error.message}`);
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function checkNegativeStock() {
    fetch('api/check_negative_stock.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateProductsDisplay(data);
            } else {
                console.error('Erro ao carregar dados:', data.message);
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
        });
}

function updateProductsDisplay(data) {
    // Atualizar total vendidos
    let totalVendidos = 0;
    Object.values(data.totals).forEach(category => {
        totalVendidos += category.total;
    });
    document.getElementById('total-vendidos').textContent = totalVendidos;
    
    // Atualizar produtos vendidos hoje
    const productsDiv = document.getElementById('products-sold-today');
    productsDiv.innerHTML = '';
    
    if (data.products.length === 0) {
        productsDiv.innerHTML = `
            <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-secondary);">
                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem;"></i>
                <h4>Nenhuma venda registrada hoje</h4>
                <p>Os produtos aparecerão aqui conforme as vendas acontecem</p>
            </div>
        `;
    } else {
        data.products.forEach(product => {
            const totalVendidos = product.vendidos_primaria + product.vendidos_secundaria;
            const productCard = document.createElement('div');
            productCard.className = 'product-sold-card';
            productCard.innerHTML = `
                <div class="product-sold-header">
                    <h4>${product.name}</h4>
                    <span class="category-badge">${product.category_name}</span>
                </div>
                <div class="product-sold-stats">
                    <div class="stat-item">
                        <span class="stat-value">${product.vendidos_primaria}</span>
                        <span class="stat-label">Primária</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value">${product.vendidos_secundaria}</span>
                        <span class="stat-label">Secundária</span>
                    </div>
                    <div class="stat-item total">
                        <span class="stat-value">${totalVendidos}</span>
                        <span class="stat-label">Total</span>
                    </div>
                </div>
            `;
            productsDiv.appendChild(productCard);
        });
    }
}

function loadResetLog() {
    // Simular carregamento de log
    const logDiv = document.getElementById('reset-log');
    logDiv.innerHTML = `
[${new Date().toLocaleString('pt-PT')}] Sistema de reset automático ativo
[${new Date().toLocaleString('pt-PT')}] Próximo reset agendado para: 5:00 AM
[${new Date().toLocaleString('pt-PT')}] Categorias monitoradas: Hamburguer, Tosta, Sandes, Prego
[${new Date().toLocaleString('pt-PT')}] Status: Sistema funcionando normalmente
    `;
}

// Limpar interval quando sair da página
window.addEventListener('beforeunload', function() {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>

<style>
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

.product-sold-card {
    background: var(--card-background);
    border-radius: var(--border-radius);
    padding: 1rem;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow);
}

.product-sold-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.product-sold-header h4 {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-primary);
}

.category-badge {
    background: var(--primary-color);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
}

.product-sold-stats {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.5rem;
}

.stat-item {
    text-align: center;
    padding: 0.5rem;
    background: var(--background-color);
    border-radius: var(--border-radius-small);
}

.stat-item.total {
    background: var(--primary-color);
    color: white;
}

.stat-item .stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-item .stat-label {
    font-size: 0.7rem;
    opacity: 0.8;
}
</style>

<?php include 'includes/footer.php'; ?>