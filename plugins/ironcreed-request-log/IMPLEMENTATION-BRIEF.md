# Техническое задание: IRONCREED Request Log 1.0

Идентификатор: `ics-brief-request-log-001`.

Редакция: `0.2.1`.

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
`docs/WORDPRESS-ORG-RELEASE-GATE.md`. Для provider-кода также прочитайте
`docs/HOSTING-UKRAINE-API-CONTRACT.md`.

## 1. Результат

Создайте плагин `IRONCREED Request Log` со slug
`ironcreed-request-log`. Администратор открывает `Tools → Request Log` и видит
раздельные журналы двух источников:

- `WordPress Runtime` — запросы, при обработке которых загрузился WordPress;
- `Hosting Ukraine` — nginx access logs, полученные по официальному API.

Каждая запись содержит доступные для её источника поля и ясную маркировку
источника. WordPress-журнал сохраняет время, метод, очищенный URI, HTTP-статус,
длительность и тип маршрута. Hosting Ukraine добавляет серверные поля,
фактически возвращаемые provider.

Плагин решает просмотр отдельных запросов и URI. Сводные графики, посещаемость,
SEO-аналитика, firewall, блокировка ботов, rate limiting, error log, request
body inspector и monitoring dashboard в 1.0 не входят.

На каждой вкладке постоянно отображайте границу источника. `WordPress Runtime` не видит
запросы, завершённые в CDN, WAF, веб-сервере, full-page cache, static handler либо до
WordPress. `Hosting Ukraine` показывает набор и период, которые возвратил API; он не обещает
запросы, отбитые вне контура журна провайдера.

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
├── docs/
│   └── HOSTING-UKRAINE-API-CONTRACT.md
└── tests/
    ├── unit/
    ├── integration/
    └── fixtures/
```

Готовый ZIP хранится вне Git. Главный файл содержит валидный plugin header,
`ABSPATH` guard, Product ID metadata, bootstrap guard и composition root.
Доменное поведение в главном файле не размещайте.

## 4. Источники

### 4.1. Выключенное состояние

После установки WordPress observer выключен, а внешние connections отсутствуют.
Активация плагина не записывает events и не выполняет HTTP-запросы.

Экран показывает оба режима, их поля, границы, нагрузку, retention, удаление и
приватность до включения или подключения. Каждое действие требует
`manage_ironcreed_request_log` и nonce.

### 4.2. WordPress Runtime Source

На раннем применимом hook создайте immutable draft observation с монотонным
временем. На `shutdown` получите status и duration, выполните redaction и один
bounded insert. Ошибка журнала не меняет status, body или headers ответа.

Не записывайте обращения самого экрана Request Log и cleanup. Классифицируйте
front-end, REST, AJAX, Cron, XML-RPC, login и admin без сохранения тела или
авторизационных данных.

### 4.3. Hosting Ukraine API Source

Реализуйте provider interface и `HostingUkraineApiProvider` как первый адаптер. Он
использует официальный метод `hosting/log/web/nginx` и остаётся read-only по
отношению к хостингу:

- API: <https://adm.tools/user/api/#/tab-sandbox/hosting/log/web/nginx>;
- описание access log: <https://www.ukraine.com.ua/wiki/hosting/sites/my-sites/access-log/>.

Для v1.0 используйте только очищенный подтверждённый контракт
`docs/HOSTING-UKRAINE-API-CONTRACT.md`. Он фиксирует endpoint, method, Bearer
authentication, параметры, ответ `.gz`, срок действия token и известные limits.
Не угадывайте другие request fields, JSON schema, диапазоны дат, pagination или
редиректы.

Первый выпуск запрашивает только журнал за текущий день: параметр `date` в запрос
не передаётся, поэтому Hosting Ukraine применяет документированное значение по
умолчанию `today`. Диапазон дат и выбор произвольной даты добавляются только после
подтверждения их точного формата и ограничений в актуальной документации.

Успешный ответ обрабатывается как ограниченный бинарный `.gz`-архив, а не как JSON.
Неуспешный HTTP-ответ, redirect, неподдерживаемый content type, превышение лимита
размера или ошибка распаковки завершают импорт безопасной общей ошибкой без показа
тела ответа. Contract, код, tests и публичная документация не содержат реальный token.

Сетевой вызов появляется только после явного `Test connection` или `Fetch logs`. В 1.0
отсутствуют фоновая синхронизация, live tail, планировщик и webhook. HTTP client подменяется
в tests. Endpoint и host зафиксированы в adapter и не вводятся в UI.

### 4.4. Поля 1.0

| Поле | Требование |
| --- | --- |
| Event ID | Локальный монотонный BIGINT primary key |
| Observed at | UTC и индексированный диапазон |
| Method | Uppercase allowlist; безопасная unknown category |
| Path | Нормализованный path с ограничением длины |
| Query | Строка после redaction и ограничения |
| Status | Integer 100–599 либо безопасный unknown |
| Duration | Только WordPress; неотрицательное ограниченное число миллисекунд |
| Route kind | Только WordPress; закрытый enum |
| Response bytes | Только provider, если возвращается API |
| Client IP | Только provider; потенциальные персональные данные |
| User-Agent | Только provider; ограниченная длина |
| Referer | Только provider; нормализация, redaction и ограниченная длина |
| Source | `wordpress-runtime` или `hosting-ukraine-nginx` |

WordPress source не добавляет IP, User-Agent и Referer. Provider source может их
получать и сохранять только после явного disclosure перед `Fetch logs`. Ни один
источник не сохраняет cookies, request/response bodies, authorization headers,
API tokens, user ID, session ID или произвольный context blob.

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
Добавьте индексы времени, source, status, method и route kind. Не индексируйте полный
URI без доказанной необходимости.

Default retention — 24 часа; диапазон — 1 час–30 дней. Default hard cap —
10 000; диапазон — 100–100 000. Настройки имеют строгий validation callback.

Реализуйте hourly WP-Cron cleanup и bounded emergency prune. Не выполняйте
`COUNT(*)` и полный cleanup в каждом запросе. Храните schema version и
выполняйте идемпотентные migrations.

Provider import применяет ту же retention и hard cap, дедуплицирует повторную ручную
загрузку одного диапазона и выполняет redaction до insert. Частичный import либо откатывается
транзакционно, либо получает явный resumable cursor; молчаливая двойная запись запрещена.

Credentials храните в отдельной option с `autoload=false`. После сохранения token не
возвращается в HTML. Disconnect удаляет credentials, но оставляет импортированные
записи до их retention или ручной очистки; UI прямо объясняет это перед disconnect.

Deactivation снимает observer и scheduled event, сохраняя данные и connection.
Uninstall удаляет table, options, credentials, capabilities и events. До первой стабильной версии
default uninstall behavior — полное удаление.

## 7. Полномочия

При активации назначьте capabilities роли Administrator. Для Multisite отдельно
определите network/site границы. Site admin видит только таблицу своего сайта;
cross-site viewer в 1.0 отсутствует.

Проверяйте view capability до запроса журнала. Проверяйте manage capability и
nonce до включения, выключения, добавления, проверки и удаления connection,
ручного `Fetch logs`, изменения retention и очистки.

## 8. Интерфейс

Создайте страницы `Request Log` и `Settings` на native WordPress admin UI. CSS
и JavaScript загружаются только на страницах плагина.

`Request Log` содержит отдельные вкладки `WordPress Runtime` и `Hosting Ukraine`. Вкладка
Hosting Ukraine видна до подключения и показывает пустое состояние со ссылкой на
`Settings → Connections`. Общая вкладка `All` допустима только как простая проекция над
тем же repository без второго storage и без скрытия source.

Основной экран содержит:

- status и badge текущего source;
- постоянное объяснение границы;
- ручное обновление для WordPress и `Fetch today's logs` для Hosting Ukraine;
- фильтры времени, метода, status class, path substring и доступных для source полей;
- пагинированную таблицу с общими колонками и source-specific details;
- ясное пустое состояние;
- отдельную очистку с подтверждением.

Page sizes: 20, 50, 100. Default sorting — newest first. Sorting и filters
используют allowlists. Filters сохраняются в URL без sensitive data.
Auto-refresh и export отсутствуют.

Не опирайтесь на private WordPress API. Если `WP_List_Table` остаётся private в
минимальной версии, создайте небольшую доступную таблицу на public APIs.

Settings содержит блоки `Sources`, `Connections` и `Privacy and retention`.

`Sources` показывает встроенный WordPress Runtime с enable/disable и его границей.
`Connections` содержит выпадающий список `Add connection`; в 1.0 в нём есть только
`Hosting Ukraine API`. Выбор открывает краткую форму с полями, подтверждёнными по
актуальному API-контракту: `host_id` и Bearer token. Форма описывает внешний сервис, данные, условия,
приватность и риск token до сохранения. Кнопки: `Test connection`, `Save connection`,
`Disconnect`. Сохранённый token не показывается; поле позволяет только заменить его.

`Privacy and retention` содержит retention, hard cap, source-specific поля, кнопки очистки и
ссылку на Privacy Policy Guide. Server log path, export, telemetry, auto-refresh и фоновая
синхронизация отсутствуют.

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

Считайте IP, URI с идентификаторами, User-Agent и Referer потенциальными персональными
данными. Получение, парсинг, фильтрация, хранение, показ и удаление являются обработкой.
Плагин не выбирает за владельца сайта правовое основание и не обещает compliance.

Добавьте текст в Privacy Policy Guide через `wp_add_privacy_policy_content`. Он отдельно
описывает:

- выключенные по умолчанию WordPress observer и Hosting Ukraine connection;
- цель, поля, границу, локальное место, доступ, retention, clear, disconnect и uninstall;
- Hosting Ukraine как внешний сервис, триггер запроса, передаваемые credentials и
  параметры, получаемые log fields и ссылки на его Terms of Service и Privacy Policy;
- точное утверждение, что плагин не отправляет полученные logs в IRONCREED или иной сервис.

Проведите review WordPress personal-data exporter/eraser для обоих источников. Привязка
журнала к email может отсутствовать; это не разрешает молча пропустить review. Реализуйте
public Privacy APIs там, где они корректно находят записи; для остального зафиксируйте точное
основание и предоставьте администратору source-specific clear и фильтры. Фраза «это технический
журнал» не отменяет обработку персональных данных.

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
5. integration tests observer, provider, mock HTTP client, storage, capabilities,
   nonces, settings, Cron, migrations и uninstall;
6. Multisite tests;
7. duplicate-instance и Suite Protocol tests;
8. negative-security fixtures;
9. reproducible package и content assertion;
10. Plugin Check `Plugin repo` на пакете.

Проверьте XSS в path/query/provider fields, encoded separators, invalid UTF-8,
control characters, query bombs, repeated sensitive keys, SQL metacharacters,
forged nonce, missing capability, malformed descriptor, SSRF attempt, cross-host
redirect, timeout, oversized/malformed response, token leakage и две копии.

## 15. Ручная проверка

На чистом single-site выполните: install ZIP; activation без notice; disabled
default; enable; front-end/REST/AJAX/admin/login/404 requests; filters;
pagination; sensitive-query redaction; clear; deactivate/reactivate; uninstall
с удалением.

Без реальных credentials проверьте пустую Hosting Ukraine вкладку, форму connection,
маскирование token и все mock success/error states. Реальный smoke test с тестовым аккаунтом
выполняется только вне CI, не печатает token и не сохраняет полученные logs в
репозитории либо отчёте.

Повторите применимое на Multisite. Проверьте keyboard-only, screen-reader labels,
narrow viewport, английский UI и одну реальную локализацию. Включите `WP_DEBUG`
и `SCRIPT_DEBUG`.

## 16. Readme

`readme.txt` пишет простым английским. Short description ≤150 characters; tags
≤5. Опишите обе границы в Description и FAQ. Не обещайте полную видимость,
безопасность, compliance либо обнаружение всех ботов.

Добавьте отдельный раздел `External services`. Назовите Hosting Ukraine, опишите когда
и зачем плагин обращается к нему, что отправляет и получает, и дайте прямые ссылки
на API docs, Terms of Service и Privacy Policy. Опишите installation, enablement,
connection, fields, retention, clear, disconnect, Multisite, uninstall, отсутствие telemetry,
support и privacy. Ссылка на GitHub ведёт к
public maintained source. Version и Stable tag совпадают; stable `trunk`
запрещён.

## 17. Milestones

1. Scaffold, headers, composition, lifecycle и dev toolchain.
2. Immutable domain types, normalization, redaction и tests.
3. Storage schema, repository, migrations, retention и tests.
4. Observer и request classification.
5. Provider port, Hosting Ukraine adapter, mock HTTP tests и parser.
6. Capabilities, connections, settings, source tabs и admin viewer.
7. Suite Protocol и duplicate-instance guard.
8. Privacy, external-service disclosure, uninstall, i18n и accessibility.
9. Package builder, Plugin Check, release evidence и manual smoke test.

После каждого milestone запускайте применимые checks. Не переносите security,
privacy, tests и packaging в неопределённый последний проход.

## 18. Приёмка

Работа принимается, когда:

- reproducible ZIP устанавливается без другого IRONCREED-плагина;
- запись выключена и включается только уполномоченным действием;
- внешний запрос отсутствует до connection и ручного действия;
- concrete requests имеют method, redacted URI, status и source-specific fields;
- source и его граница постоянно видимы;
- retention, cap, cleanup, clear и uninstall доказаны;
- capability, nonce, SQL, escaping, XSS, provider failures, credential secrecy и
  duplicate copy доказаны;
- standalone и grouped menu соответствуют Suite Protocol;
- WPCS, PHPCompatibilityWP, tests и Plugin Check проходят;
- ZIP содержит только разрешённые GPL-совместимые файлы;
- readme, privacy, changelog и security/support готовы к review;
- release report связывает source commit, tools, checks и ZIP hash.

## 19. Отложенные providers

В 1.0 не добавляйте `LocalFileProvider`, cPanel, Plesk, другие хостинги или общий
конструктор provider. Новый adapter добавляется по отдельному запросу пользователей и
проходит свой terms, security, privacy, UI и release review. Он реализует тот же application
port без внесения provider-specific branches в домен.

## 20. Передача результата

Откройте один draft pull request. В описании укажите milestones, architecture,
schema/migrations, privacy impact, suite/duplicate behavior, checks, ZIP
contents, known limitations и WordPress.org readiness.

Не называйте работу завершённой до release gate. При изменении внешнего
требования приведите официальный источник, объясните влияние и обновите
нормативный документ вместе с кодом.
