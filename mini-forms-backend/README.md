# Mini Google Forms Backend MVP (PHP)

Este é um backend MVP desenvolvido em PHP puro (sem frameworks pesados) para um sistema de formulários estilo Google Forms.

## 🚀 Tecnologias
- **PHP 8.1+**
- **MySQL**
- **Composer** (para JWT e Dotenv)
- **JWT** (Autenticação)
- **XAMPP** (Ambiente recomendado)

## 📂 Estrutura do Projeto
- `app/`: Lógica da aplicação (Controllers, Models, Middleware, Core).
- `config/`: Configurações globais.
- `public/`: Ponto de entrada (index.php) e ficheiros públicos.
- `database/`: Scripts SQL e migrações.
- `uploads/`: Armazenamento de ficheiros enviados.

## 🛠️ Instalação (XAMPP)

1.  **Clonar/Copiar o projeto** para a pasta `htdocs` do seu XAMPP:
    `C:\xampp\htdocs\mini-forms-backend`

2.  **Instalar dependências**:
    Abra o terminal na pasta do projeto e execute:
    ```bash
    composer install
    ```

3.  **Configurar Base de Dados**:
    - Crie uma base de dados chamada `mini_forms` no phpMyAdmin.
    - Importe o ficheiro `database/schema.sql`.

4.  **Configurar Variáveis de Ambiente**:
    - Edite o ficheiro `.env` com as suas credenciais do MySQL.
    - Defina uma `JWT_SECRET` segura.

5.  **Configurar o Apache**:
    - Certifique-se de que o módulo `mod_rewrite` está ativo.
    - O projeto está configurado para lidar com o subdiretório `/mini-forms-backend/public/`.

## 🔐 Autenticação
A API utiliza **JSON Web Tokens (JWT)**.
1. Faça login em `POST /api/login` para receber o token.
2. Envie o token no header de todas as requisições protegidas:
   `Authorization: Bearer <seu_token>`

## 📡 Endpoints Principais

### Públicos
- `GET /api/public/forms/{slug}`: Obtém a estrutura de um formulário.
- `POST /api/public/forms/{slug}/responses`: Submete respostas.

### Admin (Protegidos)
- `POST /api/login`: Autenticação.
- `GET /api/admin/forms`: Lista formulários do admin.
- `POST /api/admin/forms`: Cria um novo formulário.

## ⚠️ Tratamento de Erros
A API retorna erros padronizados em JSON:
- `400 Bad Request`: Dados inválidos ou em falta.
- `401 Unauthorized`: Token ausente ou inválido.
- `404 Not Found`: Recurso não encontrado.
- `500 Internal Server Error`: Erro inesperado no servidor.
