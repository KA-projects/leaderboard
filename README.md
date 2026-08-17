# Leaderboard API

Сервис подсчёта баллов пользователей и рейтинга на Laravel 12. Запускается целиком в Docker (PHP 8.2, PostgreSQL, Redis).

## Запуск

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

Проверка: `curl -H "Accept: application/json" localhost:8000/api/health` → `{"status":"ok"}`.

Любые PHP-команды — только внутри контейнера (`docker compose exec app php artisan ...`).

## Возможности сервиса

Баллы начисляются за действия: login — 1, like — 5, comment — 20, referral — 50, purchase — 100. Действие сохраняется в PostgreSQL, балл асинхронно добавляется в лидерборд Redis (через очередь). Ответы принимают `?pretty=1` для читаемого JSON.

| Метод | Маршрут | Описание |
|-------|---------|----------|
| POST | `/api/users` | Создать пользователя |
| GET | `/api/users/{id}` | Информация о пользователе |
| GET | `/api/users/{id}/rank` | Место пользователя в рейтинге |
| GET | `/api/users/{id}/neighbors` | Соседи в рейтинге (вокруг пользователя) |
| POST | `/api/actions` | Отправить действие пользователя (`user_id`, `type`) |
| GET | `/api/leaderboard` | Топ рейтинга (с пагинацией) |

## Основные команды

```bash
docker compose up -d                       # запуск всех сервисов
docker compose exec app php artisan migrate --force
docker compose exec app php artisan test   # тесты
docker compose exec app ./vendor/bin/pint  # стиль кода
docker compose exec app php artisan demo   # демо-данные + показ функционала
docker compose exec app php artisan leaderboard:rebuild  # восстановить рейтинг из PostgreSQL
docker compose exec app php artisan leaderboard:check    # сверить рейтинг Redis с PostgreSQL
docker compose up -d --scale queue=3       # масштабировать воркеры очереди
```

## API

Базовый URL: `http://localhost:8000/api`. Добавьте `?pretty=1` для читаемого JSON. Все запросы передавайте с заголовком `Accept: application/json` — без него Laravel вернёт HTML вместо JSON. Общие параметры лидерборда: `period` (`all`|`daily`|`weekly`|`monthly`), `page`, `per_page` (макс. 100).

### Создать пользователя

```bash
curl -X POST localhost:8000/api/users \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"name":"Иван","email":"ivan@example.com"}'
```

→ `201`, `{"id":1,"name":"Иван","email":"ivan@example.com",...}`

### Отправить действие (начисление баллов)

Типы: `login` (1), `like` (5), `comment` (20), `referral` (50), `purchase` (100).

```bash
curl -X POST localhost:8000/api/actions \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"user_id":1,"type":"like"}'
```

→ `201`. Балл попадает в рейтинг асинхронно через очередь.

### Топ рейтинга

```bash
curl -H "Accept: application/json" \
  "localhost:8000/api/leaderboard?period=weekly&page=1&per_page=10&pretty=1"
```

### Пользователь, его место и соседи

```bash
curl -H "Accept: application/json" localhost:8000/api/users/1
curl -H "Accept: application/json" "localhost:8000/api/users/1/rank?period=all"
curl -H "Accept: application/json" "localhost:8000/api/users/1/neighbors?limit=1&period=all"
```

`neighbors` возвращает `limit` соседей сверху и снизу вокруг пользователя. Рейтинг считается по сумме баллов за выбранный `period`.
