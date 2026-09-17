# Проверка внешнего сервиса и приватности

Статус проверки: 2026-08-28.

Область: `IRONCREED Request Log` 1.0, WordPress Runtime Source и
`HostingUkraineApiProvider`.

Документ фиксирует продуктовую и техническую проверку требований публикации.
Владелец конкретного сайта определяет применимую юрисдикцию, правовое основание
и содержание собственного privacy notice. Проверка не заменяет индивидуальную
юридическую консультацию.

IRON WARDEN prebuild фиксирует автоматические проверки disclosure, отсутствия
секретов, opt-in network boundary, credentials lifecycle и synthetic provider
fixtures. Authenticated Hosting Ukraine smoke test и актуальность terms/privacy
составляют отдельное manual evidence. Фактический API-контракт остаётся закреплён
в `plugins/ironcreed-request-log/docs/HOSTING-UKRAINE-API-CONTRACT.md`.

## 1. Итог

API-адаптер совместим с Конституцией проекта после поправок к Профилю,
WordPress-регламенту и ТЗ. Конституционный акт сохраняет редакцию `0.1.0`: его
порядок приоритета уже ставит обязательное право и правила WordPress.org выше
локальных решений.

WordPress.org допускает плагин-интерфейс к внешнему сервису, который
предоставляет самостоятельную функцию. Hosting Ukraine предоставляет такую
функцию в виде server access logs. Публикационный контур требует явного opt-in,
полного описания сервиса и ссылки на его условия.

Server access log содержит значения, способные относиться к физическому лицу:
IP-адрес, URI с идентификаторами, User-Agent и Referer. Плагин обрабатывает их
как потенциальные персональные данные на всём жизненном цикле.

## 2. WordPress.org

Проверены актуальные официальные источники:

- Detailed Plugin Guidelines:
  <https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/>;
- Plugin Handbook: Privacy:
  <https://developer.wordpress.org/plugins/privacy/>;
- Privacy Policy Guide integration:
  <https://developer.wordpress.org/plugins/privacy/suggesting-text-for-the-site-privacy-policy/>;
- personal-data exporter и eraser:
  <https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-exporter-to-your-plugin/>
  и
  <https://developer.wordpress.org/plugins/privacy/adding-the-personal-data-eraser-to-your-plugin/>.

Применимые выводы:

1. Guideline 6 разрешает интерфейсы к внешним сервисам с содержательной
   функцией и требует ясного описания сервиса в `readme.txt`, предпочтительно
   со ссылкой на Terms of Use.
2. Guideline 7 требует explicit and authorized consent до обращения к внешнему
   серверу и документацию сбора и использования данных. Активация плагина не
   инициирует соединение. Connection test и fetch являются отдельными
   уполномоченными действиями.
3. Guideline 9 запрещает обещание автоматической юридической compliance.
   Документация описывает технические свойства и оставляет выбор правового
   основания владельцу сайта.
4. Privacy Handbook рекомендует раскрывать внешние API, поля, место хранения,
   доступ, срок, удаление и возможности exporter/eraser через стандартные
   privacy hooks WordPress.

## 3. Hosting Ukraine

Проверены официальные страницы:

- Access log:
  <https://www.ukraine.com.ua/wiki/hosting/sites/my-sites/access-log/>;
- API method:
  <https://adm.tools/user/api/#/tab-sandbox/hosting/log/web/nginx>;
- public offer:
  <https://www.ukraine.com.ua/legal/publicoffer/>;
- terms:
  <https://www.ukraine.com.ua/legal/tos/>;
- privacy policy:
  <https://www.ukraine.com.ua/legal/privacypolicy/>.

Официальная документация access log называет метод
`hosting/log/web/nginx` и описывает автоматическую загрузку файла через API.
Она перечисляет время, IP, HTTP status, method/URI, User-Agent, Referer и размер
ответа. Панель хранит логи за текущий и три предыдущих месяца при наличии
запросов. Плагин сохраняет собственный более короткий bounded retention после
ручного или явно разрешённого периодического импорта.

Аутентифицированная документация проверена 2026-08-28. Метод использует
`POST https://adm.tools/action/hosting/log/web/nginx/`, заголовок
`Authorization: Bearer <token>`, параметр `host_id` типа `int` и необязательный
`date` типа `DateTime` со значением по умолчанию `today`. Ответом является
`.gz`-архив nginx access log. Token истекает через шесть месяцев после последнего
использования. Старые почасовые/суточные лимиты не считаются актуальным
контрактом: публичная инструкция указывает 60 запросов в минуту; для конкретного
ответа учитываются актуальные заголовки лимитов и Retry-After.

Эти сведения закреплены в `plugins/ironcreed-request-log/docs/`
`HOSTING-UKRAINE-API-CONTRACT.md`. Первый выпуск намеренно использует только
default `today`: он не предполагает формат пользовательской даты, диапазон,
cursor, pagination или JSON response. Публичная оферта определяет API как часть
панели управления услугами абонента; release review проверяет применимые условия
провайдера и актуальность зафиксированного контракта.

## 4. Персональные данные

Для украинского контура проверен Закон Украины «О защите персональных данных»:
<https://zakon.rada.gov.ua/laws/show/2297-17>. Применимые требования охватывают
определённую цель, соразмерный состав данных, права субъекта и организационные
и технические меры защиты.

Для сайтов, подпадающих под GDPR, проверен Regulation (EU) 2016/679:
<https://eur-lex.europa.eu/eli/reg/2016/679/oj>. Применимы определение personal
data и online identifiers, purpose limitation, data minimisation, storage
limitation, transparency, privacy by design/default и security of processing.
Суд ЕС также признавал динамический IP персональными данными для оператора,
который имеет законные средства идентификации с дополнительной информацией:
<https://curia.europa.eu/jcms/upload/docs/application/pdf/2016-10/cp160112en.pdf>.

Технические последствия:

- оба источника выключены после установки;
- Hosting Ukraine вызывается при явном lookup/test/fetch либо отдельном opt-in на периодический импорт;
- UI до подключения и fetch объясняет поля, цель, retention, доступ и удаление;
- event storage никогда не содержит credentials, authorization headers,
  cookies, request bodies и чувствительные query values;
- query redaction предшествует сохранению и показу;
- capability отделяет просмотр от управления;
- retention, hard cap, source-specific clear, disconnect и uninstall дают
  ограниченный жизненный цикл;
- `readme.txt` и Privacy Policy Guide описывают внешний сервис и персональные
  данные;
- exporter/eraser review учитывает, что стандартный WordPress API ищет записи
  по email, а access log часто не содержит корректной связи с email;
- документация плагина не назначает владельцу сайта правовое основание и не
  заявляет автоматическую compliance.

## 5. Учётные данные

API token является секретом подключения. Он хранится отдельно от event storage
в option с отключённым autoload, передаётся только по документированному
механизму authentication и не возвращается в HTML после сохранения. Token
исключается из URL, журналов, ошибок, Site Health, privacy export, diagnostics,
fixtures, CI и release evidence. Disconnect и uninstall удаляют его.

Интерфейс сообщает, что фактический scope токена определяет Hosting Ukraine.
Формулировка least privilege применяется только после подтверждения доступных
scope в актуальной API-документации.

## 6. Решение по документам

| Документ | Решение |
| --- | --- |
| `CONSTITUTION.md` | Сохранить редакцию `0.1.0`; конфликт отсутствует |
| `governance/PROFILE.md` | Поднять до `0.2.0` и учредить два источника |
| WordPress legislation | Поднять до `0.2.0`; добавить external-service, credentials и privacy нормы |
| Implementation brief | Поднять до `0.2.0`; добавить provider, UI, tests и документацию |
| Release gate | Добавить provider terms, personal-data и secret checks |
| Suite Protocol | Сохранить без изменения; источник логов не меняет межплагинный протокол |

## Дополнение от 2026-09-16

Решение ics-decision-request-log-ux-001 разрешает read-only поиск ID по домену и
opt-in периодический импорт. Исходные выводы о manual-only границе заменены этим
решением. Проверка нового authenticated lookup, consent UI, WP-Cron и локализации
остаётся обязательной перед release; актуальный контракт хранится в API-CONTRACT.
