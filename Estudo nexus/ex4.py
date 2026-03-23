import sqlite3
from datetime import datetime

def criar_banco_dados():
    """Cria o banco de dados SQLite com tabelas básicas"""

    # Conectar ao banco (cria o arquivo se não existir)
    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()

    # Criar tabela de clientes
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS clientes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            email TEXT NOT NULL,
            telefone TEXT,
            data_cadastro DATE DEFAULT CURRENT_DATE
        )
    ''')

    # Criar tabela de produtos
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS produtos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT UNIQUE NOT NULL,
            preco REAL NOT NULL,
            quantidade_estoque INTEGER DEFAULT 0,
            categoria TEXT,
            data_criacao DATE DEFAULT CURRENT_DATE
        )
    ''')

    # Criar tabela de pedidos
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS pedidos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER,
            data_pedido DATE DEFAULT CURRENT_DATE,
            status TEXT DEFAULT 'pendente',
            total REAL DEFAULT 0.0,
            FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        )
    ''')

    # Criar tabela de itens do pedido
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS itens_pedido (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pedido_id INTEGER,
            produto_id INTEGER,
            quantidade INTEGER NOT NULL,
            preco_unitario REAL NOT NULL,
            FOREIGN KEY (pedido_id) REFERENCES pedidos (id),
            FOREIGN KEY (produto_id) REFERENCES produtos (id)
        )
    ''')

    # Limpar dados anteriores (evita duplicação quando o script é executado várias vezes)
    cursor.execute('DELETE FROM itens_pedido')
    cursor.execute('DELETE FROM pedidos')
    cursor.execute('DELETE FROM produtos')
    cursor.execute('DELETE FROM clientes')

    # Resetar contadores AUTOINCREMENT (sqlite_sequence) para as tabelas usadas
    cursor.execute("DELETE FROM sqlite_sequence WHERE name IN ('clientes', 'produtos', 'pedidos', 'itens_pedido')")

    # Inserir dados de exemplo - Clientes
    clientes = [
        ('João Silva', 'joao@email.com', '11987654321'),
        ('Maria Santos', 'maria@email.com', '11987654322'),
        ('Pedro Oliveira', 'pedro@email.com', '11987654323'),
        ('Ana Costa', 'ana@email.com', '11987654324'),
    ]

    #cursor.executemany('INSERT OR IGNORE INTO clientes (nome, email, telefone) VALUES (?, ?, ?)', clientes)

    for cliente in clientes:

        cursor.execute('INSERT OR IGNORE INTO clientes (nome, email, telefone) VALUES (?, ?, ?)', cliente)

    # Inserir dados de exemplo - Produtos
    produtos = [
        ('Notebook Dell', 3500.00, 15, 'Eletrônicos'),
        ('Mouse Logitech', 120.50, 50, 'Acessórios'),
        ('Teclado Mecânico', 250.00, 30, 'Acessórios'),
        ('Monitor 24"', 800.00, 20, 'Eletrônicos'),
        ('HD Externo 1TB', 180.00, 40, 'Armazenamento'),
    ]

    for produto in produtos:

        cursor.execute('INSERT OR IGNORE INTO produtos (nome, preco, quantidade_estoque, categoria) VALUES (?, ?, ?, ?)', produto)

    # Salvar as mudanças
    conn.commit()
    conn.close()

    print("✅ Banco de dados criado com sucesso!")
    print("📁 Arquivo: loja.db")

def consultar_dados():
    """Exemplos de consultas no banco"""

    #Conecta com o banco de dados
    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()

    print("\n" + "="*50)
    print("📊 CONSULTAS NO BANCO DE DADOS")
    print("="*50)

    # 1. Listar todos os clientes
    print("\n👥 CLIENTES:")
    cursor.execute('SELECT id, nome, email FROM clientes')
    clientes = cursor.fetchall()
    for cliente in clientes:
        print(f"  {cliente[0]} - {cliente[1]} ({cliente[2]})")

    # 2. Listar produtos com estoque baixo (< 25)
    print("\n📦 PRODUTOS COM ESTOQUE BAIXO:")
    cursor.execute('SELECT nome, quantidade_estoque FROM produtos WHERE quantidade_estoque < 25' )
    produtos_baixo = cursor.fetchall()

    for produto in produtos_baixo:
        print(f"  {produto[0]} - Estoque: {produto[1]}")

    # 3. Produtos por categoria
    print("\n🏷️  PRODUTOS POR CATEGORIA:")
    cursor.execute('SELECT categoria, COUNT(*) as total FROM produtos GROUP BY categoria')
    categorias = cursor.fetchall()
    for categoria in categorias:
        print(f"  {categoria[0]}: {categoria[1]} produtos")

    # 4. Produto mais caro
    print("\n💰 PRODUTO MAIS CARO:")
    cursor.execute('SELECT nome, preco FROM produtos WHERE preco = (SELECT MAX(preco) FROM produtos)')
    produto_caro = cursor.fetchone()
    if produto_caro:
        print(f"  {produto_caro[0]} - R$ {produto_caro[1]:.2f}")

    conn.close()

def exemplo_pedido():
    """Exemplo de como inserir um pedido"""

    conn = sqlite3.connect('loja.db')
    cursor = conn.cursor()

    print("\n" + "="*50)
    print("🛒 CRIANDO UM PEDIDO DE EXEMPLO")
    print("="*50)

    # Criar um pedido para o cliente João Silva
    cursor.execute('SELECT id FROM clientes WHERE nome = "João Silva"')
    cliente_id = cursor.fetchone()[0] # Obtém o ID do cliente

    # Inserir pedido
    cursor.execute('''
        INSERT OR IGNORE INTO pedidos (cliente_id, status, total)
        VALUES (?, 'confirmado', 3620.50)
    ''', (cliente_id,))

   

    pedido_id = cursor.lastrowid # Obtém o ID do pedido recém-criado
    print(f"✅ Pedido criado - ID: {pedido_id}")

    # Adicionar itens ao pedido
    itens = [
        (pedido_id, 1, 1, 3500.00),  # 1 Notebook Dell
        (pedido_id, 2, 1, 120.50),   # 1 Mouse Logitech
    ]

    for item in itens:
        cursor.execute("INSERT INTO itens_pedido (pedido_id, produto_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)", item)

    print("✅ Itens adicionados ao pedido")

    # Atualizar estoque dos produtos
    cursor.execute('UPDATE produtos SET quantidade_estoque = quantidade_estoque - 1 WHERE id = 1')  # Notebook
    cursor.execute('UPDATE produtos SET quantidade_estoque = quantidade_estoque - 1 WHERE id = 2')  # Mouse

    print("✅ Estoque atualizado")

    conn.commit()
    conn.close()

if __name__ == "__main__":
    # Criar banco e tabelas
    criar_banco_dados()

    # Mostrar consultas
    consultar_dados()

    #Criar um pedido de exemplo
    exemplo_pedido()

    # Mostrar dados atualizados
    #consultar_dados()

    """
    print("\n" + "="*50)
    print("🎉 BANCO DE DADOS PRONTO!")
    print("📁 Arquivo: loja.db")
    print("💡 Use ferramentas como DB Browser for SQLite para visualizar")
    print("="*50)
    """