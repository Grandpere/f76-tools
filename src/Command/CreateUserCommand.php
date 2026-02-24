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

namespace App\Command;

use App\Entity\UserEntity;
use App\Repository\UserEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Cree un utilisateur local sans inscription publique.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserEntityRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email utilisateur.')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe en clair.')
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Roles supplementaires (ex: ROLE_ADMIN).');
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

        $passwordRaw = $input->getOption('password');
        if (!is_string($passwordRaw) || '' === trim($passwordRaw)) {
            $io->error('Option --password obligatoire.');

            return Command::INVALID;
        }
        $password = trim($passwordRaw);

        if (strlen($password) < 8) {
            $io->error('Le mot de passe doit contenir au moins 8 caracteres.');

            return Command::INVALID;
        }

        if (null !== $this->userRepository->findOneByEmail($email)) {
            $io->error(sprintf('Un utilisateur existe deja pour "%s".', $email));

            return Command::FAILURE;
        }

        $rolesRaw = $input->getOption('role');
        $roles = [];
        if (is_array($rolesRaw)) {
            foreach ($rolesRaw as $role) {
                if (!is_string($role)) {
                    continue;
                }
                $role = strtoupper(trim($role));
                if ('' === $role) {
                    continue;
                }
                $roles[] = $role;
            }
        }
        /** @var list<string> $roles */
        $roles = array_values(array_unique($roles));

        $user = (new UserEntity())
            ->setEmail($email)
            ->setRoles($roles);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Utilisateur cree: %s', $email));

        return Command::SUCCESS;
    }
}

