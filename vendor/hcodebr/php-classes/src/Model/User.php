<?php 

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class User extends Model{

    const SESSION = "User";
    const SECRET = "HcodePhp7_Secret";
    const ERROR = "UserError";
    const ERROR_REGISTER = "UserErrorRegister";

    public static function getFromSession()
    {

        $user = new User();

        if (isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0) {

            $user->setData($_SESSION[User::SESSION]);

        }

        return $user;

    }

    public static function checkLogin($inadmin = true) 
    {

        if (
            !isset($_SESSION[User::SESSION])
            ||
            !$_SESSION[User::SESSION]
            ||
            !(int)$_SESSION[User::SESSION]["iduser"] > 0
        ) {
            //não esta logado
            return false;

        } else {

            if ($inadmin === true && (bool)$_SESSION[User::SESSION]["inadmin"] === true) {
                
                return true;

            } else if ($inadmin === false) {

                return true;

        } else {

            return false;

        }
    
        }

    }

    public static function login($login, $password)
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
            ":LOGIN"=>$login
        ));

        if(count($results) === 0)
        {
            throw new \Exception("Usuário inexistente ou senha inválida.");
        }

        $data = $results[0];
            // print_r($password);
            // print_r($data);
        if (password_verify($password, $data["despassword"]) === true)
        {

            $user = new User(); 
        
            $user->setData($data);
            //$user->setiduser($data['iduser']);
            //$user->setinadmin($data['inadmin']);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;

        } else {
             throw new \Exception("Usuário inexistente ou senha inválida.");
        }

    }

    public static function verifyLogin($inadmin = false)
    {
        // Se não estiver logado, envia para login
        if (!User::checkLogin($inadmin)) {
            header("Location: /login");
            exit;
        }

        // Se estiver logado como admin e $inadmin = true, deixa passar (admin)
        if ($inadmin && User::checkLogin(true)) {
            return true;
        }

        // Se estiver logado como usuário normal, deixa passar
        if (!$inadmin && User::checkLogin(false)) {
            return true;
        }
    }

    public static function logout()
    {

        $_SESSION[User::SESSION] = NULL;

    }

    public static function listAll()
    {

        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");
    }

    public function save()
    {

        $sql = new Sql();

        $results = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>User::getPasswordHash($this->getdespassword()),
            ":desemail"=>$this->getdesemail(),
            ":nrphone"=>$this->getnrphone(),
            ":inadmin"=>$this->getinadmin()
        ));

        $this->setData($results[0]);

    }

    public function get($iduser)
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", array(":iduser"=>$iduser
    ));

    $data = $results[0];

    $data['desperson'] = utf8_encode($data['desperson']);

    $this->setData($data);

    }

    public function update()
    {

        $sql = new Sql();

        $results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":iduser"=>$this->getiduser(),
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            ":despassword"=>User::getPasswordHash($this->getdespassword()),
            ":desemail"=>$this->getdesemail(),
            ":nrphone"=>$this->getnrphone(),
            ":inadmin"=>$this->getinadmin()
        ));

        $this->setData($results[0]);

    }

    public function delete()
    {

        $sql = new Sql();

        $sql->query("CALL sp_users_delete(:iduser)", array(
            ":iduser"=>$this->getiduser()
        ));

    }

    public static function getForgot($email)
    {

        //die("estou no método certo");

        $sql = new Sql();

        $results = $sql->select("
            SELECT * 
            FROM tb_persons a
            INNER JOIN tb_users b USING(idperson)
            WHERE a.desemail = :email;
        ", array(
            ":email"=>$email
        ));

        if (count($results) === 0)
        {

            Throw new \Exception("Não foi possivel recuperar a senha.");

        }
        else
        {

            $data = $results[0];
            
            //var_dump($sql->select("SELECT DATABASE() AS dbname"));
            //exit;
            //var_dump($data["iduser"], $_SERVER["REMOTE_ADDR"]);
            //exit;
            //var_dump($data["iduser"], $_SERVER["REMOTE_ADDR"]);
            $results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
                ":iduser"=>$data["iduser"],
                ":desip"=>$_SERVER["REMOTE_ADDR"]
            ));
            
            if (count($results2) === 0)
            {

                throw new \Exception("Não foi possivel recuperar a senha");

            }    
            else 
            {

                $datarecovery = $results2[0];

                $code = base64_encode(openssl_encrypt($datarecovery["idrecovery"], 'aes-128-ecb', User::SECRET, OPENSSL_RAW_DATA));

                $link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";

                $mailer =  new Mailer($data["desemail"], $data["desperson"], "Redefinir Senha da Hcode Store", "forgot", array(
                    "name"=>$data["desperson"],
                    "link"=>$link
                ));

                $mailer->send();

                return $data;
                
            }

        }

    } 

    public static function validForgotDecrypt($code)
    {

        $idrecovery = openssl_decrypt(base64_decode($code), 'aes-128-ecb', User::SECRET, OPENSSL_RAW_DATA);

        $sql = new Sql();

        $results = $sql->select("
                SELECT * 
                FROM tb_userspasswordsrecoveries a
                INNER JOIN tb_users b USING(iduser)
                INNER JOIN tb_persons c USING(idperson)
                WHERE
                    a.idrecovery = :idrecovery
                    AND
                    a.dtrecovery IS NULL
                    AND
                    DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();
        ", array(
            ":idrecovery"=>$idrecovery
        ));

        if (count($results) === 0)
        {

            throw new \Exception("Não foi possível recuperar a senha.");

        }
        else
        {

            return $results[0];

        }
    }

    public static function setForgotUsed($idrecovery)
    {

        $sql = new Sql();

        $sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(
            ":idrecovery"=>$idrecovery
        ));

    }
        public function setPassword($password)
    {

        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
            ":password"=>$password,
            ":iduser"=>$this->getiduser()
        ));

    }
    
    public static function setError($msg)
    {
        $_SESSION[User::ERROR] = $msg;
    }

    public static function getError()
    {
        $msg = (isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR]) ? $_SESSION[User::ERROR] : '';
        User::clearError();
        return $msg;
    }

    public static function clearError()
    {
        $_SESSION[User::ERROR] = NULL;
    }

    public static function setErrorRegister($msg)
    {
        $_SESSION[User::ERROR_REGISTER] = $msg;
    }

    public static function getErrorRegister()
    {
        $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';
        User::clearErrorRegister();
        return $msg;
    }

    public static function clearErrorRegister()
    {
        $_SESSION[User::ERROR_REGISTER] = NULL;
    }

    public static function checkLoginExist($login)
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin", [
            ':deslogin'=>$login
        ]);

        return (count($results) > 0);

    }

    public static function getPasswordHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT, [
            'cost'=>12
        ]);

    }

}

?>