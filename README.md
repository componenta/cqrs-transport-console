# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport` v5 and Componenta App Console 5 / Config 3.

```bash
composer require componenta/cqrs-transport-console
```

The package targets the transport v5 worker contract. `WorkerCommand` builds a fail-closed `CommandWorker` and requires:

- `CommandBusInterface`;
- `CommandSerializerInterface`;
- `OperationContextSerializerInterface`;
- `TransportRegistryInterface`;
- `CommandMetadataProviderInterface` (defaults to `ReflectionCommandMetadataProvider`);
- optional `LoggerInterface`.

The Componenta Composer plugin loads the provider automatically. For a manual provider list, load it after `componenta/cqrs`, `componenta/cqrs-transport`, and `componenta/app-console`:

```php
return [
    new Componenta\CQRS\App\Transport\Console\ConfigProvider(),
];
```

The provider adds `Componenta\CQRS\App\Command\Transport\Console\WorkerCommand` to `console.commands`; the class also declares Symfony's `#[AsCommand(name: 'cqrs:worker')]` metadata.

The command argument is the **logical transport name**. `WorkerCommand` resolves that name through `TransportRegistryInterface` and passes both the transport object and the same name to `CommandWorker`. After deserializing the command and before dispatch, the worker verifies that the command's `#[Async(...)]` metadata targets exactly that transport. A command declared for `payments` therefore cannot be dispatched by an `emails` worker merely because a message appeared in the wrong queue. Command serializers remain responsible for safely constructing allowed command types.

The transport package supplies the default `OperationContextSerializerInterface`. Applications may replace it with an allowlisted serializer when operation attributes such as tenant, trace, or locale context must cross the async boundary.

`--time-limit` measures elapsed runtime with PHP's monotonic high-resolution clock, including the fractional-second component, so wall-clock adjustments and second boundaries do not prematurely expire the limit. Limits are checked between deliveries; they do not interrupt an active command or guarantee a hard deadline during blocking transport calls or idle sleep. Command, time, memory, and idle-sleep limits are all non-negative integer options.

Install the package only when the transport registry and command serializer are configured. It intentionally has no default queue or command serializer policy.

Run:

```bash
php bin/console.php cqrs:worker [transport]
```

The default logical transport name is `default`.
