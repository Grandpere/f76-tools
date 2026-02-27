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

use App\Entity\MinervaRotationEntity;
use App\Entity\UserEntity;
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

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
