# Подпроект A: улучшение бэкенда — дизайн-спецификация
<!-- lang-nav -->

Languages: **中文** · [English](2026-05-20-backend-enhancement-design.en.md) · [한국어](2026-05-20-backend-enhancement-design.ko.md) · [Русский](2026-05-20-backend-enhancement-design.ru.md) · [Deutsch](2026-05-20-backend-enhancement-design.de.md) · [Français](2026-05-20-backend-enhancement-design.fr.md) · [Español](2026-05-20-backend-enhancement-design.es.md) · [Português](2026-05-20-backend-enhancement-design.pt.md) · [हिन्दी](2026-05-20-backend-enhancement-design.hi.md) · [العربية](2026-05-20-backend-enhancement-design.ar.md) · [বাংলা](2026-05-20-backend-enhancement-design.bn.md) · [Bahasa Indonesia](2026-05-20-backend-enhancement-design.id.md) · [日本語](2026-05-20-backend-enhancement-design.ja.md)


> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Область

Данное улучшение касается бэкенда — всего 15 функциональных пунктов, 9 новых файлов + 4 изменяемых файла.

---

## Список новых/изменяемых файлов

```
app/middleware/
├── OperationLog.php          # 新增：操作日志自动记录
├── Cors.php                  # 新增：跨域
└── RateLimit.php             # 新增：Redis 限流
app/admin/controller/
├── ConfigController.php      # 新增：系统配置 CRUD
├── LogController.php         # 新增：操作日志查询
├── ProfileController.php     # 新增：个人中心（含登出）
├── UploadController.php      # 新增：文件上传
├── ImportController.php      # 新增：Excel 导入用户
└── HealthController.php      # 新增：健康检查
app/model/
├── AdminUser.php             # 修改：加 SoftDeletes + Searchable trait
└── OperationLog.php          # 修改：加 public $timestamps = false
app/middleware/
└── AdminAuth.php             # 修改：JWT 黑名单校验
app/admin/controller/
├── DashboardController.php   # 修改：改为数据库实时统计
└── UserController.php        # 修改：新增批处理动作
config/
└── route.php                 # 修改：新增路由 + 中间件
```

---

## 1. Промежуточное ПО (мидлвары)

### 1.1 CORS-мидлвар

**Файл**: `app/middleware/Cors.php`

- На OPTIONS-предзапрос сразу возвращается 204
- На обычные запросы в заголовки ответа добавляется `Access-Control-Allow-Origin: *`
- Разрешённые заголовки: `Authorization, Content-Type, API-Version`
- Максимальный кэш: 86400 секунд

Подключение: глобальный мидлвар (`config/middleware.php`)

### 1.2 Мидлвар ограничения частоты запросов

**Файл**: `app/middleware/RateLimit.php`

- Хранилище: Redis Sorted Set, скользящее окно
- По умолчанию: 60 раз/мин/IP/маршрут
- Чувствительные интерфейсы:
  - `/api/auth/login`: 10 раз/мин
  - `/api/auth/register`: 5 раз/мин
- При превышении возвращается `429 Too Many Requests`

Подключение: глобальный мидлвар (`config/middleware.php`), после Cors, перед ApiVersion

### 1.3 Мидлвар журнала операций

**Файл**: `app/middleware/OperationLog.php`

- Записываются только POST/PUT/DELETE
- Записываемые поля: user_id, action, method, path, ip, input(JSON)
- Запись выполняется после возврата ответа асинхронно (не блокирует)

Подключение: группа маршрутов `/admin`, после AdminPermission

### 1.4 Цепочка исполнения глобальных мидлваров

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Выход из системы (JWT-чёрный список)

**Файл**: `app/middleware/AdminAuth.php` (изменение)

**Принцип**: JWT сам по себе не сохраняет состояние; при выходе из системы token добавляется в Redis-чёрный список, а AdminAuth при проверке сначала проверяет чёрный список.

**Доработка AdminAuth**:
- В начале `process()` добавлено: проверка текущего token в Redis-наборе `jwt_blacklist`
- При попадании в чёрный список возвращается 401

**Маршрут выхода** (в разделе личного кабинета):

| Метод | Маршрут | Описание |
|------|------|------|
| `POST` | `/admin/profile/logout` | Добавляет текущий Bearer token в Redis-чёрный список, TTL=оставшийся срок действия token |

**Логика Logout**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Новые контроллеры и доработка существующих

### 2.1 CRUD системных конфигураций (`ConfigController`)

Наследует `BaseController`.

| Метод | Маршрут | Описание |
|------|------|------|
| `index()` | GET `/admin/config` | Пагинированный список, фильтр по `group`, пагинация `page`/`limit` |
| `store()` | POST `/admin/config` | Создание пункта конфигурации, обязательно: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Обновление value/type/description |
| `destroy()` | DELETE `/admin/config/{id}` | Удаление пункта, требуется `confirmPassword()` |

### 2.2 Запрос журнала операций (`LogController`)

Наследует `BaseController`.

| Метод | Маршрут | Описание |
|------|------|------|
| `index()` | GET `/admin/log` | Пагинированный список, фильтры: user_id, action, path, created_at(диапазон) |

Добавление/изменение/удаление не предусмотрено — журнал автоматически ведётся мидлваром.

### 2.3 Личный кабинет (`ProfileController`)

Наследует `BaseController`. Работает с текущим вошедшим пользователем (`$request->adminId`).

| Метод | Маршрут | Описание |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Обновление real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Смена пароля, требуется old_password, new_password, new_password_confirmation |

### 2.4 Загрузка файлов (`UploadController`)

Наследует `BaseController`.

| Метод | Маршрут | Описание |
|------|------|------|
| `upload()` | POST `/admin/upload` | Принимает файл, поддерживаются image/jpeg/png/gif/pdf/xlsx/docx |

- Максимум 10MB
- Путь хранения: `public/upload/{date}/{hash}.{ext}`
- Возврат: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Реальные данные на дашборде

**Файл**: `app/admin/controller/DashboardController.php` (изменение)

Замена текущих захардкоженных фейковых данных на реальную статистику из БД:

| Метрика | Источник | Описание |
|------|------|------|
| Всего пользователей | `AdminUser::count()` | Без мягко удалённых |
| Новых сегодня | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Всего ролей | `AdminRole::count()` | |
| Всего разрешений | `AdminPermission::count()` | |
| Данные тренда | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Новые за последние 7 дней по дням |
| Данные распределения | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Распределение по статусам |
| Последние операции | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Последние 10 записей журнала |

### 2.6 Пакетные операции с пользователями

**Файл**: `app/admin/controller/UserController.php` (изменение, добавление методов)

| Метод | Маршрут | Описание |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Пакетное удаление, тело запроса `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Пакетное включение/отключение, тело запроса `{ ids: [hashid, ...], status: 1|0 }` |

- Каждый id сначала конвертируется через `decodeId()` в BIGINT
- `batchDestroy()` должен пройти проверку `confirmPassword()`

### 2.7 Импорт данных

**Файл**: `app/admin/controller/ImportController.php` (новый)

| Метод | Маршрут | Описание |
|------|------|------|
| `users()` | POST `/admin/import/users` | Загрузка Excel-файла, пакетное создание пользователей |

Процесс:
1. Принимается файл `.xlsx`
2. Парсинг через PhpSpreadsheet, ожидаемые колонки: `username, password, real_name, phone, email, status`
3. Построчная проверка + создание (ID от snowflake, bcrypt-пароль, encryption для phone/email)
4. Возврат результата: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Проверка работоспособности

**Файл**: `app/admin/controller/HealthController.php` (новый)

`GET /health` (без аутентификации, не попадает в журнал операций):

Возвращает состояние подключения каждого компонента:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- При сбое проверки компонента соответствующее поле содержит строку описания ошибки
- Маршрут не вешается на префикс `/admin`, регистрируется отдельно как глобальный

---

## 3. Исправления моделей

### 3.1 Временные метки OperationLog

**Файл**: `app/model/OperationLog.php` (изменение)

Таблица `erik_operation_log` содержит только колонку `created_at` (нет `updated_at`). Eloquent при `save()` по умолчанию пытается записать `updated_at`, что вызывает SQL-ошибку.

Исправление: `public $timestamps = false;` + ручное указание `created_at` при записи.

### 3.2 Доработка модели AdminUser

- Добавлен trait `Searchable`
- Реализован `toSearchableArray()`: возвращает username, real_name
- `UserController::index()` при обнаружении ключевого слова использует `AdminUser::search($kw)->get()` вместо MySQL LIKE

Для ES сначала нужно создать индекс, можно через Scout-команды:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Изменения маршрутов

Новые маршруты в `config/route.php`:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

В `config/middleware.php` регистрируются глобальные мидлвары:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Дополнительные коды ошибок

| code | Значение | Сценарий возникновения |
|------|------|---------|
| 429 | Слишком много запросов | Сработал RateLimit |

---

## 6. Не входит в данную область

- Система уведомлений (требуются очередь сообщений + инфраструктура пушей на фронтенде)
- Страницы Flutter-фронтенда (подпроект B)
- Обновление токена HarmonyOS (подпроект C)
