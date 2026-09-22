# To My Agents!

It is my fervent wish that this file guide every AI coding agent working with code in this repository.


## What this is

Data for DressCode: what the versions of the Symfony components renamed, moved and retired, and what to write
instead. One file per Composer package, `upgrading/<component>.neon`, listed under `extra.dresscode.upgrading` of
`composer.json`; the components themselves are in `require-dev`, each on its own, so that the data are checked
against what is installed. DressCode is required as `dresscode/dresscode`.

Where an entry comes from is the upgrade guide of the Symfony monorepo (`UPGRADE-<version>.md`, the part under the
heading of the component), checked against the code of the component at its tags: the tag decides, not the guide.


## Essential commands

- `php tests/check.php <component>`: the lint of one file and its sample; the verdict is the exit code.
- `php tests/check.php <component> --update`: writes `tests/samples/<component>.expected` and `.violations` from the
  run. Always read the diff of `.code` against `.expected`: a wrong fix recorded there is a wrong fix tested ever
  after.
- `vendor/bin/tester tests`: every file and every sample.
- `php tests/corpus.php <symfony>/src/Symfony <package>...`: the data over the tests of the components themselves,
  code written for the current API, which they must leave as they are except the tests of `@group legacy`; take the
  checkout at the tag of the installed version. A component is not done until this passes over its tests.
  `--rename=<Class::member>=<name>` adds an entry wrong on purpose, which must make it fail.
- After a change of DressCode itself, `composer reinstall dresscode/dresscode`; the path repository is a copy.


## Writing the data

- A file starts with `package: vendor/name` and `group: deprecations`, the intent of its data, and goes on with
  sections `since <version>`, newest first, the version without trailing zeros (`since 7.1`). The order decides
  nothing, the sections are merged by version. The unit is the Composer package, not the component of the guide:
  `framework-bundle` and `security-bundle` have files of their own, and the data of a retired bridge go into the file
  of the bridge that replaced it (Sendinblue under `brevo-mailer`), since that is what a project upgrading has.
- Decide by what happens to the code, not by the label of the guide: a member called differently and used the same
  way is `replacedMembers`, one used differently but writable as one expression is `replacedCalls`, a Sensio
  annotation or attribute the framework reads as its own attribute is `attributeForAnnotation`, a member or an
  interface a class declared for the framework to find it, which reads an attribute of the class now
  (`$defaultName` as `#[AsCommand]`), is `attributeForMember`, what has no replacement or one of another nature
  (another type, another behavior) is `forbiddenClasses` or `forbiddenMembers` with a sentence, and a change of
  behavior, of configuration, of a Twig template, an `@internal` symbol or a signature changed only for types is
  nothing.
- A signature a child has to follow (`execute(): int`, a parameter renamed) needs no data: `overrideSignature`
  writes it from the declaration of the installed version.
- What a component deprecates only silently does not go into `forbidden-*`; `noDeprecatedClasses` and
  `noDeprecatedMembers` report it from the types.
- A key with parentheses or a dollar goes in apostrophes. `Class::name()` is a call without arguments,
  `Class::name(...$args)` one with any, `Class::NAME` without a lower-case letter a constant only,
  `Class::$name::set` a write of the property alone.
- What a later version took back gets `keep` in the section of that version. A chain across versions is written link
  by link; the lint follows it to its end.
- A sentence of `forbidden-*` is English, completes `… is forbidden:` and says what to write instead: lower case
  unless it begins with a name, its code in backticks as in the rest of the message, no period or double quotes, at
  most 160 characters without the backticks, which the lint checks. A replacement is `<verb> <API>[, which <how it differs>]`, none is `there is no replacement[, <why>][; <what
  to do>]`, and nothing to write is `…; drop the call`.
- A sample holds code of the old API the way an application writes it, a child overriding a method among it, and
  every shape the data fix or report; it may declare classes of its own, which is how a component that is no longer
  installed is reached.


## The rules of the package

What no map can say is a rule in `src/`, registered by `Plugin` and named `symfony/<slug>`:
`symfony/valueResolverForArgumentResolver` and `symfony/isGrantedForSecurityAnnotation`. The data hold
no entry for a use such a rule converts, or the two would report it twice. A rule has fixtures in
`tests/fixtures/<slug>/`, and the samples run it together with the rules the data feed.
