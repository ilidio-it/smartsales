# 🍽️ SmartSales

Sistema de gestão de cantina escolar otimizado para tablets, desenvolvido em PHP, MySQL, HTML, CSS e JavaScript.

## 📋 Índice

- [Características](#características)
- [Requisitos do Sistema](#requisitos-do-sistema)
- [Instalação](#instalação)
- [Configuração da Base de Dados](#configuração-da-base-de-dados)
- [Configuração do Sistema](#configuração-do-sistema)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Funcionalidades](#funcionalidades)
- [Credenciais Padrão](#credenciais-padrão)
- [Resolução de Problemas](#resolução-de-problemas)
- [Suporte](#suporte)

## 🚀 Características

- **Interface Otimizada para Tablets**: Botões grandes e interface intuitiva
- **Sistema POS Completo**: Vendas rápidas e eficientes
- **Gestão de Stock**: Controle separado para cantina primária e secundária
- **Múltiplos Métodos de Pagamento**: Dinheiro, conta, vale, depósito e dívida
- **Sistema de Dívidas**: Controle automático de dívidas por cliente
- **Relatórios Detalhados**: Vendas, stock, clientes e dívidas
- **Multilíngue**: Português e Inglês
- **Controle de Permissões**: Admin, Manager e Employee
- **Auto-logout**: Segurança após 5 horas de inatividade
- **Moeda**: Metical Moçambicano (Mt)

## 💻 Requisitos do Sistema

### Servidor Web
- **Apache** 2.4+ ou **Nginx** 1.18+
- **PHP** 7.4+ (recomendado 8.0+)
- **MySQL** 5.7+ ou **MariaDB** 10.3+

### Extensões PHP Necessárias
- `pdo`
- `pdo_mysql`
- `json`
- `session`
- `mbstring`

### Navegadores Suportados
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## 📦 Instalação

### 1. Download do Projeto
```bash
git clone [repository-url]
cd smartsales
```

### 2. Configuração do Servidor Web

#### XAMPP (Windows/Mac/Linux)
1. Copie a pasta do projeto para `htdocs/smartsales`
2. Inicie Apache e MySQL no painel XAMPP
3. Acesse: `http://localhost/smartsales/`

#### WAMP (Windows)
1. Copie a pasta do projeto para `www/smartsales`
2. Inicie os serviços WAMP
3. Acesse: `http://localhost/smartsales/`

#### LAMP (Linux)
1. Copie para `/var/www/html/smartsales`
2. Configure permissões:
```bash
sudo chown -R www-data:www-data /var/www/html/smartsales/
sudo chmod -R 755 /var/www/html/smartsales/
```

## 🗄️ Configuração da Base de Dados

### 1. Criar Base de Dados
Acesse phpMyAdmin ou MySQL command line:

```sql
CREATE DATABASE school_canteen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Importar Estrutura
Execute o arquivo `setup_database.php` ou importe o SQL manualmente:

```sql
-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    user_type ENUM('admin', 'manager', 'employee') NOT NULL,
    canteen_type ENUM('primary', 'secondary', 'both') NOT NULL,
    permissions JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    selling_price DECIMAL(10,2) NOT NULL,
    buying_price DECIMAL(10,2) NOT NULL,
    stock_primary INT DEFAULT 0,
    stock_secondary INT DEFAULT 0,
    min_stock INT DEFAULT 5,
    photo VARCHAR(255),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Clients table
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('primary', 'secondary', 'teacher') NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    debt DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sales table
CREATE TABLE sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    user_id INT NOT NULL,
    canteen_type ENUM('primary', 'secondary') NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'account', 'voucher', 'deposit', 'debt') NOT NULL,
    status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
    sale_date DATE NOT NULL,
    sale_time TIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Sale items table
CREATE TABLE sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Client transactions table
CREATE TABLE client_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    type ENUM('payment', 'purchase', 'debt') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    balance_after DECIMAL(10,2) NOT NULL,
    debt_after DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
);

-- Stock movements table
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    canteen_type ENUM('primary', 'secondary') NOT NULL,
    type ENUM('in', 'out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(255),
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default admin user (password: admin)
INSERT INTO users (username, password, name, user_type, canteen_type, permissions) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 'both', '{"all": true}');

-- Insert sample products
INSERT INTO products (name, selling_price, buying_price, stock_primary, stock_secondary, photo) VALUES
('Sandwich', 25.00, 18.00, 50, 30, 'sandwich.jpg'),
('Sumo de Maçã', 15.00, 10.00, 40, 25, 'apple_juice.jpg'),
('Chocolate', 12.00, 8.00, 60, 35, 'chocolate.jpg'),
('Água', 8.00, 5.00, 80, 50, 'water.jpg'),
('Bolachas', 20.00, 14.00, 35, 20, 'cookies.jpg');

-- Insert sample clients
INSERT INTO clients (name, type, balance) VALUES
('João Silva', 'primary', 150.00),
('Maria Santos', 'secondary', 205.00),
('Ana Costa', 'teacher', 250.00),
('Pedro Oliveira', 'primary', 100.00),
('Sofia Fernandes', 'secondary', 187.50);
```

### 3. Configurar Conexão
Edite o arquivo `config/database.php`:

```php
<?php
class Database {
    private $host = 'localhost';        // Seu host MySQL
    private $db_name = 'school_canteen'; // Nome da base de dados
    private $username = 'root';          // Usuário MySQL
    private $password = '';              // Senha MySQL
    private $conn;
    
    // ... resto do código permanece igual
}
?>
```

## ⚙️ Configuração do Sistema

### 1. Permissões de Pastas
Certifique-se que as seguintes pastas têm permissões de escrita:
```bash
chmod 755 uploads/
chmod 755 assets/images/
```

### 2. Configurações PHP
No `php.ini`, certifique-se que:
```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

### 3. Teste de Conexão
Acesse: `http://localhost/smartsales/test_connection.php`

Se houver problemas, use: `fix_admin_password.php` ou `debug_login.php`

## 📁 Estrutura do Projeto

```
smartsales/
├── api/                          # APIs para AJAX
│   ├── complete_sale.php        # Finalizar vendas
│   └── search_clients.php       # Buscar clientes
├── assets/                      # Recursos estáticos
│   ├── css/
│   │   └── style.css           # Estilos principais
│   ├── js/
│   │   └── app.js              # JavaScript principal
│   └── images/                 # Imagens do sistema
├── config/                     # Configurações
│   ├── database.php           # Conexão com BD
│   └── session.php            # Gestão de sessões
├── includes/                   # Arquivos incluídos
│   ├── header.php             # Cabeçalho comum
│   ├── footer.php             # Rodapé comum
│   └── lang.php               # Sistema de idiomas
├── languages/                  # Traduções
│   ├── en.php                 # Inglês
│   └── pt.php                 # Português
├── uploads/                    # Uploads de arquivos
├── login.php                   # Página de login
├── logout.php                  # Logout
├── dashboard.php               # Painel principal
├── sales.php                   # Sistema POS
├── products.php                # Gestão de produtos
├── clients.php                 # Gestão de clientes
├── reports.php                 # Relatórios
├── employees.php               # Gestão de funcionários
├── test_connection.php         # Teste de conexão
├── fix_admin_password.php      # Corrigir senha admin
├── debug_login.php             # Debug do login
└── README.md                   # Este arquivo
```

## 🎯 Funcionalidades

### 🔐 Sistema de Login
- Autenticação segura com hash de senhas
- Controle de permissões por tipo de usuário
- Auto-logout após 5 horas de inatividade

### 🛒 Sistema POS (Point of Sale)
- Interface otimizada para tablets
- Carrinho de compras em tempo real
- 5 métodos de pagamento:
  - **Dinheiro**: Pagamento à vista
  - **Conta**: Débito do saldo do cliente
  - **Vale**: Pagamento com voucher
  - **Depósito**: Paga e retira depois
  - **Dívida**: Adiciona à dívida do cliente (automático)

### 📦 Gestão de Produtos
- Cadastro completo de produtos
- Controle de stock separado (Primária/Secundária)
- Alertas de stock baixo
- Cálculo automático de lucro
- Preços em Metical (Mt)

### 👥 Gestão de Clientes
- Cadastro de alunos e professores
- Controle de saldo e dívidas
- Sistema automático de dívidas
- Histórico de transações
- Busca rápida por nome
- Pagamento de dívidas

### 📊 Relatórios
- Vendas diárias, semanais e mensais
- Relatórios de stock
- Relatórios de dívidas
- Relatórios de clientes
- Histórico por funcionário
- Exportação em CSV

### 👨‍💼 Gestão de Funcionários
- Cadastro de funcionários
- Controle de permissões
- Tipos de usuário (Admin, Manager, Employee)
- Alteração de senhas
- Status de conta (Ativo/Inativo)

### 🌐 Multilíngue
- Português (padrão)
- Inglês
- Troca de idioma em tempo real

## 🔑 Credenciais Padrão

### Administrador
- **Username**: `admin`
- **Password**: `admin`
- **Permissões**: Acesso total ao sistema

### Tipos de Usuário
1. **Admin**: Acesso completo
2. **Manager**: Gestão de vendas e relatórios
3. **Employee**: Apenas vendas

### Tipos de Cantina
1. **Primary**: Apenas cantina primária
2. **Secondary**: Apenas cantina secundária
3. **Both**: Acesso a ambas as cantinas

## 💰 Sistema de Dívidas

### Como Funciona
1. **Venda a Dívida**: Cliente compra sem pagar
2. **Aumento Automático**: Dívida é adicionada ao total do cliente
3. **Controle de Saldo**: Sistema verifica saldo vs dívida
4. **Pagamento**: Funcionário pode registrar pagamentos
5. **Relatórios**: Acompanhamento de devedores

### Fluxo de Dívida
```
Cliente compra Mt 50 → Método: Dívida → Dívida atual: Mt 50
Cliente paga Mt 30 → Dívida restante: Mt 20
Cliente compra Mt 25 → Dívida total: Mt 45
```

## 🔧 Resolução de Problemas

### Problema: "Invalid credentials" no login
**Solução**:
1. Acesse: `fix_admin_password.php`
2. Execute o script para recriar a senha
3. Tente fazer login novamente

### Problema: "Connection error"
**Solução**:
1. Verifique se MySQL está rodando
2. Confirme as credenciais em `config/database.php`
3. Execute `test_connection.php` para diagnóstico

### Problema: Dívidas não funcionam
**Solução**:
1. Verifique se a tabela `client_transactions` existe
2. Confirme se o cliente está cadastrado
3. Teste com método de pagamento "Dívida"

### Problema: "Not Found" ao acessar páginas
**Solução**:
1. Certifique-se que está acessando via servidor web (localhost)
2. Não abra os arquivos diretamente no navegador
3. Use XAMPP, WAMP ou similar

### Problema: Erro de permissões
**Solução**:
```bash
# Linux/Mac
sudo chown -R www-data:www-data /caminho/para/projeto/
sudo chmod -R 755 /caminho/para/projeto/

# Windows (executar como administrador)
icacls "C:\caminho\para\projeto" /grant Everyone:F /T
```

## 📱 Uso em Tablets

### Configurações Recomendadas
- **Resolução mínima**: 1024x768
- **Navegador**: Chrome ou Safari
- **Orientação**: Landscape (horizontal)

### Otimizações para Touch
- Botões com mínimo 60px de altura
- Espaçamento adequado entre elementos
- Feedback visual em toques
- Scroll suave em listas longas

## 🔄 Atualizações e Manutenção

### Backup da Base de Dados
```bash
mysqldump -u root -p school_canteen > backup_$(date +%Y%m%d).sql
```

### Limpeza de Logs
Execute periodicamente para limpar dados antigos:
```sql
DELETE FROM stock_movements WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
DELETE FROM client_transactions WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

## 📞 Suporte

### Logs de Erro
Verifique os logs do Apache/PHP para erros:
- **Linux**: `/var/log/apache2/error.log`
- **XAMPP**: `xampp/apache/logs/error.log`
- **WAMP**: `wamp/logs/apache_error.log`

### Arquivos de Debug
- `test_connection.php`: Testa conexão com BD
- `debug_login.php`: Debug do sistema de login
- `fix_admin_password.php`: Corrige senha do admin

### Informações do Sistema
Para obter informações detalhadas do PHP:
```php
<?php phpinfo(); ?>
```

## 📄 Licença

Este projeto é desenvolvido para uso educacional e comercial em instituições de ensino.

---

**SmartSales - Sistema de Gestão de Cantina Inteligente**

Desenvolvido com ❤️ para facilitar a gestão de cantinas escolares em Moçambique.

Para suporte técnico, consulte os arquivos de debug incluídos no projeto.