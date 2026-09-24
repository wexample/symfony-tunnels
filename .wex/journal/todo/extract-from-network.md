# Rebuild symfony-tunnels from network's v2 cursor-tree engine

Opened: 2026-09-24
Updated: 2026-09-24
Author: agent:archeology

## Read this first — status of this todo

> **This is a proposal for discussion, not an order to code.** It was written by the 2026-09 network archaeology pass. Read it, then discuss it with the owner: every design choice and recommendation below is to be challenged and validated **before** any code is written. Do not start implementing on your own.
>
> - Context: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/index.md.j2` (entry point, order between packages), then `sources.md.j2` (where the legacy code lives: archive repo, branch checkouts, GitLab issues) and the domain page linked below.
> - Pending owner decisions affecting this work are listed in `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/recap.md.j2`, section "Décisions qui t'attendent". Where this todo assumes an answer, treat it as an open question.
> - Safety: `NETWORK/local/network` runs on **production data** (real bookkeeping, real invoices in `var/`, a prod dump in `.wex/mysql/dumps/`) — read its code only, never run anything against it. Anonymize any fixture taken from network (bank exports, FEC, mails contain real names/accounts). Never copy secrets found in its history (Stripe keys, tokens, passwords, private keys).

## Goal

The package currently holds the **linear v1** tunnel code. It still imports 9 `App\` classes and calls APIs that no longer exist, so it cannot run anywhere. app-board registers the bundle but uses nothing from it.

Replace that code with the **v2 engine** from network's branch `develop-131-fos-user` (issue #314, Oct–Dec 2022). v2 provides:
- a tree of step cursors
- option negotiation
- completion strategies and redirects
- DB-stored sessions and variables
- attribute routes
- a tracer, a CLI command and a debug panel

Plug form steps and modal rendering into **symfony-forms** and **symfony-loader** as they exist today. Do not re-port network's legacy JS.

Background, algorithms, file inventory and known bugs:
`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/local/network/.wex/knowledge/readme/archeology/tunnel.md.j2`. Read it first; this todo assumes it.

Issues: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/gitlab/issues/` 314 (tunnel tests spec), 299, 055, 156 (zero-amount payment), 037, 050, 053, 133 (modals / nested forms).

Source root (read-only), abbreviated `FOS` below:
`/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/archeo/trees/develop-131-fos-user`

## Prerequisites / dependencies

- `wexample/symfony-helpers`: `AbstractEntity` (UUID v7), `Has*Trait`, `Routing/AbstractRouteLoader`, `ClassHelper`, `AbstractBundle`.
- `wexample/symfony-loader`: `Controller/AbstractPagesController`, `AdaptiveResponseService` (`__layout`), `RenderPass`, Twig `form_load`, JS `assets/js/Class/Form.ts`, `Services/AdaptiveService.ts`. Also `tests/Fixtures/App`, the model for a package test kernel.
- `wexample/symfony-forms`: `Service/FormProcessor/AbstractFormProcessor` (`onValid`, `setSuccessAction`, `redirect`, `ACTION_*`), `Attribute/FormProcessor`, `EventSubscriber/FormProcessorRequestSubscriber`, `FormResponsePayloadBuilder`.
- `wexample/symfony-translations`: `Translation/Translator` (only `DOMAIN_SEPARATOR` exists).
- Doctrine ORM and Twig are needed for the entities and templates. Add them to composer `require` the way sibling packages do; check `symfony-forms/composer.json`.
- Run `wex ai::design/rules --formatter php-code` and `--formatter javascript-code` in this package. Apply the rules:
  - one class per file
  - kinds in `Class/ Helper/ Traits/ Interface/ Enum/`
  - route names without a leading underscore; paths of machine endpoints start with `_`

## Decisions already implied by the owner

- Extract **v2** (fos-user). v1 (prod, vendor 0.1.19, current package content) is superseded: delete it. app-board has no tunnel, so nothing breaks.
- Plug into existing symfony-forms and symfony-loader mechanisms rather than porting `AdaptiveResponse`/`ModalsService`/`form.ts` from network.
- Commerce steps (cart, addresses, Stripe pay, login, user-mail, membership, campaign, product) are **not** part of this package.
- DB-defined tunnels + dynamic forms are an **optional later phase** (step 11), pending the owner's go.

## Steps (small, verifiable; commit per step)

1. **Clean slate.**
   - Delete `src/Service/AbstractTunnelManagerService.php`, `src/Service/Step/TunnelStep.php`, `src/Service/Step/Traits/FormTunnelStepTrait.php` and `src/Service/FormProcessor/Traits/TunnelFormProcessorTrait.php`.
   - Keep the bundle and extension.
   - Set up `tests/` with a fixture kernel copied in spirit from `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-loader/tests/Fixtures/App`, using SQLite, with one smoke test that boots the kernel.
   - Update README/knowledge later (step 12).

2. **Entities and enums.**
   - `Entity/TunnelSession`: status, `tunnel` name, `lastAccessedCursorHash`, `hash`, nullable user, anonymized IP, `browserData`, `dateCreated`.
   - `Entity/TunnelSessionVariable`: name, value, nullable `cursorHash`, `initial`, `dateCreated`.
   - Repositories for both.
   - `Enum/TunnelSessionStatus` (opened, completed, pending_async_action).
   - Fixes to apply:
     - `hash` = `bin2hex(random_bytes(16))`, not `uniqid()`
     - values stored as JSON (or `unserialize` with `allowed_classes: false`)
     - UUID ids
   - Read:
     - `FOS/src/Entity/TunnelSession.php`, `FOS/src/Entity/TunnelSessionVariable.php`
     - `FOS/src/Repository/TunnelSessionRepository.php`, `FOS/src/Repository/TunnelSessionVariableRepository.php`
     - `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-helpers/src/Entity/Traits/LinkedToUserTrait.php` (suite convention for the user relation).

3. **Cursor and step core, no HTTP.**
   - `Class/TunnelCursor`, renamed from `TunnelStepCursor`. Name the step property `step` instead of `service`.
   - `Service/Step/AbstractTunnelStep` (live lines 1–431 only).
   - Tree building: `createCursor`, `getAllowedNextSteps` (service or `{service, options, name}`), `alterNextStepAllowedFollowings`, and a stable hash. Add a cycle guard that throws.
   - Enums `TunnelStepCompleteStrategy` (on_init, on_next_init, on_next_redirect, on_next_redirect_recursive, manual) and `TunnelStepRedirectStrategy` (any_previous_incomplete, direct_previous_incomplete).
   - **Make steps stateless:** pass the cursor (which holds the manager) to every hook instead of `setManager()`/`getManager()` on shared services.
   - Fix these cursor bugs (see "Pitfalls" in the knowledge page):
     - `forEachCursorBetween` by-reference flag
     - `hasAllPreviousCursorComplete` recursion
     - `findFirstNextByStepOption` must match all options
     - `hasSameOptions` strict and two-way
     - `hasNext` returns bool
   - Read:
     - `FOS/src/Class/TunnelStepCursor.php`
     - `FOS/src/Wex/BaseBundle/Service/Tunnel/Step/TunnelStep.php`
     - `FOS/src/Wex/BaseBundle/Service/Tunnel/AbstractTunnelManagerService.php` (lines 1–879 only; everything after is dead)

4. **Manager, session service and variables.**
   - `Service/AbstractTunnelManagerService`: entrypoint, cursors registry, `selectCurrentCursor` negotiation (options, last accessed path, `redirects-to`, closest to root), `selectNextStepCursor`, completion/redirect helpers.
   - Init variables config (`type`, `required`, `default`, `autoInit`), with UUID-aware storage of entities.
   - `Service/TunnelSessionService`: find by hash (check the tunnel name **and** the user), by PHP-session id, or create. Plus 1-day expiry, `tunnelSessionRecreate`, and purge with the `onSessionDestroy` hook.
   - Variables get/set/remove, with global = `cursorHash IS NULL`, **not** "any cursor".
   - Fix the IP-check no-op and the null-user crash.
   - Read `FOS/src/Wex/BaseBundle/Service/Tunnel/AbstractTunnelManagerService.php` (1–879) and `FOS/src/Service/Tunnel/TunnelsRegistry.php`.
   - **Tests:** port the test tunnel as fixtures in `tests/Fixtures`: StepOne, StepTwo, StepThree, StepThreeBis, StepFour, and a manager. Read `FOS/src/Service/Tunnel/TestTunnelService.php` and `FOS/src/Service/Tunnel/Test/*.php`, without `StepWithEntity` for now. Write unit tests for:
     - tree shape (StepTwo has 3 cursors; StepThreeBis has 3 named variants)
     - hash stability
     - variables (global vs cursor)
     - completion strategies
     - `needsRedirect` defaults
     - reset of the abandoned branch (`onPreviousStepLoading`)
     - session expiry and recreate
   - Use `FOS/tests/Unit/Tunnel/TestTunnelTest.php` for intent only; it is broken.

5. **Registry, navigation, tracing, CLI.**
   - `Service/TunnelRegistry` fed by a DI tag, auto-tagged for every `AbstractTunnelManagerService`. It replaces `TunnelsRegistry` + `AbstractTunnelSelectorService`.
   - `getTreePaths`, `tunnelKnowNextSteps`, `getTreeSections`, `Service/TunnelTracingService`, `Command/TunnelInfoCommand` (`tunnel:info <name>`).
   - Read `FOS/src/Wex/BaseBundle/Service/Tunnel/{AbstractTunnelSelectorService,TunnelTracingService}.php`, `FOS/src/Command/Tunnel/TunnelInfoCommand.php`, `FOS/src/Service/Tunnel/TunnelSelectorService.php`.
   - **Tests:** known-next-steps output for each test-tunnel position; `tunnel:info` output snapshot.

6. **Routing.**
   - `Attribute/TunnelRoute(name, pathPrefix, pathSuffix, cursorPlaceholder='{step?}')`. It should also carry or derive the manager class, so the route name does not rely on a matching `#[Route(name:)]` prefix.
   - `Routing/TunnelRouteLoader` extends symfony-helpers `Routing/AbstractRouteLoader` and auto-discovers controllers carrying the attribute. Keep the path shape `tunnel/<controller-kebab-without-suffix>/<prefix>/{step?}/<suffix>`.
   - `Service/TunnelRoutingService` (`buildStepCursorUrl`, which adds `cursor-options` when a cursor has options; route params = manager + step params).
   - `Twig/TunnelExtension` (`tunnel_step_cursor_url`, `tunnel_debug_paths_data`).
   - Read `FOS/src/Wex/BaseBundle/Routing/AbstractTunnelRouteLoaderService.php`, `FOS/src/Wex/BaseBundle/Api/Attribute/TunnelControllerRoute.php`, `FOS/src/Routing/TunnelRouteLoader.php`, `FOS/src/Wex/BaseBundle/Service/Tunnel/TunnelRoutingService.php`, `FOS/src/Wex/BaseBundle/Twig/TunnelExtension.php`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-helpers/src/Routing/AbstractRouteLoader.php`.
   - **Tests:** the fixture controller generates `/tunnel/test-tunnel/with/prefix/{step?}`; URL building with options.

7. **Controller.**
   - `Controller/AbstractTunnelController` extends symfony-loader `AbstractPagesController`.
   - `handleTunnelRequest(manager, request, initVariables)`: purge, init variables (404 on error), entrypoint, parse `cursor-options`, session, guess cursor, `needsRedirect` (Response or cursor, with completion on redirect), `initAsCurrentStep`, render. With no step, redirect to the entrypoint; otherwise 404.
   - View resolution: default `tunnels/<manager name>/<step name>.html.twig`, following the loader's front path conventions. Check how `renderPage`/`buildControllerTemplatePath` resolve `@front/...` and align with that.
   - Redirects must keep the `__layout` query param, so a tunnel opened in a modal stays in the modal.
   - Read `FOS/src/Controller/Tunnels/AbstractTunnelController.php`, `FOS/src/Controller/Tunnels/TestTunnelController.php`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-loader/src/Controller/AbstractPagesController.php`, `.../symfony-loader/src/Service/AdaptiveResponseService.php`.

8. **Form steps on symfony-forms.**
   - `Service/Step/AbstractFormTunnelStep` with `getFormProcessor(cursor)`, `buildFormData(cursor)` and `onFormValid(form, cursor): ?TunnelCursor`. The default marks the cursor complete and returns `selectNextStepCursor`.
   - `Traits/TunnelFormProcessorTrait`, for processors extending `Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor`. Its `onValid()` calls the step and maps the result:
     - next cursor + embedded layout (modal/panel/overlay/embed, from `AdaptiveResponseService`) → `setSuccessAction(['type' => ACTION_EMBED_REDIRECT, 'url' => next])`
     - next cursor + full page → `redirect(next)`
     - null → `ACTION_EMBED_STAY`
   - Step forms post to the step URL: the form class must not set `$ajax = true`.
   - For JSON POSTs, the controller returns `FormResponsePayloadBuilder::build()`, the same as `FormProcessorRequestSubscriber`. Reuse that builder; do not re-implement it.
   - Read `FOS/src/Wex/BaseBundle/Service/Tunnel/Step/TunnelFormStep.php`, `FOS/src/Wex/BaseBundle/Service/FormProcessor/Traits/TunnelFormProcessorTrait.php`, `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/PACKAGES/PHP/packages/wexample/symfony-forms/src/Service/FormProcessor/AbstractFormProcessor.php`, `.../symfony-forms/src/EventSubscriber/FormProcessorRequestSubscriber.php`.
   - Reference app usage: `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/SERVICES/local/app-board/src/Controller/Pages/App/ProcessController.php`, `.../src/Service/FormProcessor/ProcessCreateFormProcessor.php`, `.../front/pages/app/process/create.html.twig`.

9. **Templates and assets.**
   - A tunnel base layout in the package assets, with these blocks:
     - side list from `tunnelKnowNextSteps` (classes previous/same/next/unknown, link only when `linkCursor`)
     - `previous_next_buttons` with `button_previous` / `button_next` and `step_has_button_previous|next` = true/false/'auto'
     - step content with a title from translation key `tunnels.<folder>.<step>` → `title` (check the key syntax in symfony-translations)
     - a form variant where the next button is the submit button
     - a debug panel (metro map, session, cursor, variables table) shown only in dev/test
   - When rendered in an embed, in-tunnel links must load inside the current embed. Use design-system `button_target` / `TargetHelper`, or one small TS class calling `AdaptiveService.get(url, {callerPage})`. Keep it minimal.
   - Port the SCSS layout idea only (side column + scrolling content; opacity per position).
   - Read `FOS/src/Wex/BaseBundle/Resources/templates/mains/tunnel.html.twig`, `FOS/front/mains/tunnel-form.html.twig`, `FOS/src/Wex/BaseBundle/Resources/css/mains/tunnel/_main.scss`, `FOS/front/tunnels/test/*.html.twig`.

10. **Generic async resume.**
    - `Service/TunnelResumeService::resumeByVariable(string $name, mixed $value, callable $onCursor)`:
      1. Load `pending_async_action` sessions holding that variable.
      2. Rebuild the manager: registry lookup by session tunnel name, then `autoInitVariables`, `createEntrypoint`, then the cursor from the variable's `cursorHash`.
      3. Call the callback, or a step interface method such as `onAsyncEvent`.
    - The Stripe `PaymentEvent` listener itself stays in the app.
    - Read `FOS/src/EventListener/PaymentTunnelEventListener.php` and `FOS/src/Service/Tunnel/Payment/Pay.php` (live code before the first `/*` block; `initAsCurrentStep`, `needsRedirect` with `finish-payment`, `onFormValid`, `onPaymentEvent`).
    - **Test:** a fixture step goes pending; resuming by variable completes the session.

11. **(Optional, only after owner approval) DB-defined tunnels.**
    - `Entity/Tunnel`, `Entity/TunnelStep` (self-referencing `previous`, `allowCompletedSession`, optional form), `Service/Step/AbstractEntityTunnelStep` (option `tunnel_step_entity_id`), `CustomTunnelService` + `DefaultStep`.
    - Dynamic forms (`Form`, `FormField`, `FormSubmission*`, `NeutralTunnelEntityForm`) must first exist in symfony-forms, and DB entity-field translations in symfony-translations. Coordinate; do not put them here.
    - Read `FOS/src/Wex/BaseBundle/Service/Tunnel/Step/TunnelEntityStep.php`, `FOS/src/Service/Tunnel/CustomTunnelService.php`, `FOS/src/Service/Tunnel/Custom/DefaultStep.php`, `FOS/src/Form/NeutralTunnelEntityForm.php`, `FOS/src/Entity/{Tunnel,TunnelStep,Form,FormField,FormSubmission,FormSubmissionField}.php`, `FOS/front/mains/tunnel-entity-form.html.twig`, `FOS/migrations/Version20221107195257.php` (the `recette-2022` acceptance questionnaire, a good demo fixture).

12. **Docs.**
    - Rewrite `README.md` and `.wex/knowledge/{readme/introduction,usage/overview,contributing/architecture}.md.j2` for v2.
    - Add a cookbook: "create a tunnel" (manager, steps, controller with `#[TunnelRoute]`, templates) and "open a tunnel in a modal".

## Do not

- Do not modify anything under `/home/weeger/Desktop/WIP/WEB/WEXAMPLE/NETWORK/` (read-only archive and production app). Never run network commands, migrations or tests.
- Do not port code that is commented out (the v1 blocks in the manager and `TunnelStep`), `*.txt`, `Step/Trait*/FormTunnelStepTrait.php`, the `Payment/Trait*` stubs or `AbstractPaymentTunnelService`. These are 2024 hard-merge residue.
- Do not port network's `AdaptiveResponse`, `AdaptiveEventsBag`, `ModalsService.ts`, `AjaxService.ts`, `components/form.ts`, or BaseBundle `AbstractFormProcessor`. Use symfony-loader and symfony-forms.
- Do not put cart, payment, Stripe, address, login, user-mail, membership, campaign or product steps in this package, and no hard-coded entity ids (tunnel 2/3, tunnel_step 3/4, product ids).
- Do not use the `App\` namespace, except for the suite's `App\Entity\User` convention if you keep a user relation.
- Do not store tunnel state in the PHP session beyond the session id.
- Do not keep `uniqid()` hashes, or `unserialize` without `allowed_classes`.

## Acceptance criteria (tests inside the package)

- The kernel boots with the bundle, and there are no `App\` imports (`grep -r "use App\\\\" src` is empty, except an optional `App\Entity\User`).
- Unit tests on the test-tunnel fixture:
  - tree shape and hashes
  - option negotiation: query options, last-path and `redirects-to` memory, closest-to-root tie-break
  - every completion strategy
  - default redirect strategies
  - `allowDirectAccess` with an `allowAccessOf` veto
  - reset of the abandoned branch on back navigation
  - global vs cursor variables
  - init-variable type checking
- Session tests: the same session is reused within a day; a new one is created after expiry, after completion, or on another tunnel's session id; the hash resume refuses another tunnel or another user; the purge calls `onSessionDestroy`.
- Functional tests (fixture HTTP client), mirroring `FOS/tests/Integration/Role/Anonymous/Controller/Tunnels/TestTunnelControllerTest.php`:
  - walk StepOne → StepTwo → each StepThreeBis variant → StepFour
  - back navigation
  - `?step-two-complete=1` redirect
  - `?step-four-redirects=1` external redirect
  - side list shows no clickable future steps
- Form step test: GET renders the form; an invalid POST re-renders it with errors; a valid POST gives a 302 to the next step. A JSON POST with `__layout=modal` gives a payload with `action.type = embed_redirect` and the next step URL.
- `tunnel:info test` prints the sections map.
- Async resume test (step 10).
- The README and knowledge pages describe v2.
