# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport`.

```bash
composer require componenta/cqrs-transport-console
```

The Componenta Composer plugin loads the provider automatically. For a manual provider list, load it after `componenta/cqrs`, `componenta/cqrs-transport`, and `componenta/app-console`:

```php
return [
    new Componenta\CQRS\App\Transport\Console\ConfigProvider(),
];
```

The provider adds `Componenta\CQRS\App\Command\Transport\Console\WorkerCommand` to `console.commands`; the class also declares Symfony's `#[AsCommand(name: 'cqrs:worker')]` metadata. Vendor classes are not part of application source discovery, so the explicit console registration is required.

The command constructor requires `CommandBusInterface`, `CommandSerializerInterface`, and `TransportRegistryInterface`. Install the package only when those transport services are configured; it intentionally has no default queue or serializer policy.

Run `php bin/console.php cqrs:worker [transport]`. The default transport name is `default`.
