<?php


namespace TedbowDrupalScripts;

use Symfony\Component\Console\Application;

class ScriptApplication extends Application
{
    protected $xdebugConfirmed = false;

    /**
     * Checks if xdebug option has already been confirmed.
     *
     * This helps when calling 1 command from another.
     *
     * @return bool
     *
     * @see \TedbowDrupalScripts\Command\CommandBase::confirmXedbug()
     */
    public function isXdebugConfirmed(): bool
    {
        return $this->xdebugConfirmed;
    }

    /**
     * Sets xdebug option confirmed.
     *
     * @param bool $xdebugConfirmed
     *
     * @see \TedbowDrupalScripts\Command\CommandBase::confirmXedbug()
     */
    public function setXdebugConfirmed(bool $xdebugConfirmed = true): void
    {
        $this->xdebugConfirmed = $xdebugConfirmed;
    }
}
