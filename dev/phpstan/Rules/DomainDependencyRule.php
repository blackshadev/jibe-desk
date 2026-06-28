<?php

declare(strict_types=1);

namespace Stan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Use_>
 */
final readonly class DomainDependencyRule implements Rule
{
    private const array ALLOWED_NAMESPACES = [
        \Attribute::class,
        'App\\Domain\\',
        'Carbon\\',
        'Illuminate\\Contracts\\',
        \Illuminate\Support\LazyCollection::class,
        \Illuminate\Support\Collection::class,
        \Illuminate\Support\Enumerable::class,
        \Illuminate\Support\Str::class,
        \Illuminate\Support\Arr::class,
        'Psr\\',
        'Ramsey\\Uuid\\',
        'Webmozart\\Assert\\',
        \JeroenG\Autowire\Attribute\Autowire::class,
        "Illuminate\\Mail\\Mailable",
        "Illuminate\\Mail\\Events",
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return Use_::class;
    }

    /**
     * @param Use_ $node
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($node->uses as $use) {
            $name = $use->name->toString();

            if (!str_contains($scope->getFile(), '/app/Domain/')) {
                continue;
            }

            if ($this->isAllowed($name)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                "Domain code may not depend on '{$name}'. " .
                "Domain layer should only depend on Domain namespaces or allowed external dependencies."
            )->identifier('domain.dependency')->build();
        }

        return $errors;
    }

    private function isAllowed(string $name): bool
    {
        $nameContainsAllowedNamespace = array_any(self::ALLOWED_NAMESPACES, fn ($allowed) => str_starts_with($name, $allowed));

        if ($nameContainsAllowedNamespace) {
            return true;
        }

        return $this->isPhpInternal($name);
    }

    private function isPhpInternal(string $name): bool
    {
        $reflected = $this->reflectionProvider->getClass($name);
        return $reflected->isBuiltin();
    }
}
