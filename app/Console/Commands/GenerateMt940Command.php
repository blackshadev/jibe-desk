<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:generate-mt940')]
#[Description('Generate MT940 bank statement files for development purposes')]
final class GenerateMt940Command extends Command
{
    private const string BIC = 'ABNANL2A';
    private const string ACCOUNT = 'NL91ABNA0417164300';
    private const string CURRENCY = 'EUR';

    private string $outputDir = 'mt940-imports';

    public function handle(): void
    {
        $statements = $this->buildStatements();

        foreach ($statements as $index => $content) {
            $filename = sprintf('mt940-dev-%s-%02d.mta', now()->format('YmdHis'), $index + 1);
            Storage::disk('local')->put($this->outputDir . '/' . $filename, $content);

            $this->info(sprintf('Generated: storage/app/private/%s/%s', $this->outputDir, $filename));
        }

        $this->info(sprintf('Done. %d file(s) created.', count($statements)));
    }

    /**
     * @mago-expect lint:halstead
     * @return array<int, string>
     */
    private function buildStatements(): array
    {
        $startBalance = 1000.00;

        /** @var array<int, array<int, array{date: string, dc: string, amount: float, code: string, description: string}>> $transactionsPerStatement */
        $transactionsPerStatement = [
            [
                ['date' => '20250720', 'dc' => 'C', 'amount' => 150.00, 'code' => 'NONREF', 'description' => "ING Bank N.V.\nREF: 20250001\nBETREFT LIDMAATSCHAP JULI 2025"],
                ['date' => '20250721', 'dc' => 'D', 'amount' => 35.50, 'code' => 'NONREF', 'description' => "NL12RABO0123456789\nJ.G. van der Berg\nBETREFT TERUGBETALING"],
            ],
            [
                [
                    'date' => '20250722',
                    'dc' => 'C',
                    'amount' => 250.00,
                    'code' => 'NONREF',
                    'description' => "NL44ABNA0987654321\nStichting Watersport\nBETREFT CONTRIBUTIE 2025-01",
                ],
                [
                    'date' => '20250723',
                    'dc' => 'C',
                    'amount' => 500.00,
                    'code' => 'NONREF',
                    'description' => "NL78INGB0123456789\nSponsoring B.V.\nBETREFT SPONSORBIJDRAGE Q3 2025",
                ],
                ['date' => '20250724', 'dc' => 'D', 'amount' => 120.00, 'code' => 'NONREF', 'description' => "NL91SNSB0987654321\nKantoorartikelen\nBETREFT FACTUUR 2025-0782"],
            ],
            [
                ['date' => '20250725', 'dc' => 'D', 'amount' => 75.00, 'code' => 'NONREF', 'description' => "NL12RABO0123456789\nVerzekering\nBETREFT PREMIE JULI 2025"],
            ],
        ];

        $statements = [];
        $balance = $startBalance;

        foreach ($transactionsPerStatement as $txIdx => $transactions) {
            $statementSeq = $txIdx + 1;
            $statementDate = now()->subDays(count($transactionsPerStatement) - $txIdx)->format('ymd');

            $lines = [];

            $lines[] = self::BIC;
            $lines[] = '940';
            $lines[] = self::BIC;
            $lines[] = sprintf(':20:WSV-MT940-DEV-%03d', $statementSeq);
            $lines[] = sprintf(':25:%s', self::ACCOUNT);
            $lines[] = sprintf(':28C:%d/%d', $statementSeq, count($transactionsPerStatement));

            $openingBalance = $balance;
            $lines[] = sprintf(':60F:C%s%s%s', $statementDate, self::CURRENCY, $this->formatAmount($openingBalance));

            foreach ($transactions as $tx) {
                $valueDate = CarbonImmutable::createFromFormat('Ymd', $tx['date']);
                $entryDate = $valueDate->addDay();

                $dc = $tx['dc'];
                $amount = $tx['amount'];
                $code = $tx['code'];
                $descLines = explode("\n", $tx['description']);
                $descFirstLine = array_shift($descLines);

                $lines[] = sprintf(
                    ':61:%s%s%s%sN%s//%s',
                    $valueDate->format('ymd'),
                    $entryDate->format('md'),
                    $dc,
                    $this->formatAmount($amount),
                    $code,
                    $descFirstLine,
                );
                $lines[] = sprintf(':86:%s', implode("\n", $descLines));

                $balance += $dc === 'C' ? $amount : -$amount;
            }

            $closingDate = now()->subDays(count($transactionsPerStatement) - $txIdx - 1)->format('ymd');
            $closingDc = $balance >= 0 ? 'C' : 'D';

            $lines[] = sprintf(':62F:%s%s%s%s', $closingDc, $closingDate, self::CURRENCY, $this->formatAmount(abs($balance)));
            $lines[] = '-';

            $statements[] = implode("\n", $lines);
        }

        return $statements;
    }

    private function formatAmount(float|int $amount): string
    {
        return str_replace('.', ',', sprintf('%.2f', $amount));
    }
}
