# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport` v5. `main` is the console integration v3 line.

```bash
composer require componenta/cqrs-transport-console
```

The package targets the transport v5 worker contract. `WorkerCommand` builds a fail-closed `CommandWorker` and requires:

- `CommandBusInterface`;
- `CommandSerializerInterface`;
- `OperationContextSerializerInterface`;
- `TransportRegistryInterface`;
- `CqrsMapProviderInterface`;
- optional `LoggerInterface`.

The Componenta Composer plugin loads the provider automatically. For a manual provider list, load it after `componenta/cqrs`, `componenta/cqrs-transport`, and `componenta/app-console`:

```php
return [
    new Componenta\CQRS\App\Transport\Console\ConfigProvider(),
];
```

The provider adds `Componenta\CQRS\App\Command\Transport\Console\WorkerCommand` to `console.commands`; the class also declares Symfony's `#[AsCommand(name: 'cqrs:worker')]` metadata.

The command argument is the **logical transport name**. `WorkerCommand` resolves that name through `TransportRegistryInterface` and passes both the transport object and the same name to `CommandWorker`. Before hydration, the worker verifies that the command's compiled `#[Async(...)]` metadata targets exactly that transport. A command declared for `payments` therefore cannot be hydrated by an `emails` worker merely because a message appeared in the wrong queue.

The transport package supplies the default `OperationContextSerializerInterface`. Applications may replace it with an allowlisted serializer when operation attributes such as tenant, trace, or locale context must cross the async boundary.

`--time-limit` measures elapsed runtime with PHP's monotonic high-resolution clock, so wall-clock adjustments do not extend or shorten a running worker. Command, time, memory, and idle-sleep limits are all non-negative integer options.

Install the package only when the transport registry and command serializer are configured. It intentionally has no default queue or command serializer policy.

Run:

```bash
php bin/console.php cqrs:worker [transport]
```

The default logical transport name is `default`.
