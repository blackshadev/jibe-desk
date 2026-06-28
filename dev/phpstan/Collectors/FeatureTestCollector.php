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
final readonly class FeatureTestCollector implements Collector
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @return array<string>|null
     */
    public function processNode(Node $node, Scope $scope): ?array
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

        return $this->getExpectedTestPaths($scope->getFile());
    }

    private function inApplicableFolder(string $path): bool
    {
        $applicableFolders = [
            'app/Http/Controllers',
            'app/Infrastructure/Database',
            'app/Observers',
            'app/Features',
        ];

        return array_any($applicableFolders, fn (string $folder) => str_contains($path, $folder));
    }

    /**
     * @return array<string>
     */
    private function getExpectedTestPaths(string $sourceFile): array
    {
        return [
            (string) preg_replace('/^(.*)\/app\/([\w\/]+).php$/', '$1/tests/Feature/$2Test.php', $sourceFile),
            (string) preg_replace('/^(.*)\/app\/([\w\/]+).php$/', '$1/tests/Unit/$2Test.php', $sourceFile),
        ];
    }
}
