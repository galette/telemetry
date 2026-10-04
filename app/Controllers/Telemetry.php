<?php namespace GaletteTelemetry\Controllers;

use DateTimeImmutable;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Constraints\Factory;
use JsonSchema\SchemaStorage;
use JsonSchema\Validator;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Illuminate\Database\Capsule\Manager as DB;

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
        $raw_nb_tel_entries = TelemetryModel::query()->where(
            'created_at',
            '>=',
            $since
        )->count(DB::raw('DISTINCT instance_uuid'));
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
        $raw_php_versions = TelemetryModel::query()->select(
            DB::raw("split_part(php_version, '.', 1) || '.' || split_part(php_version, '.', 2) as version,
                    count(DISTINCT(instance_uuid)) as total")
        )
            ->where('created_at', '>=', $since)
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
        $galette_versions = TelemetryModel::query()->select(
            DB::raw("TRIM(trailing '-dev' FROM galette_version) as version,
                    count(DISTINCT(instance_uuid)) as total")
        )
            ->where('created_at', '>=', $since)
            ->groupBy('version')
            ->get()
            ->toArray();
        $dashboard['galette_versions'] = json_encode([[
            'type'    => 'pie',
            'hole'    => .4,
            'palette' => 'belize11',
            'labels'  => array_column($galette_versions, 'version'),
            'values'  => array_column($galette_versions, 'total')
        ]]);

        // retrieve top 5 plugins
        $top_plugins = PluginModel::query()->join(
            'plugins_telemetry',
            'plugins.id',
            '=',
            'plugins_telemetry.plugin_id'
        )
            ->select(DB::raw("plugins.name, count(plugins_telemetry.*) as total"))
            ->where(
                'plugins_telemetry.created_at',
                '>=',
                $since
            )
            ->orderBy('total', 'desc')
            ->limit(5)
            ->groupBy(DB::raw("plugins.name"))
            ->get()
            ->toArray();
        $dashboard['top_plugins'] = json_encode([[
            'type'   => 'bar',
            'marker' => ['color' => "#22727B"],
            'x'      => array_column($top_plugins, 'name'),
            'y'      => array_column($top_plugins, 'total')
        ]]);

        // retrieve os
        $os_family = TelemetryModel::query()->select(
            DB::raw("os_family, count(DISTINCT(instance_uuid)) as total")
        )
            ->where('created_at', '>=', $since)
            ->groupBy(DB::raw("os_family"))
            ->get()
            ->toArray();
        $dashboard['os_family'] = json_encode([[
            'type'    => 'pie',
            'hole'    => .4,
            'palette' => 'fall6',
            'labels'  => array_column($os_family, 'os_family'),
            'values'  => array_column($os_family, 'total')
        ]]);

        // retrieve languages
        $languages = TelemetryModel::query()->select(
            DB::raw("instance_default_language, count(DISTINCT(instance_uuid)) as total")
        )
            ->where('created_at', '>=', $since)
            ->groupBy(DB::raw("instance_default_language"))
            ->get()
            ->toArray();
        $dashboard['default_languages'] = json_encode([[
            'type'    => 'pie',
            'hole'   => .4,
            'palette' => 'combo',
            'labels'  => array_column($languages, 'instance_default_language'),
            'values'  => array_column($languages, 'total')
        ]]);

        // retrieve db engine
        $db_engines = TelemetryModel::query()->select(
            DB::raw("CASE
                        WHEN UPPER(db_engine) LIKE '%POSTGRE%' THEN 'PostgreSQL'
                        WHEN UPPER(db_engine) LIKE '%MARIA%' THEN 'MariaDB'
                        WHEN UPPER(db_engine) LIKE '%PERCONA%' THEN 'Percona'
                        ELSE 'MySQL'
                    END as reduced_db_engine,
                    count(DISTINCT(instance_uuid)) as total")
        )
            ->where('created_at', '>=', $since)
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
        $web_engines = TelemetryModel::query()->select(
            DB::raw("web_engine, count(DISTINCT(instance_uuid)) as total")
        )
        ->where([
            ['created_at', '>=', $since],
            ['web_engine', '<>', '']
        ])
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
        $countries = null;

        $cache = $this->container->get('cache');
        if ($cache->hasItem('countries')) {
            $countries = $cache->getItem('countries')->get();
        }

        if ($countries === null) {
            $container_countries = $this->container->get('countries');
            $references_countries = ReferenceModel::query()->select(
                DB::raw("country as cca2, count(*) as total")
            )
                ->where('is_displayed', '=', true)
                ->groupBy(DB::raw("country"))
                ->orderBy('total', 'desc')
                ->get()
                ->toArray();
            $all_cca2 = array_column($container_countries, 'cca2');
            $db_countries = [];
            foreach ($references_countries as $ctry) {
                //replace alpha2 by alpha3 codes
                $cca2 = strtoupper($ctry['cca2']);
                $idx  = array_search($cca2, $all_cca2);
                if ($idx === false) {
                    continue;
                }
                $cca3 = strtolower($container_countries[$idx]['cca3']);
                $db_countries[] = $cca3;
            }

            $dir = $this->container->get('countries_dir');
            $countries_geo = [];
            foreach (scandir("$dir/data/") as $file) {
                if (strpos($file, '.geo.json') !== false) {
                    $geo_alpha3 = str_replace('.geo.json', '', $file);
                    if (in_array($geo_alpha3, $db_countries)) {
                        $countries_geo[$geo_alpha3] = json_decode(file_get_contents("$dir/data/$file"), true);
                    }
                }
            }
            $countries = json_encode($countries_geo);

            $cached_countries = $cache->getItem('countries');
            $cached_countries->set($countries);
            $cache->save($cached_countries);
        }

        return $this->withJson($response, (array)json_decode($countries));
    }

    public function schema(Request $request, Response $response): Response
    {
        $schema = $this->container->get('project')->getSchema();
        return $this->withJson($response, $schema);
    }

    public function allPlugins(Request $request, Response $response): Response
    {
        $since = $this->getSinceDate($this->getYears($request));

        $top_plugins = PluginModel::query()->join(
            'plugins_telemetry',
            'plugins.id',
            '=',
            'plugins_telemetry.plugin_id'
        )
            ->selectRaw("plugins.name, count(plugins_telemetry.*) as total")
            ->where(
                'plugins_telemetry.created_at',
                '>=',
                $since
            )
            ->orderBy('total', 'desc')
            ->groupBy(DB::raw("plugins.name"))
            ->limit(30)
            ->get()
            ->toArray();

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
