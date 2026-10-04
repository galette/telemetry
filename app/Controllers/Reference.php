<?php namespace GaletteTelemetry\Controllers;

use GaletteTelemetry\Gaptcha;
use GaletteTelemetry\Models\Reference as ReferenceModel;
use PHPMailer\PHPMailer\PHPMailer;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class Reference extends ControllerAbstract
{

    public function view(Request $request, Response $response): Response
    {
        $get = $request->getQueryParams();
        // default session param for this controller
        if (!isset($_SESSION['reference'])) {
            $_SESSION['reference'] = [
                "orderby" => 'updated_at',
                "sort"    => "desc"
            ];
        }

        $gaptcha = new Gaptcha();
        $_SESSION['gaptcha'] = $gaptcha->getAnswer();

        $_SESSION['reference']['pagination'] = 15;
        $order_field = $_SESSION['reference']['orderby'];
        $order_sort  = $_SESSION['reference']['sort'];

        //prepare model and common queries
        $model = ReferenceModel::query()->select('reference.*');
        $where = [
            ['is_displayed', '=', true]
        ];

        $current_filters = [];
        if (isset($_SESSION['reference']['filters'])) {
            if (!empty($_SESSION['reference']['filters']['name'])) {
                $current_filters['name'] = $_SESSION['reference']['filters']['name'];
                $where[] = ['name', 'like', "%{$_SESSION['reference']['filters']['name']}%"];
            }
            if (!empty($_SESSION['reference']['filters']['country'])) {
                $current_filters['country'] = $_SESSION['reference']['filters']['country'];
                $where[] = ['country', '=', strtolower($_SESSION['reference']['filters']['country'])];
            }
        }

        $model->where($where);
        if (count($where) > 1) {
            //calculate filtered number of references
            $current_filters['count'] = $model->count('reference.id');
        }

        $model->orderBy(
            'reference.' . $order_field,
            $order_sort
        );

        $references = $model->paginate($_SESSION['reference']['pagination']);

        $references->setPath($this->routeparser->urlFor('reference'));

        $ref_countries = [];
        $existing_countries = ReferenceModel::query()->select('country')->groupBy('country')->get();
        foreach ($existing_countries as $existing_country) {
            $ref_countries[] = $existing_country['country'];
        }

        // render in twig view
        $this->view->render(
            $response,
            'default/reference.html.twig',
            [
                'total'         => ReferenceModel::query()->where('is_displayed', '=', true)->count(),
                'class'         => 'reference',
                'showmodal'     => isset($get['showmodal']),
                'uuid'          => $get['uuid'] ?? '',
                'references'    => $references,
                'orderby'       => $_SESSION['reference']['orderby'],
                'sort'          => $_SESSION['reference']['sort'],
                'filters'       => $current_filters,
                'ref_countries' => $ref_countries,
                'gaptcha'       => $gaptcha
            ]
        );
        return $response;
    }

    /**
     * Reference fields that can be submitted, with their maximum length
     */
    private const FIELDS = [
        'uuid'        => 41,
        'name'        => 505,
        'url'         => 505,
        'country'     => 10,
        'phone'       => 30,
        'email'       => 505,
        'referent'    => 505,
        'num_members' => 10,
        'comment'     => 5000
    ];

    /**
     * Fields references list can be ordered on
     */
    private const ORDER_FIELDS = ['name', 'country', 'num_members', 'updated_at'];

    public function register(Request $request, Response $response): Response
    {
        $post = (array)$request->getParsedBody();

        //check captcha
        if (!Gaptcha::checkSession($post['gaptcha'] ?? null)) {
            return $this->redirectWithError($response, 'Invalid captcha');
        }

        // keep only known fields
        $ref_data = [];
        foreach (self::FIELDS as $field => $length) {
            $value = trim((string)($post[$field] ?? ''));
            if (mb_strlen($value) > $length) {
                return $this->redirectWithError($response, sprintf('Field %s is too long', $field));
            }
            $ref_data[$field] = $value === '' ? null : $value;
        }

        $errors = $this->validate($ref_data);
        if (count($errors)) {
            return $this->redirectWithError($response, implode(' ', $errors));
        }

        if ($ref_data['country'] !== null) {
            $ref_data['country'] = strtolower($ref_data['country']);
        }
        if ($ref_data['num_members'] !== null) {
            $ref_data['num_members'] = (int)$ref_data['num_members'];
        }

        // create reference in db
        if ($ref_data['uuid'] === null) {
            $reference = ReferenceModel::query()->create($ref_data);
        } else {
            $reference = ReferenceModel::query()->updateOrCreate(
                ['uuid' => $ref_data['uuid']],
                $ref_data
            );
        }
        // any new or updated reference must be moderated
        $reference->forceFill(['is_displayed' => false])->save();

        // send a mail to admin
        if (!empty($this->container->get('mail_admin'))) {
            $mail = new PHPMailer();
            $mail->setFrom($this->container->get('mail_from'));
            $mail->addAddress($this->container->get('mail_admin'));
            $mail->Subject = "A new reference has been submitted: " . $ref_data['name'];
            $mail->Body    = var_export($ref_data, true);
            $mail->send();
        }

        // store a message for user (displayed after redirect)
        $this->container->get('flash')->addMessage(
            'success',
            'Your reference has been stored! An administrator will moderate it before display on the site.'
        );

        // redirect to ok page
        return $this->redirect($response);
    }

    public function filter(Request $request, Response $response): Response
    {
        $post = (array)$request->getParsedBody();
        if (isset($post['reset_filters'])) {
            unset($_SESSION['reference']['filters']);
        } else {
            $_SESSION['reference']['filters'] = [
                'name'     => mb_substr(trim((string)($post['filter_name'] ?? '')), 0, 505),
                'country'  => mb_substr(trim((string)($post['filter_country'] ?? '')), 0, 10)
            ];
        }

        return $this->redirect($response);
    }

    public function order(Request $request, Response $response, string $field): Response
    {
        if (!in_array($field, self::ORDER_FIELDS, true) || !isset($_SESSION['reference'])) {
            return $this->redirect($response);
        }

        if ($_SESSION['reference']['orderby'] == $field) {
            // toggle sort if orderby requested on the same column
            $_SESSION['reference']['sort'] = ($_SESSION['reference']['sort'] == "desc"
                ? "asc"
                : "desc");
        }
        $_SESSION['reference']['orderby'] = $field;

        return $this->redirect($response);
    }

    /**
     * Validate reference data
     *
     * @param array<string, ?string> $data Reference data
     *
     * @return array<string> Errors
     */
    private function validate(array $data): array
    {
        $errors = [];

        if ($data['name'] === null) {
            $errors[] = 'Name is mandatory.';
        }

        if ($data['url'] !== null) {
            $url = preg_match('/^https?:\/\//i', $data['url']) ? $data['url'] : 'https://' . $data['url'];
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                $errors[] = 'URL is invalid.';
            }
        }

        if ($data['email'] !== null && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email is invalid.';
        }

        if ($data['country'] !== null) {
            $countries = array_keys($this->container->get('countries_names'));
            if (!in_array(strtolower($data['country']), $countries, true)) {
                $errors[] = 'Country is invalid.';
            }
        }

        $int_options = ['options' => ['min_range' => 0]];
        if ($data['num_members'] !== null && filter_var($data['num_members'], FILTER_VALIDATE_INT, $int_options) === false) {
            $errors[] = 'Number of members is invalid.';
        }

        return $errors;
    }

    /**
     * Redirect to references list
     *
     * @param Response $response Response instance
     *
     * @return Response
     */
    private function redirect(Response $response): Response
    {
        return $response
            ->withStatus(303)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('reference')
            );
    }

    /**
     * Redirect to references list with an error message
     *
     * @param Response $response Response instance
     * @param string   $message  Error message
     *
     * @return Response
     */
    private function redirectWithError(Response $response, string $message): Response
    {
        $this->container->get('flash')->addMessage('error', $message);
        return $this->redirect($response);
    }
}
