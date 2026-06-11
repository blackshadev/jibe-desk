<?php

declare(strict_types=1);

namespace Tests\Feature\Registration;

use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\FeatureTestCase;

final class RegistrationFlowTest extends FeatureTestCase
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

        $response = $this->post(route('register.payment-information'), [
            ...self::VALID_PAYMENT_INFO,
            ...$invalidData,
        ]);

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
            ->assertRedirect();
    }
}
