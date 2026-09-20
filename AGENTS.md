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


## The rules of the package

What no map can say is a rule in `src/`, registered by `Plugin` and named `symfony/<slug>`:
`symfony/valueResolverForArgumentResolver`. The data hold no entry for a use such a rule converts, or the two
would report it twice. A rule has fixtures in `tests/fixtures/<slug>/`, and the samples run it together with the rules
the data feed.
