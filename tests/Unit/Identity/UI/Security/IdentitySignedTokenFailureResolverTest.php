<?php

declare(strict_types=1);

/*
 * This file is part of a F76 project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Identity\UI\Security;

use App\Identity\Application\Security\SignedUrlGenerator;
use App\Identity\UI\Security\IdentitySignedTokenFailureResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class IdentitySignedTokenFailureResolverTest extends TestCase
{
    public function testInvalidSignatureReturnsFailureWithoutCallingTokenValidator(): void
    {
        $resolver = $this->buildResolver();
        $tokenValidatorCalled = false;

        $failureMessage = $resolver->resolve(
            Request::create('https://example.test/reset-password/token'),
            static function () use (&$tokenValidatorCalled): bool {
                $tokenValidatorCalled = true;

                return true;
            },
            'security.reset.flash.invalid_or_expired',
        );

        self::assertSame('security.reset.flash.invalid_or_expired', $failureMessage);
        self::assertFalse($tokenValidatorCalled);
    }

    public function testSignedRequestWithInvalidTokenReturnsFailure(): void
    {
        $uriSigner = new UriSigner('test-secret');
        $resolver = $this->buildResolver($uriSigner);
        $tokenValidatorCalled = false;

        $failureMessage = $resolver->resolve(
            Request::create($uriSigner->sign('https://example.test/reset-password/token')),
            static function () use (&$tokenValidatorCalled): bool {
                $tokenValidatorCalled = true;

                return false;
            },
            'security.reset.flash.invalid_or_expired',
        );

        self::assertSame('security.reset.flash.invalid_or_expired', $failureMessage);
        self::assertTrue($tokenValidatorCalled);
    }

    public function testSignedRequestWithValidTokenReturnsNull(): void
    {
        $uriSigner = new UriSigner('test-secret');
        $resolver = $this->buildResolver($uriSigner);

        $failureMessage = $resolver->resolve(
            Request::create($uriSigner->sign('https://example.test/reset-password/token')),
            static fn (): bool => true,
            'security.reset.flash.invalid_or_expired',
        );

        self::assertNull($failureMessage);
    }

    private function buildResolver(?UriSigner $uriSigner = null): IdentitySignedTokenFailureResolver
    {
        return new IdentitySignedTokenFailureResolver(
            new SignedUrlGenerator(
                self::createStub(UrlGeneratorInterface::class),
                $uriSigner ?? new UriSigner('test-secret'),
            ),
        );
    }
}
