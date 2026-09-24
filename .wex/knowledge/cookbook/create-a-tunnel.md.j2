# Create a tunnel

A tunnel is three things: a manager naming it and its first step, one service per step, and a controller giving it a URL. The demo package `wexample/symfony-tunnels-demo` holds a complete one.

## Steps

A step extends `AbstractTunnelStep`, has a name, and lists what may follow it. An entry is either a step, or an array giving the step, options and a cursor name:

```php
class IntroStep extends AbstractTunnelStep
{
    public function __construct(private readonly ChoiceStep $choiceStep)
    {
    }

    public static function getName(): string
    {
        return 'intro';
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [
            ['step' => $this->choiceStep, 'name' => 'plan-free', 'options' => ['plan' => 'free']],
            ['step' => $this->choiceStep, 'name' => 'plan-paid', 'options' => ['plan' => 'paid']],
        ];
    }
}
```

The same step twice gives two cursors: the visitor picks one by following its URL, which carries the options in `?cursor-options[...]`.

The hooks worth knowing, all taking the cursor:

- `completeStrategy()` — `ON_INIT` by default: the step is done as soon as it is shown. `ON_NEXT_INIT` waits for the next step, `MANUAL` leaves it to the step, `ON_NEXT_REDIRECT(_RECURSIVE)` completes it when it redirects to its own children.
- `needsRedirect()` — return a cursor to move within the tunnel, a `RedirectResponse` to leave it, or null to stay.
- `selectNextCursor()` — which child "next" leads to; the first by default.
- `allowAccessOf()` — veto walking back through this step once it is done.
- `buildLabel()` — the text the stepper shows.

Steps keep what they need as variables: `$cursor->setVariableValue()` for this cursor only, `$cursor->manager->setVariableValue()` for the whole session. Going back before a step drops its cursor variables.

## Form steps

A step asking for input extends `AbstractFormTunnelStep` and names a `symfony-forms` processor:

```php
class ConfirmStep extends AbstractFormTunnelStep
{
    public function getFormProcessor(TunnelCursor $cursor): AbstractFormProcessor
    {
        return $this->formProcessor;
    }

    public function buildFormData(TunnelCursor $cursor): mixed
    {
        return ['name' => $cursor->getVariableValue('name')];
    }

    public function onFormValid(FormInterface $form, TunnelCursor $cursor): ?TunnelCursor
    {
        $cursor->setVariableValue('name', $form->get('name')->getData());

        return parent::onFormValid($form, $cursor);
    }
}
```

The template receives `form` and renders it with `form_load(render_pass, form, '<form template>')`. A valid submission moves to the next step, inside the modal when the tunnel was opened in one.

## Waiting for an outside event

A step that must wait — for a payment webhook, say — sets `$cursor->manager->setSessionStatus(TunnelSessionStatus::PENDING_ASYNC_ACTION)` and stores the identifier the event will carry, e.g. `$cursor->setVariableValue('payment-id', $id)`. The event handler resumes it:

```php
$tunnelResumeService->resumeByVariable('payment-id', $id, function (?TunnelCursor $cursor, AbstractTunnelManagerService $tunnel): void {
    $cursor->setComplete();
    $tunnel->setSessionComplete();
});
```

## Manager

```php
class DemoTunnelManagerService extends AbstractTunnelManagerService
{
    public function __construct(
        TunnelSessionStorageInterface $sessionStorage,
        private readonly IntroStep $introStep,
    ) {
        parent::__construct($sessionStorage);
    }

    public static function getName(): string
    {
        return 'demo';
    }

    public function getEntrypointStep(): AbstractTunnelStep
    {
        return $this->introStep;
    }
}
```

Declaring it as a service is enough: it is tagged and found by `TunnelRegistry`. `bin/console tunnels:info demo` prints its map. Values the tunnel needs when opened are declared in `getInitVariablesConfig()` and passed by the controller.

## Controller

```php
final class DemoTunnelController extends AbstractTunnelController
{
    public static function getTunnelManagerClass(): string
    {
        return DemoTunnelManagerService::class;
    }

    #[TunnelRoute]
    public function index(Request $request): Response
    {
        return $this->handleTunnelRequest($request);
    }
}
```

This gives the route `tunnel_demo_index` on `/tunnel/demo-tunnel/{step?}`. `#[TunnelRoute]` also takes a `name`, a `pathPrefix` and a `pathSuffix`.

## Templates

Each step renders `tunnels/<tunnel>/<step>.html.twig` from the controller's front directory — `@front` for an application controller, the bundle's `assets/` for a bundle controller. The template gets `tunnel`, `tunnelStep` and `tunnelCursor`, and can include:

```twig
{%- include '@WexampleSymfonyTunnelsBundle/partials/tunnel-navigation.html.twig' -%}
{%- include '@WexampleSymfonyTunnelsBundle/partials/tunnel-buttons.html.twig' -%}
```

`tunnel_cursor_url(cursor)` gives the URL of any cursor.

## Application setup

Import the routes, and create the two tables:

```yaml
# config/routes/symfony_tunnels.yaml
symfony_tunnels:
    resource: '@WexampleSymfonyTunnelsBundle/Resources/config/routes.yaml'
```

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```
