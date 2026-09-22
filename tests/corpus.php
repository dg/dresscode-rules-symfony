<?php declare(strict_types=1);

/**
 * Runs the upgrading files over code written for the current API, the tests of the Symfony components themselves,
 * which they must leave as they are, except the tests of a deprecated API (`@group legacy`), which use it on purpose:
 *
 *   php tests/corpus.php <dir of src/Symfony> <package>... [--rename=<Class::member>=<name>] [--keep]
 *
 * Copies the Tests directory of each package into corpus-<pid>/ of the root, fixes the copy with the upgrading files of
 * this package alone, and reports every file the run changed: `legacy` for a test of
 * a deprecated API, `CHANGED` with its diff for any other, `BROKEN` for one php -l refuses, and a second run must
 * change nothing. --rename adds an entry of replacedMembers that is wrong on purpose, for the harness to prove it
 * fails; --keep leaves the copy. The verdict is the exit code: 1 for a file CHANGED or BROKEN, or a second run that
 * changed anything.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/samples.php';

$root = dirname(__DIR__);
$args = array_slice($argv ?? [], 1);
$options = array_values(array_filter($args, fn(string $arg) => str_starts_with($arg, '--')));
$packages = array_values(array_diff($args, $options));
$source = array_shift($packages);
if ($source === null || $packages === [] || !is_dir($source)) {
	exit("Usage: php tests/corpus.php <dir of src/Symfony> <package>... [--rename=<Class::member>=<name>] [--keep]\n");
}

$dirs = findPackageDirs($source);
$name = 'corpus-' . getmypid(); // so that runs over several components do not meet
$corpus = "$root/$name";
Nette\Utils\FileSystem::delete($corpus);
foreach ($packages as $package) {
	if (!isset($dirs[$package])) {
		echo "Package $package is not in $source.\n";
		exit(1);
	}

	$tests = $dirs[$package] . '/Tests';
	Nette\Utils\FileSystem::copy($tests, "$corpus/" . str_replace('/', '-', $package));
}

$originals = hashFiles($corpus);
// the data alone: overrideSignature declares what a child may leave out, which is no mistake of the data
$rules = array_fill_keys(array_diff(UpgradingRules, ['overrideSignature']), true);
foreach ($options as $option) {
	if (preg_match('~^--rename=(.+::\w+)=(\w+)$~', $option, $m)) {
		$rules['replacedMembers'] = [$m[1] => $m[2]];
	}
}

$installed = array_filter(array_map(
	fn(array $package) => $package['version'],
	DressCode\Config\ProjectPackages::read($root)->installed,
));
Nette\Utils\FileSystem::write("$root/$name.neon", Nette\Neon\Neon::encode([
	'paths' => [$name],
	'fileExtensions' => ['php'],
	'types' => 'phpstan',
	'packages' => $installed,
	'rules' => $rules,
], blockMode: true));

$failed = false;
$copies = [];
foreach ([1, 2] as $run) {
	$before = hashFiles($corpus);
	exec('php ' . escapeshellarg("$root/vendor/dresscode/dresscode/bin/dresscode") . ' fix --no-cache --fix-risky --format bare --config ' . escapeshellarg("$root/$name.neon") . ' 2>&1', $output, $exit);
	if ($exit !== 0 && $exit !== 1) {
		echo "run $run: dresscode exited with $exit\n" . implode("\n", array_slice($output, -30)) . "\n";
		$failed = true;
	}

	$after = hashFiles($corpus);
	if ($run === 2 && $after !== $before) {
		echo 'second run changed: ' . implode(', ', array_keys(array_diff_assoc($after, $before))) . "\n";
		$failed = true;
	}

	$copies = $after;
	$output = [];
}

$counts = ['legacy' => 0, 'CHANGED' => 0, 'BROKEN' => 0];
foreach (array_keys(array_diff_assoc($copies, $originals)) as $file) {
	$path = "$corpus/$file";
	exec('php -l ' . escapeshellarg($path) . ' 2>&1', $lint, $lintExit);
	$lint = [];
	$verdict = match (true) {
		$lintExit !== 0 => 'BROKEN',
		isLegacy(findSource($dirs, $file)) => 'legacy',
		default => 'CHANGED',
	};
	$counts[$verdict]++;
	echo "$verdict  $file\n";
	if ($verdict !== 'legacy') {
		exec('git diff --no-index --no-color -U1 ' . escapeshellarg(findSource($dirs, $file)) . ' ' . escapeshellarg($path) . ' 2>&1', $diff);
		echo implode("\n", array_slice($diff, 4)) . "\n";
		$diff = [];
		$failed = true;
	}
}

echo 'files ' . count($originals) . ', changed ' . array_sum($counts) . " (legacy $counts[legacy], CHANGED $counts[CHANGED], BROKEN $counts[BROKEN])\n";
if (!in_array('--keep', $options, true)) {
	try {
		Nette\Utils\FileSystem::delete("$root/$name.neon");
		Nette\Utils\FileSystem::delete($corpus);
	} catch (Nette\IOException) {
		echo "left behind, a file of it is locked: $name\n"; // a scanner of the system may hold a file for a moment
	}
}

exit($failed ? 1 : 0);


/**
 * The directory of every package of the monorepo, by its name.
 * @return array<string, string>
 */
function findPackageDirs(string $source): array
{
	$dirs = [];
	foreach (['*/*', '*/*/*', '*/*/*/*'] as $depth) { // a bridge of a component lies deepest, Component/Mailer/Bridge/X
		foreach (glob("$source/$depth/composer.json") ?: [] as $file) {
			$name = json_decode((string) file_get_contents($file), associative: true)['name'] ?? null;
			if (is_string($name) && !str_contains($file, '/Tests/')) {
				$dirs[$name] = dirname($file);
			}
		}
	}

	return $dirs;
}


/**
 * The hash of every PHP file under the directory, by its path relative to it.
 * @return array<string, string>
 */
function hashFiles(string $dir): array
{
	$hashes = [];
	foreach (Nette\Utils\Finder::findFiles('*.php')->from($dir) as $file) {
		$hashes[str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1))] = md5((string) file_get_contents($file->getPathname()));
	}

	ksort($hashes);
	return $hashes;
}


/**
 * The file of the monorepo a file of the copy came from.
 * @param  array<string, string>  $dirs
 */
function findSource(array $dirs, string $file): string
{
	[$dir, $rest] = explode('/', $file, 2);
	foreach ($dirs as $package => $path) {
		if (str_replace('/', '-', $package) === $dir) {
			return "$path/Tests/$rest";
		}
	}

	throw new LogicException("No package for $file.");
}


/**
 * Whether the test uses a deprecated API on purpose: something in it is in the group legacy, or it is one of those
 * that test the old API is refused or call it where an older version of a component lacks the new one.
 */
function isLegacy(string $file): bool
{
	$refusing = [
		'Validator/Tests/Constraints/BicValidatorTest.php', // new Bic(options: [...]) after the expected exception
		'Serializer/Tests/Fixtures/Attributes/ClassWithIgnoreAnnotation.php', // the removed Annotation\Ignore, which must not be honoured
		'Serializer/Tests/Fixtures/DummyMessageNumberTwo.php', // a @Groups nothing reads any more
		'Security/Http/Tests/Fixtures/DummyAuthenticator.php', // a dead import of the removed PassportInterface
		'SecurityBundle/Tests/Fixtures/DummyAuthenticator.php', // the same, with createAuthenticatedToken() beside createToken()
		'RememberMeBundle/Security/UserChangingUserProvider.php', // loadUserByUsername() kept for 5.x, calling it on the inner provider
		'Security/Core/User/ArrayUserProvider.php', // setUsername(), which the installed exception has no longer
	];
	return array_any($refusing, fn(string $path) => str_ends_with(str_replace('\\', '/', $file), $path))
		|| preg_match('~@group\s+legacy|#\[Group\(.legacy.\)\]|#\[IgnoreDeprecations\]~i', (string) file_get_contents($file));
}
