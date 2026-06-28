<?php

declare(strict_types=1);

namespace Stan\Rules;

use Stan\Collectors\FeatureTestCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<CollectedDataNode>
 */
final readonly class FeatureTestRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var array<string, array<?array<string>>> $data */
        $data = $node->get(FeatureTestCollector::class);

        $errors = [];
        foreach ($data as $file => [$expectedTestFiles]) {
            if ($expectedTestFiles === null) {
                continue;
            }

            $exists = array_any($expectedTestFiles, fn (string $file) => file_exists($file));

            if (!$exists) {
                $message = sprintf(
                    'Missing test for %s. Expected test at: %s or %s',
                    $file,
                    $expectedTestFiles[0] ?? '',
                    $expectedTestFiles[1] ?? '',
                );

                $errors[] = RuleErrorBuilder::message($message)
                    ->nonIgnorable()
                    ->identifier('extrastan.TestRequired')
                    ->file($file)
                    ->line(0)
                    ->build();
            }
        }

        return $errors;
    }
}
