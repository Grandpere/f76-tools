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

namespace App\Identity\UI\Console;

use App\Identity\Application\User\UserByEmailFinder;
use App\Identity\Domain\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:promote-admin',
    description: 'Ajoute ROLE_ADMIN a un utilisateur existant sans modifier son mot de passe.',
)]
final class PromoteUserAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserByEmailFinder $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email utilisateur a promouvoir.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailRaw = $input->getArgument('email');
        if (!is_string($emailRaw) || '' === trim($emailRaw)) {
            $io->error('Email invalide.');

            return Command::INVALID;
        }

        $email = mb_strtolower(trim($emailRaw));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Format email invalide.');

            return Command::INVALID;
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user instanceof UserEntity) {
            $io->error(sprintf('Utilisateur introuvable: %s', $email));

            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $io->success(sprintf('L utilisateur est deja admin: %s', $email));

            return Command::SUCCESS;
        }

        $roles[] = 'ROLE_ADMIN';
        $user->setRoles(array_values(array_unique($roles)));
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur promu admin: %s', $email));

        return Command::SUCCESS;
    }
}
