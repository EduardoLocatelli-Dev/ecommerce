<?php 

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;
use \Hcode\Model\User;

class Cart extends Model{

    const SESSION = "Cart";
    const SESSION_ERROR = "CartError";

    public static function getFromSession()
    {

        $cart = new Cart();

        if (isset($_SESSION[Cart::SESSION]) && (int)$_SESSION[Cart::SESSION]['idcart'] > 0) {

            $cart->get((int)$_SESSION[Cart::SESSION]['idcart']);

        } else {

            $cart->getFromSessionID();

            if (!(int)$cart->getidcart() > 0) {

                $data = [
                    'dessessionid'=>session_id(),
                ];

                if (User::checkLogin(false)) {

                    $user = User::getFromSession();

                    $data['iduser'] = $user->getiduser();

                }

                $cart->setData($data);

                $cart->save();

                $cart->setToSession();

            }

        }
        
        return $cart;
    
    }

    public function setToSession() 
    {

        $_SESSION[Cart::SESSION] = $this->getValues();

    }

    public function getFromSessionID()
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_carts WHERE dessessionid = :dessessionid", [
            ':dessessionid'=>session_id()
        ]);

        if (count($results) > 0) {

            $this->setData($results[0]);

        }

    }

    public function get(int $idcart)
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_carts WHERE idcart = :idcart", [
            ':idcart'=>$idcart
        ]);

        if (count($results) > 0) {

            $this->setData($results[0]);

        }

    }

    public function save()
    {

        $sql = new Sql();

        $results = $sql->select("CALL sp_carts_save(:idcart, :dessessionid, :iduser, :deszipcode, :vlfreight, :nrdays)", [
            'idcart'=>$this->getidcart(),
            'dessessionid'=>$this->getdessessionid(),
            'iduser'=>$this->getiduser(),
            'deszipcode'=>$this->getdeszipcode(),
            'vlfreight'=>$this->getvlfreight(),
            'nrdays'=>$this->getnrdays()
        ]);

        $this->setData($results[0]);
            
    }

    public function addProduct(Product $product)
    {

        $sql = new Sql();

        $sql->query("INSERT INTO tb_cartsproducts (idcart, idproduct) VALUES (:idcart, :idproduct)", [
            ':idcart'=>$this->getidcart(),
            ':idproduct'=>$product->getidproduct()
        ]);

        $this->getCalculateTotal();

    }

    public function removeProduct(Product $product, $all = false)
    {
        $sql = new Sql();

        if ($all) {

            $sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL", [
                ':idcart'=>$this->getidcart(),
                ':idproduct'=>$product->getidproduct()
            ]);

        } else {

            $sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL LIMIT 1", [
                ':idcart'=>$this->getidcart(),
                ':idproduct'=>$product->getidproduct()
            ]);

        }

        $this->getCalculateTotal();

    }

    public function getProducts()
    {

        $sql = new Sql();

        $rows = $sql->select("
            SELECT b.idproduct, b.desproduct , b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl, COUNT(*) AS nrqtd, SUM(b.vlprice) AS vltotal
            FROM tb_cartsproducts a
            INNER JOIN tb_products b ON a.idproduct = b.idproduct
            WHERE a.idcart = :idcart AND a.dtremoved IS NULL
            GROUP BY b.idproduct, b.desproduct , b.vlprice, b.vlwidth, b.vlheight, b.vllength, b.vlweight, b.desurl
            ORDER BY b.desproduct
        ", [
            ':idcart'=>$this->getidcart()
        ]);

        return Product::checkList($rows);

    }

    public function getProductsTotals()
    {

        $sql = new Sql();

        $results = $sql->select("
            SELECT SUM(vlprice) AS vlprice, SUM(vlwidth) AS vlwidth, SUM(vlheight) AS vlheight, SUM(vllength) AS vllength, SUM(vlweight) AS vlweght, COUNT(*) AS nrqtd
            FROM tb_products a
            INNER JOIN tb_cartsproducts b ON a.idproduct = b.idproduct
            WHERE b.idcart = :idcart AND dtremoved IS NULL;
        ", [
            ':idcart'=>$this->getidcart()
        ]);
  
        if (count($results) > 0) {
            return $results[0];
        } else {
            return [];
        }
            
    }

    public function setFreight($nrzipcode)
    {
        $totals = $this->getProductsTotals();

        if ($totals['nrqtd'] <= 0) {
            return null; // Não há produtos no carrinho
        }

        // 🔑 Token sandbox do Melhor Envio
        $token = "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiI5NTYiLCJqdGkiOiI2NGM4NTdiNzg2ZTA4ZjhiYWIzNGExMGE0ZGNiNmY0MjJmYzBmYTI0ZTExZTcyMzk1NjUzYzFmZDVhZmM1YzUwMTYwNzJmYTFkYzdkNzBjOSIsImlhdCI6MTc1NTY5ODg0NS40NDc1MjksIm5iZiI6MTc1NTY5ODg0NS40NDc1MzIsImV4cCI6MTc4NzIzNDg0NS40Mzk2NzksInN1YiI6IjlmYWRlNzkyLWMzYWYtNGNjNC1iYmZjLTVhNDU1NWZlMjEyOSIsInNjb3BlcyI6WyJjYXJ0LXJlYWQiLCJjYXJ0LXdyaXRlIiwiY29tcGFuaWVzLXJlYWQiLCJjb21wYW5pZXMtd3JpdGUiLCJjb3Vwb25zLXJlYWQiLCJjb3Vwb25zLXdyaXRlIiwibm90aWZpY2F0aW9ucy1yZWFkIiwib3JkZXJzLXJlYWQiLCJwcm9kdWN0cy1yZWFkIiwicHJvZHVjdHMtZGVzdHJveSIsInByb2R1Y3RzLXdyaXRlIiwicHVyY2hhc2VzLXJlYWQiLCJzaGlwcGluZy1jYWxjdWxhdGUiLCJzaGlwcGluZy1jYW5jZWwiLCJzaGlwcGluZy1jaGVja291dCIsInNoaXBwaW5nLWNvbXBhbmllcyIsInNoaXBwaW5nLWdlbmVyYXRlIiwic2hpcHBpbmctcHJldmlldyIsInNoaXBwaW5nLXByaW50Iiwic2hpcHBpbmctc2hhcmUiLCJzaGlwcGluZy10cmFja2luZyIsImVjb21tZXJjZS1zaGlwcGluZyIsInRyYW5zYWN0aW9ucy1yZWFkIiwidXNlcnMtcmVhZCIsInVzZXJzLXdyaXRlIiwid2ViaG9va3MtcmVhZCIsIndlYmhvb2tzLXdyaXRlIiwid2ViaG9va3MtZGVsZXRlIiwidGRlYWxlci13ZWJob29rIl19.PDr8cMj9Aach7cr0_R-OM7np4XPaMUkGGUJYSq4Kwjo0MwR2RHKf9vdckz_qSI3kYuXg2x4wPn73xrjlEvSznVqcKNInuYhA92MpxYjqMrXnxBJMHpYR6Jju-ix0DXm3WBW3rW9VBAeJwkiUcoiTcZ_zBq6vkz6nUFNRkTwBdlwNgYQ2NUzxueCsZ7J-inYV32hS1p2GvcbybSEd9xb5hTveLxPn8l7AHvjsX4AGMadyVPYYVLssR9ZHO3OeYOdtly35yYtR0vdFBwTfjY-7nhLgHDQn9kybhS5wuatellpz7kWzzY_kXTC1AmXUTDh9ghWqqh2bwk_Ohoy06ZAEbG2qq3A8SrtC9LopuOFccWSDpO32uvpChLDXXaPhyQ3TsgTYHkpGU5XlcMRvTzkV6XXOKHjkiF2TXS75Qji_s9ixXiec5C2EP7A69K72k-1B87_31-mr6C05sTtVgyZhNJqyXrhvVFbCi0kBRXIvKe-YRwUSIEtYkM4qBXI9ERX0wbXUN-zQOzX1aJ6360fy1S0ahbIzc92afmafUyf0XB9dQVZlVm8NHPVcN28Ym1BEsNLoN-MW7ZVFjlzWie_k8xQinrddfgtfmZf9hnBayuIAlmQGqI19XN8Z_GZGzbKiBRAFPLkS6WnrKF1bovyWBPykmdHOvwm3I3d8WOGgvHw";

        $data = [
            "from" => ["postal_code" => "09853120"], // CEP de origem
            "to" => ["postal_code" => $nrzipcode],  // CEP destino
            "products" => [
                [
                    "id" => "1",
                    "width" => (int) max(1, $totals['vlwidth']),
                    "height" => (int) max(1, $totals['vlheight']),
                    "length" => (int) max(1, $totals['vllength']),
                    "weight" => (float) max(0.1, $totals['vlweght']),
                    "insurance_value" => (float) max(0.01, $totals['vlprice']),
                    "quantity" => (int) max(1, $totals['nrqtd'])
                ]
            ],
            "options" => [
                "receipt" => false,
                "own_hand" => false,
                "collect" => false
            ]
        ];

        // cURL
        $ch = curl_init("https://sandbox.melhorenvio.com.br/api/v2/me/shipment/calculate");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$token}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Cart::setMsgError("Erro no cURL: $error");
            return null;
        }

        $result = json_decode($response, true);

        if (!$result || !is_array($result)) {
            Cart::setMsgError("Erro na resposta da API: $response");
            return null;
        }

        // Filtra apenas serviços válidos (que possuem preço)
        $shippingArray = [];
        foreach ($result as $service) {
            if (isset($service['price'])) {
                $shippingArray[] = [
                    "ServiceDescription" => $service['name'],
                    "ShippingPrice" => (float) $service['price'],
                    "DeliveryTime" => (int) $service['delivery_time']
                ];
            }
        }

        // Atualiza o carrinho com o primeiro serviço disponível
        if (count($shippingArray) > 0) {
            $this->setnrdays($shippingArray[0]['DeliveryTime']);
            $this->setvlfreight($shippingArray[0]['ShippingPrice']);
            $this->setdeszipcode($nrzipcode);
            $this->save();
        } // para não continuar o script, apenas testar
    }

    Public static function setMsgError($msg)
    {

        $_SESSION[Cart::SESSION_ERROR] = $msg;

    }

    public static function getMsgError()
    {

        $msg = (isset($_SESSION[Cart::SESSION_ERROR])) ? $_SESSION[Cart::SESSION_ERROR] : "";

        Cart::clearMsgError();

        return $msg;

    }

    public static function clearMsgError()
    {

        $_SESSION[Cart::SESSION_ERROR] = NULL;

    }

    public function updateFreight()
    {

        if ($this->getdeszipcode() != '') {

            $this->setFreight($this->getdeszipcode());

        }

    }

    public function getValues()
    {

        $this->getCalculateTotal();

        return parent::getValues(); 

    }

    public function getCalculateTotal()
    {

        $this->updateFreight();

        $totals = $this->getProductsTotals();

        $this->setvlsubtotal($totals['vlprice']);
        $this->setvltotal($totals['vlprice'] + $this->getvlfreight());

    }

}

?>