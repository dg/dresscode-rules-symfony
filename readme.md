[Upgrading Symfony with DressCode](https://dresscode.run/upgrading-symfony)
===========================================================================

[![Latest Stable Version](https://poser.pugx.org/dresscode/upgrade-symfony/v/stable)](https://packagist.org/packages/dresscode/upgrade-symfony)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://packagist.org/packages/dresscode/upgrade-symfony)

 <!---->

<h3>

✅ Upgrades code written for Symfony 5.4 [to 6, 7 and 8](#symfony-components-and-versions-covered)<br>
✅ Turns [SensioFrameworkExtraBundle annotations](#what-gets-upgraded) into attributes<br>
✅ Reports [what needs a human](#what-is-left-to-you), with what to write instead<br>
✅ Checked [against the tests of Symfony itself](#where-the-data-come-from)

</h3>

 <!---->

**Upgrade a Symfony application without hunting for renames.** Install this package, run `dresscode upgrade`, and code
written for older versions of Symfony is rewritten to the API of the versions your project stands on:

```diff
- protected static $defaultName = 'app:report';
- protected static $defaultDescription = 'Sends the daily report';
+ #[AsCommand(name: 'app:report', description: 'Sends the daily report')]

- $request = $this->requestStack->getMasterRequest();
+ $request = $this->requestStack->getMainRequest();

- /** @Security("is_granted('ROLE_ADMIN')") */
+ #[IsGranted('ROLE_ADMIN')]

- return JsonResponse::create(['name' => $user->getUsername()]);
+ return new JsonResponse(['name' => $user->getUserIdentifier()]);

- class OrderPlacedHandler implements MessageHandlerInterface
+ #[AsMessageHandler]
+ class OrderPlacedHandler
```

Two static properties became one attribute of the class, the expression of `@Security` became the role of
`#[IsGranted]`, the imports were rewritten along with them, and not a byte more of the file touched. The package
holds the data, what each version of Symfony renamed, moved or retired; the rewriting is done by
[DressCode](https://dresscode.run), the PHP coding standard and upgrade tool, which comes with it.

 <!---->

Installation and first run
==========================

**1️⃣ Install it: `composer require --dev dresscode/upgrade-symfony`**<br>
**2️⃣ Upgrade the code: `vendor/bin/dresscode upgrade`**<br>
**3️⃣ Read the diff, run your tests and PHPStan, and commit**

The package brings DressCode along, and `dresscode upgrade` takes it as it finds it, with no configuration.
Most of the rewrites need to know what a variable is, which DressCode asks your PHPStan, so the project needs
`phpstan/phpstan` installed. The style of the code stays as it is: with a `dresscode.neon`, the upgrade keeps of
its coding standard what the code already follows, and without one it changes nothing but the old API. Where it
rewrote something, run it again, as the new code may hold more to upgrade.

To have every `dresscode check` and `dresscode fix` watch for the old API too, in CI or in a Git hook, turn the
upgrade on in `dresscode.neon`; the upgrade is the set `deprecations`, with the types of the code:

```neon
typeAnalysis: phpstan

use:
	- upgrade-symfony
	- deprecations
```

DressCode can keep the style of the code as well: its standard `symfony` writes the Symfony Coding Standards as the
`@Symfony` set of PHP CS Fixer has them, so `use: [upgrade-symfony, symfony, deprecations]` gives one run that
both formats and upgrades.

 <!---->

What gets upgraded
==================

None of it is search and replace. DressCode asks PHPStan what each variable is, so `getUsername()` is rewritten where
it is called on a user and nowhere else. PHPStan answers with your `phpstan.neon` and its extensions, the one for
Symfony included. What the package rewrites:

- **renamed classes and interfaces**: `Symfony\Component\Security\Core\Security` to
  `Symfony\Bundle\SecurityBundle\Security`, `UsernameNotFoundException` to `UserNotFoundException`, the encoders of
  `security-core` to the password hashers, the bridge of Sendinblue to Brevo, with the import, the type, `new`,
  `instanceof` and attributes,
- **renamed members**: `getMasterRequest()` to `getMainRequest()`, `HttpKernelInterface::MASTER_REQUEST` to
  `MAIN_REQUEST`, `getUsername()` to `getUserIdentifier()`, `Application::add()` to `addCommand()`,
- **calls of another shape**: the static `create()` of responses to `new`, a renamed parameter
  (`new UriSigner($secret, parameter: ...)` to `hashParameter:`), a renamed key of an array of options, `ref()` of the
  container configuration to `service()`,
- **annotations of SensioFrameworkExtraBundle** to the attributes of the framework: `@Route`, `@Cache`, `@IsGranted`,
  `@Template`, and `@Security` whose expression only calls `is_granted()`,
- **other annotations to attributes**: those of routing and the serializer, the constraints of the validator and
  `@UniqueEntity`, and `@required` to `#[Required]`,
- **a member of a class to an attribute**: `$defaultName` and `$defaultDescription` of a command, and an overridden
  `getDefaultName()`, to `#[AsCommand]`, the interface `MessageHandlerInterface` to `#[AsMessageHandler]`,
- **resolvers of controller arguments** written against `ArgumentValueResolverInterface` to `ValueResolverInterface`,
  `supports()` becoming the guard at the top of `resolve()`,
- **signatures** of children of framework classes, the return types and parameters a newer version declares
  (`execute(): int` of a command), written from the installed code with no data needed.

The rewritten code takes the shape of the file around it, or of your coding standard, if the configuration names one.

 <!---->

What is left to you
===================

Where there is no replacement, or one that needs a decision, DressCode does not guess. It reports the place and says
what to write instead:

```
Method `Symfony\Bundle\FrameworkBundle\Controller\AbstractController::getDoctrine()` is forbidden: use a constructor or action argument typed with `Doctrine\Persistence\ManagerRegistry`
Method `Symfony\Component\HttpFoundation\Request::get()` is forbidden: read the bag the value comes from, the `attributes`, `query` or `request` property, such as `query->get()`
```

An upgrade is a rewrite plus a list of what remains, and the list is half of its value. Every such sentence is
written as an instruction, so the list can be worked through by you or handed to an AI coding agent as it is.

A few rewrites could change what the code does, such as a return type added to a method whose body may return
something else. Those wait for your consent: `dresscode upgrade --ask-risky` asks about each one with its diff,
`--fix-risky` makes them all.

 <!---->

One major version at a time
===========================

The data of each component apply only when your project has it, and only the sections of the versions it stands
on: the lowest version the constraint in `composer.json` allows. A project requiring `symfony/symfony` has every
component it replaces. So an upgrade goes the way you would do it by hand: raise the constraint to the next major
version, `composer update`, `dresscode upgrade`, tests, commit, and on to the next one. In each step the data meet the
installed components, whose `@deprecated` annotations tell DressCode what the next version will remove.

Code can also be upgraded before the components are, with the key `targets`, which names the version to write for:

```neon
targets:
	symfony/http-kernel: '7.4'
	symfony/security-core: '7.4'
```

`dresscode config` shows for every component the version the data start from and the versions a further update
would lead to.

 <!---->

Where the data come from
========================

The data come from the `UPGRADE-*.md` guides of Symfony, each entry checked against the code of the component at
its tags: where the guide and the code differ, the code decides. They are checked against the installed components,
so a replacement that does not exist cannot get in, and each component has a sample of old code that the data must
turn into the expected new code.

Then the data run over the tests of the components themselves, code written for the current API, and must leave
them exactly as they are. An entry that would touch correct code does not get in.

 <!---->

Compared to Rector
==================

[Rector](https://getrector.com) is the usual answer to an upgrade, and its sets for Symfony reach further back than
this package, which starts at 6.0. The difference is in the shape of the work. Where Rector changes the structure of
a statement, it prints the statement anew, so it needs a coding standard tool after it, and a rule of Rector either
rewrites the code or says nothing. DressCode is one tool, with one configuration and one diff: the rewrite keeps the
formatting of everything around it, and what cannot be rewritten is reported with what to do. It is also
[faster](https://github.com/dg/dresscode#performance-php-cs-fixer-php_codesniffer), the types from PHPStan included.

An entry is a line of text, whatever it rewrites:

```neon
since 7.4:
	replacedMembers:
		Symfony\Component\Console\Application::add: addCommand

since 6.1:
	attributeForMember:
		Symfony\Component\Console\Command\Command::$defaultName: 'Symfony\Component\Console\Attribute\AsCommand(name: $value)'
```

 <!---->

Symfony components and versions covered
=======================================

More than 650 entries, from Symfony 6.0 to the current version, for these components: `symfony/asset`,
`symfony/asset-mapper`, `symfony/browser-kit`, `symfony/cache`, `symfony/config`, `symfony/console`,
`symfony/dependency-injection`, `symfony/doctrine-bridge`, `symfony/dom-crawler`, `symfony/event-dispatcher`,
`symfony/expression-language`, `symfony/filesystem`, `symfony/finder`, `symfony/form`, `symfony/framework-bundle`,
`symfony/html-sanitizer`, `symfony/http-client`, `symfony/http-foundation`, `symfony/http-kernel`, `symfony/intl`,
`symfony/json-streamer`, `symfony/lock`, `symfony/mailer`, `symfony/messenger`, `symfony/mime`,
`symfony/monolog-bridge`, `symfony/notifier`, `symfony/options-resolver`, `symfony/property-access`,
`symfony/property-info`, `symfony/routing`, `symfony/security-bundle`, `symfony/security-core`,
`symfony/security-csrf`, `symfony/security-http`, `symfony/serializer`, `symfony/string`, `symfony/translation`,
`symfony/twig-bridge`, `symfony/type-info`, `symfony/validator`, `symfony/var-dumper`, `symfony/var-exporter` and
`symfony/workflow`, and the bridges `symfony/brevo-mailer`, `symfony/brevo-notifier`, `symfony/google-chat-notifier`,
`symfony/mail-pace-mailer`, `symfony/sevenio-notifier` and `symfony/slack-notifier`.

The data of a retired bridge are written under the one that replaced it, so those of `symfony/sendinblue-mailer`
apply to a project that has `symfony/brevo-mailer`, and the annotations of SensioFrameworkExtraBundle under the
components whose attributes replace them. The [manual](https://dresscode.run/upgrading-symfony) lists the versions
of each.

 <!---->

Limits
======

- **Most rewrites need PHPStan.** `dresscode upgrade` does not run without it, and `check` and `fix` without
  `typeAnalysis: phpstan` rewrite only classes, functions and annotations.
- **DressCode runs on PHP 8.4 to 8.6.** The code it upgrades may be written for PHP 8.0 and newer, but the package is
  installed into the project, so the project has to install on PHP 8.4 or newer.
- **The data fix the code, not its meaning.** A change of behavior, the configuration in YAML and Twig templates are
  not in the data; the `UPGRADE-*.md` guides of Symfony describe them. Run your tests after a fix.

 <!---->

Other ecosystems
================

| package | upgrades |
|---|---|
| [`dresscode/upgrade-nette`](https://github.com/dg/dresscode-upgrade-nette) | every Nette library, most from 3.0 |
| [`dresscode/upgrade-symfony`](https://github.com/dg/dresscode-upgrade-symfony) | the components and bridges of Symfony, from 6.0 |
| [`dresscode/upgrade-laravel`](https://github.com/dg/dresscode-upgrade-laravel) | the Laravel framework from 6, and the attributes of Laravel 13 |
| [`dresscode/upgrade-deegee`](https://github.com/dg/dresscode-upgrade-deegee) | Dibi and Texy |

A package like these can be written for any library; the [manual](https://dresscode.run/upgrading-data) says how.

 <!---->

Development
===========

The components the data are about are in `require-dev`, so the data are checked against their installed versions:

- `php tests/check.php <component>` lints `upgrading/<component>.neon` and runs its sample,
  `tests/samples/<component>.code`, comparing the result with `.expected` and `.violations`,
- `php tests/check.php <component> --update` writes those two from the run; read the diff, it is what the data do,
- `vendor/bin/tester tests` runs all of it,
- `php tests/corpus.php <symfony>/src/Symfony <package>...` runs the data over the tests of the components, which
  they must leave as they are.
