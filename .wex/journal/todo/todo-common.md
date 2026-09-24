# Common ground for any agent extracting a package into the PHP suite

Opened: 2026-09-24
Updated: 2026-09-24

This file holds what every extraction todo would otherwise repeat: where you stand, the
conventions the mature packages already follow, and the way of working expected of you. A
per-package todo (`extract-from-*.md` next to this file) says *what* to build; this one
says *how it must look once built*. Read both before writing a line.

Paths below are written relative to the PHP suite root — `PACKAGES/PHP/packages/wexample/`
— unless said otherwise. Your own package is one directory under it.

## 1. Where you stand

Around 45 packages, two families: `php-*` (framework-free: helpers, api, date, file, html,
yaml, pseudocode) and `symfony-*` (bundles). They are not peers — there is a spine, and it
runs in this order:

```
php-helpers ─ php-date ─ php-file ─ php-html ─ php-pseudocode
   └─ symfony-helpers ─ symfony-translations ─ symfony-dev
        └─ symfony-loader ─ symfony-routing ─ symfony-template
             └─ symfony-design-system
                  └─ symfony-forms, symfony-api, and the business packages
                     (accounting, cart, money, stripe, user, geo, content, …)
```

The suite is globally mature — `symfony-helpers` (162 php files), `symfony-loader` (85),
`symfony-api` (73), `symfony-design-system` (58), `symfony-forms` (42) are the reference
for style and wiring. What is not mature is the package *you* are extracting into: the
archaeology targets are nearly empty, a bundle class and an extension, sometimes nothing
else. So the asymmetry to keep in mind is this one: **the suite around you is the
reference, your own package is the thing being brought up to it.** Where a todo written
against the legacy code contradicts what a suite package ships today, the suite wins —
say so rather than silently following the todo.

Before writing a class, check a sibling does not already ship it:

```bash
wex app::source/search    --query <symbol> --scope suite
wex app::knowledge/search --query <subject> --scope stack
wex app::journal/search   --query <subject> --scope stack
```

## 2. Nomenclature

Run `wex ai::design/rules --formatter php-code` (and `--formatter javascript-code` if you
touch `assets/`) before deciding on any file layout — it is the authority, this section is
the summary.

- PSR-4 `Wexample\SymfonyXxx\` → `src/`, one class per file, file name = class name.
- Kinds live in their own directory under `src/`: `Class/`, `Helper/`, `Traits/`,
  `Interface/`, `Enum/`. A trait next to the class that uses it is the case to move.
- Suffix when the kind is the point: `ArrayHelper`, `HasNameTrait`, `AbstractBundle`,
  `PageService`. Services end in `Service`, base classes start with `Abstract`.
- Helpers are stateless: `public static` only, no constructor. One that needs state is a
  class and belongs in `Class/`.
- Routes carry their nature in the **path**, never in the name: `/_forms/submit` is a
  machine endpoint, `/forms/submit` is a page someone opens; the route name is
  `forms_submit` in both cases, never `_forms_submit`.
- Bundle: `src/WexampleSymfonyXxxBundle.php` extending
  `Wexample\SymfonyHelpers\Class\AbstractBundle`, plus
  `src/DependencyInjection/WexampleSymfonyXxxExtension.php` and
  `src/Resources/config/services.yaml`. Implement `LoaderBundleInterface` and return
  `getLoaderFrontPaths()` if the package ships templates or assets;
  `PseudocodeBundleInterface` if it ships exported entities.
- No `App\` namespace anywhere in `src/`, with the single exception of the suite's
  `App\Entity\User` convention for a user relation. `grep -r "use App\\\\" src` is the check.

## 3. Entities

The canonical example is `symfony-money/src/Entity/Currency.php`. Copy its shape:

```php
#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'currency')]
#[PseudocodeExport(inherited: true)]
class Currency extends AbstractEntity
```

- Extend `Wexample\SymfonyHelpers\Entity\AbstractEntity` — it gives a `Uuid` id in v7,
  never an autoincrement int.
- Reuse the `Has*Trait` family in `symfony-helpers/src/Entity/Traits/` before declaring a
  property by hand: `HasNameTrait`, `HasDateCreatedTrait`, `HasJsonDataTrait`,
  `LinkedToUserTrait`… there are several dozen and the list is the first thing to read.
- One repository per entity in `src/Repository/`.
- Enums in `src/Enum/`, never a bag of class constants, unless the mature neighbours in
  that package do otherwise.

## 4. The entity export pipeline (PHP → TS)

An entity is not finished when its PHP class compiles. If JS is ever going to read it, the
generated artefacts belong **in the package**, not in the app that installs it. Documented
at `symfony-api/.wex/knowledge/built/en/usage/entity-export-pipeline.md`; run from an app,
with the paths pointed at the package symlinked in its `vendor/`:

```bash
PKG=vendor/wexample/symfony-xxx
php bin/console pseudocode:generate:pseudocode $PKG/pseudocode $PKG/src -r
php bin/console api:export:entities --source=$PKG/pseudocode/entity --output=$PKG/assets/data/entity
node node_modules/@wexample/js-api/bin/generate-entities.mjs     --data-dir=$PKG/assets/data/entity --output-dir=$PKG/assets
node node_modules/@wexample/js-api/bin/generate-repositories.mjs --data-dir=$PKG/assets/data/entity --output-dir=$PKG/assets
```

Resulting layout, again `symfony-money`: `assets/data/entity/<entity>.json`,
`assets/Entity/<Entity>.ts`, `assets/Repository/<Entity>Repository.ts`. Three traps: the
source argument is `src`, not `src/Entity`; the JS scripts only read `--option=value`; the
pseudocode command also picks up the app's `additionalSources`, so delete foreign `.yml`
before exporting.

DTOs are a different thing from these generated entities: they are hand-written, live in
`src/Api/Dto/`, extend `AbstractDto` / `AbstractEntityDto` from `symfony-api`, and exist
only where an HTTP payload has a shape of its own.

Generate them for every entity you add, without asking: having the whole model available
in TypeScript from the start costs nothing and spares a round trip the day a front needs
it.

## 5. Front assets

`assets/` at the package root, not under `src/`, declared through
`LoaderBundleInterface::getLoaderFrontPaths()`. TypeScript follows its own design rules:
one class per file as `export default`, classes in `Common/` (or the package's existing
`Class/`, `Services/`, `Interfaces/` split — follow the neighbours), helper functions
grouped by the type they act on in `Helper/` as named exports prefixed by their subject
(`arrayShallowCopy`), no barrel files.

Do not re-port legacy front code. `symfony-loader` already ships `AdaptiveService`,
`AdaptiveClient`, `Form`; `symfony-design-system` ships the components.

## 6. Demo pages

A package proves it plugs into the design system by showing pages, and those pages do not
live in the package itself: they live in a sibling `<package>-demo`, installed by the
showcase app `MOJOE/local/design-system`. The model is
`symfony-design-system-demo` — read it before writing yours.

- Its bundle implements `LoaderBundleInterface`, `PseudocodeBundleInterface` and
  `DesignSystemElementsBundleInterface`, all three pointing at `assets/` at the package
  root.
- Controllers sit under `src/Controller/Pages/DesignSystem/`, carry a `#[Route(name:
  'wexample_design_system_…_', path: AbstractDesignSystemController::CONTROLLER_BASE_ROUTE
  . '/…')]` and `#[TemplateBasedRoutes]` from `symfony-routing`, which generates a route
  per template; the actions call `renderPage()`.
- Demo entities are prefixed `Demo` (`DemoRoom`, `DemoMessage`) and stay in the demo
  package — never in the runtime one.
- `composer.json` declares a `path` repository on `../*` with `symlink: true`, so the demo
  consumes the package being written.

The owner sets the demo package up (repository, composer wiring, install in the app). Your
part is the pages. Ship a deliberately empty one first and check it renders in the app
before building anything on it: wiring is where demo packages fail, not content.

## 7. Tests

Model: `symfony-loader/`. A package tests itself, against its own fixture kernel — never
against an app.

- `phpunit.xml` at the root, `bootstrap="tests/bootstrap.php"`, `APP_ENV=test` and
  `KERNEL_CLASS` pointing at `Wexample\SymfonyXxx\Tests\Fixtures\App\AppKernel`.
- Two suites: `tests/Unit`, `tests/Integration`. Fixtures in `tests/Fixtures/`
  (`App/` for the kernel, `front/` and `assets/` when templates are involved). SQLite.
- `composer.json`: `autoload-dev` maps `Wexample\SymfonyXxx\Tests\` → `tests/`,
  `require-dev` takes `phpunit/phpunit` and `wexample/symfony-testing`.
- The first commit of an extraction should already boot that kernel. A step whose tests do
  not run is a step not delivered.

## 8. Documentation

Sources are Jinja: `.wex/knowledge/**/*.md.j2`; the rendered prose under
`.wex/knowledge/built/<lang>/` is what gets read, and what you read yourself in other
packages. Four standard pages, matching the mature packages:
`readme/introduction`, `readme/installation`, `usage/overview`,
`contributing/architecture`. Write the architecture page the way
`symfony-loader/.wex/knowledge/built/en/contributing/architecture.md` is written: follow a
request through the layers, name real files, no marketing.

`README.md` stays short and points at the knowledge. `AGENTS.md` and `CLAUDE.md` are
generated by wex — do not edit them.

## 9. Way of working

- **A todo is a proposal, not an order.** It was written by an archaeology pass that read
  the legacy code, not by the person who wrote that code. Discuss it, challenge the design
  choices, get them validated, then build.
- One commit per step, each one leaving the package installable and its tests green.
  Stop and report at the checkpoints the todo names, rather than running the whole list.
- Report every divergence between the todo and the suite as it stands — a moved
  dependency, a class that now lives elsewhere, an API that changed. That feedback is
  half the value of the pass.
- Extraction means *the engine*, not the app that used it. Business steps, hard-coded
  entity ids, app-specific listeners stay out.
- `NETWORK/` is a read-only archive over production data. Read it; never run anything in
  it, never copy a secret out of it, anonymise any fixture taken from it.
- Do not port commented-out code, `*.txt` leftovers, or stubs. Dead code that reached the
  archive was already dead.
- Version bumps and `version.txt` / `composer.json` `version` releases are the owner's
  call, not part of a step.
