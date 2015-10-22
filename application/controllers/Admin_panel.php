<?php
/**
 * Created by IntelliJ IDEA.
 * User: wizzard
 * Date: 01.10.15
 * Time: 15:25
 */

defined('BASEPATH') OR exit('No direct script access allowed');
require_once(APPPATH . 'third_party/openpayu/lib/openpayu.php');

class Admin_panel extends T01_Controller {

    public function index()
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');
        $this->show('panels/admin/index');
    }

    public function users( )
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        $this->show( 'panels/admin/users' );
    }

    public function users_table( $page=0, $filter = null, $filter_value = null )
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        $this->load->model('Usermodel');

        $users = $this->Usermodel->get_users(
            $page,
            $this->input->post('filter'),
            $this->input->post('value')
        );
        $this->show(
            'panels/admin/users_table',
            array(
                'users' => $users,
                'next' => count($users) == 100 ? $page+1 : -1,
                'prev' => $page > 0 ? $page-1 : -1,
                'page' => $page
            )
        );
    }

    function payment_status( $payment) {
        $this->config->load('payu', true);

        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        OpenPayU_Configuration::setEnvironment('secure');
        OpenPayU_Configuration::setMerchantPosId( $this->config->item('PosId', 'payu') );
        OpenPayU_Configuration::setSignatureKey( $this->config->item('SignatureKey', 'payu') );

        $order = OpenPayU_Order::retrieve($payment)->getResponse()->orders;
        $this->Debug( $order, true );
    }


    public function user( $uid )
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        $this->load->model('Usermodel');

        $user = $this->Usermodel->fetch_user( $uid );
        if( $user == null ) redirect('/admin_panel', 'refresh');

        $this->load->model('Ordermodel');
        $plans = $this->Ordermodel->get_plan( $uid, array('A') );
        foreach( $plans as $plan )
        {
            unset($plan->banned);
            $plan->invoice = $this->Ordermodel->get_invoice4order($plan->order);

            try {
                $plan->order = $this->Ordermodel->get_order($uid, $plan->order, 'A', false)[0];
            }
            catch( Exception $e )
            {
                $plan->order = null;
            }
        }

        $calendars = $this->Ordermodel->get_callendar( $uid );
        $orders = $this->Ordermodel->get_order( null, null, null, false );

        $this->show(
            'panels/admin/user',
            array(
                'user' => $user,
                'plans' => $plans,
                'calendars' => $calendars,
                'orders' => $orders
            )
        );
    }

    public function history()
    {
        $this->show( 'panels/admin/deliveries' );
    }

    public function shedule( $date )
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        set_time_limit(0);

        $this->load->model('Ordermodel');
        $plans = $this->Ordermodel->shedule( $date );

        if( $plans != null && count($plans ) ) {
            $msg = $this->load->view('pdf/shedule', array('list' => $plans), true);
            require_once(BASEPATH . "../html2pdf/html2pdf.class.php");
            $html2pdf = new HTML2PDF('P', 'A4', 'en', true, 'UTF-8');
            $html2pdf->setDefaultFont("dejavusans");
            $html2pdf->pdf->SetDisplayMode('fullpage');
            $html2pdf->WriteHTML($msg);
            $html2pdf->Output("$date.pdf", 'D');
        }
        die( 'brak dostaw na '.$date );
    }

    public function diets( $step = 0 )
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        $this->load->model('Dietmodel');
        $this->show( 'panels/admin/diets', $this->Dietmodel->all() );
    }

    public function diet( $id = 0 )
    {
        if( !$this->isLoggedIn ) redirect('/login', 'refresh');
        if( !$this->isAdmin ) redirect('/', 'refresh');

        $this->load->model('Dietmodel');

        if( count($this->input->post()) > 0 ) {
            $this->load->library('form_validation');

            if( $id == 0 ) $this->form_validation->set_rules('name', 'nazwa diety', 'required|min_length[3]|max_length[255]|callback_diet_check');

            $prices = $this->input->post('price');
            foreach( $prices as $k => $v )
            {
                foreach( $v as $k1 => $v1 )
                    $this->form_validation->set_rules("price[$k][$k1]", '', 'required|numeric|greater_than[1]');
            }

            if ($this->form_validation->run() == TRUE ) {
                $this->Dietmodel->add( $id == 0, $this->input->post());
                redirect('/admin_panel/diets', 'refresh');
            }
        }

        $this->show(
            'panels/admin/diet',
            $this->Dietmodel->get($id)
        );
    }

    function diet_check($str)
    {
        $this->load->model('Dietmodel');
        $d = $this->Dietmodel->all()['diets'];
        if ( array_key_exists($str,$d) )
        {
            $this->form_validation->set_message('diet_check', 'form_validation_t01_exists');
            return FALSE;
        }
        else
        {
            return TRUE;
        }
    }
}