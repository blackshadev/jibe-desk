<?php

declare(strict_types=1);

namespace App\Http\Controllers\Registration;

use App\Domain\Members\NewMemberService;
use App\Domain\Registration\FormDataRepository;
use App\Domain\Registration\Step;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\ConfirmRegistrationRequest;
use App\Http\Requests\Registration\StoreMembershipRequest;
use App\Http\Requests\Registration\StorePaymentInformationRequest;
use App\Http\Requests\Registration\StorePersonalInformationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class RegistrationController extends Controller
{
    public function __construct(private readonly FormDataRepository $formDataRepository)
    {
    }

    public function showWelcomeForm(): View
    {
        $formData = $this->formDataRepository->get();

        return view('pages.register.1-welcome', compact('formData'));
    }

    public function saveWelcomeForm(): RedirectResponse
    {
        $formData = $this->formDataRepository->get();
        $this->formDataRepository->save($formData->welcome());

        return redirect()->route('register.membership');
    }

    public function showMembershipForm(): View | RedirectResponse
    {
        $formData = $this->formDataRepository->get();
        if ($formData->isStepDisallowed(Step::Membership)) {
            return redirect()->route('register.welcome');
        }

        return view('pages.register.2-membership-information', compact('formData'));
    }

    public function saveMembershipForm(StoreMembershipRequest $request): RedirectResponse
    {
        $formData = $this->formDataRepository->get();
        $this->formDataRepository->save($formData->membership($request->toMembershipData()));

        return redirect()->route('register.personal-information');
    }

    public function showPersonalInformationForm(): RedirectResponse | View
    {
        $formData = $this->formDataRepository->get();
        if ($formData->isStepDisallowed(Step::PersonalInfo)) {
            return redirect()->route('register.welcome');
        }

        return view('pages.register.3-personal-information', compact('formData'));
    }

    public function savePersonalInformationForm(StorePersonalInformationRequest $request): RedirectResponse
    {
        $formData = $this->formDataRepository->get();
        $this->formDataRepository->save($formData->personalInfo($request->toPersonalInfoData()));

        return redirect()->route('register.payment-information');
    }

    public function showPaymentInformationForm(): RedirectResponse | View
    {
        $formData = $this->formDataRepository->get();
        if ($formData->isStepDisallowed(Step::PaymentInfo)) {
            return redirect()->route('register.welcome');
        }

        return view('pages.register.4-payment-information', compact('formData'));
    }

    public function savePaymentInformationForm(StorePaymentInformationRequest $request): RedirectResponse
    {
        $formData = $this->formDataRepository->get();
        $this->formDataRepository->save($formData->paymentInfo($request->toPaymentInfoData()));

        return redirect()->route('register.confirmation');
    }

    public function showConfirmationForm(): View | RedirectResponse
    {
        $formData = $this->formDataRepository->get();
        if ($formData->isStepDisallowed(Step::Confirmation)) {
            return redirect()->route('register.welcome');
        }

        return view('pages.register.5-confirmation', compact('formData'));
    }

    public function confirmRegistration(ConfirmRegistrationRequest $request, NewMemberService $newMemberService): RedirectResponse
    {
        $formData = $this->formDataRepository->get();

        $newMemberService->fromRegistration($formData);

        $this->formDataRepository->clear();

        return redirect()->route('register.success');
    }
}
