# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport` v5.

```bash
composer require componenta/cqrs-transport-console
```

The package targets the transport v5 worker contract. `WorkerCommand` builds a fail-closed `CommandWorker` and requires the same runtime services as that worker:

- `CommandBusInterface`;
- `CommandSerializerInterface`;
- `OperationContextSerializerInterface`;
- `TransportRegistryInterface`;
- `CqrsMapProviderInterface`;
- optional `LoggerInterface`.

The command-class allowlist comes from the active CQRS map, not from reflection metadata. The console integration does not expose an unrestricted worker path.

The Componenta Composer plugin loads the provider automatically. For a manual provider list, load it after `componenta/cqrs`, `componenta/cqrs-transport`, and `componenta/app-console`:

```php
return [
    new Componenta\CQRS\App\Transport\Console\ConfigProvider(),
];
```

The provider adds `Componenta\CQRS\App\Command\Transport\Console\WorkerCommand` to `console.commands`; the class also declares Symfony's `#[AsCommand(name: 'cqrs:worker')]` metadata. Vendor classes are not part of application source discovery, so the explicit console registration is required.

The transport package supplies the default `OperationContextSerializerInterface`. Applications may replace it with an allowlisted serializer when operation attributes such as tenant, trace, or locale context must cross the async boundary.

`--time-limit` measures elapsed runtime with PHP's monotonic high-resolution clock, so wall-clock adjustments do not extend or shorten a running worker. Command, time, memory, and idle-sleep limits are all non-negative integer options.

Install the package only when the transport registry and command serializer are configured. It intentionally has no default queue or command serializer policy.

Run:

```bash
php bin/console.php cqrs:worker [transport]
```

The default transport name is `default`.
