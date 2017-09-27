<?php
use Phalcon\Mvc\View;
use Phalcon\Mvc\Controller;

class TestController extends ControllerBase
{

    public function indexAction()
    {
	   //echo json_encode(['data'=>['id'=>123]]);
       $this->view->disable();
       echo $this->data->send(['kkkk'=>['id'=>123]]);
    }

    public function getInfoAction()
    {
        $this->view->disable();
        
        echo $this->data->send(['hlbitem'=>12121]);
    }

    public function hlbAction()
    {
	   //echo "<hl>gadddd.<hl>";
        $data = json_encode(array('item'=>1234));
        $tmp = encodeResponseData($data);

        $result = decodeResponseData($tmp);
        print_r(json_decode($result));
    }

    public function showViewAction()
    {
        echo "<hl>get info ...<hl>";
    }  
}





