# InstantCMS JSON API client class

Класс для работы с API с сайта на базе InstantCMS

## Установка

Файл api.php скопировать по пути /system/core/api.php

## Использование

Заполнить константы api_key, api_point, api_point_execute своими данными.
Создать директорию /cache/api/. В ней будут кэшироваться ответы.

Вызовы можно осуществлять из любого места кода InstantCMS

Обычные методы
```php
// Без кэширования ответа
$result = cmsApi::getMethod('auth.login', ['sig' => 'qwerty', 'email' => 'test@example.com', 'password' => '123456']);
// С кэшированием ответа
$result = cmsApi::getMethod('auth.login', ['sig' => 'qwerty', 'email' => 'test@example.com', 'password' => '123456'], true);
```

Метод execute
```php
$result = cmsApi::getExecute([
[
    'method' => 'geo.get',
    'key'    => 'countries',
    'params' => [
        'type' => 'countries'
    ]
],
[
    'method' => 'users.get_user',
    'key'    => 'profile',
    'params' => [
        'user_id' => $user_id
    ]
]
]);
```

## Ссылки

* [Официальный сайт InstantCMS](https://instantcms.ru/)
* [Документация компонента](https://docs.instantcms.ru/manual/components/api)