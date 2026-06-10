# Refatoração de Sistema Web eCAEEF

Projeto desenvolvido com o objetivo de remodelar e dar uma nova aparencia ao sistrema eCAEEF.

A Clínica e Academia Escola de Educação Física (CAEEF), da UNICENTRO, oferece atendimento a pacientes do Sistema Único de Saúde (SUS) encaminhados para programas de exercícios físicos voltados ao tratamento e promoção da saúde, sendo conduzida por professores e alunos do Departamento de Educação Física (DEDUF). No contexto do programa de extensão Fábrica de Software, está em desenvolvimento o sistema eCAEEF, destinado ao gerenciamento integrado de dados de pacientes, professores e alunos, bem como ao controle de programas de treinamento, agenda, equipamentos, instalações e exportação de informações. 

A primeira parte de remodelar o banco de dados já foi finalizada, atualmente a refatoração está em andamento.

A aplicação utiliza uma arquitetura baseada em Laravel 13.

---

## Objetivos do Projeto

* Aplicar técnicas de refatoração em aplicações web.
* Melhorar a organização e a manutenção do código.
* Utilizar recursos modernos do ecossistema Laravel.

---

## Tecnologias Utilizadas

### Backend

* PHP 8.3
* Laravel 13

### Frontend

* Livewire 4
* Flux UI
* Tailwind CSS 4
* Blade Templates

### Ferramentas

* Vite
* Composer
* NPM
* Docker (Docker Compose)

### Autenticação

* Laravel Fortify

---

## Funcionalidades

### Autenticação

* Login de usuários
* Cadastro de usuários
* Recuperação de senha
* Gerenciamento de perfil

### Configurações

* Alteração de perfil
* Configurações de segurança
* Configurações de aparência

### Interface Moderna

* Componentes reutilizáveis
* Layout responsivo
* Navegação simplificada
* Estilização utilizando Tailwind CSS

---

## 📂 Estrutura do Projeto

```text
app/
├── Actions/
├── Concerns/
├── Http/
├── Livewire/
├── Models/
└── Providers/

resources/
├── views/
├── css/
└── js/

routes/
└── web.php
```

---
## Autor

Maurício Fabiano Azevedo Filho

Graduando em Ciência da Computação – UNICENTRO

* PHP
* Laravel
* Livewire
* Tailwind CSS
* Engenharia de Software
