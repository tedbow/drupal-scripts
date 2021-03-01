<?php


namespace TedbowDrupalScripts;


use Symfony\Component\Console\Application;

class ScriptApplication extends Application
{
    protected $xdebugConfirmed = FALSE;

    /**
     * @return bool
     */
    public function isXdebugConfirmed(): bool
    {
        return $this->xdebugConfirmed;
    }

    /**
     * @param bool $xdebugConfirmed
     */
    public function setXdebugConfirmed(bool $xdebugConfirmed = true): void
    {
        $this->xdebugConfirmed = $xdebugConfirmed;
    }
}