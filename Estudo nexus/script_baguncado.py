import sqlite3
import sys

# Conectar ao banco de dados
conn = sqlite3.connect('example.db')
cursor = conn.cursor()

# Criar tabela se não existir
cursor.execute('''CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    name TEXT,
    age INTEGER
)''')

# Inserir dados de exemplo
cursor.execute("INSERT INTO users (name, age) VALUES ('Alice', 30)")
cursor.execute("INSERT INTO users (name, age) VALUES ('Bob', 25)")
conn.commit()

# Ler entrada do usuário
print("Digite o nome para buscar:")
name = input()

# Consulta SQL
query = f"SELECT * FROM users WHERE name LIKE '%{name}%'"
cursor.execute(query)
results = cursor.fetchall()

# Gerar HTML bagunçado
html = "<html><head><title>Resultados</title></head><body>"
html += "<h1>Resultados da busca</h1>"
html += "<table border='1'>"
html += "<tr><th>ID</th><th>Nome</th><th>Idade</th></tr>"

for row in results:
    html += f"<tr><td>{row[0]}</td><td>{row[1]}</td><td>{row[2]}</td></tr>"

html += "</table>"

# Mais bagunça: imprimir HTML no console e salvar em arquivo
print(html)
with open('output.html', 'w') as f:
    f.write(html)

# Fechar conexão
conn.close()

# Mais bagunça: misturar com entrada novamente
print("Digite algo mais:")
extra = input()
print(f"Você digitou: {extra}")

# E ainda mais: tentar executar HTML como código? Não, isso seria loucura, mas bagunçado
# Talvez imprimir o HTML de volta como se fosse código
print("Executando HTML como código? Não, apenas imprimindo novamente:")
print(html)
