<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Registration;

use Database\Seeders\MembershipSeeder;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\FeatureTestCase;

final class RegistrationControllerTest extends FeatureTestCase
{
    private const VALID_ACTIVITIES = [
        'windsurfing_lessons' => true,
    ];

    private const VALID_PERSONAL_INFO = [
        'first_name' => 'Jan',
        'last_name' => 'Vries',
        'email' => 'jan@example.com',
        'gender' => 'M',
        'birthdate' => '1990-01-15',
        'address_street' => 'Surfstrand',
        'address_housenumber' => '2',
        'address_postalcode' => '1324CT',
        'address_city' => 'Almere',
    ];

    private const VALID_PAYMENT_INFO = [
        'banking_account_number' => 'NL91ABNA0417164300',
        'banking_bic' => 'ABNANL2A',
        'banking_account_holder_name' => 'J. de Vries',
        'mandate_accepted' => '1',
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MembershipSeeder::class);
    }

    public function test_welcome_page_renders(): void
    {
        $response = $this->get(route('register.welcome'));

        $response->assertOk();
    }

    public function test_welcome_post_redirects_to_membership(): void
    {
        $response = $this->post(route('register.welcome'));

        $response->assertRedirect(route('register.membership'));
    }

    public function test_membership_page_renders_after_welcome(): void
    {
        $this->post(route('register.welcome'));

        $response = $this->get(route('register.membership'));

        $response->assertOk();
    }

    #[TestWith(['register.membership'])]
    #[TestWith(['register.personal-information'])]
    public function test_redirect_back_to_welcome_with_invalid_state(string $routeName): void
    {
        $response = $this->get(route($routeName));

        $response->assertRedirect(route('register.welcome'));
    }

    public function test_membership_post_validates_and_redirects_to_personal_info(): void
    {
        $this->post(route('register.welcome'));

        $response = $this->post(route('register.membership'), self::VALID_ACTIVITIES);

        $response->assertRedirect(route('register.personal-information'));
    }

    public function test_membership_post_validates_at_least_one_activity(): void
    {
        $this->post(route('register.welcome'));

        $response = $this->post(route('register.membership'), [
            'windsurfing_lessons' => false,
            'rtc_lessons' => false,
            'club_access' => false,
            'storage' => false,
        ]);

        $response->assertSessionHasErrors('membership_activities');
    }

    public function test_personal_information_page_renders_after_membership(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), ['windsurfing_lessons' => true]);

        $response = $this->get(route('register.personal-information'));

        $response->assertOk();
    }

    public function test_personal_information_post_validates_required_fields(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);

        $response = $this->post(route('register.personal-information'), []);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'gender',
            'birthdate',
            'address_street',
            'address_housenumber',
            'address_postalcode',
            'address_city',
        ]);
    }

    #[TestWith([['email' => 'invalid'], 'email'])]
    #[TestWith([['address_postalcode' => 'invalid'], 'address_postalcode'])]
    #[TestWith([['gender' => 'invalid'], 'gender'])]
    public function test_personal_information_post_validates(array $data, string $error): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);

        $response = $this->post(route('register.personal-information'), $data);

        $response->assertSessionHasErrors($error);
    }

    public function test_payment_information_page_renders_after_personal_info(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);

        $response = $this->get(route('register.payment-information'));

        $response->assertOk();
    }

    public function test_payment_information_post_validates_required_fields(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);

        $response = $this->post(route('register.payment-information'), []);

        $response->assertSessionHasErrors([
            'banking_account_number',
            'banking_bic',
            'banking_account_holder_name',
            'mandate_accepted',
        ]);
    }

    #[TestWith([['mandate_accepted' => 'off'], 'mandate_accepted'])]
    public function test_payment_information_post_validates_mandate_accepted(array $invalidData, string $errorField): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);

        $response = $this->post(
            route('register.payment-information'),
            [
                ...self::VALID_PAYMENT_INFO,
                ...$invalidData,
            ],
        );

        $response->assertSessionHasErrors($errorField);
    }

    public function test_payment_information_post_stores_data_and_redirects(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);

        $response = $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

        $response->assertRedirect();
    }

    public function test_full_registration_flow_end_to_end(): void
    {
        $this->post(route('register.welcome'))
            ->assertRedirect(route('register.membership'));

        $this->post(route('register.membership'), self::VALID_ACTIVITIES)
            ->assertRedirect(route('register.personal-information'));

        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO)
            ->assertRedirect(route('register.payment-information'));

        $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO)
            ->assertRedirect(route('register.confirmation'));

        $this->post(route('register.confirmation'), [
            'confirm_data_correct' => '1',
            'confirm_membership' => '1',
        ])->assertRedirect(route('register.success'));

        $this->assertDatabaseHas('members', [
            'first_name' => self::VALID_PERSONAL_INFO['first_name'],
            'last_name' => self::VALID_PERSONAL_INFO['last_name'],
            'email' => self::VALID_PERSONAL_INFO['email'],
        ]);
        $this->assertDatabaseHas('payment_information', [
            'banking_account_number' => self::VALID_PAYMENT_INFO['banking_account_number'],
            'banking_bic' => self::VALID_PAYMENT_INFO['banking_bic'],
            'banking_account_holder_name' => self::VALID_PAYMENT_INFO['banking_account_holder_name'],
        ]);
    }

    public function test_confirmation_page_renders_after_payment_info(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);
        $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

        $response = $this->get(route('register.confirmation'));

        $response->assertOk();
        $response->assertSee(self::VALID_PERSONAL_INFO['first_name']);
        $response->assertSee(self::VALID_PERSONAL_INFO['last_name']);
        $response->assertSee(self::VALID_PERSONAL_INFO['email']);
        $response->assertSee(DateTimeImmutable::createFromFormat('Y-m-d', self::VALID_PERSONAL_INFO['birthdate'])->format('d-m-Y'));
        $response->assertSee(self::VALID_PERSONAL_INFO['address_street']);
        $response->assertSee(self::VALID_PERSONAL_INFO['address_city']);
        $response->assertSee(self::VALID_PERSONAL_INFO['address_postalcode']);
        $response->assertSee(self::VALID_PERSONAL_INFO['address_housenumber']);

        $response->assertSee(self::VALID_PAYMENT_INFO['banking_account_number']);
        $response->assertSee(self::VALID_PAYMENT_INFO['banking_bic']);
        $response->assertSee(self::VALID_PAYMENT_INFO['banking_account_holder_name']);
    }

    public function test_confirmation_requires_both_checkboxes(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);
        $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

        $response = $this->post(route('register.confirmation'), []);

        $response->assertSessionHasErrors(['confirm_data_correct', 'confirm_membership']);
    }

    public function test_confirmation_clears_session_and_redirects_to_success(): void
    {
        $this->post(route('register.welcome'));
        $this->post(route('register.membership'), self::VALID_ACTIVITIES);
        $this->post(route('register.personal-information'), self::VALID_PERSONAL_INFO);
        $this->post(route('register.payment-information'), self::VALID_PAYMENT_INFO);

        $response = $this->post(route('register.confirmation'), [
            'confirm_data_correct' => '1',
            'confirm_membership' => '1',
        ]);

        $response->assertRedirect(route('register.success'));

        $this->get(route('register.membership'))
            ->assertRedirect(route('register.welcome'));
    }

    public function test_success_page_renders(): void
    {
        $response = $this->get(route('register.success'));

        $response->assertOk();
    }
}
