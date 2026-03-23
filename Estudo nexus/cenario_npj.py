import sqlite3
import random

professores = [
    {
        "id": 1,
        "nome": "João da Silva",
        "login": "joao.silva",
        "senha": "hash_senha_123",
        "matricula": "20231001",
        "cpf": "123.456.789-00",
        
        
    },
    {
        "id": 2,
        "nome": "Maria Oliveira",
        "login": "maria.prof",
        "senha": "hash_senha_456",
        "matricula": "20231002",
        "cpf": "234.567.890-11",
  
       
    },
    {
        "id": 3,
        "nome": "Carlos Roberto Souza",
        "login": "carlos.souza",
        "senha": "hash_senha_789",
        "matricula": "20231003",
        "cpf": "345.678.901-22",
  
        
    },
    {
        "id": 4,
        "nome": "Ana Clara Mendes",
        "login": "ana.mendes",
        "senha": "hash_senha_321",
        "matricula": "20231004",
        "cpf": "456.789.012-33",

        
    },
    {
        "id": 5,
        "nome": "Pedro Henrique Lima",
        "login": "pedro.lima",
        "senha": "hash_senha_654",
        "matricula": "20231005",
        "cpf": "567.890.123-44",

       
    }
]

casos_sigilosos = [
    {
        "id": 1,
        "titulo_identificador": "Caso 2024-001: Disputa de Vizinhança",
        "conteudo_sigiloso": "Relatório detalhado sobre a disputa de limites de propriedade entre as partes A e B, incluindo mediações anteriores.",
        "nome_cliente": "José Pereira",
        "data_abertura": "2024-03-15 10:00:00",
        "professor_id": 1  # Vinculado ao professor João da Silva
    },
    {
        "id": 2,
        "titulo_identificador": "Caso 2024-002: Questão de Consumidor",
        "conteudo_sigiloso": "Documentação referente a um produto defeituoso adquirido pelo cliente. Inclui notas fiscais e tentativas de contato com o fabricante.",
        "nome_cliente": "Fernanda Lima",
        "data_abertura": "2024-03-18 14:30:00",
        "professor_id": 2  # Vinculado à professora Maria Oliveira
    },
    {
        "id": 3,
        "titulo_identificador": "Caso 2024-003: Contrato de Aluguel",
        "conteudo_sigiloso": "Análise de cláusulas de um contrato de aluguel residencial, com foco em reajuste e responsabilidades de manutenção.",
        "nome_cliente": "Ricardo Alves",
        "data_abertura": "2024-03-20 09:15:00",
        "professor_id": 1  # Também vinculado ao professor João da Silva
    },
    {
        "id": 4,
        "titulo_identificador": "Caso 2024-004: Direito de Família",
        "conteudo_sigiloso": "Consulta sobre processo de guarda compartilhada, com detalhes sobre a situação familiar e o bem-estar dos menores.",
        "nome_cliente": "Sandra Gomes",
        "data_abertura": "2024-03-21 11:00:00",
        "professor_id": 4  # Vinculado à professora Ana Clara Mendes
    },
    {
        "id": 5,
        "titulo_identificador": "Caso 2024-005: Ação Trabalhista",
        "conteudo_sigiloso": "Cálculo de verbas rescisórias e horas extras não pagas referentes ao contrato de trabalho de 2020 a 2023.",
        "nome_cliente": "Roberto Dias",
        "data_abertura": "2024-03-22 09:30:00",
        "professor_id": 3
    },
    {
        "id": 6,
        "titulo_identificador": "Caso 2024-006: Inventário Extrajudicial",
        "conteudo_sigiloso": "Levantamento de bens e herdeiros para realização de inventário em cartório, incluindo esboço de partilha amigável.",
        "nome_cliente": "Cláudia Ribeiro",
        "data_abertura": "2024-03-23 14:15:00",
        "professor_id": 5
    }
]

ids_professores_utilizados = [1, 2, 3, 4, 5]

#criacao banco de dados
def cria_banco_de_dados():

    
    conn = sqlite3.connect('npj.db')
    cursor = conn.cursor()

    cursor.execute("DROP TABLE IF EXISTS professores")
    cursor.execute("DROP TABLE IF EXISTS dados_sigilosos")
    cursor.execute("DROP TABLE IF EXISTS admin")

    #Cria tabela dos professores
    cursor.execute(
        """
        CREATE TABLE IF NOT EXISTS professores (

            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            login TEXT NOT NULL,
            senha TEXT NOT NULL,
            matricula TEXT NOT NULL,
            cpf TEXT NOT NULL
        )
        """)

    #Cria tabela dos casos sigilosos
    cursor.execute(

        """
        CREATE TABLE IF NOT EXISTS dados_sigilosos (
        
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            titulo_identificador TEXT NOT NULL,
            conteudo_sigiloso TEXT NOT NULL,
            nome_cliente TEXT NOT NULL,
            data_abertura DATETIME NOT NULL,
            professor_id INT NOT NULL,
            FOREIGN KEY (professor_id) REFERENCES professores(id)
        )

        """
    )

    #Cria tabela do admin
    cursor.execute(

        """
        CREATE TABLE IF NOT EXISTS admin (
        
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            login TEXT NOT NULL,
            senha TEXT NOT NULL
        )

        """
    )

    #Insere elementos no banco - Professores
    for professor in professores:

    
        cursor.execute(

            """
            INSERT OR IGNORE into professores (id, nome, login, senha, matricula, cpf)
            VALUES (?,?,?,?,?,?)
            """ , (professor['id'],
                  professor['nome'], 
                  professor['login'], 
                  professor['senha'], 
                  professor['matricula'], 
                  professor['cpf'])

        )
        conn.commit()

    #Insere elementos no banco - Casos sigilosos
    for casos in casos_sigilosos:

        cursor.execute(

            """
            INSERT OR IGNORE into dados_sigilosos (id, titulo_identificador, conteudo_sigiloso, nome_cliente, data_abertura, professor_id)
            VALUES (?,?,?,?,?,?)
            """ , (casos['id'],
                  casos['titulo_identificador'], 
                  casos['conteudo_sigiloso'], 
                  casos['nome_cliente'], 
                  casos['data_abertura'], 
                  casos['professor_id'])

        )
        conn.commit()

    conn.close()
    print("Banco de dados criado com sucesso")

    return 

def despacha(metodo, rota, request):

    handler = rotas.get((metodo, rota))

    return handler(request)

def login_professor(request):

    login = input("Digite seu login: ")
    senha = input("Digite sua senha: ")
    matricula = input("Digite sua matricula: ")

    #ativa banco 
    conn = sqlite3.connect('npj')
    cursor = conn.cursor()

    #Procura o login
    cursor.execute(

        """
        SELECT login, senha, matricula from professores
        WHERE login = ? 

        """, (login, )

    )
    conta_selecionada = cursor.fetchone()
    print(conta_selecionada)

    if conta_selecionada == None:

        return ('404', 'Login Inexistente')
    
    if conta_selecionada[1] != senha:

        return ('407', 'Senha incorreta')
    
    if conta_selecionada[2] != matricula:

        return ('411', 'Matricula incorreta')
    
    conn.close()
    
    return ('200', 'Login feito com sucesso')

#cadastrar no banco a conta de um professor novo
def cadastro_login_professor(request):

    nome = input("Digite seu nome")
    login = input("Digite seu login")
    senha = input("Digite sua senha")
    matricula = input("Digite sua matricula")
    cpf = input("Digite seu cpf")
    id = random.randint(1, 100)
    
    while (id in ids_professores_utilizados):
        id = random.randint(1, 100)
    
    ids_professores_utilizados.append(id)

    #Chama banco de dados
    conn = sqlite3.connect('npj')
    cursor = conn.cursor()

    #Insere os dados do professor no banco
    cursor.execute(

        """
        INSERT or IGNORE into professores (id, nome, login, senha, matricula, cpf)
        VALUES (?,?,?,?,?,?)

        """, (id, nome, login, senha, matricula, cpf)
    )
    conn.commit()

    #Fecha banco de dados
    conn.close()

    return

#Caso ele não tenha login
def escolher_opcao_professor(request):

    print('Possui uma conta?')
    opcao = input('Digite N para criar uma conta ou S para ir a tela de login: ')

    while (opcao.upper() != 'S' or opcao.upper() != 'N'):

        if opcao.upper() == 'N':
            print('Indo para a tela de criar uma conta')
            return ('POST', '/cadastro_professores')

        elif opcao.upper() == 'S': 
            
            return ('210', 'Indo para a tela de login')
        
        else:

            print('Digite entre S ou N')
            opcao = input('Digite S para criar uma conta ou N para ir a tela de login: ')

    
    

rotas = {

    ('GET', '/login_professor') : login_professor,
    ('GET', '/opcao_login_professor'): escolher_opcao_professor,
    #('GET', '/login_administrador') : login_administrador, 
    #('POST', '/cadastro_professores') : cadastro_login_professor,
    #('POST', '/cadastro_administrador') : cadastro_login_administrador,
    #('POST', '/cadastro_caso') : cadastro_caso_sigiloso

}

# 0 - Desativa o sistema (sai)
# 1 - Login Professor
# 2 - Cadastro do professor

cria_banco_de_dados()

if __name__ == '__main__':

    #Cria requisicao - Entra no sistema
    requisicao_opcao_professor = despacha('GET', '/opcao_login_professor', {})
    while (requisicao_opcao_professor[0] == '210'):
        print(requisicao_opcao_professor[1])
        requisicao_login_professor = despacha('GET', '/login_professor', {})
        print(requisicao_login_professor)
        while (requisicao_login_professor[0] != '200'):

            #Problemas no login
            #1. Login inexistente - Criar um novo ou repetir o processo
            #2. Senha incorreta - Fazer de novo o login (ok)
            #3. Matricula incorreta - Fazer de novo o login (ok)

            if requisicao_login_professor[0] == '407': #Senha incorreta

                print(requisicao_login_professor[1])
                requisicao_login_professor = despacha('GET', '/login_professor', {})
                
            
            elif requisicao_login_professor[1] == '411': #Matricula incorreta

                print(requisicao_login_professor[1])
                requisicao_login_professor = despacha('GET', '/login_professor', {})
            
            else: #Login Inexistente 

                print(requisicao_login_professor[1])
                requisicao_opcao_professor = despacha('GET', '/opcao_login_professor', {})
                if requisicao_opcao_professor[0] == '210':
                    break
                    
                




            
    print(requisicao_login_professor[1])
    print('Ver casos sigilosos')

        


