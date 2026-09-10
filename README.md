# 🍱 Agenda-Almoço
Sistema conteinerizado de agendamento e controle de fluxo para pausas de almoço em ambientes corporativos.
---
## 📌 Sobre o Projeto
O **Agenda-Almoço** é uma solução web desenvolvida para otimizar e organizar os horários de refeição de colaboradores. O sistema divide a jornada de almoço em blocos de 15 minutos (com capacidade configurável por bloco), permitindo que os funcionários reservem seus horários e a administração gerencie a grade com total controle e segurança.
---
## 🌟 Principais Recursos
- **Controle Dinâmico de Vagas:** Limite automatizado de colaboradores por intervalo de horário.
- **Autenticação 2FA Mandatória:** Obrigatoriedade de escaneamento de QR Code via aplicativo autenticador (TOTP) no primeiro acesso do colaborador.
- **Gerador de QR Code 100% Local:** Processamento do QR Code diretamente no navegador, garantindo que nenhuma chave de segurança trafegue para a internet pública.
- **Painel Administrativo Completo:** Gestão de usuários/permissões, adição/edição de horários, bloqueio global temporário da agenda e exportação de relatórios (CSV UTF-8).
- **Proteção de Auto-Rebaixamento:** Administradores não podem remover suas próprias permissões de admin, evitando o bloqueio acidental do sistema.
- **Restrição de Rede:** Configuração de servidor web para restringir o acesso ao ambiente interno (retorna HTTP 403 Forbidden para acessos externos).
---
## 🛠️ Tecnologias Utilizadas
| Camada | Tecnologia |
|---|---|
| **Back-end** | PHP (Arquitetura em Camadas: MVC + Service + Repository + Security) |
| **Roteamento** | bramus/router |
| **Banco de Dados** | PostgreSQL com PDO |
| **Conteinerização** | Docker & Docker Compose |
| **Servidor Web** | Apache com mod_rewrite |
| **Autenticação** | JWT, 2FA (TOTP), hash seguro de senhas, criptografia de chaves |
| **Front-end** | HTML5, CSS3, JavaScript ES6 (Vanilla JS) |
| **Variáveis de Ambiente** | Gestão via arquivo `.env` |
---
## 📂 Estrutura de Arquivos e Pastas
```text
Agenda-Almoco/
├── api/                        # ⚙️ BACKEND (Código PHP)
│   ├── Config/                 # Conexão PDO e leitor de .env
│   ├── Controllers/            # Controladores REST da API
│   ├── Repositories/           # Camada de Acesso ao Banco de Dados
│   ├── Security/               # Módulos de Autenticação e Criptografia
│   ├── Services/               # Regras de Negócio e Validações
│   └── index.php               # Roteador Principal da API
│
├── database/                   # 🗄️ BANCO DE DADOS (Scripts SQL)
│   └── schema.sql              # DDL oficial do banco de dados
│
├── public/                     # 🌐 FRONTEND (Interface Web)
│   ├── index.html              # Tela Principal da Agenda
│   ├── admin.html              # Painel Administrativo
│   ├── css/                    # Folhas de Estilo
│   └── js/                     # Scripts JavaScript
│
├── .gitignore                  # Arquivos ignorados pelo Git
├── .htaccess                   # Regras do Servidor Web
├── Dockerfile                  # Receita da imagem Docker
├── docker-compose.yml          # Orquestrador de Containers
├── composer.json               # Gerenciador de Dependências PHP
└── README.md                   # Documentação do Projeto