<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Tenancy\Actions\RegisterTenantOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(
        RegisterUserRequest $request,
        RegisterTenantOwner $registerTenantOwner,
    ): RedirectResponse {
        $user = $registerTenantOwner->execute($request->validated());

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}
