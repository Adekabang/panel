<?php

namespace App\Actions\Auth;

use App\Exceptions\Http\Passkey\InvalidAuthenticatorAttestationResponse;
use App\Exceptions\Http\Passkey\InvalidPasskeyJson;
use App\Exceptions\Http\Passkey\InvalidPasskeyPublicKeyCredential;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Auth\PasskeySerializer;
use Throwable;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialSource;

use function app;
use function config;

class StorePasskeyAction
{
    public function execute(
        User $user,
        string $name,
        string $passkeyJson,
        string $passkeyOptionsJson,
        string $hostName,
    ): Passkey {
        $publicKeyCredentialSource = $this->determinePublicKeyCredentialSource(
            $passkeyJson,
            $passkeyOptionsJson,
            $hostName
        );

        return $user->passkeys()->create([
            'name' => $name,
            'data' => $publicKeyCredentialSource,
        ]);
    }

    protected function determinePublicKeyCredentialSource(
        string $passkeyJson,
        string $passkeyOptionsJson,
        string $hostName,
    ): PublicKeyCredentialSource {
        $passkeyOptions = $this->getPasskeyOptions($passkeyOptionsJson);

        $publicKeyCredential = $this->getPasskey($passkeyJson);

        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            throw new InvalidPasskeyPublicKeyCredential;
        }

        $csmFactory = new CeremonyStepManagerFactory;
        if (app()->environment('local') && config('app.version') === 'canary') {
            $csmFactory->setSecuredRelyingPartyId(['localhost']);
        }
        $creationCsm = $csmFactory->creationCeremony();

        try {
            $publicKeyCredentialSource = AuthenticatorAttestationResponseValidator::create($creationCsm)->check(
                authenticatorAttestationResponse: $publicKeyCredential->response,
                publicKeyCredentialCreationOptions: $passkeyOptions,
                host: $hostName,
            );
        } catch (Throwable $exception) {
            throw new InvalidAuthenticatorAttestationResponse($exception);
        }

        return $publicKeyCredentialSource;
    }

    protected function getPasskeyOptions(string $passkeyOptionsJson): PublicKeyCredentialCreationOptions
    {
        if (! json_validate($passkeyOptionsJson)) {
            throw new InvalidPasskeyJson;
        }

        /** @var PublicKeyCredentialCreationOptions $passkeyOptions */
        $passkeyOptions = PasskeySerializer::make()->fromJson(
            $passkeyOptionsJson,
            PublicKeyCredentialCreationOptions::class
        );

        return $passkeyOptions;
    }

    protected function getPasskey(string $passkeyJson): PublicKeyCredential
    {
        if (! json_validate($passkeyJson)) {
            throw new InvalidPasskeyJson;
        }

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = PasskeySerializer::make()->fromJson(
            $passkeyJson,
            PublicKeyCredential::class
        );

        return $publicKeyCredential;
    }
}
