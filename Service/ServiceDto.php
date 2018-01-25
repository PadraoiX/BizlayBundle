<?php
namespace SanSIS\BizlayBundle\Service;

use JMS\DiExtraBundle\Annotation as DI;

/**
 * Class ServiceDto
 * @package SanSIS\BizlayBundle\Service
 * @DI\Service("servicedto")
 */
class ServiceDto
{
    /**
     * [$requestData description]
     * @var array
     */
    public $request;

    public $query;

    public $files;

    public $session;

    public function __construct()
    {
        if (isset($_POST)) {
            $this->request = new ServiceParameterBag($_POST);
        }
        if (isset($_GET)) {
            $this->query = new ServiceParameterBag($_GET);
        }
        if (isset($_FILES)) {
            $this->files = new ServiceFileBag($_FILES);
        }
        if (isset($_SESSION)) {
            $this->session = new ServiceParameterBag($_SESSION);
        }
    }

    public function __destruct()
    {
        if ($this->session) {
            $_SESSION = $this->session->all();
        }

    }
}
