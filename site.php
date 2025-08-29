<?php 

use \Hcode\Page;
use \Hcode\Model\Product;
use \Hcode\Model\Category;
use \Hcode\Model\Cart;
use \Hcode\Model\Address;
use \Hcode\Model\User;
use \Hcode\Model\Order;
use \Hcode\Model\OrderStatus;

$app->get('/', function() {

	$products = Product::listAll();
    
	$page = new Page();

	$page->setTpl("index", [
		'products'=>Product::checkList($products)
	]);

});

$app->get("/categories/:idcategory", function($idcategory){

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;
	
	$category = new Category();

	$category->get((int)$idcategory);

	$pagination = $category->getProductsPage($page);

	$pages = [];

	for ($i = 1; $i <= $pagination['pages']; $i++) {
		array_push($pages, [
			'link' => '/categories/'.$category->getidcategory().'?page='.$i,
			'page' => $i
		]);
	}

	$page = new Page();

	$page->setTpl("category", [
		'category'=>$category->getValues(),
		'products'=>$pagination["data"],
		'pages'=>$pages
	]);

});

$app->get("/products/:deurl", function($desurl){

	$product = new Product();

	$product->getFromURL($desurl);

	$page = new Page();

	$page->setTpl("product-detail", [
		'product'=>$product->getValues(),
		'categories'=>$product->getCategories()
	]);

});

$app->get("/cart", function(){

	$cart = Cart::getFromSession();

	$page = new Page();

	$page->setTpl("cart", [
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts()
	]);

});

$app->get("/cart/:idproduct/add", function($idproduct){

	$product = new Product();

	$product->get((int)$idproduct);

	$cart = Cart::getFromSession();

	$qtd = (isset($_GET['qtd'])) ? (int)$_GET['qtd'] : 1;

	for ($i = 0; $i < $qtd; $i++) {
		
		$cart->addProduct($product);
	
	}

	header("Location: /cart");
	exit;

});

$app->get("/cart/:idproduct/minus", function($idproduct){

	$product = new Product();

	$product->get((int)$idproduct);

	$cart = Cart::getFromSession();

	$cart->removeProduct($product);

	// echo "<pre>";
	// print_r($cart->getProducts());
	// exit;

	header("Location: /cart");
	exit;

});

$app->get("/cart/:idproduct/remove", function($idproduct){

	$product = new Product();

	$product->get((int)$idproduct);

	$cart = Cart::getFromSession();

	$cart->removeProduct($product, true);

	header("Location: /cart");
	exit;

});

$app->post("/cart/freight", function() {

	$cart = Cart::getFromSession();

	$cart->setFreight($_POST['zipcode']);

	header("Location: /cart");
	exit;

});

// GET /checkout → exibe a página de checkout
$app->get("/checkout", function() {

    User::verifyLogin(false);

    $address = new Address();
    $cart = Cart::getFromSession();

    if (isset($_GET['zipcode'])) {
        // limpa o CEP para só números
        $zip = preg_replace('/\D/', '', $_GET['zipcode']);

        // carrega endereço pelo CEP
        $address->loadFromCEP($zip);

        // atualiza o carrinho com o CEP
        $cart->setdeszipcode($zip);

        // calcula frete/total (use setFreight se existir no seu Cart)
        if (method_exists($cart, 'setFreight')) {
            $cart->setFreight($zip);
        } else {
            $cart->getCalculateTotal();
        }

        $cart->save();
    }

    // Base: valores vindos do objeto Address (ou vazio se não veio nada)
    $values = $address->getValues();
    $addressData = [
        "desaddress"    => $values["desaddress"]    ?? "",
        "descomplement" => $values["descomplement"] ?? "",
        "desdistrict"   => $values["desdistrict"]   ?? "",
        "descity"       => $values["descity"]       ?? "",
        "desstate"      => $values["desstate"]      ?? "",
        "descountry"    => $values["descountry"]    ?? "",
        "deszipcode"    => $values["deszipcode"]    ?? ""
    ];

    // Se usuário estiver logado, sobrescreve os campos que ele tiver cadastrado
    $user = User::getFromSession();
    if ($user->getiduser() > 0) {
        $u = $user->getValues();
        foreach (["desaddress","descomplement","desdistrict","descity","desstate","descountry","deszipcode"] as $k) {
            if (!empty($u[$k])) {
                $addressData[$k] = $u[$k];
            }
        }
    }

	if (!$address->getdesaddress()) $address->setdesaddress('');
	if (!$address->getdescomplement()) $address->setdescomplement('');
	if (!$address->getdesdistrict()) $address->setdesdistrict('');
	if (!$address->getdescity()) $address->setdescity('');
	if (!$address->getdesstate()) $address->setdesstate('');
	if (!$address->getdescountry()) $address->setdescountry('');
	if (!$address->getdeszipcode()) $address->setdeszipcode('');

    $page = new Page();

    $page->setTpl("checkout", [
        'cart'    => $cart->getValues(),
		'products' => $cart->getProducts(),
        'address' => $addressData,
		'error' => Address::getMsgError()
    ]);
	
});

// POST /checkout → processa envio do formulário
$app->post("/checkout", function() {

    User::verifyLogin(false);

	if (!isset($_POST['zipcode']) || $_POST['zipcode'] === '') {
		Address::setMsgError("Informe o CEP.");
		header("Location: /checkout");
		exit;
	}

	if (!isset($_POST['desaddress']) || $_POST['desaddress'] === '') {
		Address::setMsgError("Informe o endereço.");
		header("Location: /checkout");
		exit;
	}

	if (!isset($_POST['desdistrict']) || $_POST['desdistrict'] === '') {
		Address::setMsgError("Informe o bairro.");
		header("Location: /checkout");
		exit;
	}

	if (!isset($_POST['descity']) || $_POST['descity'] === '') {
		Address::setMsgError("Informe a cidade.");
		header("Location: /checkout");
		exit;
	}

	if (!isset($_POST['desstate']) || $_POST['desstate'] === '') {
		Address::setMsgError("Informe o estado.");
		header("Location: /checkout");
		exit;
	}

	if (!isset($_POST['descountry']) || $_POST['descountry'] === '') {
		Address::setMsgError("Informe o país.");
		header("Location: /checkout");
		exit;
	}

	$user = User::getFromSession();
	
	$address = new Address();
	
	$_POST['denumber'] = $_POST['number'] ?? '';
	
	$_POST['deszipcode'] =  $_POST['zipcode'];
	$_POST['idperson'] = $user->getidperson();
	
	$address->setData($_POST);

	$address->save();
	
	$cart = Cart::getFromSession();
	
	$totals = $cart->getvlsubtotal();
	
	$order = new Order();
	

	$order->setData([
		'idcart' => $cart->getidcart(),
		'idaddress' => $address->getidaddress(),
		'iduser' => $user->getiduser(),
		'idstatus' => OrderStatus::EM_ABERTO,
		'vltotal' => ($totals['vlprice'] ?? 0) + ($cart->getvlfreight() ?? 0)
	]);
	
	$order->save();
	
	
    header("Location: /order/".$order->getidorder());
    exit;
});


$app->get("/login", function(){

	$page = new Page();

	$page->setTpl("login", [
		'error'=>User::getError(),
		'errorRegister'=>User::getErrorRegister(),
		'registerValues'=>(isset($_SESSION['registerValues'])) ? $_SESSION['registerValues'] : ['name'=>'', 'email'=>'', 'phone'=>'']
	]);

});

$app->post("/login", function(){

	try {

	User::login($_POST['login'], $_POST['password']);

	} catch(Exception $e) {

		User::setError($e->getMessage());

	}

	header("Location: /checkout");
	exit;

});

$app->get("/logout", function(){

	User::logout();

	header("Location: /login");
	exit;

});

$app->post("/register", function(){

	$_SESSION['registerValues'] = $_POST;

	if(!isset($_POST['desperson']) || $_POST['desperson'] == '') {

		User::setErrorRegister("Preencha o seu nome.");
		header("Location: /login");
		exit;

	}

	if(!isset($_POST['deslogin']) || $_POST['deslogin'] == '') {

		User::setErrorRegister("Preencha o seu e-mail.");
		header("Location: /login");
		exit;

	}

	if(!isset($_POST['despassword']) || $_POST['despassword'] == '') {

		User::setErrorRegister("Preencha a sua senha.");
		header("Location: /login");
		exit;

	}

	if (User::checkLoginExist($_POST['deslogin']) === true) {

		User::setErrorRegister("Este e-mail já está cadastrado.");
		header("Location: /login");
		exit;

	}

	$user = new User();

	$user->setData([
		'inadmin'=>0,
		'deslogin'=>$_POST['deslogin'],
		'desperson'=>$_POST['desperson'],
		'desemail'=>$_POST['deslogin'],
		'despassword'=>$_POST['despassword'],
		'nrphone'=>$_POST['nrphone']
	]);

	$user->save();

	User::login($_POST['deslogin'], $_POST['despassword']);

	header("Location: /checkout");
	exit;

});

$app->get("/forgot", function() {

	$page = new Page();

	$page->setTpl("forgot");

});

$app->post("/forgot", function() {

	$user = User::getForgot($_POST["email"]);

	header("Location: /forgot/sent");
	exit;

});

$app->get("/forgot/sent", function(){

	$page = new Page();

	$page->setTpl("forgot-sent");

});

$app->get("/forgot/reset", function (){

	$user = User::validForgotDecrypt($_GET["code"]);

	$page = new Page();

	$page->setTpl("forgot-reset", array(
		"name"=>$user["desperson"],
		"code"=>$_GET["code"]
	));

});

$app->post("/forgot/reset", function(){

	$forgot = User::validForgotDecrypt($_POST["code"]);

	User::setForgotUsed($forgot["idrecovery"]);

	$user = new User();

	$user->get((int)$forgot["iduser"]);

	$password = password_hash($_POST["password"], PASSWORD_DEFAULT, [
		"cost"=>12
	]);

	$user->setPassword($password);

	$page = new Page();

	$page->setTpl("forgot-reset-success");

});

$app->get("/profile", function(){

	User::verifyLogin(false);

	$user = User::getFromSession();

	$user->get($user->getiduser());

	$page = new Page();

	$page->setTpl("profile", [
		'user'=>$user->getValues(),
		'profileMsg'=>User::getSuccess(),
		'profileError'=>User::getError()
	]);

});

$app->post("/profile", function(){

	User::verifyLogin(false);

	if (!isset($_POST['desperson']) || $_POST['desperson'] === '') {
		User::setError("Preencha o seu nome.");
		header("Location: /profile");
		exit;
	}

	if (!isset($_POST['desemail']) || $_POST['desemail'] === '') {
		User::setError("Preencha o seu e-mail.");
		header("Location: /profile");
		exit;
	}
	
	$user = User::getFromSession();

	if ($_POST['desemail'] !== $user->getdesemail()) {

		if (User::checkLoginExist($_POST['desemail']) === true){

			User::setError("Este e-mail já está cadastrado.");
			header("Location: /profile");
			exit;

		}

	}

	$_POST['inadmin'] = $user->getinadmin();
	$_POST['despassword'] = $user->getdespassword();
	$_POST['deslogin'] = $_POST['deslogin'] ?? $user->getdeslogin();

	$user->setData($_POST);

	$user->update();

	User::setSuccess("Dados alterados com sucesso!");

	header('Location: /profile');
	exit;

});

$app->get("/order/:idorder", function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);

	$page = new Page();

	$page->setTpl("payment", [
		'order'=>$order->getValues()
	]);

});

$app->get("/boleto/:idorder", function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);

	// DADOS DO BOLETO PARA O SEU CLIENTE
	$dias_de_prazo_para_pagamento = 10;
	$taxa_boleto = 5.00;
	$data_venc = date("d/m/Y", time() + ($dias_de_prazo_para_pagamento * 86400));  // Prazo de X dias OU informe data: "13/04/2006"; 
	$valor_cobrado = formatPrice($order->getvltotal()); // Valor - REGRA: Sem pontos na milhar e tanto faz com "." ou "," ou com 1 ou 2 ou sem casa decimal
	$valor_cobrado = str_replace(",", ".",$valor_cobrado);
	$valor_boleto=number_format($valor_cobrado+$taxa_boleto, 2, ',', '');

	$dadosboleto["nosso_numero"] = $order->getidorder();  // Nosso numero - REGRA: Máximo de 8 caracteres!
	$dadosboleto["numero_documento"] = $order->getidorder();	// Num do pedido ou nosso numero
	$dadosboleto["data_vencimento"] = $data_venc; // Data de Vencimento do Boleto - REGRA: Formato DD/MM/AAAA
	$dadosboleto["data_documento"] = date("d/m/Y"); // Data de emissão do Boleto
	$dadosboleto["data_processamento"] = date("d/m/Y"); // Data de processamento do boleto (opcional)
	$dadosboleto["valor_boleto"] = $valor_boleto; 	// Valor do Boleto - REGRA: Com vírgula e sempre com duas casas depois da virgula

	// DADOS DO SEU CLIENTE
	$dadosboleto["sacado"] = $order->getdesperson();
	$dadosboleto["endereco1"] = $order->getdessaddress() . " " . $order->getdesdistrict();
	$dadosboleto["endereco2"] = $order->getdescity() . " - " . $order->getdesstate() . " - " . $order->getdescountry() . " - CEP: " . $order->getdeszipcode();

	// INFORMACOES PARA O CLIENTE
	$dadosboleto["demonstrativo1"] = "Pagamento de Compra na Loja Hcode E-commerce";
	$dadosboleto["demonstrativo2"] = "Taxa bancária - R$ 0,00";
	$dadosboleto["demonstrativo3"] = "";
	$dadosboleto["instrucoes1"] = "- Sr. Caixa, cobrar multa de 2% após o vencimento";
	$dadosboleto["instrucoes2"] = "- Receber até 10 dias após o vencimento";
	$dadosboleto["instrucoes3"] = "- Em caso de dúvidas entre em contato conosco: suporte@hcode.com.br";
	$dadosboleto["instrucoes4"] = "&nbsp; Emitido pelo sistema Projeto Loja Hcode E-commerce - www.hcode.com.br";

	// DADOS OPCIONAIS DE ACORDO COM O BANCO OU CLIENTE
	$dadosboleto["quantidade"] = "";
	$dadosboleto["valor_unitario"] = "";
	$dadosboleto["aceite"] = "";		
	$dadosboleto["especie"] = "R$";
	$dadosboleto["especie_doc"] = "";


	// ---------------------- DADOS FIXOS DE CONFIGURAÇÃO DO SEU BOLETO --------------- //


	// DADOS DA SUA CONTA - ITAÚ
	$dadosboleto["agencia"] = "1690"; // Num da agencia, sem digito
	$dadosboleto["conta"] = "48781";	// Num da conta, sem digito
	$dadosboleto["conta_dv"] = "2"; 	// Digito do Num da conta

	// DADOS PERSONALIZADOS - ITAÚ
	$dadosboleto["carteira"] = "175";  // Código da Carteira: pode ser 175, 174, 104, 109, 178, ou 157

	// SEUS DADOS
	$dadosboleto["identificacao"] = "Hcode Treinamentos";
	$dadosboleto["cpf_cnpj"] = "24.700.731/0001-08";
	$dadosboleto["endereco"] = "Rua Ademar Saraiva Leão, 234 - Alvarenga, 09853-120";
	$dadosboleto["cidade_uf"] = "São Bernardo do Campo - SP";
	$dadosboleto["cedente"] = "HCODE TREINAMENTOS LTDA - ME";

	// NÃO ALTERAR!
	$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . "res" . DIRECTORY_SEPARATOR . "boletophp" . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR;

	require_once($path . "funcoes_itau.php");
	require_once($path . "layout_itau.php");

	});

?>