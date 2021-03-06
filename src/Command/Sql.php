<?php

namespace Jh\Workflow\Command;

use Jh\Workflow\ProcessFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * @author Michael Woodward <michael@wearejh.com>
 */
class Sql extends Command implements CommandInterface
{
    use DockerAwareTrait;
    use ProcessRunnerTrait;

    public function __construct(ProcessFactory $processFactory)
    {
        parent::__construct();
        $this->processFactory = $processFactory;
    }

    public function configure()
    {
        $this
            ->setName('sql')
            ->setDescription('Run arbitary sql against the database')
            ->addOption('sql', 's', InputOption::VALUE_OPTIONAL, 'SQL to run directly to mysql')
            ->addOption('file', 'f', InputOption::VALUE_OPTIONAL, 'Path to a file to import');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainerName('db');

        if ($input->getOption('sql')) {
            $this->runRaw($container, $input->getOption('sql'), $output);
        }

        if ($input->getOption('file')) {
            $file = $input->getOption('file');

            if (!file_exists($file) || !is_file($file)) {
                throw new \RuntimeException('SQL file does not exist!');
            }

            $this->runFile($container, $file, $output);
        }
    }

    private function runRaw(string $container, string $sql, OutputInterface $output)
    {
        extract($this->getDbDetails(), EXTR_OVERWRITE);

        $command = sprintf('docker exec -t %s mysql -u%s -p%s %s -e "%s"', $container, $user, $pass, $db, $sql);
        $this->runProcessShowingOutput($output, $command);
    }

    private function runFile(string $container, string $file, OutputInterface $output)
    {
        extract($this->getDbDetails(), EXTR_OVERWRITE);

        $command = sprintf('docker exec -i %s mysql -u%s -p%s %s < %s', $container, $user, $pass, $db, $file);
        $this->runProcessShowingOutput($output, $command);
        $output->writeln('<info>DB import complete!</info>');
    }

    private function getDbDetails() : array
    {
        $envVars   = $this->getDevEnvironmentVars();
        $dbDetails = [];

        $dbDetails['user'] = $envVars['MYSQL_USER'] ?? 'docker';
        $dbDetails['pass'] = $envVars['MYSQL_PASSWORD'] ?? 'docker';
        $dbDetails['db']   = $envVars['MYSQL_DATABASE'] ?? 'docker';

        return $dbDetails;
    }
}
