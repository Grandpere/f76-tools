<?php

declare(strict_types=1);

namespace App\Identity\UI\Security;

use Symfony\Component\HttpFoundation\Request;

final class IdentityEmailFormPayloadExtractor
{
    public function extract(Request $request): IdentityEmailFormPayload
    {
        return new IdentityEmailFormPayload(
            mb_strtolower(trim((string) $request->request->get('email', ''))),
            (string) $request->request->get('_csrf_token', ''),
            (string) $request->request->get('website', ''),
            (string) $request->request->get('cf-turnstile-response', ''),
        );
    }
}
