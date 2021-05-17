<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to call 'git push' and run checks first.
 *
 * This is used instead using a 'pre-push' git hook because it could time out
 * if tests take a long time.
 */
class GitPush extends CommandBase
{
    use RunTestsTrait;

    protected static $defaultName = 'git:push';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure();
        $this->setAliases(['push']);
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Force option to pass on to git push command.');
        $this->addArgument('pass_on',InputArgument::IS_ARRAY, 'Arguments to pass on to git push command.');
        $this->addTestRunOptions();
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        $runChecks = $this->getApplication()->get('run:checks');
        $input->setOption('skip', $input->getOption('skip'));
        if ($runChecks->execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        $passOnArgs = $input->getArgument('pass_on');
        $args_string  = implode(' ', $passOnArgs);
        $args_string .= $input->getOption('force') === null ? ' --force' : '';
        // Enforce this command is used to push.
        // @see pre-push.php
        touch('.pre-push');
        system("git push $args_string");
        return self::SUCCESS;
    }


}