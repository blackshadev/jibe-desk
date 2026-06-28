<?php

declare(strict_types=1);

namespace Stan\Collectors;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<Class_, string>
 */
final readonly class UnitTestCollector implements Collector
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): ?string
    {
        if ($node->name === null) {
            return null;
        }

        if ($node->isAbstract()) {
            return null;
        }

        if (!$this->inApplicableFolder($scope->getFile())) {
            return null;
        }

        return $this->getExpectedTestPath($scope->getFile());
    }

    private function inApplicableFolder(string $path): bool
    {
        $applicableFolders = [
            'app/Domain',
            'app/Features',
            'app/Http/Responses',
        ];

        return array_any($applicableFolders, fn (string $folder) => str_contains($path, $folder));
    }

    private function getExpectedTestPath(string $sourceFile): string
    {
        return (string) preg_replace('/^(.*)\/app\/([\w\/]+).php$/', '$1/tests/Unit/$2Test.php', $sourceFile);
    }
}
