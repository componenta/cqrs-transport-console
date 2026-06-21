# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport`.

```bash
composer require componenta/cqrs-transport-console
```

Register the provider:

```php
return [
    new Componenta\CQRS\App\Transport\Console\ConfigProvider(),
];
```

The package registers `Componenta\CQRS\App\Command\Transport\Console\WorkerCommand` as an autowired service. The command name is `cqrs:worker`.