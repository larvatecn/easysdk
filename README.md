# EasySDK

抽取移植 EasyWechat 核心，去除无用模块，使其可以支持其他类型 SDK，作为第三方其他类型 HTTP SDK 的基包。

## Installation

```bash
composer require larva/easysdk -vv
```

## Usage

```php
$options = [
    //Http 配置
    'http' => [
        'max_retries' => 1,//失败重试次数
        'retry_delay' => 500,//重试延迟
        //'log_template' => '>>>>>>>>\n{request}\n<<<<<<<<\n{response}\n--------\n{error}',//日志模板
    ],
    // 日志配置
    'log' => [
        'default' => 'dev', // 默认使用的 channel，生产环境可以改为下面的 prod
        'channels' => [
            // 测试环境
            'dev' => [
                'driver' => 'single',
                'path' => 'easysdk.log',
                'level' => 'debug',
            ],
            // 生产环境
            'prod' => [
                'driver' => 'daily',
                'path' => 'easysdk.log',
                'level' => 'info',
            ],
        ],
    ],
];
$app = new \Larva\EasySDK\ServiceContainer($options);
// 一般是 继承 \Larva\EasySDK\ServiceContainer 类来扩展出API
```