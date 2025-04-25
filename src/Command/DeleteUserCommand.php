<?php
namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:delete-user',
    description: 'Elimina un usuario por email (solo en entornos seguros)',
)]
class DeleteUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParameterBagInterface $params,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Correo del usuario a eliminar');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $env = $this->params->get('kernel.environment');
        $email = $input->getArgument('email');

        if (!in_array($env, ['dev', 'test'], true)) {
            $this->sendProdAlert($email);

            $output->writeln('<error>⛔ Este comando está prohibido en producción (entorno actual: ' . $env . ')</error>');
            return Command::FAILURE;
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln('<comment>⚠️ Usuario no encontrado: ' . $email . '</comment>');
            return Command::SUCCESS;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            '¿Seguro que deseas eliminar el usuario ' . $email . '? (yes/no) ', false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('<comment>❌ Operación cancelada.</comment>');
            return Command::SUCCESS;
        }

        $this->em->remove($user);
        $this->em->flush();

        $executor = getenv('USER') ?: getenv('USERNAME') ?: 'desconocido';
        $output->writeln('<info>✅ Usuario eliminado por: ' . $executor . '</info>');

        return Command::SUCCESS;
    }

    private function sendProdAlert(string $emailTargeted)
    {
        $alert = (new Email())
            ->from('alerta@ironwhisper.com')
            ->to('admin@ironwhisper.com') // Ajusta aquí el destinatario real
            ->subject('🚨 ALERTA: Intento de eliminación de usuario en producción')
            ->text('Se intentó eliminar el usuario (' . $emailTargeted . ') en entorno de producción.');

        $this->mailer->send($alert);
    }
}