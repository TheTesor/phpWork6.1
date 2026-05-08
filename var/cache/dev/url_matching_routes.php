<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/api/login' => [[['_route' => 'api_login', '_controller' => 'App\\Controller\\AuthController::login'], null, ['POST' => 0], null, false, false, null]],
        '/api/token/refresh' => [[['_route' => 'api_token_refresh', '_controller' => 'App\\Controller\\AuthController::refresh'], null, ['POST' => 0], null, false, false, null]],
        '/api/logout' => [[['_route' => 'api_logout', '_controller' => 'App\\Controller\\AuthController::logout'], null, ['POST' => 0], null, false, false, null]],
        '/api/password/change' => [[['_route' => 'api_password_change', '_controller' => 'App\\Controller\\AuthController::changePassword'], null, ['POST' => 0], null, false, false, null]],
        '/api/devices' => [[['_route' => 'api_devices', '_controller' => 'App\\Controller\\DeviceController::index'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
    ],
    [ // $dynamicRoutes
    ],
    null, // $checkCondition
];
