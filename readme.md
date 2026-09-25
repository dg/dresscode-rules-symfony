# dresscode/rules-symfony

What the versions of the [Symfony](https://symfony.com) components renamed, moved and retired, and what to write
instead, as data for [DressCode](https://dresscode.run). With it installed, `dresscode fix` rewrites code
written for older versions of Symfony to the API of the versions the project stands on, and reports what has to be
rewritten by hand, with what to write instead.


Installation
------------

```shell
composer require --dev dresscode/rules-symfony
```

DressCode finds the package by itself. The data apply in the rules of the group `deprecations`, most of which need the
types of the code from PHPStan:

```neon
types: phpstan

groups:
	- deprecations
```

The data of a component apply only when the project has it, and only the sections of the versions the project stands
on: the lowest version its constraint in `composer.json` allows, or the version the key `packages` of the configuration
names. A project requiring `symfony/symfony` has every component it replaces in its version.

What the components changed in their signatures, the return types and the parameters a child of their classes has to
declare (`execute(): int` of a command), needs no data: the rule `overrideSignature` of the same group writes them,
and since the body may return something else, only with `dresscode fix --fix-risky`.

What no data can say, the package brings as rules of its own, turned on by the same group:
`symfony/valueResolverForArgumentResolver` makes a resolver of controller arguments implement
`ValueResolverInterface`, its `supports()` becoming the guard of `resolve()`, and
`symfony/isGrantedForSecurityAnnotation` writes the `@Security` annotation of SensioFrameworkExtraBundle asking
`is_granted()` as the attribute `#[IsGranted]`.

Components with data: `symfony/asset`, `symfony/asset-mapper`, `symfony/browser-kit`, `symfony/cache`,
`symfony/config`, `symfony/console`, `symfony/dependency-injection`, `symfony/doctrine-bridge`, `symfony/dom-crawler`,
`symfony/event-dispatcher`, `symfony/expression-language`, `symfony/filesystem`, `symfony/finder`, `symfony/form`,
`symfony/framework-bundle`, `symfony/html-sanitizer`, `symfony/http-client`, `symfony/http-foundation`,
`symfony/http-kernel`, `symfony/intl`, `symfony/json-streamer`, `symfony/lock`, `symfony/mailer`, `symfony/messenger`,
`symfony/mime`, `symfony/monolog-bridge`, `symfony/notifier`, `symfony/options-resolver`, `symfony/property-access`,
`symfony/property-info`, `symfony/routing`, `symfony/security-bundle`, `symfony/security-core`,
`symfony/security-csrf`, `symfony/security-http`, `symfony/serializer`, `symfony/string`, `symfony/translation`,
`symfony/twig-bridge`, `symfony/type-info`, `symfony/validator`, `symfony/var-dumper`, `symfony/var-exporter`,
`symfony/workflow`; bridges: `symfony/brevo-mailer`, `symfony/brevo-notifier`, `symfony/google-chat-notifier`,
`symfony/mail-pace-mailer`, `symfony/sevenio-notifier`, `symfony/slack-notifier`, each written under the one that
replaced the retired bridge (the data of `symfony/sendinblue-mailer` apply to a project that has `symfony/brevo-mailer`).


Development
-----------

The components the data are about are in `require-dev`, so the data are checked against their installed versions:

- `php tests/check.php <component>` lints `upgrading/<component>.neon` against the installed component and runs its
  sample, `tests/samples/<component>.code`, comparing the result with `.expected` and `.violations`,
- `php tests/check.php <component> --update` writes those two from the run; read the diff, it is what the data do,
- `vendor/bin/tester tests` runs all of it,
- `php tests/corpus.php <symfony>/src/Symfony <package>...` runs the data over the tests of the components themselves,
  which they must leave as they are.

How the keys and values are written is described on the page "Maps of replacements" of the DressCode manual.
