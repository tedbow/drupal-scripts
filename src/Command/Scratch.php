<?php


namespace TedbowDrupalScripts\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Just a command to run php
 */
class Scratch extends CommandBase {

  protected const REQUIRE_CLEAN_GIT = false;

  protected static $defaultName = "scratch";
  protected function execute(InputInterface $input, OutputInterface $output) {
    if (parent::execute($input, $output) === self::FAILURE) {
      return self::FAILURE;
    }
    $this->deleteComposerRepos();
    return self::SUCCESS;
  }

  private function deleteComposerRepos() {
    $fs = new Filesystem();
    $fs->remove('test.json');
    $fs->copy('composer.json', 'test.json');
    $json = json_decode(file_get_contents('test.json'));
    unset($json->repositories);
    file_put_contents('test.json', json_encode($json));

  }


}