<?php
/**
 * (GM工具)管理端用户
 */
class LoginAdminUser extends ModelBase{
  const AUTH_SECRET = 'auth_suffix';
  
  public function initialize(){
    $this->setConnectionService('db_login_server');
  }
  
  //添加用户
  public function add($name, $auth){
    if($this->findFirst(['name="'.$name.'"'])) {
      return false;
    }
    $self = new self;
    $self->name = $name;
    $self->password = $this->encodePassword('123456');
    $self->pwd_status = 0;
    $self->auth_type = $auth; //授权级别
    $self->status = 0;
    $self->create_time   = date("Y-m-d H:i:s");
    return $self->save();
  }
  
  public function checkUser($name, $password){
    $user = self::findFirst(['name="'.$name.'" and password="'.$this->encodePassword($password).'"']);
    if(!$user){
      return false;
    }
    return true;
  }
  
  public function getUser($name){

    $user = self::findFirst(['name="'.$name.'" and status=0']);
    if(!$user){
      return false;
    }
    $user = $user->toArray();
    $AdminAuth = new LoginAdminAuth;
    $auth = $AdminAuth->findFirst($user['auth_type']); //auth_type对应到表LoginAdminAuth中的id, 获取一行数据
    if($auth){
      $user['auth'] = $auth->toArray();
      
      if($user['auth']['auth'] == '0'){
        $user['auth']['auth'] = true;
      }
      elseif($user['auth']['auth'] == ''){
        $user['auth']['auth'] = [];
      }
      else{
        $user['auth']['auth'] = explode(',', $user['auth']['auth']);
      }
    }
    else{
      $user['auth'] = [];
    }
    return $user;
  }
  
  public function modifyPwd($name, $oldpassword, $password){
    $au = $this->updateAll(['password'=>'"'.$this->encodePassword($password).'"', 'pwd_status'=>1], ['name'=>"'".$name."'", 'password'=>"'".$this->encodePassword($oldpassword)."'"]);
    if(!$au){
      return false;
    }
    return true;
  }
  
  public function encodePassword($password){
    return md5($password . self::AUTH_SECRET);
  }
}