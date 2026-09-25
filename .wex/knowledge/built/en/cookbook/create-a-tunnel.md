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

The template receives `form` and renders it with `form_load(render_pass, form, '<form template>')`. The form type adds no submit button: `tunnel-buttons.html.twig` renders the next button of a form step as its submit, pointing at the form tag from outside through the HTML `form` attribute, so the way on stays in the same place on every step. It reads « Next »; a step asking for a payment says so by returning another translation key from `buildSubmitLabel()`. A valid submission moves to the next step, inside the modal when the tunnel was opened in one.

## Waiting for an outside event

A step that must wait — for a payment webhook, say — sets `$cursor->manager->setSessionStatus(TunnelSessionStatus::PENDING_ASYNC_ACTION)` and stores the identifier the event will carry, e.g. `$cursor->setVariableValue('payment-id', $id)`. The event handler resumes it:

```php
$tunnelResumeService->resumeByVariable('payment-id', $id, function (?TunnelCursor $cursor, AbstractTunnelManagerService $tunnel): void {
    $cursor->setComplete();
    $tunnel->setSessionComplete();
});
```

## Session expiration

A session nobody walks expires a day after the last step displayed. The tunnel changes that for all its steps, a step for the time the visitor stands on it — the one holding a room or a stock asks for minutes:

```php
public function getSessionExpiration(TunnelCursor $cursor): string
{
    return '15 minutes';
}
```

Expired sessions are dropped every 15 minutes by the scheduler, which calls `onSessionDestroy()` on every step first: that is where a held stock is given back.

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

A tunnel is mounted in the pages it belongs to: its controller carries the class-level `#[Route]` of those pages, and the tunnel follows their URL, their route names, their menu and their breadcrumb.

```php
#[Route(path: 'tunnels/plan/', name: 'tunnels_plan_')]
final class PlanTunnelController extends AbstractTunnelController
{
    public static function getTunnelManagerClass(): string
    {
        return DemoTunnelManagerService::class;
    }

    #[TunnelRoute(cursorPlaceholder: '{step}')]
    public function index(Request $request): Response
    {
        return $this->handleTunnelRequest($request);
    }
}
```

This gives the route `tunnels_plan_index` on `/tunnels/plan/{step}`: the steps stand below the page `/tunnels/plan`. Each step is given its label as `page_title`, which the layout titles the page with and the breadcrumb ends on. A controller without a class-level `#[Route]` gets `tunnel_<tunnel>_index` on `/tunnel/<controller>/{step?}` instead. `#[TunnelRoute]` also takes a `name`, a `pathPrefix` and a `pathSuffix`.

## Templates

Each step renders `tunnels/<tunnel>/<step>.html.twig` from the controller's front directory — `@front` for an application controller, the bundle's `assets/` for a bundle controller. The template gets `tunnel`, `tunnelStep` and `tunnelCursor`, and can include:

```twig
{%- include '@WexampleSymfonyTunnelsBundle/partials/tunnel-navigation.html.twig' -%}
{%- include '@WexampleSymfonyTunnelsBundle/partials/tunnel-buttons.html.twig' -%}
```

On a last step, `tunnel-timeline.html.twig` lists the way the visitor came, one entry per step; a step says what was done on it by returning a line from `buildSummary()`.

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

The purge of expired sessions runs on the scheduler: the application needs a worker consuming its transport, one long-running process — a container of its own under Docker.

```bash
php bin/console messenger:consume scheduler_default
```

`bin/console tunnels:purge` runs it by hand.

Crawlers are kept out of the tunnels through the `/robots.txt` of `symfony-seo`, once its routes are imported: every tunnel route is disallowed by the fixed start of its path, wherever it is mounted.
