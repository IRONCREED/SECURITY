# Проверка внешнего сервиса и приватности

Статус проверки: 2026-08-28.

Область: `IRONCREED Request Log` 1.0, WordPress Runtime Source и
`HostingUkraineApiProvider`.

Документ фиксирует продуктовую и техническую проверку требований публикации.
Владелец конкретного сайта определяет применимую юрисдикцию, правовое основание
и содержание собственного privacy notice. Проверка не заменяет индивидуальную
юридическую консультацию.

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
ручного импорта.

Публичная оферта определяет API как часть панели управления услугами абонента.
Доступная публичная документация не содержит отдельной лицензии для стороннего
API-клиента и не раскрывает полный контракт метода без авторизации. Перед
релизом разработчик проверяет текущие endpoint, authentication, request/response
schema, limits, scope токена и условия использования в аутентифицированной
документации. Неясное право распространённого стороннего клиента закрывается
письменным подтверждением Hosting Ukraine до подачи в WordPress.org.

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
- Hosting Ukraine вызывается только после настройки и ручного действия;
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
