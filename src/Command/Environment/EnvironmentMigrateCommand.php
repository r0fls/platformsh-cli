<?php
namespace Platformsh\Cli\Command\Environment;

use Platformsh\Cli\Command\CommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

class EnvironmentMigrateCommand extends CommandBase
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('environment:migrate')
            ->setAliases(['migrate'])
            ->addArgument('target', InputArgument::REQUIRED, 'Target environment for MySQL database sync.')
            ->setDescription('Copy DB from current environment to target environment');
        $this->addProjectOption()
             ->addEnvironmentOption()
             ->addAppOption();
    }
    protected function db(InputInterface $input, OutputInterface $output)
    {
	$EXPORT_CMD = "mysqldump -h database.internal --single-transaction main | gzip - > /tmp/database.sql.gz";
	$IMPORT_CMD = "zcat /tmp/database.sql.gz | mysql -h database.internal main";
        $this->validateInput($input);

        $sshUrl = $this->getSelectedEnvironment()
                       ->getSshUrl($this->selectApp($input));

        $sshOptions = 't';

        // Pass through the verbosity options to SSH.
        if ($output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG) {
            $sshOptions .= 'vv';
        } elseif ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERY_VERBOSE) {
            $sshOptions .= 'v';
        } elseif ($output->getVerbosity() <= OutputInterface::VERBOSITY_QUIET) {
            $sshOptions .= 'q';
        }

	// Export the database on the remote environment
        $command = "ssh -$sshOptions " . escapeshellarg($sshUrl);
        $command .= ' ' . escapeshellarg($EXPORT_CMD);

        $this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);

        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
	proc_close($process);

	// Copy the database to the local environment
        $command = "scp " . escapeshellarg($sshUrl) . ":/tmp/database.sql.gz ./";
        $this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
	proc_close($process);

	// Get the target sshUrl
	$target = $input->getArgument("target");
	$envs = $this->api()->getEnvironments($this->getSelectedProject(), $refresh ? true : null);
	$targetEnv = array_filter($envs, function($k) use($target){
		return $k == $target;
	}, ARRAY_FILTER_USE_KEY);
	$targetSshUrl = explode("//",  $targetEnv[$target]->getData()["_links"]["ssh"]["href"])[1];
	// Copy the local db to the target environment
        $command = "scp ./database.sql.gz " . escapeshellarg($targetSshUrl) . ":/tmp/";
        $this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
	proc_close($process);

	// Remove local copy of db
        $command = "rm ./database.sql.gz";
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
	proc_close($process);

	// Import the database on the target environment
        $command = "ssh -$sshOptions " . escapeshellarg($targetSshUrl);
        $command .= ' ' . escapeshellarg($IMPORT_CMD);
        $this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
        $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
	proc_close($process);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
	//$this->db($input, $output);
	// STATIC FILES

	// This gets repeated above
        $this->validateInput($input);
        $sshUrl = $this->getSelectedEnvironment()
                       ->getSshUrl($this->selectApp($input));
	$yaml = new Parser();
	$mounts = $yaml->parse(file_get_contents('./.platform.app.yaml'))["mounts"];

	foreach ($mounts as $mount) {
		$name = key($mount);
		// Copy from location to local temp directory
		$command = "scp -r " . escapeshellarg($sshUrl) . ":~/" . $name . " ./tmp." . $name;
		$this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
		$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
		proc_close($process);
		// Get the target sshUrl (this gets repeated above)
		$target = $input->getArgument("target");
		$envs = $this->api()->getEnvironments($this->getSelectedProject(), $refresh ? true : null);
		$targetEnv = array_filter($envs, function($k) use($target){
			return $k == $target;
		}, ARRAY_FILTER_USE_KEY);
		$targetSshUrl = explode("//",  $targetEnv[$target]->getData()["_links"]["ssh"]["href"])[1];
		$command = "scp -r ./tmp." . $name . escapeshellarg($targetSshUrl) . ":~/";
		$this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
		$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
		proc_close($process);
		$command = "rm ./tmp." . $name;
		$this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
		$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
		proc_close($process);
	}
	return;
    }
}
