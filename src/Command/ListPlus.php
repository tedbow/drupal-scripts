<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ListPlus extends ListCommand
{

    //protected static $defaultName = 'listplus';

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        parent::configure(); // TODO: Change the autogenerated stub
        $this->setName('listplus');
    }


    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $args = $input->getArguments();
        parent::execute($input, $output);
        $noArgCommands = [];
        $allAliases = [];
        foreach ($this->getApplication()->all() as $name => $command) {
            // If commands are always listed before their aliases this will work.
            $allAliases = array_merge($allAliases, $command->getAliases());
            if (in_array($name, ['list', 'help', 'listplus']) || in_array($name, $allAliases)) {
                continue;
            }
            $args = $command->getDefinition()->getArguments();
            foreach ($args as $arg) {
                if ($arg->isRequired()) {
                    continue 2;
                }
            }
            $noArgCommands[$name] = $command;
        }
        $q = new QuestionHelper($input, $output);
        $choice = $q->ask($input, $output, new ChoiceQuestion("Run?", array_keys($noArgCommands)));
        $command = $noArgCommands[$choice];
        $command->run($input, $output);
        return self::SUCCESS;

    }


}