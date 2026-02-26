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

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactControllerTest extends WebTestCase
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

    public function testContactPageIsAccessible(): void
    {
        $crawler = $this->browser()->request('GET', '/contact');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('form'));
        self::assertCount(1, $crawler->filter('input[name="email"]'));
        self::assertCount(1, $crawler->filter('input[name="subject"]'));
        self::assertCount(1, $crawler->filter('textarea[name="message"]'));
    }

    public function testContactPostRedirectsWithSuccessFlash(): void
    {
        $crawler = $this->browser()->request('GET', '/contact');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $csrfToken = (string) $tokenNode->attr('value');

        $this->browser()->request('POST', '/contact', [
            '_csrf_token' => $csrfToken,
            'email' => 'visitor@example.com',
            'subject' => 'Need help',
            'message' => 'Hello, I have a question about my account access.',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertSame('/contact', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));

        $this->browser()->followRedirect();
        self::assertStringContainsString(
            'Your message has been sent.',
            (string) $this->browser()->getResponse()->getContent(),
        );
    }

    public function testContactRateLimitTriggersAfterRepeatedPosts(): void
    {
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $crawler = $this->browser()->request('GET', '/contact');
            $tokenNode = $crawler->filter('input[name="_csrf_token"]');
            self::assertCount(1, $tokenNode);
            $csrfToken = (string) $tokenNode->attr('value');

            $this->browser()->request('POST', '/contact', [
                '_csrf_token' => $csrfToken,
                'email' => 'ratelimit@example.com',
                'subject' => sprintf('Request %d', $attempt),
                'message' => 'Message body valid for contact rate limiter test.',
            ]);
            self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
            self::assertSame('/contact', parse_url((string) $this->browser()->getResponse()->headers->get('location'), PHP_URL_PATH));
        }

        $this->browser()->followRedirect();
        self::assertStringContainsString(
            'Too many attempts. Please wait a few minutes and try again.',
            (string) $this->browser()->getResponse()->getContent(),
        );
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
            throw new \LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
