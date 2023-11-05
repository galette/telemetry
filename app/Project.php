<?php

namespace GaletteTelemetry;

class Project
{
    private string $slug = 'galette';
    private string $url;
    /** @var mixed */
    private $logger;
    /** @var array<string, array<string, string>> */
    private array $footer_links = [];
    /** @var array<string, array<string, string>> */
    private array $social_links = [];

    /**
     * Constructor
     *
     * @param mixed $logger Logger
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Set project configuration
     *
     * @param array<string, mixed> $config Configuration values
     *
     * @return Project
     */
    public function setConfig(array $config): self
    {
        if (isset($config['url'])) {
            $this->url = $config['url'];
        }

        if (isset($config['footer_links'])) {
            $this->footer_links = $config['footer_links'];
        }

        if (isset($config['social_links'])) {
            $this->social_links = $config['social_links'];
        }

        return $this;
    }

    /**
     * Generate or retrieve project's schema as JSON
     *
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        $jsonfile = realpath(__DIR__ . '/../misc/json.spec.base');
        return json_decode(file_get_contents($jsonfile), true);
    }

    /**
     * Map schema data into model
     *
     * @param array<string, mixed> $json JSON sent data as array
     *
     * @return array<string, mixed>
     */
    public function mapModel(array $json): array
    {
        $slug = $this->getSlug();

        //basic mapping
        $data = [
            'instance_uuid' => $this->truncate($json[$slug]['uuid'], 41),
            'galette_version' => $this->truncate($json[$slug]['version'], 25),
            'instance_default_language' => $this->truncate($json[$slug]['default_language'], 10),
            'db_engine' => $this->truncate($json['system']['db']['engine'], 50),
            'db_version' => $this->truncate($json['system']['db']['version'], 50),
            'db_size' => (int) $json['system']['db']['size'],
            'db_log_size' => (int) $json['system']['db']['log_size'],
            'db_sql_mode' => $json['system']['db']['sql_mode'],
            'web_engine' => $this->truncate($json['system']['web_server']['engine'], 50),
            'web_version' => $this->truncate($json['system']['web_server']['version'], 50),
            'php_version' => $this->truncate($json['system']['php']['version'], 50),
            'php_modules' => implode(',', $json['system']['php']['modules']),
            'php_config_max_execution_time' => (int) $json['system']['php']['setup']['max_execution_time'],
            'php_config_memory_limit' => $this->truncate($json['system']['php']['setup']['memory_limit'], 10),
            'php_config_post_max_size' => $this->truncate($json['system']['php']['setup']['post_max_size'], 10),
            'php_config_safe_mode' => (bool) $json['system']['php']['setup']['safe_mode'],
            'php_config_session' => $json['system']['php']['setup']['session'],
            'php_config_upload_max_filesize' => $this->truncate($json['system']['php']['setup']['upload_max_filesize'], 10),
            'os_family' => $this->truncate($json['system']['os']['family'], 50),
            'os_distribution' => $this->truncate($json['system']['os']['distribution'], 50),
            'os_version' => $this->truncate($json['system']['os']['version'], 50),
        ];

        $usage = [
            'avg_members' => $this->truncate($json[$slug]['usage']['avg_members'], 50),
            'avg_contributions' => $this->truncate($json[$slug]['usage']['avg_contributions'], 50),
            'avg_transactions' => $this->truncate($json[$slug]['usage']['avg_transactions'], 50),
        ];

        return $data + $usage;
    }

    /**
     * Truncate a string
     *
     * @param string  $string Original string to truncate
     * @param integer $length String length limit
     *
     * @return string
     */
    public function truncate(string $string, int $length): string
    {
        if (mb_strlen($string) > $length) {
            if ($this->logger !== null) {
                $this->logger->warning("String exceed length $length", [$string]);
            } else {
                trigger_error("String exceed length $length\n$string", E_USER_NOTICE);
            }
            $string = mb_substr($string, 0, $length);
        }

        return $string;
    }

    /**
     * Get project slug
     *
     * @return string
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get project URL
     *
     * @return string
     */
    public function getURL(): string
    {
        return $this->url;
    }

    /**
     * Get footer links
     *
     * @return array<string, array<string, string>>
     */
    public function getFooterLinks(): array
    {
        return $this->footer_links;
    }

    /**
     * Get social links
     *
     * @return array<string, array<string, string>>
     */
    public function getSocialLinks(): array
    {
        return $this->social_links;
    }
}
