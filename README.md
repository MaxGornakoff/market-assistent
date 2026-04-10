# Market Assistant

Стартовая структура проекта для помощника менеджера по ценообразованию магазина на **Яндекс Маркете**.

## Стек

- `backend/` — Laravel 10 API
- `frontend/` — Vue 3 + Vite интерфейс
- `docker-compose.yml` — MySQL + phpMyAdmin в Docker

## Структура

```text
market-assistent/
├─ backend/
│  ├─ app/
│  │  ├─ Http/Controllers/Api/
│  │  ├─ Integrations/MoySklad/
│  │  ├─ Integrations/YandexMarket/
│  │  └─ Services/Pricing/
│  └─ routes/api.php
├─ frontend/
│  └─ src/
│     ├─ router/
│     ├─ services/
│     ├─ stores/
│     └─ views/
└─ docker-compose.yml
```

## Быстрый старт

### 1. Поднять MySQL в Docker

```bash
docker compose up -d
```

- MySQL: `127.0.0.1:3306`
- phpMyAdmin: `http://localhost:8081`

### 2. Запустить Laravel API

```bash
cd backend
php artisan serve
```

API будет доступен на `http://localhost:8000`.

### 3. Запустить Vue frontend

```bash
cd frontend
npm run dev
```

Frontend будет доступен на `http://localhost:5173`.

## Что уже подготовлено

- базовый `health` endpoint: `GET /api/health`
- стартовый `dashboard-summary` endpoint: `GET /api/dashboard-summary`
- конфиг-заготовки для `МойСклад` и `Яндекс Маркет`
- базовая UI-структура под будущие модули ценообразования
