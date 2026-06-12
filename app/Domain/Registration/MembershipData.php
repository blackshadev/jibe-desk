<?php

declare(strict_types=1);

namespace App\Domain\Registration;

/**
 * @phpstan-type MembershipDataArray array{
 *     regularWindsurfingLessons?: boolean,
 *     rtc?: boolean,
 *     clubhouseAccess?: boolean,
 *     boardStorage?: boolean,
 *     watersportFederationNumber?: string
 *  }
 */

final class MembershipData
{
    public function __construct(
        public bool $regularWindsurfingLessons,
        public bool $rtc,
        public bool $clubhouseAccess,
        public bool $boardStorage,
        public string $watersportFederationNumber
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            regularWindsurfingLessons: false,
            rtc: false,
            clubhouseAccess: false,
            boardStorage: false,
            watersportFederationNumber: '',
        );
    }

    /**
     * @param MembershipDataArray $data
     */
    public static function createFromArray(array $data): self
    {
        return new self(
            regularWindsurfingLessons: $data['regularWindsurfingLessons'] ?? false,
            rtc: $data['rtc'] ?? false,
            clubhouseAccess: $data['clubhouseAccess'] ?? false,
            boardStorage: $data['boardStorage'] ?? false,
            watersportFederationNumber: $data['watersportFederationNumber'] ?? '',
        );
    }

    /** @return MembershipDataArray */
    public function toArray(): array
    {
        return [
            'regularWindsurfingLessons' => $this->regularWindsurfingLessons,
            'rtc' => $this->rtc,
            'clubhouseAccess' => $this->clubhouseAccess,
            'boardStorage' => $this->boardStorage,
            'watersportFederationNumber' => $this->watersportFederationNumber,
        ];
    }
}
