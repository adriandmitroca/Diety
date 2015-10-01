<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Login extends T01_Controller {

	public function index()
	{
        if( $this->isLoggedIn ) redirect('/', 'refresh');

        if( count($this->input->post()) > 0 )
        {
            $this->load->library('form_validation');

            $this->form_validation->set_rules('email', 'email', 'required');
            $this->form_validation->set_rules('pass', 'hasło', 'required');

            if ($this->form_validation->run() == TRUE )
            {
                $this->load->model('Usermodel');
                $session_data = $this->Usermodel->get_user( $this->input->post() );

                if( $session_data != null ) {
                    switch( $session_data->status ) {
                        case 'N':
                            $this->show('login', array('msg' => 'Konto nieaktywne, zobacz email'));
                            return;
                        case 'B':
                            $this->show('login', array('msg' => 'Konto zablokowane'));
                            return;
                        default:
                            $this->session->set_userdata('user', $session_data);
                            $this->session->unset_userdata('cart');
                            redirect('/', 'refresh');
                            break;
                    }
                }
                else {
                    $this->show('login', array( 'msg' => 'Błąd logowania'));
                    return;
                }
            }
        }

		$this->show('login', array( 'msg' => ''));
	}

    public function out()
    {
        $this->session->unset_userdata('user');
        $this->session->unset_userdata('cart');
        redirect('/', 'refresh');
    }
}
