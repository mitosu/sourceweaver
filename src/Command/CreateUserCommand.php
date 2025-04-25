<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Crea un nuevo usuario de forma segura (solo para entornos de desarrollo)',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private ParameterBagInterface $params,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('roles', InputArgument::IS_ARRAY, 'Roles separados por espacio');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->params->get('kernel.environment');
        $email = $input->getArgument('email');

        if (!in_array($env, ['dev', 'test'], true)) {
            $this->sendProdAlert($email);

            $output->writeln('<error>⛔ Comando bloqueado: no se puede ejecutar en producción (' . $env . ')</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '¿Deseas realmente crear el usuario ' . $email . '? (yes/no) ', 
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>Operación cancelada por el usuario.</comment>');
            return Command::SUCCESS;
        }

        $plainPassword = $input->getArgument('password');
        $roles = $input->getArgument('roles');

        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));

        $this->em->persist($user);
        $this->em->flush();

        $executor = getenv('USER') ?: getenv('USERNAME') ?: 'desconocido';
        $output->writeln('<info>✅ Usuario creado correctamente por: ' . $executor . '</info>');

        return Command::SUCCESS;
    }

    private function sendProdAlert(string $emailTargeted)
    {
        $alert = (new Email())
            ->from('alerta@ironwhisper.com')
            ->to('admin@ironwhisper.com') // Ajusta el destinatario real
            ->subject('🚨 ALERTA: Intento de creación de usuario en producción')
            ->text('Se intentó crear un usuario (' . $emailTargeted . ') en entorno de producción.');

        $this->mailer->send($alert);
    }
}