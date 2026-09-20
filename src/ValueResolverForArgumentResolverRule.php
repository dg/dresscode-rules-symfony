<?php declare(strict_types=1);

namespace DressCodeRules\Symfony;

use DressCode\{NodeRule, RuleContext, RuleGroup, RuleInfo, Stage};
use DressCode\Rules\CodeWriter;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{Node, Parser, SymbolKind, Token, Trivia};
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\{ClassNode, ReturnNode};
use Symfony;
use function count;


/**
 * A resolver of controller arguments written against ArgumentValueResolverInterface, which http-kernel 6.2 deprecated
 * and 7.0 removed, implementing ValueResolverInterface instead: its resolve() opens with the guard that returns no
 * value where supports() is false, the test the framework made before, and supports() stays for what calls it. A
 * supports() that returns true alone needs no guard.
 *
 * Reported and left: a class that inherits supports() or resolve() rather than declaring them, and any other
 * reference of the interface, a type of a parameter or instanceof, whose code may call supports().
 */
#[RuleInfo(
	'symfony/valueResolverForArgumentResolver',
	Stage::Structure,
	description: 'Implements `ValueResolverInterface` instead of `ArgumentValueResolverInterface`, `supports()` becoming a guard of `resolve()`',
	group: RuleGroup::Deprecations,
	requires: ['symfony/http-kernel' => '>=6.2'],
)]
final class ValueResolverForArgumentResolverRule extends NodeRule
{
	private const Old = Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface::class; // dresscode:ignore symfony/valueResolverForArgumentResolver
	private const New = Symfony\Component\HttpKernel\Controller\ValueResolverInterface::class;


	public function getVisitedTypes(): array
	{
		return [NameNode::class];
	}


	public function enter(Node|Token $node, RuleContext $context): void
	{
		if (
			!$node instanceof NameNode
			|| $node->symbolKind !== SymbolKind::ClassLike
			|| !$node->isReference()
			|| strcasecmp($context->getAnalysis(NameResolver::class)->resolveClass($node), self::Old) !== 0
		) {
			return;
		}

		$class = $node->parent?->parent;
		$message = 'Interface `' . self::Old . '` is replaced by `' . self::New . '`';
		if (!$class instanceof ClassNode || $class->implements !== $node->parent) {
			$context->report($node, "$message, which has no `supports()`", fixable: false);
			return;
		}

		$supports = self::findMethod($class, 'supports');
		$resolve = self::findMethod($class, 'resolve');
		if ($supports?->body === null || $resolve?->body === null) {
			$context->report($node, "$message, but the class declares no `supports()` and `resolve()` of its own", fixable: false);

		} elseif ($context->report($node, "$message, whose `resolve()` returns no value where `supports()` is false")) {
			$node->text = CodeWriter::writeClass(self::New, $node, $context);
			if (!self::returnsTrue($supports)) {
				self::addGuard($resolve, $context);
			}
		}
	}


	private static function findMethod(ClassNode $class, string $name): ?MethodNode
	{
		foreach ($class->members as $member) {
			if ($member instanceof MethodNode && strcasecmp($member->name->text, $name) === 0) {
				return $member;
			}
		}

		return null;
	}


	private static function returnsTrue(MethodNode $method): bool
	{
		$statements = $method->body?->statements->getItems() ?? [];
		return count($statements) === 1
			&& $statements[0] instanceof ReturnNode
			&& $statements[0]->expression?->hasValue()
			&& $statements[0]->expression->toValue() === true;
	}


	/** Opens the body of resolve() with the test supports() made, by the names of the parameters of resolve(). */
	private static function addGuard(MethodNode $resolve, RuleContext $context): void
	{
		$style = $context->getStyle();
		$arguments = array_map(fn($parameter) => '$' . $parameter->variable->plainName, $resolve->parameters->getItems());

		$indentation = ($resolve->getFirstToken()?->getIndentation() ?? '') . $style->indent;
		$statement = (new Parser)->parseStatement(
			'if (!$this->supports(' . implode(', ', $arguments) . ')) {' . $style->lineEnding
			. $indentation . $style->indent . 'return [];' . $style->lineEnding
			. $indentation . '}',
		);
		$statement->setEdgeTrivia(
			[new Trivia(Trivia::Whitespace, $indentation)],
			[new Trivia(Trivia::LineEnding, $style->lineEnding), new Trivia(Trivia::LineEnding, $style->lineEnding)],
		);
		$resolve->body?->statements->insert(0, $statement);
	}
}
