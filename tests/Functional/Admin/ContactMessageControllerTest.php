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
use App\Support\Domain\Contact\ContactMessageStatusEnum;
use App\Support\Domain\Entity\ContactMessageEntity;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ContactMessageControllerTest extends WebTestCase
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

    public function testNonAdminCannotAccessContactMessagesPage(): void
    {
        $user = $this->createUser('member@example.com', 'secret123', ['ROLE_USER']);
        $this->browser()->loginUser($user);
        $this->browser()->request('GET', '/en/admin/contact-messages');

        self::assertSame(403, $this->browser()->getResponse()->getStatusCode());
    }

    public function testAdminCanSeeContactMessagesAndFilterByStatus(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $this->createContactMessage('one@example.com', 'Question 1', 'First message body', ContactMessageStatusEnum::NEW);
        $this->createContactMessage('two@example.com', 'Question 2', 'Second message body', ContactMessageStatusEnum::CLOSED);

        $this->browser()->loginUser($admin);
        $this->browser()->request('GET', '/en/admin/contact-messages?status=closed');

        self::assertSame(200, $this->browser()->getResponse()->getStatusCode());
        $content = $this->browser()->getResponse()->getContent() ?: '';
        self::assertStringContainsString('two@example.com', $content);
        self::assertStringNotContainsString('one@example.com', $content);
    }

    public function testAdminCanUpdateContactMessageStatus(): void
    {
        $admin = $this->createUser('admin@example.com', 'secret123', ['ROLE_ADMIN']);
        $contact = $this->createContactMessage('visitor@example.com', 'Need help', 'Message body for status update.');

        $this->browser()->loginUser($admin);
        $crawler = $this->browser()->request('GET', '/en/admin/contact-messages');
        $tokenNode = $crawler->filter(sprintf('form[action*="/en/admin/contact-messages/%d/status"] input[name="_csrf_token"]', $contact->getId()));
        self::assertCount(1, $tokenNode);

        $this->browser()->request('POST', sprintf('/en/admin/contact-messages/%d/status', $contact->getId()), [
            '_csrf_token' => (string) $tokenNode->attr('value'),
            'status' => ContactMessageStatusEnum::IN_PROGRESS->value,
        ]);
        self::assertSame(302, $this->browser()->getResponse()->getStatusCode());

        $this->entityManager?->clear();
        $updated = $this->entityManager?->find(ContactMessageEntity::class, $contact->getId());
        self::assertInstanceOf(ContactMessageEntity::class, $updated);
        self::assertSame(ContactMessageStatusEnum::IN_PROGRESS, $updated->getStatus());
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

    private function createContactMessage(
        string $email,
        string $subject,
        string $message,
        ContactMessageStatusEnum $status = ContactMessageStatusEnum::NEW,
    ): ContactMessageEntity {
        $contact = new ContactMessageEntity()
            ->setEmail($email)
            ->setSubject($subject)
            ->setMessage($message)
            ->setStatus($status)
            ->setIp('127.0.0.1');

        $this->entityManager?->persist($contact);
        $this->entityManager?->flush();

        return $contact;
    }

    private function truncateTables(): void
    {
        if (null === $this->entityManager) {
            return;
        }
        $this->entityManager->getConnection()->executeStatement('TRUNCATE TABLE contact_message, player_item_knowledge, item_book_list, player, item, app_user RESTART IDENTITY CASCADE');
    }

    private function browser(): KernelBrowser
    {
        if (null === $this->client) {
            throw new LogicException('Client is not initialized.');
        }

        return $this->client;
    }
}
