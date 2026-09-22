<?php declare(strict_types=1);

namespace DressCodeUpgrade\Symfony;

use DressCode\Analyses\PhpDoc;
use DressCode\{CodeWriter, Decision, Domain, NodeRule, RuleContext, RuleInfo, Stage};
use PHPStan\PhpDocParser\Ast\ConstExpr\{ConstExprIntegerNode, ConstExprStringNode};
use PHPStan\PhpDocParser\Ast\PhpDoc\Doctrine\DoctrineTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{Builder, Node, SymbolKind, Token};
use PhpSyntax\Nodes\{ArgumentNode, AttributeGroupNode, AttributeNode, UseItemNode};
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use Sensio;
use Symfony;
use function count, is_int, is_string;


/**
 * The Security annotation of SensioFrameworkExtraBundle, which Symfony 6.2 took over as the attribute IsGranted, written
 * as that attribute where its expression only asks is_granted() with a literal, `is_granted('ROLE_ADMIN')` becoming
 * `#[IsGranted('ROLE_ADMIN')]`, a second argument naming an argument of the action becoming the subject, and every
 * condition of an `and` an attribute of its own; message and statusCode go over as they are. The annotation and the
 * attribute of the bundle are taken alike.
 *
 * Reported and left: any other expression, whose variables IsGranted names otherwise than the bundle did, as the
 * arguments of the action, which it reads under args, and an attribute of the bundle standing in a group with others.
 */
#[RuleInfo(
	Stage::Structure,
	modifiesComments: true,
	requires: ['symfony/security-http' => '>=6.2'],
	analyses: [PhpDoc::class, NameResolver::class],
)]
final class IsGrantedForSecurityAnnotationRule extends NodeRule
{
	private const Security = Sensio\Bundle\FrameworkExtraBundle\Configuration\Security::class;
	private const IsGranted = Symfony\Component\Security\Http\Attribute\IsGranted::class;
	private const Refusal = ', but its expression does more than call `is_granted()` with a literal, and `IsGranted` names the variables of an expression differently';

	/** @var array<string, true>  lowercased aliases the imports of the file so far give the class Security */
	private array $aliases = [];


	public static function getDecisions(): array
	{
		return [new Decision(
			'symfony.isGrantedAttribute',
			Domain::state('required'),
			'The attribute `IsGranted` for the `@Security` annotation of SensioFrameworkExtraBundle asking `is_granted()`',
		)];
	}


	public function getVisitedNodes(): array
	{
		return [
			UseItemNode::class, ClassNode::class, MethodNode::class, AttributeNode::class,
		];
	}


	public function beforePass(RuleContext $context): void
	{
		$this->aliases = [];
	}


	public function enter(Node|Token $node, RuleContext $context): void
	{
		if ($node instanceof UseItemNode) {
			if ($node->alias !== null && strcasecmp($node->fullName, self::Security) === 0) {
				$this->aliases[strtolower($node->alias->text)] = true;
			}
		} elseif ($node instanceof AttributeNode) {
			$this->enterAttribute($node, $context);
		} elseif ($node instanceof ClassNode || $node instanceof MethodNode) {
			$this->enterDeclaration($node, $context);
		}
	}


	private function enterDeclaration(ClassNode|MethodNode $node, RuleContext $context): void
	{
		// the annotation is written with the short name of the class, or with an alias an import gives it
		$docComment = $node->getDocComment();
		if (
			$docComment === null
			|| $docComment->inInterpolation
			|| !array_any(['security', ...array_keys($this->aliases)], fn(string $name) => stripos($docComment->text, $name) !== false)
		) {
			return;
		}

		$phpDoc = $context->getAnalysis(PhpDoc::class);
		$tree = $phpDoc->parse($docComment);
		$kept = $codes = [];
		foreach ($tree->children as $child) {
			if (
				!$child instanceof PhpDocTagNode
				|| !$child->value instanceof DoctrineTagValueNode
				|| strcasecmp(self::resolveAnnotation($child->name, $node, $context), self::Security) !== 0
			) {
				$kept[] = $child;
				continue;
			}

			$arguments = [];
			foreach ($child->value->annotation->arguments as $argument) {
				$arguments[] = [$argument->key?->name, match (true) {
					$argument->value instanceof ConstExprStringNode => $argument->value->value,
					$argument->value instanceof ConstExprIntegerNode => (int) $argument->value->value,
					default => null,
				}];
			}

			$written = trim($child->value->description) === '' ? self::writeArguments($arguments) : null;
			$message = 'Annotation `@Security` is replaced by the attribute `#[' . self::IsGranted . ']`';
			if ($written === null) {
				$context->report($node, $message . self::Refusal . '.', trivia: $docComment, fixable: false);
				$kept[] = $child;
			} elseif ($context->report($node, $message . '.', trivia: $docComment)) {
				$class = CodeWriter::writeClass(self::IsGranted, $node, $context);
				array_push($codes, ...array_map(fn(string $arguments) => $class . $arguments, $written));
			} else {
				$kept[] = $child;
			}
		}

		if ($codes !== []) {
			$tree->children = $kept;
			if (PhpDoc::isEmpty($tree)) {
				$node->removeDocComment();
			} else {
				$node->replaceDocComment($phpDoc->print($tree, $docComment));
			}

			CodeWriter::addAttributes($node, $codes, $context);
		}
	}


	private function enterAttribute(AttributeNode $node, RuleContext $context): void
	{
		if (
			(strcasecmp($node->name->shortName, 'Security') !== 0 && !isset($this->aliases[strtolower($node->name->text)]))
			|| strcasecmp($context->getAnalysis(NameResolver::class)->resolveClass($node->name), self::Security) !== 0
		) {
			return;
		}

		$arguments = [];
		foreach ($node->arguments?->items->getItems() ?? [] as $argument) {
			$arguments[] = $argument instanceof ArgumentNode && $argument->ellipsis === null && $argument->value->hasValue()
				? [$argument->name?->text, $argument->value->toValue()]
				: [null, null];
		}

		$group = $node->parent?->parent;
		$written = self::writeArguments($arguments);
		$message = 'Attribute `#[' . self::Security . ']` is replaced by `#[' . self::IsGranted . ']`';
		if ($written === null) {
			$context->report($node, $message . self::Refusal . '.', fixable: false);
		} elseif (count($written) > 1 && (!$group instanceof AttributeGroupNode || count($group->items->getItems()) > 1)) {
			$context->report(
				$node,
				"$message, but it becomes several `#[IsGranted]`, and its group holds other attributes.",
				fixable: false,
			);
		} elseif ($context->report($node, $message . '.')) {
			$class = CodeWriter::writeClass(self::IsGranted, $node, $context);
			$codes = array_map(fn(string $arguments) => $class . $arguments, $written);
			$replacement = (new Builder)->fragment(AttributeGroupNode::class, '#[' . implode(', ', $codes) . ']');
			if (count($written) > 1 && $group instanceof AttributeGroupNode) {
				$group->replaceWith($replacement);
			} else {
				$node->replaceWith($replacement->items->getItems()[0]->withoutEdgeTrivia());
			}
		}
	}


	/**
	 * The arguments of each attribute IsGranted written instead of the expression and the options of the bundle, with
	 * their parentheses; null for an expression that asks more than is_granted() with a literal, or for an argument
	 * that is no literal.
	 * @param  list<array{?string, mixed}>  $arguments  the name of each, null for a positional one, and its value
	 * @return ?list<string>
	 */
	private static function writeArguments(array $arguments): ?array
	{
		$expression = null;
		$options = [];
		foreach ($arguments as $position => [$name, $value]) {
			$name ??= $position === 0 ? 'expression' : null;
			if ($name === 'expression' && is_string($value)) {
				$expression = $value;
			} elseif ($name === 'message' && is_string($value)) {
				$options[] = 'message: ' . var_export($value, return: true);
			} elseif ($name === 'statusCode' && is_int($value)) {
				$options[] = "statusCode: $value";
			} else {
				return null;
			}
		}

		$conditions = $expression === null ? [] : preg_split('~\s+(?:and|&&)\s+~', trim($expression));
		$codes = [];
		foreach ($conditions ?: [] as $condition) {
			if (!preg_match('~^is_granted\(\s*(\'|")([\w.:-]+)\1\s*(?:,\s*([a-zA-Z_]\w*)\s*)?\)$~D', $condition, $m)) {
				return null;
			}

			$codes[] = '(' . implode(', ', ["'$m[2]'", ...(isset($m[3]) ? ["subject: '$m[3]'"] : []), ...$options]) . ')';
		}

		return $codes ?: null;
	}


	/** The class the name of a Doctrine annotation stands for, as the imports of the file and its namespace make it. */
	private static function resolveAnnotation(string $name, Node $at, RuleContext $context): string
	{
		$name = ltrim($name, '@');
		if (str_starts_with($name, '\\')) {
			return substr($name, 1);
		}

		$resolver = $context->getAnalysis(NameResolver::class);
		$parts = explode('\\', $name, 2);
		$base = $resolver->getImports(SymbolKind::ClassLike, $at)[strtolower($parts[0])] ?? ltrim(
			$resolver->getNamespace($at) . '\\' . $parts[0],
			'\\',
		);
		return isset($parts[1]) ? "$base\\$parts[1]" : $base;
	}
}
