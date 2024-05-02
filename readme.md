## Ответ на тестовое задание от FunPay

### Что было сделано:

1. Реализован функционал формирования sql-запросов (MySQL) из шаблона и значений параметров в файле `FpDbTest/Database.php`.
2. Дополнительно к тестам из задания реализованный функционал был покрыт тестами с использованием библиотеки `phpunit` в файле `tests/ExtendedDatabaseTest.php`.
3. Для удобства запуска тестов подготовлен `Dockerfile` и `docker-compose.yml`, запускающий несколько контейнеров - один с `php` и `phpunit`, другой для развертывания `MariaDB` (обращения к базе используется для правильного экранирования строк).

### Как запустить тесты:

```
make tests
```

### Если отсутствует `make` тесты можно запустить набором команд:

```
docker run --rm -u $(id -u):$(id -g) -v $(pwd):/app composer:latest composer install --prefer-dist --ignore-platform-reqs
docker-compose up -d
docker-compose run php php FpDbTest/test.php
docker-compose run php vendor/bin/phpunit
docker-compose down
```