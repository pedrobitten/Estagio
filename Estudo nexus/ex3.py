from flask import Flask, render_template


# Lista de produtos
produtos = [
    {"nome": "Notebook Dell", "preco": 3500.00, "quantidade_estoque": 15},
    {"nome": "Mouse Logitech", "preco": 120.50, "quantidade_estoque": 50},
    {"nome": "Teclado Mecânico", "preco": 250.00, "quantidade_estoque": 30},
    {"nome": "Monitor 24\"", "preco": 800.00, "quantidade_estoque": 20},
    {"nome": "HD Externo 1TB", "preco": 180.00, "quantidade_estoque": 40},
    {"nome": "Impressora Laser", "preco": 450.00, "quantidade_estoque": 10},
    {"nome": "Webcam HD", "preco": 95.00, "quantidade_estoque": 25},
    {"nome": "Fone de Ouvido", "preco": 75.00, "quantidade_estoque": 60},
]

app = Flask(__name__)

@app.route('/')
def home():
    return render_template('ex3.html', produtos=produtos)

if __name__ == "__main__":
    app.run(debug=True)

#Explicação:
# Entender como o código HTML funciona