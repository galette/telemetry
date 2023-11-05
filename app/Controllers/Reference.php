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
                "orderby" => 'created_at',
                "sort"    => "desc"
            ];
        }

        $gaptcha = new Gaptcha();
        $_SESSION['gaptcha'] = serialize($gaptcha);

        $_SESSION['reference']['pagination'] = 15;
        $order_field = $_SESSION['reference']['orderby'];
        $order_sort  = $_SESSION['reference']['sort'];

        //prepare model and common queries
        $ref = new ReferenceModel();
        $model = $ref->newInstance();
        $where = [
            ['is_displayed', '=', true]
        ];

        $model = call_user_func_array(
            [
                $model,
                'select'
            ],
            ['reference.*']
        );

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

    public function register(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();

        // clean data
        $posted_gaptcha = (int)$post['gaptcha'];
        unset($post['gaptcha']);
        unset($post['csrf_name']);
        unset($post['csrf_value']);

        if (empty($post['num_members'])) {
            unset($post['num_members']);
        }

        $ref_data = $post;

        //check captcha
        $gaptcha = unserialize($_SESSION['gaptcha']);
        if (!$gaptcha->check($posted_gaptcha)) {
            $this->container->get('flash')->addMessage(
                'error',
                'Invalid captcha'
            );
            return $response
                ->withStatus(301)
                ->withHeader(
                    'Location',
                    $this->routeparser->urlFor('reference')
                );
        }

        // alter data
        $ref_data['country'] = strtolower($ref_data['country']);

        // create reference in db
        if ('' == $ref_data['uuid']) {
            $reference = ReferenceModel::query()->create(
                $ref_data
            );
        } else {
            $reference = ReferenceModel::query()->updateOrCreate(
                ['uuid' => $ref_data['uuid']],
                $ref_data
            );
        }

        // send a mail to admin
        $mail = new PHPMailer;
        $mail->setFrom($this->container->get('mail_from'));
        $mail->addAddress($this->container->get('mail_admin'));
        $mail->Subject = "A new reference has been submitted: ".$post['name'];
        $mail->Body    = var_export($post, true);
        $mail->send();

        // store a message for user (displayed after redirect)
        $this->container->get('flash')->addMessage(
            'success',
            'Your reference has been stored! An administrator will moderate it before display on the site.'
        );

        // redirect to ok page
        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('reference')
            );
    }

    public function filter(Request $request, Response $response): Response
    {
        $post = $request->getParsedBody();
        if (isset($post['reset_filters'])) {
            unset($_SESSION['reference']['filters']);
        } else {
            $_SESSION['reference']['filters'] = [
                'name'     => $post['filter_name'],
                'country'  => $post['filter_country']
            ];
        }

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('reference')
            );
    }

    public function order(Request $request, Response $response, string $field): Response
    {
        if ($_SESSION['reference']['orderby'] == $field) {
            // toggle sort if orderby requested on the same column
            $_SESSION['reference']['sort'] = ($_SESSION['reference']['sort'] == "desc"
                ? "asc"
                : "desc");
        }
        $_SESSION['reference']['orderby'] = $field;

        return $response
            ->withStatus(301)
            ->withHeader(
                'Location',
                $this->routeparser->urlFor('reference')
            );
    }
}
