<?php

namespace GaletteTelemetry\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Versioned static assets
 *
 * Assets are served with a long max-age and cached by the CDN: append the file
 * modification time so each build gets a new URL instead of a stale copy.
 */
class AssetExtension extends AbstractExtension
{
    /**
     * @var string
     */
    protected $public_dir;

    /**
     * @var array<string, string>
     */
    protected $versioned = [];

    /**
     * Constructor
     * @param string $public_dir Web root directory
     */
    public function __construct(string $public_dir)
    {
        $this->public_dir = rtrim($public_dir, '/');
    }

    /**
     * Get functions
     *
     * @return array<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('asset', [$this, 'getAsset'])
        ];
    }

    /**
     * Get asset path with its version
     *
     * @param string $path Path relative to web root, with a leading slash
     *
     * @return string
     */
    public function getAsset(string $path): string
    {
        if (!isset($this->versioned[$path])) {
            $mtime = @filemtime($this->public_dir . $path);
            $this->versioned[$path] = $mtime === false ? $path : $path . '?v=' . $mtime;
        }
        return $this->versioned[$path];
    }
}
