<?php
// Uncomment to enable debug mode. Recommended for development.
//defined('YII_DEBUG') or define('YII_DEBUG', true);
//
//// Uncomment to enable dev environment. Recommended for development
//defined('YII_ENV') or define('YII_ENV', 'prod');
//
//// zh_CN.UTF-8 => 中文,  en_US.UTF-8 => English
//setlocale(LC_ALL, 'zh_CN.UTF-8');
//putenv('LC_ALL=zh_CN.UTF-8');

return [
    'components' => [
        /*'db' => [
            'dsn'       => isset($_ENV['WALLE_DB_DSN'])  ? $_ENV['WALLE_DB_DSN']  : 'mysql:host=127.0.0.1;dbname=walle',
            'username'  => isset($_ENV['WALLE_DB_USER']) ? $_ENV['WALLE_DB_USER'] : 'root',
            'password'  => isset($_ENV['WALLE_DB_PASS']) ? $_ENV['WALLE_DB_PASS'] : 'PassWord123@',
        ],*/
        /*'mail' => [
            'transport' => [
                'host'       => isset($_ENV['WALLE_MAIL_HOST']) ? $_ENV['WALLE_MAIL_HOST'] : 'smtp.sina.com',     # smtp 发件地址
                'username'   => isset($_ENV['WALLE_MAIL_USER']) ? $_ENV['WALLE_MAIL_USER'] : 'tang1988918@sina.com',  # smtp 发件用户名
                'password'   => isset($_ENV['WALLE_MAIL_PASS']) ? $_ENV['WALLE_MAIL_PASS'] : 'tang1988918!',       # smtp 发件人的密码
                'port'       => isset($_ENV['WALLE_MAIL_PORT']) ? $_ENV['WALLE_MAIL_PORT'] : 25,                       # smtp 端口
                'encryption' => isset($_ENV['WALLE_MAIL_ENCRYPTION']) ? $_ENV['WALLE_MAIL_ENCRYPTION'] : 'tls',                    # smtp 协议
            ],
            'messageConfig' => [
                'charset' => 'UTF-8',
                'from'    => [
                    (isset($_ENV['WALLE_MAIL_EMAIL']) ? $_ENV['WALLE_MAIL_EMAIL'] : 'tang1988918@sina.com') => (isset($_ENV['WALLE_MAIL_NAME']) ? $_ENV['WALLE_MAIL_NAME'] : 'roobo'),
                ],  # smtp 发件用户名(须与mail.transport.username一致)
            ],
        ],*/
        'mail' => [
            'transport' => [
                'host'       => isset($_ENV['WALLE_MAIL_HOST']) ? $_ENV['WALLE_MAIL_HOST'] : 'smtp.exmail.qq.com',     # smtp 发件地址
                'username'   => isset($_ENV['WALLE_MAIL_USER']) ? $_ENV['WALLE_MAIL_USER'] : 'tangzhihui@roobo.com',  # smtp 发件用户名
                'password'   => isset($_ENV['WALLE_MAIL_PASS']) ? $_ENV['WALLE_MAIL_PASS'] : 'Tang1988918!',       # smtp 发件人的密码
                'port'       => isset($_ENV['WALLE_MAIL_PORT']) ? $_ENV['WALLE_MAIL_PORT'] : 25,                       # smtp 端口
                'encryption' => isset($_ENV['WALLE_MAIL_ENCRYPTION']) ? $_ENV['WALLE_MAIL_ENCRYPTION'] : 'tls',                    # smtp 协议
            ],
            'messageConfig' => [
                'charset' => 'UTF-8',
                'from'    => [
                    (isset($_ENV['WALLE_MAIL_EMAIL']) ? $_ENV['WALLE_MAIL_EMAIL'] : 'tangzhihui@roobo.com') => (isset($_ENV['WALLE_MAIL_NAME']) ? $_ENV['WALLE_MAIL_NAME'] : 'roobo'),
                ],  # smtp 发件用户名(须与mail.transport.username一致)
            ],
        ],
        /*'request' => [
            'cookieValidationKey' => 'PdXWDAfV5-gPJJWRar5sEN71DN0JcDRV',
        ],*/
    ],
    'language'   => isset($_ENV['WALLE_LANGUAGE']) ? $_ENV['WALLE_LANGUAGE'] : 'zh-CN', // zh-CN => 中文,  en => English
];
