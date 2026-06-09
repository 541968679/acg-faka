<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Controller\Base\API\Manage;
use App\Service\ManageSSO;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Post;
use Kernel\Waf\Filter;

/**
 * Class Auth
 * @package App\Controller\Admin\Api
 */
class Authentication extends Manage
{

    #[Inject]
    private ManageSSO $sso;

    public function login(): array
    {
        $username = (string)$this->request->post("username", Filter::NORMAL);
        $password = (string)$this->request->post("password", Filter::NORMAL);
        $remember = (bool)$this->request->post("remember", Filter::BOOLEAN);
        return $this->json(200, "success", $this->sso->login($username, $password, $remember));
    }
}
