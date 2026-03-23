import sqlite3
from flask import Flask, render_template

#Criação do banco de dados
def criacao_banco_de_dados():

    conn = sqlite3.connect('exemplo.db')
    cursor = conn.cursor()

    #Remove tabelas anteriores
    cursor.execute(

        """
        DROP TABLE IF EXISTS exemplo;
        """
    )

    #Criacao da tabela
    cursor.execute(

        """
        CREATE TABLE exemplo (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            nome TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE, 
            senha TEXT NOT NULL,
            idade INTEGER NOT NULL)

        """

    )


    cursor.execute(

        """
        INSERT OR IGNORE INTO exemplo (nome, email, senha, idade) values
        ('ALICE', 'alice@gmail.com', '123456', 25)
        
        """
    )

    cursor.execute(

        """
        INSERT OR IGNORE INTO exemplo (nome, email, senha, idade) values
        ('Bob', 'bob@gmail.com', '67890', 30)
        
        """
    )

    conn.commit()
    conn.close()
    print("Banco de dados criado e populado com sucesso!")
    return 


#Primeira função: entrada e validação
def entrada(request):

    email = request.get('email')
    senha = request.get('senha')

    #Acesso ao banco
    conn = sqlite3.connect('exemplo.db')
    cursor = conn.cursor()

    cursor.execute(
        """
        SELECT email from exemplo
        WHERE email = ?  


        """, (email, )
    )
    email_recebido = cursor.fetchone()

    if email_recebido == None:

        return ('400', 'Email não cadastrado')
    
    cursor.execute(
        """
        SELECT senha from exemplo
        WHERE email = ?  
        """, (email, )
    )
    senha_recebida = cursor.fetchone()

    if senha_recebida == None or senha_recebida[0] != senha:
        return ('400', 'Senha incorreta')

    conn.close()

    return ('200', 'Login bem-sucedido') 

def acesso_dados(request):

    #Login
    email = request.get('email')

    #Acesso ao bando 
    conn = sqlite3.connect('exemplo.db')
    cursor = conn.cursor()

    cursor.execute(

        """
        SELECT nome, email, idade from exemplo
        WHERE email = ?


        """, (email, )

    )
    dados_usuario = cursor.fetchone()
    conn.close()
    

    return ('200', {'Leitura de dados bem-sucedida' : dados_usuario})


#Função para rotas
def despachar_requisicao(metodo, rota, request):

    handler = rotas.get((metodo, rota))
    return handler(request) if handler else ('404', 'Erro encontrado')

#Rotas

rotas = {

    ('GET', '/usuario') : entrada,
    #('GET', '/consulta') : consulta_dados,
    ('GET', '/informacoes') : acesso_dados

}

app = Flask(__name__)
@app.route('/')
def home():
    
    return render_template('ex7.html', dados=acesso[1]['Leitura de dados bem-sucedida'])

if __name__ == "__main__":

    

    #Criacao do banco 
    criacao_banco_de_dados()

    #Simulação de requisição
    requisicao = {
        'email': 'alice@gmail.com', 
        'senha': '123456'
    }

    resposta = despachar_requisicao('GET', '/usuario', requisicao)
    print(resposta[1])
    if resposta[0] == '200':
        print("Acesso concedido!")
        acesso = despachar_requisicao('GET', '/informacoes', requisicao)
        print(acesso[1]['Leitura de dados bem-sucedida'])
        app.run(debug=True)
        


    
