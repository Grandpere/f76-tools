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

use App\Catalog\Domain\Entity\MinervaRotationEntity;
use App\Catalog\Domain\Minerva\MinervaRotationSourceEnum;
use App\Identity\Domain\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MinervaRotationControllerTest extends WebTestCase
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

    public function testNonAdminCannotAccessAdminPage(): void
    {
        $user = $this->createUser('member@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/admin/minerva-rotation');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanAccessAdminPage(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);
        $this->browser()->request('GET', '/admin/minerva-rotation');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanRegenerateRotationFromForm(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/regenerate', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
        ]);

        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $count = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        self::assertTrue(is_int($count) || is_numeric($count));
        self::assertGreaterThan(0, (int) $count);
    }

    public function testAdminCanCreateAndDeleteManualOverride(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/override/create"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/override/create', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'location' => 'Foundation',
            'listCycle' => '9',
            'startsAt' => '2026-04-01T12:00',
            'endsAt' => '2026-04-03T12:00',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $manual = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->findOneBy(['source' => MinervaRotationSourceEnum::MANUAL->value]);
        self::assertInstanceOf(MinervaRotationEntity::class, $manual);
        $manualId = $manual->getId();
        self::assertIsInt($manualId);

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $deleteTokenNode = $crawler->filter(sprintf(
            'form[action*="/admin/minerva-rotation/override/%d/delete"] input[name="_csrf_token"]',
            $manualId,
        ));
        self::assertCount(1, $deleteTokenNode);

        $this->browser()->request('POST', sprintf('/admin/minerva-rotation/override/%d/delete', $manualId), [
            '_csrf_token' => (string) $deleteTokenNode->attr('value'),
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $deleted = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->findOneBy(['id' => $manualId]);
        self::assertNull($deleted);
    }

    public function testRegenerationKeepsManualOverrideAndSkipsOverlappingGeneratedWindow(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->browser()->loginUser($admin);

        $this->persistRotation(
            source: MinervaRotationSourceEnum::MANUAL,
            location: 'Foundation',
            listCycle: 8,
            startsAt: '2026-03-12T12:00:00+00:00',
            endsAt: '2026-03-16T12:00:00+00:00',
        );

        $crawler = $this->browser()->request('GET', '/admin/minerva-rotation');
        $tokenNode = $crawler->filter('form[action*="/admin/minerva-rotation/regenerate"] input[name="_csrf_token"]');
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', '/admin/minerva-rotation/regenerate', [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'from' => '2026-03-01',
            'to' => '2026-03-20',
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $rows = $this->entityManager?->getRepository(MinervaRotationEntity::class)
            ->findBy([], ['startsAt' => 'ASC']);
        self::assertIsArray($rows);
        /** @var list<MinervaRotationEntity> $rows */
        $manualCount = 0;
        $generatedCount = 0;
        foreach ($rows as $row) {
            if (MinervaRotationSourceEnum::MANUAL === $row->getSource()) {
                ++$manualCount;
                continue;
            }
            if (
                $row->getStartsAt() <= new DateTimeImmutable('2026-03-16T12:00:00+00:00')
                && $row->getEndsAt() >= new DateTimeImmutable('2026-03-12T12:00:00+00:00')
            ) {
                self::fail('Generated row should be skipped when overlapped by manual override.');
            }
            ++$generatedCount;
        }

        self::assertSame(1, $manualCount);
        self::assertGreaterThan(0, $generatedCount);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $plainPassword, array $roles): UserEntity
    {
        $hasher = $this->browser()->getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $user = new UserEntity()
            ->setEmail($email)
            ->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, $plainPassword));

        $this->entityManager?->persist($user);
        $this->entityManager?->flush();

        return $user;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE minerva_rotation, contact_message, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function persistRotation(
        MinervaRotationSourceEnum $source,
        string $location,
        int $listCycle,
        string $startsAt,
        string $endsAt,
    ): void {
        $rotation = new MinervaRotationEntity()
            ->setSource($source)
            ->setLocation($location)
            ->setListCycle($listCycle)
            ->setStartsAt(new DateTimeImmutable($startsAt))
            ->setEndsAt(new DateTimeImmutable($endsAt));

        $this->entityManager?->persist($rotation);
        $this->entityManager?->flush();
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
