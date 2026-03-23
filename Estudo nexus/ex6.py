import sqlite3

def criacao_banco_de_dados():

    #conexao com o banco de dados
    conn = sqlite3.connect("sistema.db")
    cursor = conn.cursor()

    # Força a recriação da tabela para garantir que o esquema esteja atualizado
    cursor.execute('DROP TABLE IF EXISTS usuarios')

    #Criação da tabela de usuários
    cursor.execute('''
                    CREATE TABLE IF NOT EXISTS usuarios (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        nome TEXT NOT NULL, 
                        email TEXT NOT NULL UNIQUE, 
                        senha TEXT NOT NULL,
                        logado BOOLEAN NOT NULL DEFAULT FALSE,
                        ultimo_acesso DATETIME,
                        permissao TEXT NOT NULL, 
                        token TEXT NOT NULL, 
                        nivel_acesso INTEGER NOT NULL
                    )  
    ''')
 
    # ==================== EXEMPLOS DE DICIONÁRIO SESSION ====================

    # Exemplo 1: Session de usuário administrativo
    session_admin = {
        'usuario_id': 1,
        'usuario_nome': 'João Silva',
        'usuario_email': 'joao@email.com',
        'senha': 'senhaAdmin!23',
        'logado': False,
        'ultimo_acesso': '2024-01-15 10:30:00',
        'permissao': 'admin',
        'token': 'abc123def456',
        'nivel_acesso': 10
    }

    # Exemplo 2: Session de usuário comum
    session_usuario_comum = {
        'usuario_id': 5,
        'usuario_nome': 'Maria Santos',
        'usuario_email': 'maria@email.com',
        'senha': 'maria123',
        'logado': False,
        'ultimo_acesso': '2024-01-15 14:20:00',
        'permissao': 'user',
        'token': 'xyz789abc123',
        'nivel_acesso': 1
    }

    
    # Exemplo 4: Session de moderador
    session_moderador = {
        'usuario_id': 3,
        'usuario_nome': 'Pedro Oliveira',
        'usuario_email': 'pedro@email.com',
        'senha': 'mod789',
        'logado': False,
        'ultimo_acesso': '2024-01-15 09:15:00',
        'permissao': 'moderador',
        'token': 'mod456token789',
        'nivel_acesso': 5
    }

    # Exemplo 5: Session de cliente
    session_cliente = {
        'usuario_id': 7,
        'usuario_nome': 'Ana Costa',
        'usuario_email': 'ana@email.com',
        'senha': 'ana12345',
        'logado': False,
        'ultimo_acesso': '2024-01-15 11:45:00',
        'permissao': 'cliente',
        'token': 'cliente001token',
        'nivel_acesso': 2
    }

    # Exemplo 6: Session de usuário editor
    session_editor = {
        'usuario_id': 2,
        'usuario_nome': 'Lucas Ferreira',
        'usuario_email': 'lucas@email.com',
        'senha': 'editor456',
        'logado': False,
        'ultimo_acesso': '2024-01-15 13:20:00',
        'permissao': 'editor',
        'token': 'user002token',
        'nivel_acesso': 7
    }

    

    # Exemplo 8: Session de usuário gerente
    session_gerente = {
        'usuario_id': 4,
        'usuario_nome': 'Carlos Silva',
        'usuario_email': 'carlos@email.com',
        'senha': 'gerente789',
        'logado': False,
        'ultimo_acesso': '2024-01-15 15:30:00',
        'permissao': 'gerente',
        'token': 'editor_token_456',
        'nivel_acesso': 8
    }

    #Popular o banco de dados com as sessões de exemplo
    sessions = [
        session_admin,
        session_usuario_comum,
        session_moderador,
        session_cliente,
        session_editor,
        session_gerente
    ]

    for session in sessions:
        # Garantir que os valores sejam passados como uma sequência suportada pelo sqlite3
        valores = (
            session['usuario_id'],
            session['usuario_nome'],
            session['usuario_email'],
            session['senha'],
            session['logado'],
            session['ultimo_acesso'],
            session['permissao'],
            session['token'],
            session['nivel_acesso'],
        )

        cursor.execute('''
                        INSERT or IGNORE INTO usuarios (id, nome, email, senha, logado, ultimo_acesso, permissao, token, nivel_acesso)
                        VALUES (?,?,?,?,?,?,?,?,?)
        ''', valores)

    # Desativo o banco
    conn.commit()
    conn.close()

    print("✅ Banco de dados criado e populado com sessões de exemplo!")

    return


def login(request):
    
    """Simula o processo de login de um usuário"""
    print("🔐 Login - Processando requisição...")
    # Simulação de autenticação

    #Conecta com o banco de dados
    conn = sqlite3.connect("sistema.db")
    cursor = conn.cursor()

    #Pega email e senha do formulário
    email = request.get('email')
    senha = request.get('senha')

    #Consulta o usuário no banco de dados
    cursor.execute('SELECT email from usuarios WHERE email = ?', (email,))
    usuario = cursor.fetchone()

    if usuario == None:

        return ('401', 'Email inválido')
    
    cursor.execute('SELECT senha from usuarios WHERE email = ?', (email, ))
    senha_armazenada = cursor.fetchone()

    if senha_armazenada is None or senha_armazenada[0] != senha:
        return ('401', 'Senha incorreta')

    # Atualiza o status de login do usuário - Esta conectado
    cursor.execute('UPDATE usuarios SET logado = TRUE WHERE email = ?', (email,))
    conn.commit()  # Adiciona commit para salvar as mudanças no banco

    # Fecha a conexão
    conn.close()
    

    return ('200', 'Login bem-sucedido')

#Mostrar que deu erro
def consulta_dados(request):

    conn = sqlite3.connect('sistema.db')
    cursor = conn.cursor()

    cursor.execute('SELECT id, nome, email, senha from usuarios')

    dados = cursor.fetchall()
    #for dado in dados:
    #    print(f"ID: {dado[0]}, Nome: {dado[1]}, Email: {dado[2]}, senha: {dado[3]}")

    conn.close()
    print(dados)

    return ('200', {'dados_encontrados' : dados})

def logout(request):

    conn = sqlite3.connect('sistema.db')
    cursor = conn.cursor()

    email = request.get('email')

    cursor.execute('UPDATE usuarios SET logado = FALSE WHERE email = ?', (email,))

    conn.commit()
    conn.close()

    return ('200', 'Logout bem-sucedido')


def despachar_requisicao(metodo, rota, request):

    handler = rotas.get((metodo, rota))
    return handler(request) if handler else ('404', 'Rota não encontrada')


rotas = {
    ('GET', '/usuarios'): login,
    ('GET', '/consulta'): consulta_dados,
    ('POST', '/usuarios'): logout,
}

if __name__ == "__main__":
    
    criacao_banco_de_dados()

    #Testando as rotas
    
    #Rota de consulta : consulta
    resultado = despachar_requisicao('GET', '/consulta', {})
    if resultado[0] == '200':
        for dado in resultado[1]['dados_encontrados']:
            print(f"ID: {dado[0]}, Nome: {dado[1]}, Email: {dado[2]}, senha: {dado[3]}")
    else:
        print(resultado[1])

    #Rota de consulta : login usuario
    resultado = despachar_requisicao('GET', '/usuarios', {'email' : 'joao@email.com', 'senha' : 'senhaAdmin!23'})
    if resultado[0] == '200':
        print(resultado[1])

    resultado = despachar_requisicao('GET', '/usuarios', {'email' : 'pedropbittencourt@gmail.com', 'senha': '43434'})
    print(resultado[1])

    resultado = despachar_requisicao('GET', '/usuarios', {'email' : 'joao@email.com', 'senha' : 'senhaAdminw!23'})
    print(resultado[1])

    #Rota de consulta : logout usuario
    resultado = despachar_requisicao('POST', '/usuarios', {'email' : 'joao@email.com', 'senha' : 'senhaAdmin!23'})
    print(resultado[1])