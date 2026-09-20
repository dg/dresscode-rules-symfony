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
- After a change of DressCode itself, `composer reinstall dresscode/dresscode`; the path repository is a copy.
