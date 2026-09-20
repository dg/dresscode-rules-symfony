<?php declare(strict_types=1);

namespace DressCodeUpgrade\Symfony;

use DressCode\{CodeWriter, Decision, Domain, NodeRule, RuleContext, RuleInfo, Stage};
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\{Builder, Node, SymbolKind, Token, Trivia};
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\{NameNode, UseItemNode};
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
#[RuleInfo(Stage::Structure, requires: ['symfony/http-kernel' => '>=6.2'], analyses: [NameResolver::class])]
final class ValueResolverForArgumentResolverRule extends NodeRule
{
	private const Old = Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface::class; // dresscode:ignore symfony.valueResolver
	private const New = Symfony\Component\HttpKernel\Controller\ValueResolverInterface::class;
	private const OldShortName = 'ArgumentValueResolverInterface';

	/** @var array<string, true>  lowercased aliases the imports of the file so far give the interface */
	private array $aliases = [];


	public static function getDecisions(): array
	{
		return [
			new Decision(
				'symfony.valueResolver',
				Domain::state('required'),
				'`ValueResolverInterface` for `ArgumentValueResolverInterface`, `supports()` becoming a guard of `resolve()`',
			),
		];
	}


	public function getVisitedNodes(): array
	{
		return [UseItemNode::class, NameNode::class];
	}


	public function beforePass(RuleContext $context): void
	{
		$this->aliases = [];
	}


	public function enter(Node|Token $node, RuleContext $context): void
	{
		// a name of the interface is written with its short name, or with an alias an import gives it
		if ($node instanceof UseItemNode) {
			if ($node->alias !== null && str_ends_with(strtolower($node->fullName), strtolower('\\' . self::OldShortName))) {
				$this->aliases[strtolower($node->alias->text)] = true;
			}
			return;
		}

		if (
			!$node instanceof NameNode
			|| (
				stripos($node->token->text, self::OldShortName) === false
				&& ($this->aliases === [] || !isset($this->aliases[strtolower($node->token->text)]))
			)
			|| $node->symbolKind !== SymbolKind::ClassLike
			|| !$node->isReference()
			|| strcasecmp($context->getAnalysis(NameResolver::class)->resolveClass($node), self::Old) !== 0
		) {
			return;
		}

		$class = $node->parent?->parent;
		$message = 'Interface `' . self::Old . '` is replaced by `' . self::New . '`';
		if (!$class instanceof ClassNode || $class->implements !== $node->parent) {
			$context->report(
				$node,
				"$message, but the new interface has no `supports()`, which code typed by the old one may call.",
				fixable: false,
			);
			return;
		}

		$supports = self::findMethod($class, 'supports');
		$resolve = self::findMethod($class, 'resolve');
		if ($supports?->body === null || $resolve?->body === null) {
			$context->report($node, "$message, but the class does not declare both `supports()` and `resolve()` itself.", fixable: false);

		} elseif ($context->report($node, "$message, whose `resolve()` returns no value where `supports()` is false.")) {
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
		$style = $context->style;
		$arguments = array_map(fn($parameter) => '$' . $parameter->variable->plainName, $resolve->parameters->getItems());

		$indentation = $resolve->getFirstToken()->getIndentation() . $style->indent;
		$statement = (new Builder)->statement(
			'if (!$this->supports(' . implode(', ', $arguments) . ')) {' . $style->lineEnding
			. $indentation . $style->indent . 'return [];' . $style->lineEnding
			. $indentation . '}',
		);
		$statement->setEdgeTrivia(
			[new Trivia(Trivia::Whitespace, $indentation)],
			[Trivia::fromText($style->lineEnding), Trivia::fromText($style->lineEnding)],
		);
		$resolve->body?->statements->insert(0, $statement);
	}
}
