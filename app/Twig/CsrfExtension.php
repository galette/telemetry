<?php

namespace GaletteTelemetry\Twig;

use Slim\Csrf\Guard;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class CsrfExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var Guard
     */
    protected $csrf;

    /**
     * Constructor
     * @param Guard $csrf CSRF instance
     */
    public function __construct(Guard $csrf)
    {
        $this->csrf = $csrf;
    }

    /**
     * Get globals
     *
     * @return array<string, ?string>
     */
    public function getGlobals(): array
    {
        // CSRF token name and value
        $nameKey = $this->csrf->getTokenNameKey();
        $valueKey = $this->csrf->getTokenValueKey();
        $name = $this->csrf->getTokenName();
        $value = $this->csrf->getTokenValue();

        return [
            'csrf_name_key' => $nameKey,
            'csrf_value_key' => $valueKey,
            'csrf_name' => $name,
            'csrf_value' => $value
        ];
    }
}
