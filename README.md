# Componenta CQRS Transport Console

Symfony Console worker command for `componenta/cqrs-transport`.

```bash
composer require componenta/cqrs-transport-console
```

The supported application generations are:

```text
componenta/app-console 2.x  <->  componenta/cqrs 2.0.1+
componenta/app-console 3.x  <->  componenta/cqrs 3.0.0+
```

These pairs reflect their shared DI generation; they are not a Cartesian compatibility matrix. The adapter supports transport v2.0.1+, v3.0.0+, and the current transport v4 API. Composer also enforces the CQRS constraint declared by the selected transport release, so only mutually compatible versions are installed.

Transport v2.0.0 is intentionally not supported: it predates the command metadata allowlist accepted by `CommandWorker`, so it cannot provide the fail-closed worker path required by this adapter. `app-console` v1 is not declared compatible because it belongs to the older `componenta/config` v1 dependency generation.

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

The console worker path is fail-closed on every supported transport version because it always supplies the command metadata allowlist explicitly. Current transport v4 makes that allowlist mandatory in `CommandWorker::__construct()`; v3 fails closed when it is omitted and exposes `CommandWorker::unsafe()` as the explicit trusted-transport bypass. Transport v2.0.1 has the older nullable constructor form, but this console adapter always supplies the allowlist and never uses the unrestricted path.

Install the package only when the transport registry and serializer are configured. It intentionally has no default queue or serializer policy.

Run `php bin/console.php cqrs:worker [transport]`. The default transport name is `default`.
