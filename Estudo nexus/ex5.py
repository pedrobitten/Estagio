import sqlite3

# Handlers para clientes
def listar_clientes():
    """Lista todos os clientes do banco de dados"""
    try:
        conn = sqlite3.connect('loja.db')
        cursor = conn.cursor()
        cursor.execute('SELECT id, nome, email, telefone FROM clientes')
        clientes = cursor.fetchall()
        conn.close()

        # Formatar como lista de dicionários
        resultado = []
        for cliente in clientes:
            resultado.append({
                'id': cliente[0],
                'nome': cliente[1],
                'email': cliente[2],
                'telefone': cliente[3]
            })

        return ('200', {'clientes': resultado})

    except Exception as e:
        return ('500', f'Erro interno: {str(e)}')

def criar_cliente(request):
    """Cria um novo cliente no banco de dados"""
    try:
        # Extrair dados da requisição
        nome = request.get('nome')
        email = request.get('email')
        telefone = request.get('telefone')

        # Validação básica
        if not nome or not email:
            return ('400', 'Nome e email são obrigatórios')

        conn = sqlite3.connect('loja.db')
        cursor = conn.cursor()

        # Verificar se email já existe
        cursor.execute('SELECT id FROM clientes WHERE email = ?', (email,))
        if cursor.fetchone():
            conn.close()
            return ('409', 'Email já cadastrado')

        # Inserir novo cliente
        cursor.execute('''
            INSERT INTO clientes (nome, email, telefone)
            VALUES (?, ?, ?)
        ''', (nome, email, telefone))

        cliente_id = cursor.lastrowid
        conn.commit()
        conn.close()

        return ('201', {'id': cliente_id, 'mensagem': 'Cliente criado com sucesso'})

    except Exception as e:
        return ('500', f'Erro interno: {str(e)}')

# Handlers para produtos
def listar_produtos(request):
    """Lista todos os produtos do banco de dados"""
    try:
        conn = sqlite3.connect('loja.db')
        cursor = conn.cursor()
        cursor.execute('SELECT id, nome, preco, quantidade_estoque, categoria FROM produtos')
        produtos = cursor.fetchall()
        conn.close()

        # Formatar como lista de dicionários
        resultado = []
        for produto in produtos:
            resultado.append({
                'id': produto[0],
                'nome': produto[1],
                'preco': produto[2],
                'quantidade_estoque': produto[3],
                'categoria': produto[4]
            })

        return ('200', {'produtos': resultado})

    except Exception as e:
        return ('500', f'Erro interno: {str(e)}')

def criar_produto(request):
    """Cria um novo produto no banco de dados"""
    try:
        # Extrair dados da requisição
        nome = request.get('nome')
        preco = request.get('preco')
        quantidade_estoque = request.get('quantidade_estoque', 0)
        categoria = request.get('categoria')

        # Validação básica
        if not nome or not preco:
            return ('400', 'Nome e preço são obrigatórios')

        conn = sqlite3.connect('loja.db')
        cursor = conn.cursor()

        # Verificar se produto já existe
        cursor.execute('SELECT id FROM produtos WHERE nome = ?', (nome,))
        if cursor.fetchone():
            conn.close()
            return ('409', 'Produto já cadastrado')

        # Inserir novo produto
        cursor.execute('''
            INSERT INTO produtos (nome, preco, quantidade_estoque, categoria)
            VALUES (?, ?, ?, ?)
        ''', (nome, preco, quantidade_estoque, categoria))

        produto_id = cursor.lastrowid
        conn.commit()
        conn.close()

        return ('201', {'id': produto_id, 'mensagem': 'Produto criado com sucesso'})

    except Exception as e:
        return ('500', f'Erro interno: {str(e)}')

# Handler para pedidos
def listar_pedidos(request):
    """Lista todos os pedidos do banco de dados"""
    try:
        conn = sqlite3.connect('loja.db')
        cursor = conn.cursor()
        cursor.execute('''
            SELECT p.id, c.nome, p.data_pedido, p.status, p.total
            FROM pedidos p
            JOIN clientes c ON p.cliente_id = c.id
            ORDER BY p.data_pedido DESC
        ''')
        pedidos = cursor.fetchall()
        conn.close()

        # Formatar como lista de dicionários
        resultado = []
        for pedido in pedidos:
            resultado.append({
                'id': pedido[0],
                'cliente_nome': pedido[1],
                'data_pedido': pedido[2],
                'status': pedido[3],
                'total': pedido[4]
            })

        return ('200', {'pedidos': resultado})

    except Exception as e:
        return ('500', f'Erro interno: {str(e)}')
    
# Dicionário de rotas: mapeia (método, caminho) para função handler
rotas = {
    ('GET', '/clientes'): listar_clientes,
    ('POST', '/clientes'): criar_cliente,
    ('GET', '/produtos'): listar_produtos,
    ('POST', '/prod utos'): criar_produto,
    ('GET', '/pedidos'): listar_pedidos,
}

def despachar(method, path, request):
    """Despacha a requisição para o handler correto baseado no método e caminho"""
    handler = rotas.get((method, path))
    return handler(request) if handler else ('404', 'não encontrado')

# Exemplo de uso
if __name__ == "__main__":
    # Testar as rotas
    print("=== Testando rotas ===")

    # Teste 1: Listar clientes (simulado)
    print("\n1. GET /clientes:")
    resultado = despachar('GET', '/clientes', {})
    print(f"Status: {resultado[0]}")
    if resultado[0] == '200':
        print(f"Clientes encontrados: {len(resultado[1]['clientes'])}")

    # Teste 2: Criar cliente (simulado)
    print("\n2. POST /clientes:")
    novo_cliente = {'nome': 'Carlos Silva', 'email': 'carlos@email.com', 'telefone': '11999999999'}
    resultado = despachar('POST', '/clientes', novo_cliente)
    print(f"Status: {resultado[0]}")
    if resultado[0] == '201':
        print(f"Cliente criado com ID: {resultado[1]['id']}")

    # Teste 3: Rota inexistente
    print("\n3. GET /rota-inexistente:")
    resultado = despachar('GET', '/rota-inexistente', {})
    print(f"Status: {resultado[0]} - {resultado[1]}")


#Explicação:
# O código define uma estrutura básica de um sistema de gerenciamento de loja, com handlers para clientes
# e produtos, além de um handler para listar pedidos. Ele inclui uma função de despacho que mapeia métodos HTTP e caminhos para funções específicas, permitindo simular requisições e testar a funcionalidade dos handlers. O código também inclui testes para verificar se as rotas estão funcionando corretamente, simulando a criação de um cliente e listando os clientes existentes.
# Basicamente, é para entender o que a função POST faz, e como o código é organizado para lidar com diferentes tipos de requisições.