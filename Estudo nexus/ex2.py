usuarios = [
    {"nome": "João Silva", "email": "joao.silva@email.com", "idade": 28},
    {"nome": "Maria Santos", "email": "maria.santos@email.com", "idade": 34},
    {"nome": "Pedro Oliveira", "email": "pedro.oliveira@email.com", "idade": 22},
    {"nome": "Ana Costa", "email": "ana.costa@email.com", "idade": 41},
    {"nome": "Lucas Ferreira", "email": "lucas.ferreira@email.com", "idade": 19},
]

def salvar_usuario(form):

    nome_usuario = form.get('nome_usuario')
    email = form.get('email')
    idade = form.get('idade')
    erros = {}

    #Clausula WHERE
    if nome_usuario == "":

        erros['nome_usuario'] = "Nome de usuário é obrigatório"
    

    if email == "":

        erros['email'] = "Email é obrigatório"
    
    elif '@' not in email:

        erros['email'] = "Email inválido"

    if idade == "":

        erros['idade'] = "Idade é obrigatória"

    return  {'ok': len(erros) == 0, 'erros': erros}

if __name__ == "__main__":
    
    #Teste 1: Formulário válido
    print("=== Teste 1: Formulário válido ===")
    form = {'nome_usuario': '', 'email': 'carlosemail.com', 'idade': 30}
    erros = salvar_usuario(form)
    if erros['ok'] == True:
        print("Usuário salvo com sucesso!")
    else: 
        print("Erros:", ', '.join(erros['erros'].values()))

#Expliacao:
# Mostrar que a função, após fazer o método GET, consegue validar os dados do formulário, identificando erros como campos vazios ou email inválido, e retorna um dicionário indicando se a operação foi bem-sucedida e quais foram os erros encontrados. 