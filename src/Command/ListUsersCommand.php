<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:list-users',
    description: 'Lista todos los usuarios registrados en la aplicación',
)]
class ListUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParameterBagInterface $params
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->params->get('kernel.environment');
        if (!in_array($env, ['dev', 'test'], true)) {
            $output->writeln('<error>⛔ Este comando no puede ejecutarse en producción (entorno actual: ' . $env . ')</error>');
            return Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $users = $this->em->getRepository(User::class)->findAll();

        if (empty($users)) {
            $io->warning('No hay usuarios registrados.');
            return Command::SUCCESS;
        }

        $data = [];
        foreach ($users as $user) {
            $data[] = [
                $user->getId(),
                $user->getEmail(),
                implode(', ', $user->getRoles()),
            ];
        }

        $io->table(
            ['UUID', 'Email', 'Roles'],
            $data
        );

        return Command::SUCCESS;
    }
}
