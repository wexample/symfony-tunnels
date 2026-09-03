# symfony_tunnels

Version: 2.0.1

symfony-tunnels provides an abstract framework for building sequential multi-step flows — "tunnels" — in Symfony applications: a manager service orchestrates the ordered list of steps, persists progress in a `TunnelSession` entity, and enforces access control so a user cannot reach step N until step N−1 is marked complete. Individual steps extend `TunnelStep` and optionally mix in `FormTunnelStepTrait` to handle form submission within a step. It is aimed at Symfony developers implementing wizard-style processes such as checkout flows, onboarding sequences, or any user journey that must advance in strict order.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

## Architecture

The package is a Symfony bundle that provides an abstract framework for sequential multi-step flows. Application code subclasses one manager service and one step class per step; the bundle supplies the plumbing that connects them.

### Bundle bootstrap

src/WexampleSymfonyTunnelsBundle.php extends `AbstractBundle` from `wexample/symfony-helpers` and is the Symfony bundle entry point. It carries no logic of its own.

src/DependencyInjection/WexampleSymfonyTunnelsExtension.php calls `$this->loadConfig(__DIR__, $container)`, which loads src/Resources/config/services.yaml. That file registers everything under `src/Service/` with autowiring and autoconfiguration, tagged `controller.service_arguments`.

### Manager: `AbstractTunnelManagerService`

src/Service/AbstractTunnelManagerService.php is the orchestrator. Application code creates one concrete subclass per tunnel and must implement:

```php
public static function getTunnelName(): string;
```

The manager holds the ordered list of steps, set once via `setTunnelSteps(array $steps)`, which assigns each step its zero-based position and back-reference to the manager.

**`handleRequest(string $controllerClassName, Request $request, ?string $stepName): Response|TunnelStep|null`** is the single entry point a controller calls. Its sequence:

1. No `$stepName` → redirect to step 0.
2. Resolve a `TunnelStep` by matching `$stepName` against each step's static `$name`.
3. `initSession()` — find or create a `TunnelSession` record in the database; store its ID in the PHP session under the key `tunnel-{tunnelName}`. An existing session is reused only when it is not completed, is less than one day old, the client IP has not changed, and the authenticated user matches.
4. `preventAccess()` — scan all steps whose position precedes the requested one; if any is incomplete, redirect to the earliest incomplete step (or step 0 if none found).
5. If the step is the first, clear the `completed` tracking object stored in the session.
6. Delegate to `$step->handleRequest($request)`.

Navigation helpers (`adaptiveRedirectToNext`, `redirectToNext`, `redirectToOffset`, `redirectToStep`, `buildStepUrl`) are all thin wrappers that resolve the target `TunnelStep` by position offset and generate its URL.

The manager keeps two separate stores:

- **PHP browser session** — `getBrowserSessionVariable` / `setBrowserSessionVariable`, keyed under `tunnel-{tunnelName}` in `Request::getSession()`. Holds the tunnel-session DB id and any transient data.
- **Database session** — `getTunnelSessionVariable` / `setTunnelSessionVariable`, serialised into the `TunnelSession` entity's `data` field via `TunnelSessionCrudService`.

### Step: `TunnelStep`

src/Service/Step/TunnelStep.php is the abstract base for every step. A step owns:

| Member | Purpose |
|---|---|
| `static string $name` | URL slug and session key |
| `int $position` | Zero-based index, set by the manager |
| `AbstractTunnelManagerService $manager` | Back-reference |

**`handleRequest(Request $request): self|Response|null`** returns `$this` by default, signalling the controller to render the step's template. Returning a `Response` redirects or terminates the request.

**Access control** is enforced by `preventAccess()`, which delegates to `allowDirectAccess()`. The default rule: step 0 is always accessible; any later step requires its predecessor to be complete. Override `redirectToStepPosition(): ?int` to force a redirect from within a step regardless of completion state.

**Completion** is tracked as a `completed` object stored in the database session. `setCompleted()` writes `true` at key `step-{position}`; `isCompleted()` reads it back.

**Template resolution**: `getView()` returns `tunnels/{tunnelName}/{stepName}.html.twig`. `getViewParams()` passes `['tunnel' => $this->getManager()]`.

**Translation domain**: `getTranslationDomain()` returns `tunnels.{tunnelName}.{stepName}`, which `trans(string $key)` prefixes when building translation keys.

### Form integration traits

When a step needs to handle a Symfony form, two traits wire the step to an application-side form processor.

src/Service/Step/Traits/FormTunnelStepTrait.php is mixed into the step subclass. Its `init()` method calls `$this->getFormProcessor()->setTunnelStep($this)`, creating the cross-reference. It overrides `handleRequest()`:

- **GET** → `$formProcessor->createForm()`
- **POST** → `$formProcessor->handleSubmission($request)`

After either branch it calls `onFormRender(FormInterface $form)`, then asks the processor for a response via `handleSubmissionResponseFromForm($form)`. If the processor returns a `Response` (a redirect on success), that is returned; otherwise `$this` is returned so the controller renders the template. `getViewParams()` is extended to inject `form` (a `FormView`) alongside `tunnel`.

The hooks `onFormRender(FormInterface $form)` and `onFormValid(FormInterface $form)` are empty by default and intended for override.

src/Service/FormProcessor/Traits/TunnelFormProcessorTrait.php is mixed into the application-side form processor. It holds `?TunnelStep $tunnelStep` and routes the processor's own lifecycle callbacks (`onRender`, `onValid`) back into the step's hooks:

```php
public function onRender(FormInterface $form): void
{
    $this->getTunnelStep()->onFormRender($form);
}

public function onValid(FormInterface $form): void
{
    $this->getTunnelStep()->onFormValid($form);
}
```

### Call path through a request

```
Controller
  └─ AbstractTunnelManagerService::handleRequest()
       ├─ (no step) → RedirectResponse to step 0
       ├─ getStepByName()          resolve TunnelStep
       ├─ initSession()            find/create TunnelSession in DB
       ├─ preventAccess()          redirect if prerequisites unmet
       └─ TunnelStep::handleRequest()
            ├─ (plain step)        return $this  →  controller renders getView()
            └─ (FormTunnelStepTrait)
                 ├─ GET  createForm()
                 ├─ POST handleSubmission() → onFormValid() → setCompleted() + redirect
                 └─ return $this or Response
```

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- wexample/symfony-helpers: >=7.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
