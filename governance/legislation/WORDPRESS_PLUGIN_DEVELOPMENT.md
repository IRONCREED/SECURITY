# Регламент разработки WordPress-плагинов IRONCREED

Идентификатор: `ics-act-wordpress-development-001`.

Редакция: `0.1.0`.

Дата принятия: 2026-08-28.

Конституционное основание: принятая «Конституция кода»,
[`ics-profile-001`](../PROFILE.md) и обязательный внешний порядок WordPress.

Акт переносит применимые правила `BASE` из закреплённого `code-constitution`
и конкретизирует их для PHP, JavaScript, CSS, HTML, WordPress Core APIs,
WordPress.org, Multisite и локального журнала. Локальные правила используют
префикс `ICS-WP`.

## 1. Внешний стандарт

### ICS-WP-E01. WordPress Coding Standards

Весь новый и изменяемый код распространяемого плагина строго следует
официальным WordPress Coding Standards для соответствующего языка. Требование
охватывает форму, имена, документацию, интернационализацию, совместимость,
безопасность и взаимодействие с WordPress.

Для PHP применяется актуальный WordPress Coding Standards через
PHP_CodeSniffer с `WordPress-Core`, `WordPress-Docs` и `WordPress-Extra`.
PHPCompatibilityWP проверяет объявленный диапазон PHP.

При конфликте общей стилистической нормы `BASE` и официальной нормы WordPress
для distributable plugin code действует специальная норма WordPress.

### ICS-WP-E02. Правила каталога

Актуальные Detailed Plugin Guidelines проверяются перед заявкой и релизом.
Исходники остаются читаемыми. В пакет не входят обфускация, недокументированный
remote service, alternate updater, remote executable code, trialware, tracking
без согласия, навязчивый notice, скрытая реклама либо библиотека, уже
предоставляемая WordPress.

Плагин к моменту подачи является полным и пригодным к установке. Пустой каркас,
зарезервированное имя либо обещание будущей функции не подаются.

## 2. Граница продукта и пакета

### ICS-WP-P01. Независимый плагин

Каждый `/plugins/<slug>/` содержит один плагин и один Product ID. Он
активируется, деактивируется, обновляется и удаляется без другого
IRONCREED-плагина. Общая навигация является optional interoperability.

Каталог, главный PHP-файл, slug и text domain совпадают. Prefix, namespace,
options, tables, Cron hooks, REST namespaces, actions/filters и asset handles
принадлежат уникальному продуктовому пространству.

### ICS-WP-P02. Release package

ZIP содержит один корневой каталог с точным slug. В него входят только runtime-
файлы, лицензия, `readme.txt`, переводы и пользовательская документация. Tests,
fixtures, governance, CI, build sources, reports, dependency caches и приватные
данные исключаются воспроизводимой allowlist-сборкой.

Build использует чистый checkout и закреплённые инструменты. Результат
связывается с Git SHA и SHA-256 ZIP. Ручное исправление ZIP запрещено.

## 3. Архитектура

### ICS-WP-A01. Слои

Домен содержит модели, правила, redaction и детерминированные преобразования.
Application содержит scenarios и ports. Adapters соединяют их с hooks,
`$wpdb`, Options, Cron, roles, filesystem и admin UI. Composition root связывает
конкретные реализации в bootstrap.

Домен не читает superglobals, не выполняет SQL, не печатает HTML и не вызывает
WordPress globals. Adapter переводит внешний `mixed` в проверенное внутреннее
значение.

### ICS-WP-A02. WordPress API first

WordPress API применяется для capabilities, nonces, URLs, paths, HTTP, SQL,
Cron, Settings, i18n, filesystem и escaping. Прямой PHP-вызов разрешён для
чистой локальной операции при отсутствии эквивалента и без обхода контроля.

Shell-команды, `eval`, динамический include по пользовательскому пути,
unserialize непроверенных данных, URL wrappers для локального файла и запись в
каталог плагина запрещены.

### ICS-WP-A03. Зависимости

Runtime dependency требует решения с функцией, лицензией, размером, supply-
chain риском и основанием превосходства над малой локальной реализацией. Версия
1.0 первого плагина использует только WordPress Core и PHP. Development
dependencies закрепляются lockfile и не попадают в ZIP.

## 4. Безопасность

### ICS-WP-S01. Полномочие до действия

Каждая admin page, AJAX action, REST route, CLI command и state-changing handler
проверяет конкретное capability до чтения чувствительных данных или изменения.
Nonce подтверждает происхождение намерения после проверки capability и не
заменяет полномочие.

Multisite различает site и network capability. Network-активация не сообщает
site administrator доступ к журналам других сайтов.

### ICS-WP-S02. Вход и выход

Вход проходит allowlist, проверку типа, диапазона и семантики, затем
нормализацию и sanitization для хранения. Выход экранируется в последнем
контексте. Значение для базы не считается безопасным для HTML, URL, JSON или
SQL.

Table identifier и sorting выбираются из константного allowlist. SQL values
передаются через `$wpdb->prepare()` либо безопасный API. Log line всегда
считается недоверенным вводом.

### ICS-WP-S03. Файлы

File adapter принимает только пути, учреждённые configuration constant либо
доверенным server integration filter. Произвольное поле пути в wp-admin
запрещено. Путь проходит `realpath`, проверку обычного читаемого файла,
allowlist, запрет wrapper, NUL и traversal. `open_basedir` соблюдается.

Reader использует bounded tail и не загружает весь журнал. Изменение файла,
chmod/chown, ротация, удаление, публичное копирование и отправка запрещены.

### ICS-WP-S04. Дубли экземпляров

Product ID неизменен. Activation guard отклоняет вторую активную копию
независимо от каталога и версии. Bootstrap guard предотвращает повторное
объявление symbols. Сообщение называет обе копии и безопасное действие;
автоматическая деактивация другой копии запрещена.

## 5. Приватность и данные

### ICS-WP-D01. Минимизация

Собирается минимальный набор. Request body, cookies, authorization headers,
пароли, nonce, токены и секреты не записываются. Список sensitive query keys
содержит обязательный минимум и расширяется filter без возможности ослабить
его.

Опциональное поле требует явного включения и документации цели. Telemetry и
внешняя передача отсутствуют в первом плагине.

### ICS-WP-D02. Ограниченность

Storage имеет предел времени и количества. Cleanup идемпотентен, работает через
Cron и bounded prune при лимите. Отсутствие loopback WP-Cron не отменяет prune
при последующей записи или просмотре.

Activation, upgrade, deactivation и uninstall имеют отдельные tests. Uninstall
удаляет данные по ясно описанной настройке; default для request log — полное
удаление.

### ICS-WP-D03. Privacy surface

Плагин предлагает текст для Privacy Policy Guide и описывает поля, цель, срок,
место хранения, доступ и удаление. Personal-data exporter/eraser добавляются,
когда схема содержит данные, связываемые с субъектом. Отсутствие IP не отменяет
review URI и query.

## 6. Производительность и надёжность

### ICS-WP-R01. Горячий путь

Observer выполняет постоянное ограниченное количество работы, не делает HTTP,
не считает всю таблицу и не запускает cleanup на каждом запросе. Один bounded
insert допускается только после явного включения. Ошибка журнала не изменяет
основной HTTP-ответ.

### ICS-WP-R02. Административный запрос

Список использует индексированную пагинацию, allowlisted filters и ограниченный
page size. Auto-refresh выключен по умолчанию; ручное обновление сохраняет
фильтры.

### ICS-WP-R03. Диагностика

Сообщение называет источник, границу и безопасное действие. Плагин не раскрывает
absolute path, SQL, stack trace или fragment журнала без capability. Ошибка
observer изолируется от основного запроса.

## 7. Интерфейс и язык

### ICS-WP-U01. Нативный UI

Admin UI использует нативные WordPress components, notices, buttons, Dashicons
и стандартные libraries. Собственная стилизация минимальна и загружается только
на страницах плагина. Реклама и branding не вытесняют функцию.

### ICS-WP-U02. Доступность

Форма имеет label, keyboard order, visible focus, доступное описание ошибки и
не полагается на цвет. Таблица сохраняет смысл на узком экране. Динамический
результат объявляется live region без потери фокуса.

### ICS-WP-U03. Интернационализация

Исходный язык strings — английский. Каждая строка проходит WordPress i18n API с
text domain, совпадающим со slug. Dynamic value отделяется от переводимой
фразы, placeholder документируется, translator comment добавляется по
необходимости.

## 8. Межплагинный протокол

### ICS-WP-I01. Регистрация

Плагин регистрирует inert descriptor в `ironcreed_suite_registry_v1` и не
вызывает другой плагин напрямую. Descriptor проходит проверку. Группа
определяется tuple `vendor/theme/protocol`.

### ICS-WP-I02. Координация меню

При двух участниках coordinator определяется лексикографически по full Product
ID и регистрирует один top-level menu. Каждый участник регистрирует собственную
страницу и capability. Coordinator не читает данные другого плагина. Полный
контракт содержится в `/docs/IRONCREED-SUITE-PROTOCOL.md`.

## 9. Проверки

### ICS-WP-Q01. Обязательный набор

Одна немутирующая команда выполняет:

1. Composer validation и exact lockfile install;
2. WordPress Coding Standards;
3. PHPCompatibilityWP;
4. unit, integration, multisite и negative-security tests;
5. migrations, activation, deactivation, uninstall и duplicate guard;
6. i18n и release metadata;
7. воспроизводимую ZIP-сборку и package allowlist;
8. официальный Plugin Check на пакете.

Каждая ошибка даёт ненулевой exit code. CI запускает ту же команду без auto-fix.

### ICS-WP-Q02. Security fixtures

Fixtures включают XSS в URI/log line, SQL metacharacters, invalid UTF-8,
oversized query, traversal, URL wrapper, symlink, неожиданный status,
отсутствующее capability, неверный nonce, duplicate copy, повреждённую схему,
Multisite boundary и отказ Cron. Они синтетические и не содержат production data.

### ICS-WP-Q03. Evidence

Pull request перечисляет проверки, версии среды, остаточные риски, ручной
сценарий и связь с требованиями. Waiver имеет узкую область, владельца, срок,
компенсирующую проверку и утверждение. Suppression без записи не создаёт waiver.

## 10. Версии и выпуск

### ICS-WP-V01. Версия и миграция

Плагин использует SemVer. Изменение схемы, default, capability, public hook,
минимальной версии либо uninstall behavior классифицируется явно. Миграция
идемпотентна и связывает прежнюю и новую схему.

### ICS-WP-V02. Метаданные

Version в main file, Stable tag, changelog, Git tag, ZIP и WordPress.org SVN
tag совпадают. `Stable tag: trunk` для нового плагина запрещён. `Tested up to`
указывает реально проверенную выпущенную major-версию WordPress.

### ICS-WP-V03. WordPress.org SVN

SVN используется после одобрения только для законченных релизов. Один выпуск
поступает coherent commit с информативным сообщением. Частые микрокоммиты и
искусственное обновление видимости запрещены.

## 11. Изменение акта

Изменение стандарта, лицензии, package boundary, схемы, privacy default, suite
protocol, capability либо release gate обновляет акт вместе с tests и
migration. Редакционная правка увеличивает patch, совместимое расширение —
minor, несовместимый порядок — major и требует переходного акта.
