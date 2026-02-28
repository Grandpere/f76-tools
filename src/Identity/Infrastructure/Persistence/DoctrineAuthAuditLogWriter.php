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

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Application\Security\AuthAuditLogWriter;
use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Domain\Entity\AuthAuditLogEntity;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class DoctrineAuthAuditLogWriter implements AuthAuditLogWriter
{
    public function __construct(
        private readonly UserByEmailFinder $userByEmailFinder,
        private readonly AuthAuditLogEntityRepository $authAuditLogEntityRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function write(string $level, string $event, ?string $email, ?string $clientIp, array $context): void
    {
        try {
            $entity = new AuthAuditLogEntity();
            $entity
                ->setLevel($level)
                ->setEvent($event)
                ->setClientIp($clientIp)
                ->setContext($context)
                ->setEmailHash($this->extractEmailHash($context));

            if (is_string($email) && '' !== trim($email)) {
                $entity->setUser($this->userByEmailFinder->findOneByEmail($email));
            }

            $this->authAuditLogEntityRepository->add($entity);
            $this->entityManager->flush();
        } catch (Throwable) {
            // Never break authentication flows because of audit persistence.
        }
    }

    /**
     * @param array<string, bool|float|int|string|null> $context
     */
    private function extractEmailHash(array $context): ?string
    {
        $value = $context['emailHash'] ?? null;

        return is_string($value) && '' !== trim($value) ? trim($value) : null;
    }
}
