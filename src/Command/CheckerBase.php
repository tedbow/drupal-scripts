<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class CheckerBase extends CommandBase
{

    protected const REQUIRE_CLEAN_GIT = FALSE;

    /**
     * @var string|null
     */
    protected $diffPoint;

    protected function execute(InputInterface $input, OutputInterface $output, ?string $diffPoint = NULL) {
        if (parent::execute($input, $output) === self::FAILURE) {
            return self::FAILURE;
        }
        if (!$diffPoint) {
            $diffPoint = $this->getDiffPoint();
        }
        $this->diffPoint = $diffPoint;
        if (!$this->doCheck()) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    /**
     * @return bool
     */
    abstract protected function doCheck(): bool;
}