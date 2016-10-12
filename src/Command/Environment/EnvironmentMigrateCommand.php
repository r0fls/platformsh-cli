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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validateInput($input);
        $sshUrl = $this->getSelectedEnvironment()
                       ->getSshUrl($this->selectApp($input));
	$yaml = new Parser();
	$mounts = $yaml->parse(file_get_contents('./.platform.app.yaml'))["mounts"];
	foreach (array_keys($mounts) as $mount) {
		$name = $mount;
		$target = $input->getArgument("target");
		$envs = $this->api()->getEnvironments($this->getSelectedProject(), $refresh ? true : null);
		$targetEnv = array_filter($envs, function($k) use($target){
			return $k == $target;
		}, ARRAY_FILTER_USE_KEY);
		$targetSshUrl = explode("//",  $targetEnv[$target]->getData()["_links"]["ssh"]["href"])[1];
		//$command = "rsync -ae " . $sshUrl . ":~/" . $name . " " . $targetSshUrl . ":~/" . $name;
		$rsync_cmd = 'rsync -e "ssh -p 50000" -vuar ~'  . $name . ' localhost:~' . $name;
		$command = "ssh -R localhost:50000:" . $targetSshUrl . ":22 " . $sshUrl . " " . escapeshellarg($rsync_cmd);
		print($command . "\n");
		//$command = 'ssh -A ' . $sshUrl . " rsync -vuar ~" . $name . " " . $targetSshUrl . ":~" . $name;
		$this->stdErr->writeln("Running command: <info>$command</info>", OutputInterface::VERBOSITY_VERBOSE);
		$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
		proc_close($process);
	}
	return;
    }
}
