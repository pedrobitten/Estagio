
# Dados em memória
clientes = [
    {'id': 1, 'nome': 'João Silva', 'email': 'joao@email.com', 'telefone': '11987654321'},
    {'id': 2, 'nome': 'Maria Santos', 'email': 'maria@email.com', 'telefone': '11987654322'},
    {'id': 3, 'nome': 'João Oliveira', 'email': 'joao.o@email.com', 'telefone': '11987654323'},
    {'id': 4, 'nome': 'Carlos João', 'email': 'carlos@email.com', 'telefone': '11987654324'},
    {'id': 5, 'nome': 'Ana Costa', 'email': 'ana@email.com', 'telefone': '11987654325'},
    {'id': 6, 'nome': 'Pedro Gomes', 'email': 'pedro@email.com', 'telefone': '11987654326'},
    {'id': 7, 'nome': 'João Ferreira', 'email': 'joao.f@email.com', 'telefone': '11987654327'},
    {'id': 8, 'nome': 'Lucia Silva', 'email': 'lucia@email.com', 'telefone': '11987654328'},
]

def buscar_clientes(request):
    """
    Busca clientes de forma segura com paginação.
    
    Args:
        request (dict): Dicionário com 'nome' e 'página'
    
    Returns:
        dict: Resultado com dados, total e informações de paginação
    """
    # Validação e extração de parâmetros
    nome = request.get('nome', '').strip()
    
    pagina = request.get('página', 1)
    
    # Validação de página
    try:
        pagina = max(1, int(pagina))
    except (ValueError, TypeError):
        pagina = 1
    
    # Paginação
    items_por_pagina = 3
    
    # Filtragem segura: busca case-insensitive por nome
    # Cláusula WHERE
    if nome:

        resultados = []

        for cliente in clientes:
            if nome.lower() in cliente['nome'].lower():
                resultados.append(cliente)
    else:
        resultados = clientes

    # Cálculo de paginação
    total_items = len(resultados)
    total_paginas = (total_items + items_por_pagina - 1) // items_por_pagina
    
    # Validação de página
    pagina = min(pagina, max(1, total_paginas))
    
    # Aplicar paginação
    inicio = (pagina - 1) * items_por_pagina
    fim = inicio + items_por_pagina
    dados_paginados = resultados[inicio:fim]
    
    return {
        'dados': dados_paginados,
        'total': total_items,
        'pagina': pagina,
        'total_paginas': total_paginas,
        'items_por_pagina': items_por_pagina
    }

# Testes
if __name__ == '__main__':

    
    # Teste 1: Buscar por nome específico
    print("=== Teste 1: Buscar clientes com 'João' ===")
    resultado = buscar_clientes({'nome': 'João', 'página': 1})
    print(f"Total encontrado: {resultado['total']}")
    print(f"Página {resultado['pagina']} de {resultado['total_paginas']}")
    for cliente in resultado['dados']:
        print(f"  - {cliente['nome']} ({cliente['email']})")
    
        
    # Teste 2: Segunda página
    print("\n=== Teste 2: Segunda página ===")
    resultado = buscar_clientes({'nome': 'João', 'página': 2})
    print(f"Página {resultado['pagina']} de {resultado['total_paginas']}")
    for cliente in resultado['dados']:
        print(f"  - {cliente['nome']} ({cliente['email']})")
    
    
    # Teste 3: Buscar todos (sem filtro)
    print("\n=== Teste 3: Todos os clientes (página 1) ===")
    resultado = buscar_clientes({'página': 1})
    print(f"Total: {resultado['total']} | Página {resultado['pagina']} de {resultado['total_paginas']}")
    for cliente in resultado['dados']:
        print(f"  - {cliente['nome']}")

#Explicação:
# 1. O código define uma função `buscar_clientes` que recebe um dicionário de requisição com parâmetros de busca e paginação.
# 2. A função valida os parâmetros, filtra os clientes com base no nome (busca case-insensitive) e aplica a paginação.
# 3. O código inclui testes para verificar a funcionalidade de busca e paginação, garantindo que os resultados sejam corretos e que a paginação funcione conforme esperado. 
# Basicamente, é para entender o que a função GET faz