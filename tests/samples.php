<?php declare(strict_types=1);

use DressCode\Testing\UpgradingTester;
use DressCodeUpgrade\Symfony\Plugin;

/**
 * The decisions a sample runs with beside the upgrading files: the signature of an override as the ancestor declares it,
 * and those of the rules of this package that read the code alone.
 */
const SampleDecisions = [
	'classes' => ['overridingMethod' => ['signature' => 'required', 'parameterName' => 'required']],
	'symfony' => ['valueResolver' => 'required'],
];


/** The decisions the corpus is run with: the data of the upgrading files, and the rules of this package that read the code alone. */
const CorpusDecisions = [
	'upgrading' => ['packages' => true],
	'symfony' => ['valueResolver' => 'required'],
];


/**
 * What the upgrading file of a library has wrong, checked against the installed library.
 * @return list<string>
 */
function lintLibrary(string $library): array
{
	$root = dirname(__DIR__);
	return UpgradingTester::collectProblems("$root/upgrading/$library.neon", $root, array_keys((new Plugin)->getManifest()->rules));
}


/**
 * The sample of a library, code written for its old API, as the upgrading files fix it, and what they report in it:
 * the fixed code, and the violations as `line: message`, the fixed ones among them.
 * @return array{string, string}
 */
function runSample(string $library): array
{
	$code = (string) file_get_contents(__DIR__ . "/samples/$library.code");
	$result = UpgradingTester::runSample($code, dirname(__DIR__), SampleDecisions, $library);
	$violations = array_map(fn($violation) => "$violation->line: $violation->message", $result->violations);
	return [(string) $result->output, implode("\n", $violations) . "\n"];
}
