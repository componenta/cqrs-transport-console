# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport`.

```bash
composer require componenta/cqrs-transport-console
```

The package supports `componenta/app-console` v2/v3, Componenta CQRS v2/v3, the published transport v2/v3 APIs, and the current transport v4 API. `app-console` v1 is not declared compatible because it belongs to the older `componenta/config` v1 dependency generation.

The Componenta Composer plugin loads the provider automatically. For a manual provider list, load it after `componenta/cqrs`, `componenta/cqrs-transport`, and `componenta/app-console`:

```php
return [
    new Componenta\CQRS\App\Transport\Console\ConfigProvider(),
];
```

The provider adds `Componenta\CQRS\App\Command\Transport\Console\WorkerCommand` to `console.commands`; the class also declares Symfony's `#[AsCommand(name: 'cqrs:worker')]` metadata. Vendor classes are not part of application source discovery, so the explicit console registration is required.

The command constructor requires:

- `CommandBusInterface`;
- `CommandSerializerInterface`;
- `TransportRegistryInterface`;
- a complete `CommandMetadataProviderInterface` allowlist.

The safe worker path is always fail-closed. There is no nullable metadata-provider fallback in the console command; unrestricted worker construction is available only through the explicit `CommandWorker::unsafe()` API for integrity-protected trusted transports.

Install the package only when the transport registry and serializer are configured. It intentionally has no default queue or serializer policy.

Run `php bin/console.php cqrs:worker [transport]`. The default transport name is `default`.
