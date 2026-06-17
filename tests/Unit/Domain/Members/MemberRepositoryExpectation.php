<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\Dto\NewMember;
use App\Domain\Members\Member;
use App\Domain\Members\MemberId;
use App\Domain\Members\MemberRepository;
use Mockery;
use Mockery\MockInterface;

use function PHPUnit\Framework\equalTo;

final readonly class MemberRepositoryExpectation
{
    private function __construct(
        public MockInterface&MemberRepository $mock,
    ) {}

    public static function create(): self
    {
        return new self(Mockery::mock(MemberRepository::class));
    }

    public function expectsGetById(MemberId $memberId, Member $member): void
    {
        $this->mock
            ->expects('getById')
            ->with(equalTo($memberId))
            ->andReturn($member);
    }

    public function expectsGetByEmail(string $email, MemberId $result): void
    {
        $this->mock
            ->expects('getByEmail')
            ->with(equalTo($email))
            ->andReturn($result);
    }

    public function expectsNewMember(NewMember $newMember, MemberId $result): void
    {
        $this->mock
            ->expects('newMember')
            ->with(equalTo($newMember))
            ->andReturn($result);
    }
}
