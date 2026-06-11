<?php

declare(strict_types=1);

use App\Http\Controllers;
use Illuminate\Support\Facades\Route;

Route::get('/registratie/welkom', [Controllers\Registration\RegistrationController::class, 'showWelcomeForm'])->name('register.welcome');
Route::post('/registratie/welkom', [Controllers\Registration\RegistrationController::class, 'saveWelcomeForm']);

Route::get('/registratie/lidmaatschap', [Controllers\Registration\RegistrationController::class, 'showMembershipForm'])->name('register.membership');
Route::post('/registratie/lidmaatschap', [Controllers\Registration\RegistrationController::class, 'saveMembershipForm']);

Route::get('/registratie/persoonlijke-informatie', [Controllers\Registration\RegistrationController::class, 'showPersonalInformationForm'])->name('register.personal-information');
Route::post('/registratie/persoonlijke-informatie', [Controllers\Registration\RegistrationController::class, 'savePersonalInformationForm']);

Route::get('/registratie/betaalgegevens', [Controllers\Registration\RegistrationController::class, 'showPaymentInformationForm'])->name('register.payment-information');
Route::post('/registratie/betaalgegevens', [Controllers\Registration\RegistrationController::class, 'savePaymentInformationForm']);

Route::get('/registratie/bevestiging', fn () => view('pages.register.5-confirmation'))->name('register.confirmation');
