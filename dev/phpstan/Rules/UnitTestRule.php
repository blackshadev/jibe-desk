<?php

declare(strict_types=1);

namespace Stan\Rules;

use Stan\Collectors\UnitTestCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<CollectedDataNode>
 */
final readonly class UnitTestRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var array<string, array<string>> $data */
        $data = $node->get(UnitTestCollector::class);

        $errors = [];
        foreach ($data as $file => [$expectedTestFile]) {
            if (!file_exists($expectedTestFile)) {
                $message = sprintf(
                    'Missing unit test for %s. Expected test at: %s',
                    $file,
                    $expectedTestFile,
                );

                $errors[] = RuleErrorBuilder::message($message)
                    ->nonIgnorable()
                    ->identifier('extrastan.UnitTestRequired')
                    ->file($file)
                    ->line(0)
                    ->build();
            }
        }

        return $errors;
    }
}
