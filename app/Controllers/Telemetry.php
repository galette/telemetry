<?php namespace GaletteTelemetry\Controllers;

use DateTimeImmutable;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Builder;

use GaletteTelemetry\Models\Telemetry  as TelemetryModel;
use GaletteTelemetry\Models\Plugin as PluginModel;
use GaletteTelemetry\Models\Reference  as ReferenceModel;
use GaletteTelemetry\Models\PluginTelemetry;

class Telemetry extends ControllerAbstract
{

    public function view(Request $request, Response $response): Response
    {
        $years = $this->getYears($request);
        $since = $this->getSinceDate($years);

        $dashboard = [];

        // retrieve nb of telemetry entries
        $raw_nb_tel_entries = $this->latestEntries($since)->count();
        $nb_tel_entries = [
            'raw' => $raw_nb_tel_entries,
            'nb'  => (string)$raw_nb_tel_entries
        ];
        $dashboard['nb_telemetry_entries'] = json_encode($nb_tel_entries);

        // retrieve nb of reference entries
        $raw_nb_ref_entries = ReferenceModel::query()
            ->where(
                'updated_at',
                '>=',
                $since
            )
            ->where(
                'is_displayed',
                '=',
                true
            )
            ->count();
        $nb_ref_entries = [
            'raw' => $raw_nb_ref_entries,
            'nb'  => (string)$raw_nb_ref_entries
        ];
        $dashboard['nb_reference_entries'] = json_encode($nb_ref_entries);

        // retrieve php versions -- bar
        $raw_php_versions = $this->latestEntries($since)->select(
            DB::raw("split_part(php_version, '.', 1) || '.' || split_part(php_version, '.', 2) as version,
                    count(*) as total")
        )
            ->groupBy(DB::raw("version"))
            ->orderBy(DB::raw("version"), 'asc')
            ->get()
            ->toArray();

        $php_versions_series = [
            'y'    => [],
            'x'    => [],
            'type' => 'bar',
        ];

        foreach ($raw_php_versions as $php_version) {
            $php_versions_series['x'][] = 'PHP ' . $php_version['version'];
            $php_versions_series['y'][] = $php_version['total'];
        }
        $dashboard['php_versions'] = json_encode([$php_versions_series]);

        // retrieve reference country
        $container_countries = $this->container->get('countries');
        $references_countries = ReferenceModel::query()->select(
            DB::raw("country as cca2, count(*) as total")
        )
            ->where('created_at', '>=', $since)
            ->where('is_displayed', '=', true)
            ->groupBy(DB::raw("country"))
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();
        $all_cca2 = array_column($container_countries, 'cca2');
        $references_countries_codes = [];
        foreach ($references_countries as $ctry) {
            //replace alpha2 by alpha3 codes
            $cca2 = strtoupper($ctry['cca2']);
            $idx  = array_search($cca2, $all_cca2);
            if ($idx === false) {
                continue;
            }
            $references_countries_codes[] = [
                'total' => $ctry['total'],
                'cca3'  => strtolower($container_countries[$idx]['cca3']),
                'name'  => $container_countries[$idx]['name']['common']
            ];
        }
        $dashboard['references_countries'] = json_encode($references_countries_codes);

        // retrieve galette versions
        $galette_versions = $this->latestEntries($since)->select(
            DB::raw("TRIM(trailing '-dev' FROM galette_version) as version,
                    count(*) as total")
        )
            ->groupBy('version')
            ->get()
            ->toArray();
        $galette_versions = $this->groupOldVersions($galette_versions);
        $dashboard['galette_versions'] = json_encode([[
            'type'      => 'pie',
            'hole'      => .4,
            'sort'      => false,
            'direction' => 'clockwise',
            'palette'   => 'belize11',
            'labels'    => array_column($galette_versions, 'version'),
            'values'    => array_column($galette_versions, 'total')
        ]]);

        // retrieve top 5 plugins
        $top_plugins = $this->topPlugins($since, 5);
        $dashboard['top_plugins'] = json_encode([[
            'type'   => 'bar',
            'marker' => ['color' => "#22727B"],
            'x'      => array_column($top_plugins, 'name'),
            'y'      => array_column($top_plugins, 'total')
        ]]);

        // retrieve os
        $os_family = $this->latestEntries($since)->select(
            // some hosts send the full uname (ie. "Linux localhost 4.18.0-553…"), keep only OS name
            DB::raw("regexp_replace(os_family, '^(\\S+) \\S+ [0-9].*$', '\\1') as reduced_os_family,
                    count(*) as total")
        )
            ->groupBy('reduced_os_family')
            ->get()
            ->toArray();
        $dashboard['os_family'] = json_encode([[
            'type'    => 'pie',
            'hole'    => .4,
            'palette' => 'fall6',
            'labels'  => array_column($os_family, 'reduced_os_family'),
            'values'  => array_column($os_family, 'total')
        ]]);

        // retrieve languages
        $languages = $this->latestEntries($since)->select(
            DB::raw("instance_default_language, count(*) as total")
        )
            ->groupBy(DB::raw("instance_default_language"))
            ->get()
            ->toArray();
        // display language names; regional variants are merged
        $languages_names = [];
        foreach ($languages as $language) {
            $name = \Locale::getDisplayLanguage($language['instance_default_language'], 'en');
            $languages_names[$name] = ($languages_names[$name] ?? 0) + $language['total'];
        }
        uksort(
            $languages_names,
            fn($a, $b) => $languages_names[$b] <=> $languages_names[$a] ?: strcmp($a, $b)
        );
        $dashboard['default_languages'] = json_encode([[
            'type'        => 'bar',
            'orientation' => 'h',
            'marker'      => ['color' => '#006495'],
            'x'           => array_values($languages_names),
            'y'           => array_keys($languages_names)
        ]]);

        // retrieve db engine
        $db_engines = $this->latestEntries($since)->select(
            DB::raw("CASE
                        WHEN UPPER(db_engine) LIKE '%POSTGRE%' THEN 'PostgreSQL'
                        WHEN UPPER(db_engine) LIKE '%MARIA%' THEN 'MariaDB'
                        WHEN UPPER(db_engine) LIKE '%PERCONA%' THEN 'Percona'
                        ELSE 'MySQL'
                    END as reduced_db_engine,
                    count(*) as total")
        )
            ->groupBy('reduced_db_engine')
            ->get()
            ->toArray();
        $dashboard['db_engines'] = json_encode([[
            'type'   => 'pie',
            'hole'   => .4,
            'palette' => 'nivo',
            'labels' => array_column($db_engines, 'reduced_db_engine'),
            'values' => array_column($db_engines, 'total')
        ]]);

        // retrieve web engine
        $web_engines = $this->latestEntries($since)->select(
            DB::raw("web_engine, count(*) as total")
        )
            ->where('web_engine', '<>', '')
            ->groupBy(DB::raw("web_engine"))
            ->get()
            ->toArray();
        $dashboard['web_engines'] = json_encode([[
            'type'    => 'pie',
            'hole'    => .4,
            'palette' => 'bluestone',
            'labels'  => array_column($web_engines, 'web_engine'),
            'values'  => array_column($web_engines, 'total')
        ]]);

        $this->view->render(
            $response,
            'default/telemetry.html.twig',
            [
                'form' => [
                    'years' => $years
                ],
                'class' => 'telemetry'
            ] + $dashboard
        );

        return $response;
    }

    public function send(Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', 'application/json');

        // check request content type
        if (!str_contains($request->getHeaderLine('Content-Type'), 'application/json')) {
            $response = $response->withStatus(400);
            $response->getBody()->write(
                json_encode([
                    'message' => 'Content-Type must be application/json'
                ])
            );
            return $response;
        }

        $post = $request->getBody()->getContents();
        $json = json_decode($post, true);

        // check if sent json is an array
        if (!is_array($json)) {
            $response = $response->withStatus(400);
            $response->getBody()->write(
                json_encode([
                    'message' => 'body seems invalid (not a json ?)'
                ])
            );
            return $response;
        }

        // check json structure
        $project = $this->container->get('project');
        $schema = $project->getSchema();

        $storage = new SchemaStorage();
        $storage->addSchema('file://mySchema', $schema);
        $validator = new Validator(new Factory($storage));

        $validator->validate(
            $json,
            $schema,
            Constraint::CHECK_MODE_TYPE_CAST
        );

        if (!$validator->isValid()) {
            $response = $response->withStatus(400);
            $response->getBody()->write(
                json_encode([
                    'message' => 'json not validated against schema',
                    'errors' => $validator->getErrors()
                ])
            );
            return $response;
        }

        $json = $json['data'];
        $data = $project->mapModel($json);
        /** @var TelemetryModel $telemetry_m */
        $telemetry_m = TelemetryModel::query()->create($data);

        // manage plugins
        foreach ($json[$project->getSlug()]['plugins'] as $plugin) {
            /** @var PluginModel $plugin_m */
            $plugin_m = PluginModel::query()->firstOrCreate(['name' => $project->truncate($plugin['key'], 50)]);

            PluginTelemetry::query()->create([
                'telemetry_entry_id' => $telemetry_m->id,
                'plugin_id'          => $plugin_m->id,
                'version'            => $project->truncate($plugin['version'], 50)
            ]);
        }

        return $this->withJson($response, ['message' => 'OK']);
    }

    public function geojson(Request $request, Response $response): Response
    {
        $container_countries = $this->container->get('countries');
        $references_countries = ReferenceModel::query()
            ->select('country')
            ->where('is_displayed', '=', true)
            ->groupBy('country')
            ->pluck('country')
            ->toArray();
        $all_cca2 = array_column($container_countries, 'cca2');
        $db_countries = [];
        foreach ($references_countries as $cca2) {
            //replace alpha2 by alpha3 codes
            $idx = array_search(strtoupper((string)$cca2), $all_cca2);
            if ($idx === false) {
                continue;
            }
            $db_countries[] = strtolower($container_countries[$idx]['cca3']);
        }
        sort($db_countries);

        // cache key depends on displayed countries, so a newly moderated country is shown
        $cache = $this->container->get('cache');
        $cached_countries = $cache->getItem('geojson_' . md5(implode(',', $db_countries)));
        if (!$cached_countries->isHit()) {
            $dir = $this->container->get('countries_dir');
            $countries_geo = [];
            foreach ($db_countries as $cca3) {
                $file = "$dir/data/$cca3.geo.json";
                if (file_exists($file)) {
                    $countries_geo[$cca3] = json_decode(file_get_contents($file), true);
                }
            }
            $cached_countries->set(json_encode((object)$countries_geo));
            $cached_countries->expiresAfter(30 * 24 * 3600);
            $cache->save($cached_countries);
        }

        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write($cached_countries->get());
        return $response;
    }

    public function schema(Request $request, Response $response): Response
    {
        $schema = $this->container->get('project')->getSchema();
        return $this->withJson($response, $schema);
    }

    public function allPlugins(Request $request, Response $response): Response
    {
        $since = $this->getSinceDate($this->getYears($request));

        $top_plugins = $this->topPlugins($since, 30);

        return $this->withJson(
            $response,
            [
                [
                    'type'   => 'bar',
                    'marker' => ['color' => "#22727B"],
                    'x'      => array_column($top_plugins, 'name'),
                    'y'      => array_column($top_plugins, 'total')
                ]
            ]
        );
    }

    /**
     * Get a query on the latest telemetry entry of each instance during the period
     *
     * Instances send telemetry regularly; counting all their entries would count
     * an upgraded instance once per version it ran during the period.
     *
     * @param DateTimeImmutable $since Date from which data are displayed
     *
     * @return Builder<TelemetryModel>
     */
    private function latestEntries(DateTimeImmutable $since): Builder
    {
        $latest = TelemetryModel::query()
            ->selectRaw('DISTINCT ON (instance_uuid) *')
            ->where('updated_at', '>=', $since)
            ->orderBy('instance_uuid')
            ->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc');

        $query = TelemetryModel::query();
        $query->getQuery()->fromSub($latest, 'telemetry');
        return $query;
    }

    /**
     * Get most used plugins, by number of instances using them
     *
     * @param DateTimeImmutable $since Date from which data are displayed
     * @param int               $limit Number of plugins to retrieve
     *
     * @return array<int, array<string, mixed>>
     */
    private function topPlugins(DateTimeImmutable $since, int $limit): array
    {
        return $this->latestEntries($since)
            ->join('plugins_telemetry', 'telemetry.id', '=', 'plugins_telemetry.telemetry_entry_id')
            ->join('plugins', 'plugins.id', '=', 'plugins_telemetry.plugin_id')
            ->selectRaw('plugins.name, count(DISTINCT telemetry.instance_uuid) as total')
            ->groupBy('plugins.name')
            ->orderBy('total', 'desc')
            ->orderBy('plugins.name')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Group versions of old branches, and sort them, most recent first
     *
     * Versions of the two most recent branches are kept, older ones are grouped
     * per branch (ie. "v1.1.x"). Versions without patch number are merged with
     * their ".0" counterpart.
     *
     * @param array<int, array<string, mixed>> $versions Versions, with their total
     *
     * @return array<int, array<string, mixed>>
     */
    private function groupOldVersions(array $versions): array
    {
        $branch = fn(string $version): string => preg_match('/^v?(\d+\.\d+)/', $version, $m) ? $m[1] : $version;
        $compare = fn(string $a, string $b): int => version_compare(ltrim($b, 'v'), ltrim($a, 'v'));

        $branches = array_unique(array_map(fn($version) => $branch($version['version']), $versions));
        usort($branches, $compare);
        $recent_branches = array_slice($branches, 0, 2);

        $grouped = [];
        foreach ($versions as $version) {
            $name = $branch($version['version']);
            // some versions are sent without patch number (ie. "v1.3" for "v1.3.0")
            $label = in_array($name, $recent_branches, true)
                ? preg_replace('/^(v?\d+\.\d+)$/', '$1.0', $version['version'])
                : "v$name.x";
            $grouped[$label] = ($grouped[$label] ?? 0) + $version['total'];
        }
        // compare grouped versions on their branch
        uksort($grouped, fn($a, $b) => $compare(str_replace('.x', '', $a), str_replace('.x', '', $b)));

        $versions = [];
        foreach ($grouped as $label => $total) {
            $versions[] = [
                'version' => $label,
                'total'   => $total
            ];
        }
        return $versions;
    }

    /**
     * Get requested number of years from query parameters
     *
     * @param Request $request Request instance
     *
     * @return int Number of years, between 1 and 99 (99 meaning "always")
     */
    private function getYears(Request $request): int
    {
        $years = (int)($request->getQueryParams()['years'] ?? -1);
        if ($years < 1 || $years > 99) {
            $years = 99;
        }
        return $years;
    }

    /**
     * Get the date from which data are displayed
     *
     * @param int $years Number of years
     *
     * @return DateTimeImmutable
     */
    private function getSinceDate(int $years): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('-%d years', $years));
    }
}
