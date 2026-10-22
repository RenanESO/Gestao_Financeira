# MoneyControl - Controle Financeiro Pessoal

Projeto Laravel 12 com **MySQL 8.0+ (InnoDB)** como banco principal.
O MySQL local identificado no Wamp e a versao 8.0.31, com `pdo_mysql` habilitado.

## Documentacao do Projeto

- [Visao geral e instalacao](docs/README.md)
- [Estrutura do banco MySQL](docs/DATABASE.md)
- [Guia do desenvolvedor](docs/DEVELOPER_GUIDE.md)
- [Historico de desenvolvimento](docs/AI_DEV_LOG.md)
- [Regras de documentacao](docs/REGRAS_DOCUMENTACAO.md)

Configure as credenciais locais em `.env` conforme `.env.example`:
`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306` e
`DB_DATABASE=gestao_financeira`. O usuario `root` e a senha vazia sao apenas
referencias para desenvolvimento no Wamp; ajuste conforme sua instalacao.
Use usuario dedicado com senha em producao.

A troca de configuracao nao cria o banco nem transfere dados de outro banco.
Consulte o passo a passo em `docs/README.md` antes de executar migrations.
Os testes automatizados continuam isolados em SQLite em memoria.

---

## Referencia do Skeleton Laravel

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Sobre o Laravel

O Laravel é um framework para aplicações web com uma sintaxe expressiva e elegante. Acreditamos que o desenvolvimento deve ser uma experiência prazerosa e criativa para ser verdadeiramente gratificante. O Laravel torna o desenvolvimento menos trabalhoso ao simplificar tarefas comuns presentes em muitos projetos web, tais como:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

O Laravel é acessível, poderoso e oferece as ferramentas necessárias para aplicações grandes e robustas.

## Aprendendo Laravel

O Laravel possui a [documentação](https://laravel.com/docs) mais extensa e detalhada — além de uma vasta biblioteca de tutoriais em vídeo — entre todos os frameworks modernos para aplicações web, tornando muito simples começar a utilizá-lo. Você também pode conferir o [Laravel Learn](https://laravel.com/learn), onde encontrará um guia para a criação de uma aplicação Laravel moderna.

Se você prefere não ler, o [Laracasts](https://laracasts.com) pode ajudar. O Laracasts oferece milhares de tutoriais em vídeo sobre diversos temas, incluindo Laravel, PHP moderno, testes unitários e JavaScript. Aprimore suas habilidades explorando nossa biblioteca completa de vídeos.

## Patrocinadores do Laravel

Gostaríamos de agradecer aos patrocinadores abaixo pelo financiamento do desenvolvimento do Laravel. Se você tiver interesse em se tornar um patrocinador, visite o [programa de parceiros do Laravel (Laravel Partners)](https://partners.laravel.com).

### Parceiros Premium

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contribuição

Obrigado por considerar contribuir para o framework Laravel! O guia de contribuição pode ser encontrado na [documentação do Laravel](https://laravel.com/docs/contributions).

## Código de Conduta

Para garantir que a comunidade Laravel seja acolhedora para todos, por favor, leia e siga o [Código de Conduta](https://laravel.com/docs/contributions#code-of-conduct).

## Vulnerabilidades de Segurança

Se você descobrir uma vulnerabilidade de segurança no Laravel, por favor, envie um e-mail para Taylor Otwell pelo endereço [taylor@laravel.com](mailto:taylor@laravel.com). Todas as vulnerabilidades de segurança serão tratadas prontamente.

## Licença

O framework Laravel é um software de código aberto licenciado sob a [licença MIT](https://opensource.org/licenses/MIT).
