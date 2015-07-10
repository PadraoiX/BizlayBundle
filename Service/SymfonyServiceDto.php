<?php
namespace SanSIS\BizlayBundle\Service;

use \JMS\DiExtraBundle\Annotation as DI;
use \Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class SymfonyServiceDto
 * @package SanSIS\BizlayBundle\Service
 * @DI\Service("symfonyservicedto")
 */
class SymfonyServiceDto extends ServiceDto
{
    /**
     * @DI\InjectParams({
     *     "rstck" = @DI\Inject("request_stack")
     * })
     */
    public function __construct(RequestStack $rstck)
    {
        parent::__construct();

        $req = $rstck->getCurrentRequest();

        $this->request = $req->request;
        $this->query = $req->query;
        $this->files = $req->files;

        $this->session = $req->getSession();
    }

    public function __destruct()
    {
    }
}
