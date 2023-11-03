<?php

namespace GaletteTelemetry;

use Symfony\Component\Cache\Adapter\AbstractAdapter;

class Project
{
    private string $slug = 'galette';
    private string $url;
    /** @var null|false|array<string, mixed> */
    private null|array|false $schema_usage;
    private bool $schema_plugins = true;
    /** @var array<string, string> */
    private array $mapping = [];
    /** @var mixed */
    private $logger;
    private string $project_path;
    /** @var array<string, array<string, string>> */
    private array $footer_links = [];
    /** @var array<string, array<string, string>> */
    private array $social_links = [];
    /** @var array<string, array<string, string>> */
    private $dyn_references = [
        'num_members' => [
            'label'         => 'Number of members',
            'short_label'   => '#members'
        ]
     ];

    /**
     * Constructor
     *
     * @param mixed $logger Logger
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger;
        $this->project_path = __DIR__ . '/../projects/' . $this->slug;
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
        $this->checkConfig($config);

        if (isset($config['url'])) {
            $this->url = $config['url'];
        }

        if (isset($config['schema'])) {
            $this->setSchemaConfig($config['schema']);
        }

        if (isset($config['mapping'])) {
            $this->mapping = $config['mapping'];
        }

        if (isset($config['footer_links'])) {
            $this->footer_links = $config['footer_links'];
        }

        if (isset($config['social_links'])) {
            $this->social_links = $config['social_links'];
        }

        if (isset($config['dyn_references'])) {
            $this->dyn_references = $config['dyn_references'];
        }

        return $this;
    }

    /**
     * Check for required options in configuration
     *
     * @param array<string, mixed> $config Configuration values
     *
     * @return void
     */
    public function checkConfig(array $config)
    {
        if (isset($config['schema']['usage'])) {
            $cusage = $config['schema']['usage'];
            if (!is_array($cusage) && false !== $cusage) {
                throw new \UnexpectedValueException('Schema usage must be an array or false if present!');
            } elseif (is_array($cusage)) {
                if (!isset($config['mapping'])) {
                    throw new \DomainException('a mapping is mandatory if you define schema usage');
                } else {
                    $ukeys = array_keys($cusage);
                    sort($ukeys);
                    $mkeys = array_keys($config['mapping']);
                    sort($mkeys);
                    if ($ukeys != $mkeys) {
                        throw new \UnexpectedValueException('schema usage and mapping keys must fit');
                    }
                }
            }
        }

        if (isset($config['schema']['plugins'])) {
            if (false !== $config['schema']['plugins']) {
                throw new \UnexpectedValueException('Schema plugins must be false if present!');
            }
        }

        if (isset($config['dyn_references'])) {
            if (!is_array($config['dyn_references']) && $config['dyn_references'] !== false) {
                throw new \UnexpectedValueException('Dynamic references configuration must be an array or false');
            }
        }

        $known_types = [
            'number',
            'boolean'
        ];
        foreach ($this->dyn_references as $reference) {
            if (isset($reference['type']) && !in_array($reference['type'], $known_types)) {
                throw new \UnexpectedValueException('Unkown type ' . $reference['type']);
            }
        }
    }

    /**
     * Set schema configuration
     *
     * @param mixed $config Schema configuration
     *
     * @return void
     */
    private function setSchemaConfig($config)
    {
        if (isset($config['usage'])) {
            if (is_array($config['usage']) || false === $config['usage']) {
                $this->schema_usage = $config['usage'];
            }
        }
        if (isset($config['plugins'])) {
            if (false === $config['plugins']) {
                $this->schema_plugins = false;
            }
        }
    }

    /**
     * Generate or retrieve project's schema as JSON
     *
     * @param AbstractAdapter|null $cache Cache instance
     *
     * @return string
     */
    public function getSchema(?AbstractAdapter $cache): string
    {
        if (null != $cache && $cache->hasItem($this->getSlug() . 'schema')) {
            $schema = $cache->getItem($this->getSlug() . '_schema.json')->get();
            if (null != $schema) {
                return $schema;
            }
        }

        $jsonfile = realpath(__DIR__ . '/../misc/json.spec.base');
        $schema = json_decode(file_get_contents($jsonfile));

        $schema->id = ($url = $this->getURL()) ? $url : $schema->id;

        $data = $schema->properties->data;
        $slug = $this->getSlug();
        $data->properties->$slug = clone $data->properties->project;
        foreach ($data->required as &$required) {
            if ($required == 'project') {
                $required = $slug;
            }
        }
        unset($data->properties->project);

        $not_required = [];
        //false means no plugins requested
        if (false === $this->schema_plugins) {
            unset($data->properties->$slug->properties->plugins);
            $not_required[] = 'plugins';
        }

        if (null !== $this->schema_usage) {
            //first, drop existing usage config
            unset($data->properties->$slug->properties->usage);
            //false means no usage requested
            if (false !== $this->schema_usage) {
                $usages = new \stdClass();
                $usages->properties = new \stdClass();
                $requireds = [];
                foreach ($this->schema_usage as $usage => $conf) {
                    $object = new \stdClass;
                    $object->type = $conf['type'];
                    if ($conf['required']) {
                        $requireds[] = $usage;
                    }
                    $usages->properties->$usage = $object;
                }
                if (count($requireds)) {
                    $usages->required = $requireds;
                }
                $usages->type = 'object';
                $data->properties->$slug->properties->usage = $usages;
            } else {
                $not_required[] = 'usage';
            }
        }

        if (count($not_required) > 0) {
            $requireds = $data->properties->$slug->required;
            foreach ($requireds as $key => $value) {
                if (in_array($value, $not_required)) {
                    unset($requireds[$key]);
                }
            }
            $data->properties->$slug->required = array_values($requireds);
        }

        $schema = json_encode($schema);

        if (null != $cache) {
            $cached_schema = $cache->getItem($this->getSlug() . '_schema.json');
            $cached_schema->set($schema);
            $cache->save($cached_schema);
        }

        return $schema;
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

    /**
     * Get dynamic references
     *
     * @return array<string, array<string, string>>
     */
    public function getDynamicReferences(): array
    {
        return $this->dyn_references;
    }
}
