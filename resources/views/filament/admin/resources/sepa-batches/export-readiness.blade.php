@php /** @var \App\Domain\Billing\Payment\Readiness\ExportReadinessReport $report */ @endphp

<div class="space-y-4 text-sm">
    @if ($report->isEmpty())
        <p class="text-success-600 dark:text-success-400">{{ __('labels.export_readiness.clear') }}</p>
    @else
        @foreach ([
            'batch_level' => $report->batchLevelIssues(),
            'broken_data' => $report->brokenDataIssues(),
            'overridable' => $report->overridableIssues(),
        ] as $section => $issues)
            @if ($issues !== [])
                <div>
                    <h4 class="font-semibold text-gray-950 dark:text-white">
                        {{ __('labels.export_readiness.' . $section) }}
                    </h4>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-gray-600 dark:text-gray-300">
                        @foreach ($issues as $issue)
                            <li>
                                @if ($issue->invoiceId)
                                    {{ $issue->invoiceNumber }} — {{ $issue->memberName }} —
                                    {{ $issue->amount }} —
                                @endif
                                {{ __('labels.export_readiness.reason.' . $issue->reason->value) }}
                                @if ($issue->detail)
                                    ({{ $issue->detail }})
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    @endif
</div>
