<?php

namespace SanSIS\BizlayBundle\Controller;

use \Doctrine\Common\Annotations\AnnotationReader;
use \JMS\DiExtraBundle\Annotation as DI;
use \SanSIS\BizlayBundle\Entity\AbstractEntity;
use \SanSIS\BizlayBundle\Service\ServiceData;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use \Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use \Symfony\Bundle\FrameworkBundle\Controller\Controller;
use \Symfony\Component\DependencyInjection\ContainerInterface;
use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class ControllerAbstract extends Controller
{
    /**
     * Título dos relatórios
     */
    protected $institutionalTitle = '';

    /**
     * Subtitulos dos relatórios
     */
    protected $institutionalSubscription = '';

    /**
     * Coloca a controller em modo de debug, para não obscurecimento dos ids
     */
    protected $debug = false;

    /**
     * Atenção: o padrão é nomedacontroller.service.
     * Caso não seja definido, será buscado automaticamente.
     *
     * @var string - nome da service principal do controller
     */
    protected $service;

    /**
     * @var string - nome da view a ser renderizada
     */
    protected $indexView;

    /**
     * @var string - Título para a view
     */
    protected $indexFormAction = "Pesquisar / Listar";

    /**
     * @var Token CSRF
     */
    protected $token;

    /**
     * Delimitador utilizado para a obscurecer os ids
     *
     * @var string
     */
    protected static $__boundary = '<-==->';

    protected static $__time = null;

    /**
     * Hora utilizada para obscurecer ids
     *
     * @return [type] [description]
     */
    public function getTime()
    {
        if (is_null(self::$__time)) {
            self::$__time = time();
        }
        return self::$__time;
    }

    /**
     * Define o token para os formulários
     * @param [type] $token [description]
     */
    protected function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * Obtém o token para os formulários
     *
     * @return [type] [description]
     */
    protected function getToken()
    {
        $token = $this->get('form.csrf_provider')->generateCsrfToken('validateForm');
        $this->setToken($token);

        return $this->token;
    }

    /**
     * Ob
     * @param  [type] $item [description]
     * @return [type]       [description]
     */
    public function obfuscateIds($item)
    {
        if (is_array($item)) {
            if (!$this->debug || $this->get('kernel')->getEnvironment() == "prod") {
                foreach ($item as $k => $val) {
                    if (is_object($val) && $val instanceof AbstractEntity) {
                        $val = $val->toArray();
                    }
                    if (is_array($val)) {
                        $item[$k] = $this->obfuscateIds($val);
                    } else {
                        if ($k === 'id') {
                            $item[$k] = $this->obfuscateEntityId($item['id']);
                        }
                    }
                }
            }
        }
        return $item;
    }

    public function obfuscateEntityId($val = null)
    {
        if ($val) {
            $val = base64_encode($this->getTime() . self::$__boundary . $val);
        }
        return $val;
    }

    public function clarifyIds($item)
    {
        if (is_array($item)) {
            if (!$this->debug || $this->get('kernel')->getEnvironment() == "prod") {
                foreach ($item as $k => $val) {
                    if (is_array($val)) {
                        $item[$k] = $this->clarifyIds($val);
                    } else {
                        if ($k === 'id' || $k === 'idDel') {
                            $item[$k] = $this->clarifyEntityId($item[$k]);
                        }
                    }
                }
            }
        }
        return $item;
    }

    public function clarifyEntityId($val = null)
    {
        if ($val && !is_int($val)) {
            $tmpId = explode(self::$__boundary, base64_decode($val));
            if (isset($tmpId[1])) {
                $val = $tmpId[1];
            }
        }

        return $val;
    }

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        /**
         * Adiciona o innerEntity na lista de annotations ignoradas
         */
        AnnotationReader::addGlobalIgnoredName('innerEntity');
        //$this->get('doctrine.orm.entity_manager')->getConfiguration()->setQuoteStrategy(new OracleQuoteStrategy());
    }

    /**
     * Action default da controller
     *
     * @Route("/")
     * @Template
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        if (method_exists($this->getService(), 'getFormData')) {
            $params = array(
                'formData' => $this->getService()->getFormData(),
            );
        } else {
            $params = array();
        }

        $params['formTitleAction'] = $this->indexFormAction;

        if ($this->indexView) {
            return $this->render($this->indexView, $params);
        } else {
            return $params;
        }
    }

    /**
     * Pega um conjunto de dados e retorna no formato JSON
     * @param mixed $data
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function renderJson($data)
    {

        $resp = json_encode($data);

        $header = array(
            'Content-Type' => 'application/json',
        );

        $response = new Response($resp, 200, $header);

        return $response;
    }

    /**
     * Retorna a Service respectiva da Controller
     *
     * @return \SanSIS\BizlayBundle\Service\AbstractService
     */
    protected function getService()
    {
        if ($this->service) {
            return $this->get($this->service);
        } else {
            $arr = explode('\\', get_class($this));
            $arr[count($arr) - 1] = (str_replace('Controller', '', $arr[count($arr) - 1])) . '.service';

            return $this->get(strtolower($arr[count($arr) - 1]));
        }
    }

    /**
     * @param string $message
     * @param string $type
     */
    protected function addMessage($message, $type = 'info')
    {
        $this->get('core.message')->addMessage($type, $message);
    }

    /**
     * Redireciona de volta para a página anterior
     *
     * @param integer $status
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectByReferer($status = 302)
    {
        $params = $this->getRefererRoute();
        return $this->redirectByRouteName($params[0], $status, $params[1]);
    }

    /**
     * Obtém o nome da rota do referer - útil para redirects
     *
     * @return array
     */
    protected function getRefererRoute()
    {
        $request = $this->getRequest();

        //look for the referer route
        $referer = $request->headers->get('referer');
        $host = $request->headers->get('host');
        $lastPath = substr($referer, strpos($referer, $request->headers->get('host')));

        $lastPath = str_replace($host, '', $lastPath);
        $lastPath = str_replace('/app_dev.php', '', $lastPath);
        $lastPath = str_replace('/app.php', '', $lastPath);
        $lastPath = explode('?', $lastPath);
        $routePath = $lastPath[0];

        $matcher = $this->get('router')->getMatcher();
        $parameters = $matcher->match($routePath);

        $route = $parameters['_route'];

        $tmp = array();

        foreach ($parameters as $k => $param) {
            if ($k != '_route' && $k != '_controller') {
                $tmp[$k] = $param;
            }
        }

        if (isset($lastPath[1])) {
            $params = explode('&', $lastPath[1]);
            foreach ($params as $param) {
                $par = explode('=', $param);
                $tmp[$par[0]] = $par[1];
            }
        }

        $params = $tmp;

        return array($route, $params);
    }

    /**
     * Redireciona com base no nome da rota
     *
     * @param string $routeName
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function redirectByRouteName(
        $routeName,
        $status = 302,
        $parameters = array(),
        $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ) {
        return $this->redirect($this->generateUrl($routeName, $parameters, $referenceType), $status);
    }

    /**
     *
     */
    protected function getRefererUrl()
    {
        $params = $this->getRefererRoute();
        return $this->generateUrl($params[0], $params[1], UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * @return ServiceData
     */
    public function getDto()
    {
        $dto = $this->get('symfonyservicedto');

        $request = $this->clarifyIds($dto->request->all());
        foreach ($request as $k => $v) {
            $dto->request->set($k, $v);
        }

        $query = $this->clarifyIds($dto->query->all());
        foreach ($query as $k => $v) {
            $dto->query->set($k, $v);
        }

        return $dto;
    }

    /**
     * Retorna nome de rotas conforme construídas pela annotation
     *
     * @param $action - nome da action (usar edit, create, view, delete, search ou index)
     * @return string - nome da rota construída automaticamente
     */
    public function autoRoute($action)
    {
        $routeName = $action . 'Route';

        $action = str_replace('action', '', strtolower($action));
        $actionRouteName = $action . 'Route';
        if (!isset($this->$actionRouteName) || !$this->$actionRouteName) {
            $class = str_replace('Controller', '', get_class($this));
            $class = str_replace('Bundle', '', $class);
            $class = str_replace('\\\\', '\\', $class);
            $actionRoute = strtolower(str_replace('\\', '_', $class)) . '_' . $action;
            return $this->$actionRouteName = $actionRoute;
        } else {
            return $this->$actionRouteName;
        }
    }

    /**
     * Renderiza um array em formato do excel.
     * @param  [type] $arr [description]
     * @return [type]      [description]
     */
    public function renderExcel($arr, $cab = null, $cols = null)
    {
        $this->exportArrayToExcel($arr, $cab, $cols);
    }

    /**
     * Converte qualquer array bidimensional dado em um Excel
     *
     * @param $arr
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     */
    public function exportArrayToExcel($arr, $cab = null, $cols = null)
    {
        if (!$cab) {
            $cab = $this->setExportHeader($arr);
        }
        if (!empty($cols)) {
            $arr = $this->filterCols($arr, $cols);
        }

        //Cria o excel e adiciona o conteúdo a ele
        $excel = new \PHPExcel();
        $sheet = $excel->setActiveSheetIndex(0);
        // //cabeçalho
        $sheet->fromArray($cab, null, 'A14');
        // //corpo
        $sheet->fromArray($arr, null, 'A15');
        // autosize cols
        if (!empty($cols)) {
            for ($col = 'A', $i = 0; $i < count($cols); $col++, $i++) {
                $excel->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
                $excel->getActiveSheet()->getStyle($col . "14")->getFont()->setBold(true);
            }
        }
        $excel->getActiveSheet()->getStyle('A11:I11')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $excel->getActiveSheet()->getStyle('A12:I12')->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $excel->getActiveSheet()->getStyle('A11:I11')->getFont()->setBold(true);
        $excel->getActiveSheet()->getStyle('A12:I12')->getFont()->setBold(true);
        $excel->setActiveSheetIndex(0)->mergeCells('A11:I11');
        $excel->setActiveSheetIndex(0)->mergeCells('A12:I12');
        $excel->setActiveSheetIndex(0)->setCellValue('A11', $this->institutionalTitle);
        $excel->setActiveSheetIndex(0)->setCellValue('A12', $this->institutionalSubscription);
        $this->drawLogo($excel);
        // Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="export.xls"');
        header('Cache-Control: max-age=0');
        $objWriter = \PHPExcel_IOFactory::createWriter($excel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    private function drawLogo($phpExcelObject)
    {
        $objDrawing = new \PHPExcel_Worksheet_Drawing;
        $objDrawing->setName('Logo');
        $objDrawing->setDescription('Logo');
        $objDrawing->setPath('images/logo-relatorio-pequena-excel.png')
                   ->setResizeProportional(false)
                   ->setOffsetX(20)
                   ->setOffsetY(5)
                   ->setWorksheet($phpExcelObject->getActiveSheet());
    }

    /**
     * Filtra os índices do array que devem ser passados para o Excel
     *
     * @param $arr
     * @param $cols
     * @return array
     */
    public function filterCols($arr, $cols)
    {
        $newArr = array();
        $newArrItem = array();
        for ($i = 0; $i < count($arr); $i++) {
            foreach ($cols as $index) {
                $newArrItem[$index] = $arr[$i][$index];
            }
            array_push($newArr, $newArrItem);
        }
        return $newArr;
    }

    /**
     * Constrói o cabeçalho do documento exportado automaticamente.
     * Deve ser sobrescrito quando não se quiser o cabeçalho com o nome do atributo no objeto.
     *
     * @param $arr
     * @return array
     */
    public function setExportHeader($arr)
    {
        $cab = array();
        foreach ($arr[0] as $k => $v) {
            $cab[$k] = $k;
        }
        return $cab;
    }

    /**
     * Constrói as colulas do documento exportado automaticamente.
     * Deve ser sobrescrito
     *
     * @param $arr
     * @return array
     */
    public function setExportColumns($arr)
    {
        $cab = array();
        foreach ($arr[0] as $k => $v) {
            $cab[$k] = $k;
        }
        return $cab;
    }

    /**
     * Renderiza um array em formato PDF.
     * @param  [type] $arr [description]
     * @return [type]      [description]
     */
    public function renderPdf($data, $cab = null, $cols = null, $template = 'BizlayBundle::exportpdf.html.twig')
    {
        if (!$cab) {
            $cab = $this->setExportHeader($data);
        }

        if (!$cols) {
            $cols = $this->setExportColumns($data);
        }

        $html = $this->container->get('templating')->render($template, array(
            'colNames' => $cab,
            'colValues' => $cols,
            'result' => $data,
            'institutionalTitle' => $this->institutionalTitle,
            'institutionalSubscription' => $this->institutionalSubscription,
            //'logo' => $this->getLogo()
        ));

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="file.pdf"',
            )
        );
    }
}
