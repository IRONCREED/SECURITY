# Техническое задание: IRONCREED Request Log 1.0

Идентификатор: `ics-brief-request-log-001`.

Редакция: `0.1.0`.

Статус: `approved-for-implementation`.

Коллега, вам поручено разработать первый публичный WordPress-плагин в
монорепозитории IRONCREED Security. Отнеситесь к нему как к небольшой
репутационной утилите: функция остаётся узкой, а качество кода, приватность,
документация и прохождение проверки WordPress.org получают полный приоритет.
Пояснения в скобках раскрывают технический смысл требования и не предоставляют
альтернативный необязательный вариант.

Перед началом прочитайте корневые `AGENTS.md`, `CONSTITUTION.md`,
`governance/PROFILE.md`,
`governance/legislation/WORDPRESS_PLUGIN_DEVELOPMENT.md`,
`docs/IRONCREED-SUITE-PROTOCOL.md` и
`docs/WORDPRESS-ORG-RELEASE-GATE.md`.

## 1. Результат

Создайте плагин `IRONCREED Request Log` со slug
`ironcreed-request-log`. Администратор открывает `Tools → Request Log` и видит
конкретные HTTP-запросы, достигшие WordPress: время, метод, очищенный URI,
HTTP-статус, длительность, тип маршрута и источник наблюдения.

Плагин решает просмотр отдельных запросов и URI. Сводные графики, посещаемость,
SEO-аналитика, firewall, блокировка ботов, rate limiting, error log, request
body inspector и monitoring dashboard в 1.0 не входят.

Постоянно отображайте источник `WordPress runtime` и границу: журнал содержит
только запросы, при обработке которых загрузился WordPress. Запросы,
завершённые в CDN, WAF, веб-сервере, full-page cache, static handler либо до
WordPress, отсутствуют.

## 2. Идентичность

Используйте неизменные значения:

| Назначение | Значение |
| --- | --- |
| Plugin Name | `IRONCREED Request Log` |
| Directory | `ironcreed-request-log` |
| Main file | `ironcreed-request-log.php` |
| Text domain | `ironcreed-request-log` |
| Product ID | `ironcreed/request-log` |
| Vendor ID | `ironcreed` |
| Theme ID | `security` |
| Suite protocol | `1` |
| PHP namespace | `Ironcreed\Request_Log` |
| Non-namespaced prefix | `ironcreed_request_log_` |
| Table suffix | `ironcreed_request_log_events` |
| View capability | `view_ironcreed_request_log` |
| Manage capability | `manage_ironcreed_request_log` |
| Minimum PHP | `8.0` |
| License | `GPL-2.0-or-later` |

Определите минимальную WordPress после выбора Core APIs и подтвердите её
integration test. `Tested up to` устанавливается перед release candidate по
реально проверенной выпущенной версии.

## 3. Структура

Организуйте плагин следующим образом; небольшое уточнение допустимо при
сохранении ответственности и package boundary:

```text
plugins/ironcreed-request-log/
├── ironcreed-request-log.php
├── uninstall.php
├── readme.txt
├── license.txt
├── changelog.txt
├── includes/
│   ├── domain/
│   ├── application/
│   ├── infrastructure/
│   ├── admin/
│   └── suite/
├── assets/
├── languages/
└── tests/
    ├── unit/
    ├── integration/
    └── fixtures/
```

Готовый ZIP хранится вне Git. Главный файл содержит валидный plugin header,
`ABSPATH` guard, Product ID metadata, bootstrap guard и composition root.
Доменное поведение в главном файле не размещайте.

## 4. Наблюдение

### 4.1. Выключенное состояние

После установки журналирование выключено. Экран показывает назначение, поля,
границу, default retention и кнопку включения. Включение требует
`manage_ironcreed_request_log` и nonce. Пока запись выключена, event rows не
создаются.

### 4.2. Начало и завершение

На раннем применимом hook создайте immutable draft observation с монотонным
временем. На `shutdown` получите status и duration, выполните redaction и один
bounded insert. Ошибка журнала не меняет status, body или headers ответа.

Не записывайте обращения самого экрана Request Log и cleanup. Классифицируйте
front-end, REST, AJAX, Cron, XML-RPC, login и admin без сохранения тела или
авторизационных данных.

### 4.3. Поля 1.0

| Поле | Требование |
| --- | --- |
| Event ID | Локальный монотонный BIGINT primary key |
| Observed at | UTC и индексированный диапазон |
| Method | Uppercase allowlist; безопасная unknown category |
| Path | Нормализованный path с ограничением длины |
| Query | Строка после redaction и ограничения |
| Status | Integer 100–599 либо безопасный unknown |
| Duration | Неотрицательное ограниченное число миллисекунд |
| Route kind | Закрытый enum |
| Source | `wordpress-runtime` |

Не добавляйте IP, User-Agent, Referer, cookies, request/response bodies, headers,
user ID, session ID или произвольный context blob. Изменение требует новой
product/privacy редакции.

## 5. URI и redaction

Разделите URI на path и query. Path проходит проверку encoding, удаление control
characters и ограничение длины. Не декодируйте encoded separator так, чтобы
менялась структура пути.

Sensitive values заменяются `[redacted]`. Обязательный case-insensitive минимум:

`password`, `pass`, `pwd`, `token`, `access_token`, `refresh_token`, `api_key`,
`apikey`, `secret`, `nonce`, `_wpnonce`, `authorization`, `auth`, `key`,
`signature`, `sig`, `code`, `email`.

Filter может добавлять ключи и не может ослабить минимум. Ограничьте число
параметров, глубину массива, длину ключа/значения и итоговой query. Повторяющиеся
ключи обрабатывайте детерминированно. На выводе escape применяется заново.

## 6. Storage и lifecycle

Создайте отдельную таблицу через поддерживаемый WordPress migration pattern.
Добавьте индексы времени, status, method и route kind. Не индексируйте полный
URI без доказанной необходимости.

Default retention — 24 часа; диапазон — 1 час–30 дней. Default hard cap —
10 000; диапазон — 100–100 000. Настройки имеют строгий validation callback.

Реализуйте hourly WP-Cron cleanup и bounded emergency prune. Не выполняйте
`COUNT(*)` и полный cleanup в каждом запросе. Храните schema version и
выполняйте идемпотентные migrations.

Deactivation снимает observer и scheduled event, сохраняя данные. Uninstall
удаляет table, options, capabilities и events. До первой стабильной версии
default uninstall behavior — полное удаление.

## 7. Полномочия

При активации назначьте capabilities роли Administrator. Для Multisite отдельно
определите network/site границы. Site admin видит только таблицу своего сайта;
cross-site viewer в 1.0 отсутствует.

Проверяйте view capability до запроса журнала. Проверяйте manage capability и
nonce до включения, выключения, изменения retention и очистки.

## 8. Интерфейс

Создайте страницы `Request Log` и `Settings` на native WordPress admin UI. CSS
и JavaScript загружаются только на страницах плагина.

Основной экран содержит:

- status записи и badge `Source: WordPress runtime`;
- постоянное объяснение границы;
- ручное обновление;
- фильтры времени, метода, status class, route kind и path substring;
- пагинированную таблицу времени, method, URI, status, duration и route;
- ясное пустое состояние;
- отдельную очистку с подтверждением.

Page sizes: 20, 50, 100. Default sorting — newest first. Sorting и filters
используют allowlists. Filters сохраняются в URL без sensitive data.
Auto-refresh и export отсутствуют.

Не опирайтесь на private WordPress API. Если `WP_List_Table` остаётся private в
минимальной версии, создайте небольшую доступную таблицу на public APIs.

Settings содержит enable/disable, retention и hard cap. Server log path, export
и telemetry отсутствуют.

## 9. Suite Protocol

Реализуйте `IRONCREED Suite Protocol v1`. Один Request Log размещается в
`Tools`. При двух совместимых `ironcreed/security/1` он участвует в единственном
`IRONCREED — Security`. Плагин регистрирует только собственные callbacks и
capabilities.

Добавьте test fixtures для second same-theme и different-theme plugins. Они не
попадают в ZIP.

## 10. Защита от двух версий

В main header добавьте metadata immutable Product ID. Activation guard через
WordPress APIs отклоняет активацию, если другой active path объявляет
`ironcreed/request-log`.

Bootstrap guard завершает загрузку второй копии до duplicate symbols. Покажите
contextual dismissible notice пользователю с `activate_plugins`, назовите оба
plugin basenames и предложите оставить одну копию. Автоматическая деактивация
запрещена.

Проверьте обычную, network и программную activation, два переименованных
каталога, одинаковую и разные версии. Номер версии не влияет на запрет.

## 11. Приватность

Добавьте текст в Privacy Policy Guide: запись выключена по умолчанию, поля,
возможное наличие идентификаторов в URI, место, доступ, retention и deletion.

Проведите review personal-data exporter/eraser. Если redacted URI всё ещё
связывается с субъектом, реализуйте public Privacy APIs либо зафиксируйте точное
основание решения. Фраза «IP не записывается» недостаточна.

## 12. Ошибки

Admin notice появляется только на страницах плагина, кроме activation conflict
и critical migration failure. Notice dismissible либо исчезает после
исправления и содержит безопасное действие.

Не показывайте SQL, stack trace или absolute path. Development logging следует
WordPress debug policy. Собственный log file в каталоге плагина запрещён.

## 13. Код и инструменты

Следуйте WordPress Coding Standards без blanket exclusions. Используйте
WordPress Core и first-party PHP. Runtime Composer package, JS framework и CDN
версии 1.0 не нужны.

Создайте dev-only Composer toolchain с exact lockfile для PHPCS, WordPress
Coding Standards, PHPCompatibilityWP и PHPUnit integration tooling. Запишите
назначение и лицензию direct dependencies. ZIP исключает `vendor`.

`composer check` выполняет полный немутирующий набор. `composer build` создаёт
release ZIP из allowlist. CI использует `composer install`.

## 14. Автоматические проверки

Добавьте GitHub Actions с checkout submodules и PHP matrix:

1. Composer validation и lockfile integrity;
2. WordPress Coding Standards: zero errors;
3. PHPCompatibilityWP для PHP 8.0+;
4. domain/redaction/normalization/classification unit tests;
5. integration tests observer, storage, capabilities, nonces, settings, Cron,
   migrations и uninstall;
6. Multisite tests;
7. duplicate-instance и Suite Protocol tests;
8. negative-security fixtures;
9. reproducible package и content assertion;
10. Plugin Check `Plugin repo` на пакете.

Проверьте XSS в path/query, encoded separators, invalid UTF-8, control
characters, query bombs, repeated sensitive keys, SQL metacharacters, forged
nonce, missing capability, malformed descriptor и две копии.

## 15. Ручная проверка

На чистом single-site выполните: install ZIP; activation без notice; disabled
default; enable; front-end/REST/AJAX/admin/login/404 requests; filters;
pagination; sensitive-query redaction; clear; deactivate/reactivate; uninstall
с удалением.

Повторите применимое на Multisite. Проверьте keyboard-only, screen-reader labels,
narrow viewport, английский UI и одну реальную локализацию. Включите `WP_DEBUG`
и `SCRIPT_DEBUG`.

## 16. Readme

`readme.txt` пишет простым английским. Short description ≤150 characters; tags
≤5. Опишите WordPress runtime boundary в Description и FAQ. Не обещайте полный
server access log, безопасность, compliance либо обнаружение всех ботов.

Опишите installation, enablement, fields, retention, clear, Multisite,
uninstall, отсутствие telemetry, support и privacy. Ссылка на GitHub ведёт к
public maintained source. Version и Stable tag совпадают; stable `trunk`
запрещён.

## 17. Milestones

1. Scaffold, headers, composition, lifecycle и dev toolchain.
2. Immutable domain types, normalization, redaction и tests.
3. Storage schema, repository, migrations, retention и tests.
4. Observer и request classification.
5. Capabilities, settings и admin viewer.
6. Suite Protocol и duplicate-instance guard.
7. Privacy, uninstall, i18n и accessibility.
8. Package builder, Plugin Check, release evidence и manual smoke test.

После каждого milestone запускайте применимые checks. Не переносите security,
privacy, tests и packaging в неопределённый последний проход.

## 18. Приёмка

Работа принимается, когда:

- reproducible ZIP устанавливается без другого IRONCREED-плагина;
- запись выключена и включается только уполномоченным действием;
- concrete requests имеют method, redacted URI, status, duration, route/source;
- граница наблюдения постоянно видима;
- retention, cap, cleanup, clear и uninstall доказаны;
- capability, nonce, SQL, escaping, XSS и duplicate copy доказаны;
- standalone и grouped menu соответствуют Suite Protocol;
- WPCS, PHPCompatibilityWP, tests и Plugin Check проходят;
- ZIP содержит только разрешённые GPL-совместимые файлы;
- readme, privacy, changelog и security/support готовы к review;
- release report связывает source commit, tools, checks и ZIP hash.

## 19. Отложенный Server Access Log Source

После принятия 1.0 и отдельного security review может появиться server-log
adapter. Сохраните application port, только если это не усложняет 1.0, но не
добавляйте file code, path setting и UI в первый пакет.

Будущий adapter принимает только trusted configured paths, соблюдает
`open_basedir`, читает bounded tail без shell, не изменяет файл, redacts IP и
query по умолчанию и маркирует source `server-access-log`. Его выпуск требует
нового задания и privacy/release review.

## 20. Передача результата

Откройте один draft pull request. В описании укажите milestones, architecture,
schema/migrations, privacy impact, suite/duplicate behavior, checks, ZIP
contents, known limitations и WordPress.org readiness.

Не называйте работу завершённой до release gate. При изменении внешнего
требования приведите официальный источник, объясните влияние и обновите
нормативный документ вместе с кодом.
