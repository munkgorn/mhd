<?php
defined('BASEPATH') or exit('No direct script access allowed');



class Member extends CI_Controller
{
    
  public function __construct()
  {
    parent::__construct();
  }

  public function index()
  {
    $data = array();
    $data['heading_title'] = 'ข้อมูลผู้ใช้งาน';
    $this->load->template('member/form', $data);
  }

  public function dashboard()
  {
    $data = array();
    $data['heading_title'] = 'Dashboard';
    $this->load->template('member/dashboard', $data);
  }

  public function login() 
  {
    $data = array();  

    $data['action'] = base_url('member/login');
    $data['link_forgot'] = base_url('member/forgot');

    if ($this->session->has_userdata('success')) {
      $data['success'] = $this->session->success;
      $this->session->unset_userdata('success');
    } else {
      $data['success'] = '';
    }
    if ($this->session->has_userdata('error')) {
      $data['error'] = $this->session->error;
      $this->session->unset_userdata('error');
    } else {
      $data['error'] = '';
    }

    if ($this->input->server('REQUEST_METHOD')=='POST') {
      if ($this->model_member->checkConfirm($this->input->post('email'), md5($this->input->post('password'))) == false) {
        $this->session->set_userdata('error','ท่านยังไม่ได้ยืนยันอีเมล กรุณายืนยันอีเมลเข้าระบบของท่านที่อีเมลผู้สมัคร');
        log_message('error', 'ท่านยังไม่ได้ยืนยันอีเมล กรุณายืนยันอีเมลเข้าระบบของท่านที่อีเมลผู้สมัคร');
        redirect('member/login');
        exit();
      }
      $result = $this->model_member->login($this->input->post('email'), md5($this->input->post('password')));
      if ( $result !== false ) {
        $edit = array('date_login'=>date('Y-m-d H:i:s', time()));
        $this->model_member->edit($result->id, $edit);

        // ? หาค่า รหัสสมาชิก และ อีเมลบน navbar
        $member_info = $this->model_member->getLists(array('mhd_member.id'=>$result->id)); // get again for detail year;
        $email = '';
        if (count($member_info)==0) {
          $member_no = '';
          $member_no = $member_info->member_no;
          $email = $member_info->email;
        } else { // ? กรณีที่ผู้ใช้ เป็นสมาชิกหลายปี จึงมีหลายรหัส ปปปปxxxx
          $member_no = array();
          foreach ($member_info as $value) {
            $member_no[] = $value->member_no;
            $email = $value->email;
          }
          $member_no = implode(', ', $member_no);
        }

        $this->session->set_userdata('token', $this->encryption->encrypt(json_encode($result)));
        $this->session->set_userdata('member_info', array('member_no'=>$member_no, 'email'=>$email));

        $year = $this->model_setting->get('config_register_open')==1 ?$this->model_year->getList( $this->model_setting->get('config_register_year_id') )->year : false;
        $this->session->set_userdata('year', $year);
      
        redirect('home');
      } else {
        $this->session->set_userdata('error', 'อีเมล รหัสผ่านผิดพลาด');
        log_message('error', 'อีเมล รหัสผ่านผิดพลาด '.$this->input->post('email').' '.$this->input->post('password'));
        redirect('member/login?notfound');
      }
      exit();
    }

    $this->load->view('member/login', $data);
  }

  public function logout() {
    $this->session->unset_userdata('token');
    $this->session->set_userdata('success','ออกจากระบบเรียบร้อยแล้ว');
    redirect('member/login');
  }

  public function register() {
    $data = array();
    $data['action'] = base_url('member/register');
    $data['year'] = $this->model_year->getList( $this->model_setting->get('config_register_year_id') )->year;

    if ($this->session->has_userdata('success')) {
      $data['success'] = $this->session->success;
      $this->session->unset_userdata('success');
    } else {
      $data['success'] = '';
    }
    if ($this->session->has_userdata('error')) {
      $data['error'] = $this->session->error;
      $this->session->unset_userdata('error');
    } else {
      $data['error'] = '';
    }
    
    if ($this->input->server('REQUEST_METHOD')=='POST' && $this->validation_register()) {
      // ? Add Member
      $insert = array(
        'email' => $this->input->post('email'),
        'password' => md5($this->input->post('password')),
        'firstname' => $this->input->post('firstname'),
        'lastname' => $this->input->post('lastname'),
        'telephone' => $this->input->post('telephone'),
        'date_added' => date('Y-m-d H:i:s')
      );
      $member_id = $this->model_member->add($insert);

      if ($member_id>0) {
        // ? Add Company of member
        $insert = array(
          'member_id' => $member_id,
          'name' => $this->input->post('hospital'),
          'room' => $this->input->post('room'),
          'address_1' => $this->input->post('address_1'),
          'address_2' => $this->input->post('address_2'),
          'district' => $this->input->post('district'),
          'country' => $this->input->post('country'),
          'province' => $this->input->post('province'),
          'postcode' => $this->input->post('postcode'),
          'telephone' => $this->input->post('telephone'),
          'fax' => $this->input->post('fax'),
          'date_added' => date('Y-m-d H:i:s')
        );
        $company_id = $this->model_company->add($insert);

        // ? Add Register
        $insert = array(
          'parent_id' => 0, // ! Fixed this อีกเคสคือ สมัครสมาชิกให้คนอื่น
          'member_id' => $member_id,
          'company_id' => $company_id,
          'year_id' => $this->model_setting->get('config_register_year_id'),
          'total' => 0.00,
          'date_added' => date('Y-m-d H:i:s')
        );
        $this->model_register->add($insert);
        


        

        $member_info = $this->model_member->getListByEmail($this->input->post('email'));
        $this->model_member->edit($member_info->id, array('date_modify'=>date('Y-m-d H:i:s')));
        $dataemail = array(
          'name' => $this->input->post('firstname').' '.$this->input->post('lastname'),
          'email' => $this->input->post('email'),
          'link' => base_url().'member/forgot',
          'link_confirm' => base_url().'member/confirm/'.$this->input->post('email')
        );
        $message = $this->load->view('email/register', $dataemail, true);
        $subject = 'สมัครสมาชิก โครงการประเมินคุณภาพทางห้องปฏิบัติการโดยองค์กรภายนอก';
        $this->email->smtpsend($this->input->post('email'), $subject, $message);

        $this->session->set_userdata('success', 'สมัครสมาชิกเรียบร้อยแล้ว');
        redirect('member/login');
      } else {
        $this->session->set_userdata('error', 'เกิดข้อผิดพลาดในการสมัครสมาชิก');
        redirect('member/register');
      }
    }
    $this->load->view('member/register', $data);
  }

  public function forgot() 
  {
    $data = array();
    $data['action'] = base_url('member/forgot');
    if ($this->session->has_userdata('success')) {
      $data['success'] = $this->session->success;
      $this->session->unset_userdata('success');
    } else {
      $data['success'] = '';
    }
    if ($this->session->has_userdata('error')) {
      $data['error'] = $this->session->error;
      $this->session->unset_userdata('error');
    } else {
      $data['error'] = '';
    }
    
    if ($this->input->server('REQUEST_METHOD')=='POST') {
      $email = $this->input->post('email');
      $check_email = $this->model_member->findEmail($email);
      if ($check_email) {
        $member_info = $this->model_member->getListByEmail($email);
        $code = rand(10000,99999);
        $this->model_member->edit($member_info->id, array('forget_code'=>$code,'date_modify'=>date('Y-m-d H:i:s')));
        $dataemail = array(
          'name' => $member_info->firstname.' '.$member_info->lastname,
          'link' => base_url('member/change/'.$email.'/'.$code)
        );
        $message = $this->load->view('email/forgot', $dataemail, true);
        $subject = 'ลืมรหัสผ่าน โครงการประเมินคุณภาพทางห้องปฏิบัติการโดยองค์กรภายนอก';
        $this->email->smtpsend($member_info->email, $subject, $message);
      } else {
        $this->session->set_userdata('error', 'ไม่พบอีเมลในระบบ กรุณาติดต่อเจ้าหน้าที่');
        redirect('member/forgot');
      }
    }

    $this->load->view('member/forgot', $data);
  }

  public function change($email,$code) 
  {
    // $email = $this->input->get('email');
    // $code = $this->input->get('code');

    if ($this->input->server('REQUEST_METHOD')=='POST'){
      $check = $this->model_member->getEmailAndCode($email, $code);
      if ($check) {
        $member_info = $this->model_member->getListByEmail($email);
        $update = array(
          'forget_code' => '',
          'date_modify' => date('Y-m-d H:i:s'),
          'password' => md5($this->input->post('password'))
        );
        $this->model_member->edit($member_info->id, $update);
        $this->session->set_userdata('success', 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        redirect('member/login');
      } else {
        $this->session->set_userdata('error', 'รหัสและอีเมลของท่านไม่ตรงกัน กรุณาลองใหม่อีกครั้ง');
        redirect('member/forgot');
      }
    }

    $data = array();
    $data['action'] = base_url('member/change/'.$email.'/'.$code);
    
    $this->load->view('member/change', $data);
  }

  public function confirm($email)
  {
    if (!empty($email)) {
      $member_info = $this->model_member->getListByEmail($email);
      $this->model_member->edit($member_info->id, array('confirm'=>1));
      $this->session->set_userdata('success','ยืนยันอีเมลเรียบร้อยแล้ว ท่านสามารถเข้าสู่ระบบได้');
      redirect('member/login');
    } else {
      $this->session->set_userdata('error','ไม่พบอีเมลในการยืนยัน');
      redirect('member/login');
    }
    
  }

  public function validation() 
  {
    $this->session->unset_userdata('error');
    if ( valid_email($this->input->post('email')) == false ) {
      $this->session->set_userdata('error', 'ไม่พบอีเมล');
    }
    if ($this->input->post('password') == null) {
      $this->session->set_userdata('error', 'ไม่พบรหัสผ่าน');
    }

    if ($this->session->has_userdata('error')) {
      redirect('member/login');
    }
    
    return true;
  }

  public function validation_register() 
  {
    $this->session->unset_userdata('error');
    if ( valid_email($this->input->post('email')) == false ) {
      $this->session->set_userdata('error', 'ไม่พบอีเมล');
    } else {
      $result = $this->model_member->findEmail($this->input->post('email'));
      if ($result) {
        $this->session->set_userdata('error', 'อีเมลนี้มีผู้ใช้งานในระบบแล้ว');
      }
    }
    if ($this->input->post('password') == null) {
      $this->session->set_userdata('error', 'ไม่พบ รหัสผ่าน');
    }
    if ($this->input->post('confirm') == null) {
      $this->session->set_userdata('error', 'กรุณายืนยันรหัสผ่าน');
    }
    if ($this->input->post('password')!=$this->input->post('confirm')) {
      $this->session->set_userdata('error','รหัสผ่านไม่ตรงกัน');
    }
    if ($this->input->post('firstname') == null) {
      $this->session->set_userdata('error', 'ไม่พบ ชื่อ');
    }
    if ($this->input->post('lastname') == null) {
      $this->session->set_userdata('error', 'ไม่พบ นามสกุล');
    }
    if ($this->input->post('telephone') == null) {
      $this->session->set_userdata('error', 'ไม่พบ เบอร์โทรศัพท์');
    }
    if ($this->input->post('hospital') == null) {
      $this->session->set_userdata('error', 'ไม่พบ ชื่อโรงพยาบาล');
    }
    if ($this->input->post('room') == null) {
      $this->session->set_userdata('error', 'ไม่พบ ชื่อห้องปฏิบัติการ');
    }
    if ($this->input->post('address_1') == null) {
      $this->session->set_userdata('error', 'ไม่พบ ที่อยู่เลขที่บ้าน');
    }
    if ($this->input->post('district') == null) {
      $this->session->set_userdata('error', 'ไม่พบ แขวง/ตำบล');
    }
    if ($this->input->post('country') == null) {
      $this->session->set_userdata('error', 'ไม่พบ เขต/อำเภอ');
    }
    if ($this->input->post('province') == null) {
      $this->session->set_userdata('error', 'ไม่พบ จังหวัด');
    }
    if ($this->input->post('postcode') == null) {
      $this->session->set_userdata('error', 'ไม่พบ รหัสไปษณีย์');
    }
    

    if ($this->session->has_userdata('error')) {
      redirect('member/register');
    }
    
    return true;
  }

}


/* End of file Member.php */
/* Location: ./application/controllers/Member.php */