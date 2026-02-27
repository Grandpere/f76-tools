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

namespace App\Tests\Functional\Admin;

use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

final class ItemTranslationControllerTest extends WebTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?KernelBrowser $client = null;
    private string $testCatalogFile;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        \assert(is_string($projectDir));
        $this->testCatalogFile = $projectDir.'/translations/items.zz.yaml';

        $this->truncateTables();
        $this->cleanupTestCatalog();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestCatalog();
        parent::tearDown();
        $this->entityManager?->close();
        $this->entityManager = null;
        $this->client = null;
    }

    public function testPageRedirectsWhenNotAuthenticated(): void
    {
        $this->browser()->request('GET', '/admin/translations/items');

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertStringContainsString('/login', (string) $this->browser()->getResponse()->headers->get('location'));
    }

    public function testPageRendersForAuthenticatedUser(): void
    {
        $user = $this->createUser('translations-view@example.com');
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/admin/translations/items?locale=fr&target=zz&q=item.misc.10.name');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(1, $crawler->filter('h1:contains("Traductions des items")'));
        self::assertCount(1, $crawler->filter('textarea[name="entries[item.misc.10.name]"]'));
    }

    public function testPostPersistsTranslationsForLocale(): void
    {
        $user = $this->createUser('translations-save@example.com');
        $this->browser()->loginUser($user);
        $crawler = $this->browser()->request('GET', '/admin/translations/items?locale=fr&target=zz');
        $tokenNode = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);
        $token = (string) $tokenNode->attr('value');
        self::assertNotSame('', $token);

        $entryNode = $crawler->filter('textarea[name^="entries["]')->first();
        self::assertCount(1, $entryNode);
        $entryFieldName = (string) $entryNode->attr('name');
        self::assertMatchesRegularExpression('/^entries\[(.+)\]$/', $entryFieldName);
        preg_match('/^entries\[(.+)\]$/', $entryFieldName, $matches);
        $entryKey = $matches[1];

        $this->browser()->request('POST', '/admin/translations/items?locale=fr&target=zz', [
            '_csrf_token' => $token,
            'target' => 'zz',
            'entries' => [
                $entryKey => 'Valeur FR test',
            ],
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        self::assertFileExists($this->testCatalogFile);
        $parsed = Yaml::parseFile($this->testCatalogFile);
        self::assertIsArray($parsed);
        self::assertSame('Valeur FR test', $parsed[$entryKey] ?? null);
    }

    public function testPostRejectsInvalidCsrfToken(): void
    {
        $user = $this->createUser('translations-invalid-csrf@example.com');
        $this->browser()->loginUser($user);

        $this->browser()->request('POST', '/admin/translations/items?locale=fr&target=zz', [
            '_csrf_token' => 'invalid-token',
            'target' => 'zz',
            'entries' => [
                'item.misc.10.name' => 'Should not be saved',
            ],
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());
        self::assertFileDoesNotExist($this->testCatalogFile);
    }

    public function testPageSupportsPaginationParameters(): void
    {
        $user = $this->createUser('translations-pagination@example.com');
        $this->browser()->loginUser($user);

        $crawler = $this->browser()->request('GET', '/admin/translations/items?locale=fr&target=zz&q=item.misc.&perPage=2&page=2');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        self::assertCount(2, $crawler->filter('textarea[name^="entries["]'));
        self::assertCount(1, $crawler->filter('div.translations-pager'));
    }

    private function createUser(string $email): UserEntity
    {
        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('$2y$13$5QzWfXyM7FuU7f1w8rRZBupJrbj5gaMmkX6A8hA1z7f4h56yQW2mS');

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('TRUNCATE TABLE player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function cleanupTestCatalog(): void
    {
        if (is_file($this->testCatalogFile)) {
            unlink($this->testCatalogFile);
        }
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
