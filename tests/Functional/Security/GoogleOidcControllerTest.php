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

namespace App\Tests\Functional\Security;

use App\Identity\Application\Oidc\GoogleOidcClient;
use App\Identity\Application\Oidc\GoogleOidcConfig;
use App\Identity\Application\Oidc\GoogleOidcUserProfile;
use App\Identity\Domain\Entity\UserEntity;
use App\Identity\Domain\Entity\UserIdentityEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GoogleOidcControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->truncateTables();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->client = null;
    }

    public function testStartRouteRedirectsToGoogleAuthorizationWhenEnabled(): void
    {
        $this->browser()->getContainer()->set(GoogleOidcConfig::class, new TestEnabledGoogleOidcConfig());
        $this->browser()->getContainer()->set(GoogleOidcClient::class, new TestGoogleOidcClient('https://accounts.google.com/o/oauth2/v2/auth'));

        $this->browser()->request('GET', '/en/auth/google/start');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        $location = (string) $this->browser()->getResponse()->headers->get('location');
        self::assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        self::assertStringContainsString('state=', $location);
        self::assertStringContainsString('nonce=', $location);
        self::assertStringContainsString('code_challenge=', $location);
    }

    public function testCallbackWithInvalidStateRedirectsToLoginWithWarning(): void
    {
        $this->browser()->getContainer()->set(GoogleOidcConfig::class, new TestEnabledGoogleOidcConfig());
        $this->browser()->getContainer()->set(GoogleOidcClient::class, new TestGoogleOidcClient('https://accounts.google.com/o/oauth2/v2/auth?mock=1'));

        $this->browser()->request('GET', '/en/auth/google/callback?code=code-123&state=invalid-state');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/en/login', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
    }

    public function testCallbackCreatesUserIdentityAndLogsIn(): void
    {
        $client = new TestGoogleOidcClient('https://accounts.google.com/o/oauth2/v2/auth');
        $this->browser()->getContainer()->set(GoogleOidcConfig::class, new TestEnabledGoogleOidcConfig());
        $this->browser()->getContainer()->set(GoogleOidcClient::class, $client);
        $this->browser()->disableReboot();

        $this->browser()->request('GET', '/en/auth/google/start');
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $redirectLocation = (string) $this->browser()->getResponse()->headers->get('location');
        parse_str((string) parse_url($redirectLocation, PHP_URL_QUERY), $redirectQuery);
        $state = $redirectQuery['state'] ?? null;
        self::assertIsString($state);
        self::assertNotSame('', trim($state));

        $this->browser()->request('GET', '/en/auth/google/callback?code=code-123&state='.rawurlencode($state));
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/en/', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $user = $this->entityManager?->getRepository(UserEntity::class)->findOneBy(['email' => 'oidc@example.com']);
        self::assertInstanceOf(UserEntity::class, $user);
        self::assertTrue($user->isEmailVerified());

        $identity = $this->entityManager?->getRepository(UserIdentityEntity::class)->findOneBy([
            'provider' => 'google',
            'providerUserId' => 'sub-test',
        ]);
        self::assertInstanceOf(UserIdentityEntity::class, $identity);
        self::assertSame('oidc@example.com', $identity->getProviderEmail());

        $this->browser()->followRedirect();
        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('oidc@example.com', $this->browser()->getResponse()->getContent() ?: '');
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}

final class TestEnabledGoogleOidcConfig implements GoogleOidcConfig
{
    public function isEnabled(): bool
    {
        return true;
    }

    public function issuer(): string
    {
        return 'https://accounts.google.com';
    }

    public function clientId(): string
    {
        return 'test-client-id';
    }

    public function clientSecret(): string
    {
        return 'test-client-secret';
    }
}

final class TestGoogleOidcClient implements GoogleOidcClient
{
    public function __construct(
        private readonly string $authorizationUrl,
    ) {
    }

    public function buildAuthorizationUrl(string $redirectUri, string $state, string $nonce, string $codeChallenge): string
    {
        $query = http_build_query([
            'state' => $state,
            'nonce' => $nonce,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
        ]);

        return $this->authorizationUrl.'?'.$query;
    }

    public function fetchUserProfileFromAuthorizationCode(string $code, string $redirectUri, string $codeVerifier): GoogleOidcUserProfile
    {
        return new GoogleOidcUserProfile('sub-test', 'oidc@example.com', true);
    }
}
